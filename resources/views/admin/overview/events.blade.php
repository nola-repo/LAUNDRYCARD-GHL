@forelse($recentEvents as $event)
  <tr data-key="{{ $event->id }}">
    <td>{{ $event->created_at?->copy()->utc()->format('M j, Y · H:i:s') ?? '—' }}</td>
    <td class="ov-id">{{ $event->stage }}</td>
    <td>@if($event->outcome)<x-badge :value="$event->outcome" />@else — @endif</td>
    <td class="ov-id">{{ $event->store_id ?? '—' }}@if($event->ghl_location_id)<small>{{ \Illuminate\Support\Str::limit($event->ghl_location_id, 8, '…') }}</small>@endif</td>
    <td>{{ $event->message ?: '—' }}</td>
    <td class="ov-actions"><button class="ov-row-details" type="button" data-detail-open data-detail-title="{{ ucfirst(str_replace('_', ' ', $event->stage)) }} event" data-detail-summary="Integration activity recorded at {{ $event->created_at?->copy()->utc()->format('M j, Y · H:i:s') ?? 'an unknown time' }} UTC." aria-label="Details for {{ ucfirst(str_replace('_', ' ', $event->stage)) }} event" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden>Details</button>
      <template class="ov-detail-template"><dl class="ov-detail-list">
        <div><dt>Time (UTC)</dt><dd>{{ $event->created_at?->copy()->utc()->format('M j, Y · H:i:s') ?? '—' }}</dd></div>
        <div><dt>Stage</dt><dd>{{ $event->stage }}</dd></div>
        <div><dt>Outcome</dt><dd>{{ $event->outcome ?: 'Not recorded' }}</dd></div>
        <div><dt>Store</dt><dd class="ov-id">{{ $event->store_id ?? '—' }}</dd></div>
        @if($event->ghl_location_id)<div><dt>GHL location</dt><dd class="ov-id">{{ \Illuminate\Support\Str::limit($event->ghl_location_id, 8, '…') }}</dd></div>@endif
        <div><dt>Message</dt><dd>{{ $event->message ?: '—' }}</dd></div>
      </dl></template>
    </td>
  </tr>
@empty
  <tr data-key="empty"><td colspan="6" class="ov-empty">No integration events recorded yet.</td></tr>
@endforelse
