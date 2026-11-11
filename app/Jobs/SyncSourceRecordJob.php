<?php

namespace App\Jobs;

use App\Exceptions\Integration\RetryableException;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Services\ContactSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncSourceRecordJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 600;

    public function __construct(public int $recordId, public string $correlationId, public ?int $userId = null)
    {
    }

    public function uniqueId(): string
    {
        return 'sync-record-'.$this->recordId;
    }

    public function tries(): int
    {
        return (int) config('integration.sync.tries');
    }

    public function handle(ContactSyncService $sync): void
    {
        $record = Rec::find($this->recordId);
        if (! $record) {
            return;
        }

        // Fast skip if record is already synced, skipped, or not in actionable status
        if ($record->isSynced() || $record->processing_status === Rec::SKIPPED) {
            return;
        }

        // Anonymous card (no email, phone or name): nothing to send to GHL.
        // Skip without touching the GHL API or creating sync attempts.
        if (blank($record->customer_email) && blank($record->customer_phone) && blank($record->customer_name)) {
            Rec::whereKey($record->id)->whereIn('processing_status', [Rec::QUEUED, Rec::PENDING])
                ->update(['processing_status' => Rec::SKIPPED, 'hold_reason' => 'no_contact_identifier']);

            return;
        }

        try {
            $sync->process($record, $this->correlationId, $this->userId);
        } catch (RetryableException $e) {
            if ($this->attempts() >= $this->tries()) {
                return; // record stays FAILED and retry_eligible for manual retry
            }
            Rec::whereKey($record->id)->where('processing_status', Rec::FAILED)->update(['processing_status' => Rec::QUEUED]);
            $backoff = config('integration.sync.backoff');
            $delay = $e->retryAfter ?? ($backoff[min($this->attempts() - 1, count($backoff) - 1)] ?? 60);
            $this->release($delay);
        }
    }

    public function failed(Throwable $e): void
    {
        Rec::whereKey($this->recordId)->whereIn('processing_status', [Rec::QUEUED, Rec::PROCESSING])->update(['processing_status' => Rec::FAILED]);
    }
}
