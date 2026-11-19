@forelse($recentEvents as $event)
  <tr data-key="{{ $event->id }}">
    <td>{{ $event->created_at?->copy()->utc()->format('M j, Y · H:i:s') ?? '—' }}</td>
    <td class="ov-id">{{ $event->stage }}</td>
    <td>@if($event->outcome)<x-badge :value="$event->outcome" />@else — @endif</td>
    <td class="ov-id">{{ $event->store_id ?? '—' }}@if($event->ghl_location_id)<small>{{ \Illuminate\Support\Str::limit($event->ghl_location_id, 8, '…') }}</small>@endif</td>
    <td>{{ $event->message ?: '—' }}</td>
  </tr>
@empty
  <tr data-key="empty"><td colspan="5" class="ov-empty">No integration events recorded yet.</td></tr>
@endforelse
