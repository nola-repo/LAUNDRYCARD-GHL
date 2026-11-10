@extends('layouts.app')
@section('title','Branch Mapping Monitor')
@section('content')

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M18 6v6a6 6 0 0 1-6 6H6"></path></svg>
      Routing Topology
    </span>
    <h1>Branch Mapping</h1>
    <p class="sub">Each store is routed to a GoHighLevel location from explicit configuration and active OAuth installs. Unresolved or conflicting branches are held — never rerouted.</p>
  </div>
  <form method="POST" action="{{ route('admin.mappings.auto-map') }}">
    @csrf
    <button type="submit" class="btn primary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
      <span>Run auto-map</span>
    </button>
  </form>
</div>

@php
  $totalStores = $rows->count();
  $routableCount = $rows->filter(fn($r) => $r['decision']['routable'])->count();
  $heldCount = $totalStores - $routableCount;
@endphp

<div class="metric-row">
  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Discovered Branches</span>
      <div class="metric-icon metric-icon--violet">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
      </div>
    </div>
    <div class="metric-num">{{ $totalStores }}</div>
    <div class="metric-sub">Active LaundryCard stores</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Routable Pairs</span>
      <div class="metric-icon metric-icon--emerald">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
      </div>
    </div>
    <div class="metric-num" style="color:var(--ok-light)">{{ $routableCount }}</div>
    <div class="metric-sub">1-to-1 store to location route</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Unmapped Branches</span>
      <div class="metric-icon metric-icon--amber">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      </div>
    </div>
    <div class="metric-num">{{ $heldCount }}</div>
    <div class="metric-sub">Safely held from contact sync</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Unassigned GHL Installs</span>
      <div class="metric-icon metric-icon--cyan">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
      </div>
    </div>
    <div class="metric-num">{{ $unassigned->count() }}</div>
    <div class="metric-sub">Locations without a store pair</div>
  </div>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M18 6v6a6 6 0 0 1-6 6H6"></path></svg>
        Store ⇄ Location Routing Matrix
      </h2>
      <span class="muted" style="font-size:12px">Strict deterministic mapping between physical LaundryCard branches and GoHighLevel sub-accounts</span>
    </div>
    <span class="badge b-info">1:1 Isolation Enforced</span>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Store ID</th>
          <th>LaundryCard Branch</th>
          <th>GHL Location ID</th>
          <th>GHL Sub-Account</th>
          <th>Mapping Status</th>
          <th>Provenance</th>
          <th>OAuth Health</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
          @php($store = $row['store'])
          @php($decision = $row['decision'])
          @php($mapping = $decision['mapping'])
          @php($connection = $decision['connection'])
          <tr>
            <td><span class="store-id-pill">Store {{ $store->store_id }}</span></td>
            <td style="font-weight:700;color:var(--text-title)">{{ $store->name ?? '—' }}</td>
            <td>
              @if($connection?->location_id)
                <span class="card-mono">{{ $connection->location_id }}</span>
              @else
                <span class="muted">—</span>
              @endif
            </td>
            <td style="font-weight:600;color:var(--text-title)">{{ $connection?->location_name ?? '—' }}</td>
            <td>
              @if($decision['routable'])
                <x-badge value="verified"/>
              @else
                <x-badge :value="$decision['reason'] ?? 'unmapped'"/>
              @endif
            </td>
            <td><span class="tag-chip">{{ $mapping?->origin ?? 'unresolved' }}</span></td>
            <td>
              @if($connection)
                <x-badge :value="$connection->status"/>
              @else
                <span class="muted" style="font-size:12px">no authorized connection</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="muted" style="text-align:center;padding:32px">No LaundryCard stores have been discovered.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-header-bar">
      <h2>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        Authorized GHL locations without a resolved mapping
      </h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px">
      @forelse($unassigned as $connection)
        <div class="clay-well" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
          <div>
            <div style="font-weight:700;color:var(--text-title)">{{ $connection->location_name ?? '—' }}</div>
            <div class="mono muted" style="font-size:11.5px;margin-top:2px">{{ $connection->location_id }}</div>
          </div>
          <x-badge :value="$connection->status"/>
        </div>
      @empty
        <div class="clay-well muted" style="text-align:center;padding:24px">
          None — every location is mapped.
        </div>
      @endforelse
    </div>
  </div>

  <div class="card">
    <div class="card-header-bar">
      <h2>
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
        Automatic mapping activity
      </h2>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>When</th>
            <th>Decision</th>
            <th>Store</th>
            <th>Location</th>
          </tr>
        </thead>
        <tbody>
          @forelse($audit as $entry)
            <tr>
              <td class="muted">{{ $entry->created_at?->diffForHumans() }}</td>
              <td><x-badge :value="$entry->action"/></td>
              <td class="mono">{{ $entry->store?->store_id ?? '—' }}</td>
              <td class="mono">{{ $entry->connection?->location_id ?? '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="muted" style="text-align:center;padding:24px">No mapping decisions recorded.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
