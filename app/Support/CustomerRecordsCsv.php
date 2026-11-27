<?php

namespace App\Support;

use App\Models\LaundrycardSourceRecord;

final class CustomerRecordsCsv
{
    public const COLUMNS = [
        'card_number' => 'Card Number',
        'store_id' => 'Store ID',
        'store_name' => 'Store Name',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'customer_name' => 'Customer Full Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'balance' => 'Current Balance',
        'classification' => 'Classification',
        'eligibility_status' => 'Eligibility Status',
        'eligibility_reason' => 'Eligibility Reason',
        'processing_status' => 'Processing Status',
        'hold_reason' => 'Hold Reason',
        'reload_count' => 'Reload Count',
        'reload_total' => 'Reload Total ($)',
        'location_id' => 'GHL Location ID',
        'contact_id' => 'GHL Contact ID',
        'first_observed_at' => 'First Observed (UTC)',
        'last_observed_at' => 'Last Observed (UTC)',
        'synced_at' => 'Synced At (UTC)',
    ];

    public const GROUPS = [
        'Card & branch' => ['card_number', 'store_id', 'store_name'],
        'Customer details' => ['first_name', 'last_name', 'customer_name', 'email', 'phone'],
        'Balance & activity' => ['balance', 'classification', 'eligibility_status', 'eligibility_reason', 'reload_count', 'reload_total'],
        'Contact sync' => ['processing_status', 'hold_reason', 'location_id', 'contact_id'],
        'Dates' => ['first_observed_at', 'last_observed_at', 'synced_at'],
    ];

    public static function values(LaundrycardSourceRecord $record): array
    {
        [$first, $last] = Normalizer::splitName($record->customer_name);

        return [
            'card_number' => $record->card_number,
            'store_id' => $record->store?->store_id ?? '',
            'store_name' => $record->store?->name ?? '',
            'first_name' => $first ?? '',
            'last_name' => $last ?? '',
            'customer_name' => $record->customer_name ?? '',
            'email' => $record->customer_email ?? '',
            'phone' => $record->customer_phone ?? '',
            'balance' => $record->balance !== null ? number_format((float) $record->balance, 2, '.', '') : '',
            'classification' => $record->classification ?? '',
            'eligibility_status' => $record->eligibility_status ?? '',
            'eligibility_reason' => $record->eligibility_reason ?? '',
            'processing_status' => $record->processing_status ?? '',
            'hold_reason' => $record->hold_reason ?? '',
            'reload_count' => $record->reload_count ?? 0,
            'reload_total' => $record->reload_total !== null ? number_format((float) $record->reload_total, 2, '.', '') : '0.00',
            'location_id' => $record->destination_location_id ?? ($record->connection?->location_id ?? ''),
            'contact_id' => $record->contactMapping?->ghl_contact_id ?? '',
            'first_observed_at' => $record->first_observed_at?->toIso8601String() ?? '',
            'last_observed_at' => $record->last_observed_at?->toIso8601String() ?? '',
            'synced_at' => $record->synced_at?->toIso8601String() ?? '',
        ];
    }
}
