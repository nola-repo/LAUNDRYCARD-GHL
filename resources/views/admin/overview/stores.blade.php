@forelse($stores as $store)
  @php($connection = $store->activeMapping?->connection)
  <tr data-key="{{ $store->id }}">
    <td><strong>{{ $store->name ?: 'Store '.$store->store_id }}</strong><small>{{ implode(' · ', array_filter([$store->store_id, $store->city, $store->state])) }}</small>
      @if($store->last_scan_status === 'failed')<small class="ov-error">Last scan failed</small>@endif
    </td>
    <td>@if($connection)
      {{ $connection->location_name ?: 'GHL location' }}<small>{{ ucfirst($connection->status) }} · {{ \Illuminate\Support\Str::limit($connection->location_id, 8, '…') }}</small>
    @else<span class="badge b-warn">Unmapped</span>@endif</td>
    <td class="ov-numeric">{{ number_format($store->source_records_count) }}</td>
  </tr>
@empty
  <tr data-key="empty"><td colspan="3" class="ov-empty">No LaundryCard stores discovered yet.</td></tr>
@endforelse
