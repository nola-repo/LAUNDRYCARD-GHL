<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LaundryCard transaction record.
 *
 * Raw payloads are stored in raw_payload. Normalised fields (transaction_type,
 * amount, etc.) are populated only after the actual API response field names
 * and type codes have been confirmed from a live API call.
 *
 * @property int         $id
 * @property int         $laundrycard_store_id
 * @property int|null    $source_record_id
 * @property string      $card_number
 * @property \Carbon\Carbon $occurred_at
 * @property string|null $transaction_type_raw
 * @property string|null $transaction_type
 * @property string|null $amount
 * @property string|null $bonus_amount
 * @property string|null $balance_after
 * @property string|null $mach_no
 * @property array       $raw_payload
 * @property string|null $transaction_ref
 * @property \Carbon\Carbon $ingested_at
 */
class LaundrycardTransaction extends Model
{
    protected $table = 'laundrycard_transactions';

    protected $fillable = [
        'laundrycard_store_id',
        'source_record_id',
        'card_number',
        'occurred_at',
        'transaction_type_raw',
        'transaction_type',
        'amount',
        'bonus_amount',
        'balance_after',
        'mach_no',
        'raw_payload',
        'transaction_ref',
    ];

    protected $casts = [
        'occurred_at'  => 'datetime',
        'ingested_at'  => 'datetime',
        'amount'       => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'raw_payload'  => 'array',
    ];

    // Normalised type constants — only populated after type codes are confirmed.
    public const TYPE_RELOAD     = 'reload';
    public const TYPE_VEND       = 'vend';
    public const TYPE_BONUS      = 'bonus';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_UNKNOWN    = 'unknown';

    public function store(): BelongsTo
    {
        return $this->belongsTo(LaundrycardStore::class, 'laundrycard_store_id');
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(LaundrycardSourceRecord::class, 'source_record_id');
    }
}

