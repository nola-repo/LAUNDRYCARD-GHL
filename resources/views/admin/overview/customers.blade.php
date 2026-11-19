@forelse($latestNewCustomers as $record)
  @php
    [$label, $tone] = match ($record->eligibility_status) {
        'eligible' => ['Eligible', 'ok'],
        'ineligible_zero_balance' => ['Zero balance', 'mute'],
        'ineligible_negative_balance' => ['Negative balance', 'warn'],
        'invalid_balance' => ['Invalid balance', 'warn'],
        'pending_balance_verification' => ['Unverified', 'info'],
        default => [str_replace('_', ' ', $record->eligibility_status ?: 'Unknown'), 'mute'],
    };
    $email = \App\Support\Sanitizer::maskEmail($record->customer_email);
    $phone = \App\Support\Sanitizer::maskPhone($record->customer_phone);
  @endphp
  <tr data-key="{{ $record->id }}">
    <td class="ov-id">{{ substr($record->card_number, 0, 2) }}••••{{ substr($record->card_number, -2) }}</td>
    <td class="ov-id">{{ $record->store?->store_id ?? '—' }}</td>
    <td><strong>{{ $record->customer_name ? \Illuminate\Support\Str::limit($record->customer_name, 1, '').'***' : '—' }}</strong>
      @if($email || $phone)<small>{{ implode(' · ', array_filter([$email, $phone])) }}</small>@endif
    </td>
    <td class="ov-numeric">{{ $record->balance === null ? 'Unknown' : '$'.number_format((float) $record->balance, 2) }}</td>
    <td><span class="badge b-{{ $tone }}">{{ $label }}</span></td>
    <td>{{ $record->first_observed_at?->copy()->utc()->format('M j, Y · H:i') ?? '—' }}</td>
    <td class="ov-actions"><button class="ov-row-details" type="button" data-detail-open data-detail-title="Card {{ substr($record->card_number, 0, 2) }}••••{{ substr($record->card_number, -2) }}" data-detail-summary="Recently detected customer record." aria-label="Details for card {{ substr($record->card_number, 0, 2) }}••••{{ substr($record->card_number, -2) }}" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden>Details</button>
      <template class="ov-detail-template"><dl class="ov-detail-list">
        <div><dt>Card</dt><dd class="ov-id">{{ substr($record->card_number, 0, 2) }}••••{{ substr($record->card_number, -2) }}</dd></div>
        <div><dt>Store</dt><dd>{{ $record->store?->name ?: 'Store '.$record->store?->store_id }} <span class="ov-detail-note">({{ $record->store?->store_id ?? '—' }})</span></dd></div>
        <div><dt>Customer</dt><dd>{{ $record->customer_name ? \Illuminate\Support\Str::limit($record->customer_name, 1, '').'***' : '—' }}</dd></div>
        @if($email)<div><dt>Email</dt><dd>{{ $email }}</dd></div>@endif
        @if($phone)<div><dt>Phone</dt><dd>{{ $phone }}</dd></div>@endif
        <div><dt>Balance</dt><dd>{{ $record->balance === null ? 'Unknown' : '$'.number_format((float) $record->balance, 2) }}</dd></div>
        <div><dt>Eligibility</dt><dd>{{ $label }}</dd></div>
        <div><dt>First seen (UTC)</dt><dd>{{ $record->first_observed_at?->copy()->utc()->format('M j, Y · H:i') ?? '—' }}</dd></div>
      </dl></template>
    </td>
  </tr>
@empty
  <tr data-key="empty"><td colspan="7" class="ov-empty">New cards will appear here after a store’s first baseline scan.</td></tr>
@endforelse
