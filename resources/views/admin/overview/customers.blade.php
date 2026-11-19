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
  </tr>
@empty
  <tr data-key="empty"><td colspan="6" class="ov-empty">New cards will appear here after a store’s first baseline scan.</td></tr>
@endforelse
