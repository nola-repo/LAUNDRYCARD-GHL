@extends('layouts.app')
@section('title','Branch Mapping Monitor')
@section('content')
<h1>Branch Mapping Monitor</h1>
<p class="sub">Mappings are resolved automatically from explicit store-to-location configuration and active OAuth installations. Unresolved or conflicting branches are held without fallback routing.</p>

<div class="card" style="overflow:auto">
<table>
  <tr><th>Store ID</th><th>LaundryCard branch</th><th>GHL Location ID</th><th>GHL location</th><th>Mapping status</th><th>Provenance</th><th>OAuth health</th></tr>
  @forelse($rows as $row)
    @php($store = $row['store'])
    @php($decision = $row['decision'])
    @php($mapping = $decision['mapping'])
    @php($connection = $decision['connection'])
    <tr>
      <td class="mono">{{ $store->store_id }}</td>
      <td>{{ $store->name ?? '—' }}</td>
      <td class="mono">{{ $connection?->location_id ?? '—' }}</td>
      <td>{{ $connection?->location_name ?? '—' }}</td>
      <td>@if($decision['routable'])<x-badge value="verified"/>@else<x-badge :value="$decision['reason'] ?? 'unmapped'"/>@endif</td>
      <td>{{ $mapping?->origin ?? 'unresolved' }}</td>
      <td>{{ $connection?->status ?? 'no authorized connection' }}</td>
    </tr>
  @empty
    <tr><td colspan="7" class="muted">No LaundryCard stores have been discovered.</td></tr>
  @endforelse
</table>
</div>

<div class="grid g2">
  <div class="card"><h2>Authorized GHL locations without a resolved mapping</h2>
    @forelse($unassigned as $connection)
      <div>{{ $connection->location_name ?? '—' }} <span class="mono muted">{{ $connection->location_id }}</span> <x-badge :value="$connection->status"/></div>
    @empty<div class="muted">None.</div>@endforelse
  </div>
  <div class="card"><h2>Automatic mapping activity</h2>
    <table><tr><th>When</th><th>Decision</th><th>Store</th><th>Location</th></tr>
    @forelse($audit as $entry)
      <tr><td>{{ $entry->created_at?->diffForHumans() }}</td><td><x-badge :value="$entry->action"/></td><td class="mono">{{ $entry->store?->store_id ?? '—' }}</td><td class="mono">{{ $entry->connection?->location_id ?? '—' }}</td></tr>
    @empty<tr><td colspan="4" class="muted">No mapping decisions recorded.</td></tr>@endforelse</table>
  </div>
</div>
@endsection
