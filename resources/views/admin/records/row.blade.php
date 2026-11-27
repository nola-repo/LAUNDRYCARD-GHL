@php
  $hasProfile = filled($record->customer_name) || filled($record->customer_email) || filled($record->customer_phone);
  $hasMach = app(\App\Services\LaundryCardClient::class)->hasMachNo($record->store?->store_id ?? '');
  $tags = app(\App\Services\ContactClassificationService::class)->managedTags($record);
  $historyUrl = route('admin.transactions', ['card' => $record->card_number, 'store' => $record->store?->store_id]);
  $activity = $record->source_timestamp ?? $record->first_observed_at;
@endphp
<tr>
  <th scope="row" data-label="Cardholder" class="records-person">
    <a class="records-card-link" href="{{ $historyUrl }}" aria-label="View credit history for card {{ $record->card_number }}"><svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 9h20M6 15h4"/></svg>{{ $record->card_number }}</a>
    <strong class="records-person-name">{{ $record->customer_name ? ucwords(strtolower($record->customer_name)) : ($hasProfile ? 'Unnamed cardholder' : 'Anonymous card') }}</strong>
    <div class="records-contact-lines">
      @if($record->customer_email)<span>{{ $record->customer_email }}</span>@endif
      @if($record->customer_phone)<span>{{ $record->customer_phone }}</span>@endif
      @unless($hasProfile)<span>{{ $hasMach ? 'POS ready · awaiting contact details' : 'POS configuration pending' }}</span>@endunless
    </div>
  </th>
  <td data-label="Branch"><strong class="records-branch-name">{{ $record->store?->name ?: 'Store unavailable' }}</strong><span class="records-id">Store {{ $record->store?->store_id ?? 'unavailable' }}</span></td>
  <td data-label="Balance & eligibility">
    <strong class="records-balance {{ (float) $record->balance < 0 ? 'is-negative' : '' }}">{{ $record->balance === null ? 'Pending' : ((float) $record->balance < 0 ? '−$' : '$').number_format(abs((float) $record->balance), 2) }}</strong>
    <x-badge :value="$record->eligibility_status"/>
    @if($record->eligibility_reason && $record->eligibility_reason !== $record->eligibility_status)<p class="records-cell-note">{{ ucwords(str_replace('_', ' ', $record->eligibility_reason)) }}</p>@endif
    @if(($record->reload_count ?? 0) > 0)<a class="records-history-link" href="{{ $historyUrl }}">{{ $record->reload_count }} {{ \Illuminate\Support\Str::plural('reload', $record->reload_count) }} · ${{ number_format((float) $record->reload_total, 2) }}</a>@endif
  </td>
  <td data-label="Customer type">
    <span class="records-classification">{{ match($record->classification) { 'new_observed' => 'New active · 90 days', 'confirmed_new' => 'Confirmed new', default => 'Historical baseline' } }}</span>
    <details class="records-disclosure"><summary>Tags <span>{{ count($tags) }}</span></summary><div class="records-tag-list">@foreach($tags as $tag)<span>{{ $tag }}</span>@endforeach</div></details>
  </td>
  <td data-label="Contact sync">
    <x-badge :value="$record->processing_status"/>
    @if($record->hold_reason)<p class="records-hold-reason">{{ $holdReasonLabels[$record->hold_reason] ?? ucwords(str_replace('_', ' ', $record->hold_reason)) }}</p>@endif
    @if($record->destination_location_id || $record->contactMapping?->ghl_contact_id)
      <details class="records-disclosure"><summary>Sync identifiers</summary><dl class="records-sync-ids">
        @if($record->destination_location_id)<div><dt>Location</dt><dd>{{ $record->destination_location_id }}</dd></div>@endif
        @if($record->contactMapping?->ghl_contact_id)<div><dt>Contact</dt><dd>{{ $record->contactMapping->ghl_contact_id }}</dd></div>@endif
      </dl></details>
    @else<p class="records-cell-note">No linked GHL contact</p>@endif
  </td>
  <td data-label="Activity · UTC">
    @if($activity)<time class="records-time" datetime="{{ $activity->toIso8601String() }}"><strong>{{ $activity->copy()->utc()->format('M d, Y') }}</strong><span>{{ $activity->copy()->utc()->format('H:i') }} UTC</span></time><p class="records-cell-note">{{ $record->source_timestamp ? $activity->diffForHumans() : 'First observed' }}</p>@else<span class="records-cell-note">Not recorded</span>@endif
  </td>
</tr>
