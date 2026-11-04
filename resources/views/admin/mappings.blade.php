@extends('layouts.app')
@section('title','Branch Mapping Monitor')
@section('content')

@if(session('status'))
<div style="margin-bottom:1rem;padding:12px 16px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:6px;color:#065f46;font-weight:500;">
  {{ session('status') }}
</div>
@endif
@if(session('error'))
<div style="margin-bottom:1rem;padding:12px 16px;background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-weight:500;">
  {{ session('error') }}
</div>
@endif

<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="margin:0">Branch Mapping Monitor</h1>
    <p class="sub" style="margin:4px 0 0">Mappings are resolved automatically from explicit store-to-location configuration and active OAuth installations. Unresolved or conflicting branches are held without fallback routing.</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST" action="{{ route('admin.mappings.auto-map') }}">
      @csrf
      <button type="submit" class="btn" style="background:#2563eb;color:#fff;padding:8px 16px;border-radius:6px;font-weight:600;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
        🔄 Run Auto-Map
      </button>
    </form>
  </div>
</div>

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
    @empty<div class="muted">None — all locations are mapped! ✅</div>@endforelse
  </div>
  <div class="card"><h2>Automatic mapping activity</h2>
    <table><tr><th>When</th><th>Decision</th><th>Store</th><th>Location</th></tr>
    @forelse($audit as $entry)
      <tr><td>{{ $entry->created_at?->diffForHumans() }}</td><td><x-badge :value="$entry->action"/></td><td class="mono">{{ $entry->store?->store_id ?? '—' }}</td><td class="mono">{{ $entry->connection?->location_id ?? '—' }}</td></tr>
    @empty<tr><td colspan="4" class="muted">No mapping decisions recorded.</td></tr>@endforelse</table>
  </div>
</div>
@endsection
