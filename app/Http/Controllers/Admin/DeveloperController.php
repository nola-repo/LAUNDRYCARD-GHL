<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Integration\IntegrationException;
use App\Http\Controllers\Controller;
use App\Jobs\SyncSourceRecordJob;
use App\Models\LaundrycardSourceRecord as Rec;
use App\Models\LaundrycardStore;
use App\Services\BranchMappingService;
use App\Services\ContactSyncService;
use App\Services\CustomerDiscoveryService;
use App\Services\LaundryCardClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeveloperController extends Controller
{
    public function testLaundryCard(LaundryCardClient $lc)
    {
        try {
            $r = $lc->testConnection();

            return back()->with('result', ['title' => 'LaundryCard API: OK', 'data' => $r]);
        } catch (IntegrationException $e) {
            return back()->with('error', 'LaundryCard API test failed: '.$e->getMessage());
        }
    }

    public function refreshStores(BranchMappingService $svc, LaundryCardClient $lc)
    {
        try {
            return back()->with('result', ['title' => 'Store list refreshed', 'data' => $svc->refreshStores($lc)]);
        } catch (IntegrationException $e) {
            return back()->with('error', 'Store refresh failed: '.$e->getMessage());
        }
    }

    /** Read-only: no persistence beyond the run summary, no queueing, never touches GHL. */
    public function readOnlyScan(Request $request, CustomerDiscoveryService $discovery)
    {
        $run = $discovery->run(true, 'manual', $request->user()->id);

        return back()->with('result', ['title' => "Read-only discovery {$run->status}", 'data' => collect($run->stats ?? [])->except('stores')->all() + ['per_store' => $run->stats['stores'] ?? [], 'error' => $run->error]]);
    }

    public function routingPreview(Request $request, BranchMappingService $svc)
    {
        $data = $request->validate(['store_id' => 'required|string|max:20']);
        $store = $svc->storeByAnyId($data['store_id']);
        if (! $store) {
            return back()->with('error', 'Unknown LaundryCard store id.');
        }
        $d = $svc->resolve($store);

        return back()->with('result', ['title' => "Routing preview for store {$store->store_id}", 'data' => [
            'routable' => $d['routable'],
            'reason' => $d['reason'],
            'destination_location_id' => $d['routable'] ? $d['connection']->location_id : null,
            'destination_name' => $d['routable'] ? $d['connection']->location_name : null,
            'note' => 'Decision uses persisted Store ID → verified Location ID only. No external call was made.',
        ]]);
    }

    /** Explicit administrator approval to process one specific record (may bypass baseline/unconfirmed holds). */
    public function processTestRecord(Request $request, ContactSyncService $sync)
    {
        $data = $request->validate(['record_id' => 'required|exists:laundrycard_source_records,id', 'confirm' => 'accepted']);
        $record = Rec::findOrFail($data['record_id']);
        if (in_array($record->processing_status, [Rec::BASELINE_SKIPPED, Rec::SKIPPED], true)) {
            $record->update(['processing_status' => Rec::PENDING, 'hold_reason' => null]);
        }
        try {
            $r = $sync->process($record, (string) Str::uuid(), $request->user()->id);
        } catch (IntegrationException $e) {
            return back()->with('error', 'Processing failed (retryable): '.$e->getMessage());
        }

        return back()->with('result', ['title' => 'Test record processed', 'data' => $r]);
    }

    public function retry(Request $request, Rec $record, BranchMappingService $svc)
    {
        if ($request->input('confirm') !== '1') {
            return back()->with('error', 'Confirm that you reviewed this record before retrying.');
        }
        $claimed = Rec::whereKey($record->id)->whereIn('processing_status', [Rec::FAILED, Rec::HELD])
            ->update(['processing_status' => Rec::QUEUED, 'hold_reason' => null]);
        if (! $claimed) {
            return back()->with('error', 'Record is not in a retryable state.');
        }
        SyncSourceRecordJob::dispatch($record->id, (string) Str::uuid(), $request->user()->id);

        return back()->with('status', 'Record queued for retry. It is still subject to a verified mapping; otherwise it is held again.');
    }
}
