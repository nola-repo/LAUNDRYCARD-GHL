@forelse($stores as $store)
  @php($connection = $store->activeMapping?->connection)
  <tr data-key="{{ $store->id }}">
    <td class="ov-store-name" data-label="Store"><strong>{{ $store->name ?: 'Store '.$store->store_id }}</strong><small>{{ implode(' · ', array_filter([$store->store_id, $store->city, $store->state])) }}</small>
      @if($store->last_scan_status === 'failed')<small class="ov-error">Last scan failed</small>@endif
    </td>
    <td class="ov-destination" data-label="GHL destination">
      <span class="ov-store-caption" aria-hidden="true">GHL destination</span>
      @if($connection)
      <span>{{ $connection->location_name ?: 'GHL location' }}</span><small>{{ ucfirst($connection->status) }} · {{ \Illuminate\Support\Str::limit($connection->location_id, 8, '…') }}</small>
    @else<span class="badge b-warn">Unmapped</span>@endif</td>
    <td class="ov-numeric" data-label="Records"><span class="ov-store-caption" aria-hidden="true">Records</span><span>{{ number_format($store->source_records_count) }}</span></td>
    <td class="ov-actions" data-label="More"><button class="ov-row-details" type="button" data-detail-open data-detail-title="{{ $store->name ?: 'Store '.$store->store_id }}" data-detail-summary="Store routing and record activity." aria-label="Open details for {{ $store->name ?: 'Store '.$store->store_id }}" title="View store details" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg></button>
      <template class="ov-detail-template"><dl class="ov-detail-list">
        <div><dt>Store ID</dt><dd class="ov-id">{{ $store->store_id }}</dd></div>
        <div><dt>Location</dt><dd>{{ implode(' · ', array_filter([$store->city, $store->state])) ?: 'Not recorded' }}</dd></div>
        <div><dt>GHL destination</dt><dd>{{ $connection?->location_name ?: 'Unmapped' }}</dd></div>
        @if($connection)<div><dt>Mapping status</dt><dd>{{ ucfirst($connection->status) }}</dd></div><div><dt>Location ID</dt><dd class="ov-id">{{ \Illuminate\Support\Str::limit($connection->location_id, 8, '…') }}</dd></div>@endif
        <div><dt>Records</dt><dd>{{ number_format($store->source_records_count) }}</dd></div>
        <div><dt>Last scan</dt><dd>{{ $store->last_scan_at?->copy()->utc()->format('M j, Y · H:i T') ?? 'None recorded' }} @if($store->last_scan_status) · {{ ucfirst($store->last_scan_status) }} @endif</dd></div>
      </dl></template>
    </td>
  </tr>
@empty
  <tr data-key="empty"><td colspan="4" class="ov-empty">No LaundryCard stores discovered yet.</td></tr>
@endforelse
