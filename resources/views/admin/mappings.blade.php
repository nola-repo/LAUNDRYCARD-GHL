@extends('layouts.app')
@section('title', 'Branch Mapping')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-actions.css') }}?v={{ filemtime(public_path('css/admin-actions.css')) }}">
<link rel="stylesheet" href="{{ asset('css/mappings.css') }}?v={{ filemtime(public_path('css/mappings.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/admin-actions.js') }}?v={{ filemtime(public_path('js/admin-actions.js')) }}" defer></script>
<script src="{{ asset('js/mappings.js') }}?v={{ filemtime(public_path('js/mappings.js')) }}" defer></script>
@endpush
@section('content')
@php
  $totalStores = $rows->count();
  $routableCount = $rows->filter(fn ($row) => $row['decision']['routable'])->count();
  $heldCount = $totalStores - $routableCount;
  $reasonLabels = [
    'no_mapping' => ['No mapping', 'No verified location is assigned to this branch.'],
    'mapping_not_verified' => ['Not verified', 'The proposed mapping is not verified for sync.'],
    'mapping_conflict' => ['Mapping conflict', 'The configured location differs from the saved mapping.'],
    'connection_unusable' => ['Connection unavailable', 'The mapped OAuth connection cannot be used for sync.'],
  ];
@endphp

<div class="mapping-page" id="mapping-page">
  <header class="mapping-hero" id="mapping-top">
    <div class="mapping-hero-copy">
      <p class="mapping-eyebrow">Branch routing · LaundryCard + GoHighLevel</p>
      <h1>Branch mapping</h1>
      <p class="mapping-intro">Every branch, its own destination. Review store connections and resolve what is holding sync.</p>
    </div>
    <div class="mapping-rule">
      <span class="mapping-pair-symbol" aria-hidden="true"><i></i><span></span><i></i></span>
      <strong>One branch. One location.</strong>
      <p>Only configured pairs can sync.<br>Conflicting routes stay on hold.</p>
    </div>
    <dl class="mapping-totals" aria-label="Branch routing summary">
      <div><dt>Discovered branches</dt><dd>{{ $totalStores }}</dd></div>
      <div class="mapping-total-ready"><dt>Ready to sync</dt><dd>{{ $routableCount }}</dd></div>
      <div class="mapping-total-held"><dt>Held branches</dt><dd>{{ $heldCount }}</dd></div>
      <div class="mapping-total-locations"><dt>Unassigned locations</dt><dd>{{ $unassigned->count() }}</dd></div>
    </dl>
  </header>

  <div class="mapping-toolbar" data-mapping-toolbar>
    <a class="mapping-toolbar-title" href="#mapping-top">Branch mapping</a>
    <nav aria-label="Branch mapping sections">
      <a href="#branch-routes" aria-current="location">Routes</a>
      <a href="#unassigned-locations">Unassigned <span>{{ $unassigned->count() }}</span></a>
      <a href="#mapping-activity">Activity</a>
    </nav>
    <div class="mapping-toolbar-actions">
      <a class="admin-action" href="{{ route('admin.installations') }}">OAuth installations</a>
      <button class="admin-action admin-action-primary" type="button" data-confirm-open="auto-map-dialog" aria-haspopup="dialog">Run auto-map</button>
    </div>
  </div>

  <section class="mapping-section" id="branch-routes" aria-labelledby="branch-routes-title">
    <div class="mapping-section-heading">
      <div><p class="mapping-eyebrow">Store connections</p><h2 id="branch-routes-title" tabindex="-1">Branch routes</h2></div>
      <p>Read across a row to trace each destination.</p>
    </div>
    <div class="mapping-route-panel">
      <div class="mapping-filters" data-mapping-controls hidden>
        <div class="mapping-search">
          <label for="mapping-search">Find a branch or location</label>
          <div class="mapping-search-field">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
            <input id="mapping-search" type="search" autocomplete="off" placeholder="Name, store ID or location ID" aria-controls="mapping-route-body">
            <button type="button" class="mapping-search-clear" aria-label="Clear branch search" hidden>×</button>
          </div>
        </div>
        <div class="mapping-filter-group" role="group" aria-label="Filter by routing status">
          <button type="button" data-mapping-filter="all" aria-pressed="true">All <span>{{ $totalStores }}</span></button>
          <button type="button" data-mapping-filter="ready" aria-pressed="false">Ready <span>{{ $routableCount }}</span></button>
          <button type="button" data-mapping-filter="held" aria-pressed="false">Held <span>{{ $heldCount }}</span></button>
        </div>
      </div>
      <table class="mapping-route-table">
        <caption class="mapping-sr-only">LaundryCard branches and their GoHighLevel destinations, routing decisions, mapping sources, and OAuth health.</caption>
        <thead><tr><th scope="col">LaundryCard branch</th><th scope="col">GoHighLevel location</th><th scope="col">Routing decision</th><th scope="col">Source &amp; OAuth</th></tr></thead>
        <tbody id="mapping-route-body">
          @forelse($rows as $row)
            @php
              $store = $row['store'];
              $decision = $row['decision'];
              $mapping = $decision['mapping'];
              $connection = $decision['connection'];
              [$reasonLabel, $reasonHelp] = $reasonLabels[$decision['reason'] ?? ''] ?? ['On hold', 'This branch does not have a usable verified route.'];
              $origin = match ($mapping?->origin) {
                'explicit_config' => 'Configured pair',
                null => 'Unresolved',
                default => ucfirst(str_replace('_', ' ', $mapping->origin)),
              };
            @endphp
            <tr data-mapping-row data-routing="{{ $decision['routable'] ? 'ready' : 'held' }}">
              <th scope="row" data-label="LaundryCard branch">
                <div class="mapping-branch-identity">
                  <span class="mapping-branch-mark" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 8h.01M15 8h.01M9 11h.01M15 11h.01"/></svg></span>
                  <div><strong>{{ $store->name ?: 'Unnamed branch' }}</strong><span class="mapping-id">Store {{ $store->store_id }}</span>@if($store->status !== 'active')<span class="mapping-store-state">{{ ucfirst($store->status) }} in store discovery</span>@endif</div>
                </div>
              </th>
              <td data-label="GoHighLevel location" class="mapping-destination">
                <strong>{{ $connection?->location_name ?: ($connection ? 'Unnamed location' : 'No location assigned') }}</strong>
                @if($connection?->location_id)<span class="mapping-id">{{ $connection->location_id }}</span>@else<span class="mapping-cell-note">Awaiting a verified pair</span>@endif
              </td>
              <td data-label="Routing decision">
                <span class="mapping-state {{ $decision['routable'] ? 'mapping-state-ready' : 'mapping-state-held' }}"><span aria-hidden="true">{{ $decision['routable'] ? '✓' : '!' }}</span>{{ $decision['routable'] ? 'Ready to sync' : $reasonLabel }}</span>
                <p class="mapping-cell-note">{{ $decision['routable'] ? 'Verified branch and location pair.' : $reasonHelp }}</p>
              </td>
              <td data-label="Source & OAuth">
                <span class="mapping-origin">{{ $origin }}</span>
                @if($connection)<span class="mapping-oauth-health">OAuth <x-badge :value="$connection->status" /></span>@else<span class="mapping-cell-note">No authorized connection</span>@endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="mapping-empty"><strong>No branches discovered yet</strong><p>Branches will appear after LaundryCard store discovery runs.</p></td></tr>
          @endforelse
          <tr data-mapping-no-results hidden><td colspan="4" class="mapping-empty"><strong>No matching branches</strong><p>Try a different name or ID, or clear your filters.</p><button type="button" class="admin-action" data-mapping-reset>Clear filters</button></td></tr>
        </tbody>
      </table>
      <div class="mapping-pagination" data-mapping-controls hidden>
        <p id="mapping-result-count" role="status" aria-live="polite" aria-atomic="true"></p>
        <div><button type="button" class="admin-action" data-mapping-previous>Previous</button><span data-mapping-page-count></span><button type="button" class="admin-action" data-mapping-next>Next</button></div>
      </div>
    </div>
    <p class="mapping-route-note">Held branches remain excluded from contact sync until their configured route is usable.</p>
  </section>

  <div class="mapping-support-grid">
    <section class="mapping-panel mapping-section" id="unassigned-locations" aria-labelledby="unassigned-title">
      <div class="mapping-panel-heading"><div><p class="mapping-eyebrow">Awaiting a branch</p><h2 id="unassigned-title" tabindex="-1">Unassigned locations</h2></div><span class="mapping-count">{{ $unassigned->count() }}</span></div>
      <p class="mapping-panel-intro">OAuth locations without a verified branch mapping.</p>
      <div class="mapping-list-scroll" tabindex="0" role="region" aria-label="Unassigned OAuth locations">
        <ul class="mapping-location-list">
          @forelse($unassigned as $connection)
            <li><div><strong>{{ $connection->location_name ?: 'Unnamed location' }}</strong><span class="mapping-id">{{ $connection->location_id }}</span></div><x-badge :value="$connection->status" /></li>
          @empty
            <li class="mapping-empty"><strong>No unassigned locations</strong><p>There are no OAuth locations awaiting a branch mapping.</p></li>
          @endforelse
        </ul>
      </div>
      <div class="mapping-panel-footer"><p>Check the configured store/location pair before running auto-map.</p><a class="admin-action" href="{{ route('admin.installations') }}">Review installations</a></div>
    </section>

    <section class="mapping-panel mapping-section" id="mapping-activity" aria-labelledby="mapping-activity-title">
      <div class="mapping-panel-heading"><div><p class="mapping-eyebrow">Mapping history</p><h2 id="mapping-activity-title" tabindex="-1">Recent decisions</h2></div><span class="mapping-history-label">Latest {{ $audit->count() }}</span></div>
      <p class="mapping-panel-intro">Automatic mapping decisions, recorded in UTC.</p>
      <div class="mapping-list-scroll" tabindex="0" role="region" aria-label="Recent mapping decisions">
        <ol class="mapping-activity-list">
          @forelse($audit as $entry)
            <li>
              <div class="mapping-activity-title"><strong>{{ $entry->action === 'auto_verified' ? 'Pair verified automatically' : ucfirst(str_replace('_', ' ', $entry->action)) }}</strong><time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->utc()->format('M d, Y H:i') }} UTC</time></div>
              <p><span class="mapping-id">{{ $entry->store ? 'Store '.$entry->store->store_id : 'Store unavailable' }}</span><span class="mapping-activity-separator" aria-hidden="true">/</span><span class="mapping-id">{{ $entry->connection?->location_id ?? 'Location unavailable' }}</span></p>
            </li>
          @empty
            <li class="mapping-empty"><strong>No decisions recorded yet</strong><p>New mapping decisions will appear here after auto-map runs.</p></li>
          @endforelse
        </ol>
      </div>
    </section>
  </div>

  <x-confirm-action id="auto-map-dialog" title="Run automatic branch mapping?" :action="route('admin.mappings.auto-map')" confirm-label="Run auto-map" busy-label="Running auto-map…">
    <p>Check the configured branch/location pairs and create any missing mappings where both the store and OAuth connection are active.</p>
    <p>Existing mappings stay in place. Conflicting or unresolved branches remain on hold.</p>
  </x-confirm-action>
</div>
@endsection
