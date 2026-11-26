@extends('layouts.app')
@section('title','Customer Records')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/records.css') }}?v={{ @filemtime(public_path('css/records.css')) }}">
@endpush
@section('content')

<div class="records-page">
  <div class="page-head">
    <div>
      <span class="page-kicker">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        LaundryCard directory
      </span>
      <h1>Customer records</h1>
      <p class="sub">Track cardholders from discovery through GoHighLevel sync.</p>
    </div>
    <div class="btn-row">
      <a href="{{ route('admin.records.export', request()->query()) }}" class="btn" title="Export matching customer records as CSV">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
        <span>Export CSV</span>
      </a>
    </div>
  </div>

  <div id="records-live-bar" class="records-live-bar records-live-bar--connecting"
       data-url="{{ route('admin.monitoring.updates') }}">
    <div class="records-live-bar__inner">
      <span class="records-live-bar__indicator" aria-hidden="true"></span>
      <span id="records-live-text" role="status" aria-live="polite" aria-atomic="true">Connecting to the customer record feed…</span>
      <span id="records-new-badge" class="records-new-badge" role="status" aria-live="polite" style="display:none"></span>
      <button id="records-refresh-btn" class="records-refresh-btn" type="button" style="display:none">
        Refresh records
      </button>
      <a id="records-signin-link" class="records-new-badge" href="{{ route('login') }}" style="display:none">Sign in</a>
      <span class="records-live-bar__meta" aria-live="off">
        <span id="records-live-checked">Waiting for first check</span>
        <span id="records-live-count">{{ number_format($counts['all']) }} records in directory</span>
      </span>
    </div>
    <div class="records-live-bar__progress" id="records-live-progress" aria-hidden="true"></div>
  </div>

  <dl class="records-metrics" aria-label="Customer record totals">
    <div class="records-metric records-metric--lead">
      <dt>Total accounts</dt>
      <dd data-live-stat="records">{{ number_format($counts['all']) }}</dd>
      <small>All discovered cards</small>
    </div>
    <div class="records-metric records-metric--new">
      <dt>New active · 90 days</dt>
      <dd>{{ number_format($counts['new']) }}</dd>
      <small>Recent card activity</small>
    </div>
    <div class="records-metric">
      <dt>Named accounts</dt>
      <dd>{{ number_format($counts['named']) }}</dd>
      <small>Customer name on file</small>
    </div>
    <div class="records-metric records-metric--synced">
      <dt>Synced to GHL</dt>
      <dd>{{ number_format($counts['synced']) }}</dd>
      <small>Created or updated contacts</small>
    </div>
    <div class="records-metric records-metric--held">
      <dt>Needs attention</dt>
      <dd>{{ number_format($counts['held']) }}</dd>
      <small>Held or failed syncs</small>
    </div>
  </dl>

  <nav class="records-categories" aria-label="Customer record categories">
    <a href="{{ route('admin.records') }}" class="filter-tab {{ empty($activeTab) && !request()->hasAny(['classification','status','has_name']) ? 'active' : '' }}" @if(empty($activeTab) && !request()->hasAny(['classification','status','has_name'])) aria-current="page" @endif>
      <span>All records</span><span class="tab-badge">{{ number_format($counts['all']) }}</span>
    </a>
    <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','classification']), ['tab' => 'new'])) }}" class="filter-tab {{ $activeTab === 'new' || request('classification') === \App\Models\LaundrycardSourceRecord::NEW_OBSERVED ? 'active' : '' }}" @if($activeTab === 'new' || request('classification') === \App\Models\LaundrycardSourceRecord::NEW_OBSERVED) aria-current="page" @endif>
      <span>New active</span><span class="tab-badge">{{ number_format($counts['new']) }}</span>
    </a>
    <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','has_name']), ['tab' => 'named'])) }}" class="filter-tab {{ $activeTab === 'named' || request('has_name') === 'yes' ? 'active' : '' }}" @if($activeTab === 'named' || request('has_name') === 'yes') aria-current="page" @endif>
      <span>Has a name</span><span class="tab-badge">{{ number_format($counts['named']) }}</span>
    </a>
    <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','status']), ['tab' => 'synced'])) }}" class="filter-tab {{ $activeTab === 'synced' ? 'active' : '' }}" @if($activeTab === 'synced') aria-current="page" @endif>
      <span>Synced</span><span class="tab-badge">{{ number_format($counts['synced']) }}</span>
    </a>
    <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','status']), ['tab' => 'held'])) }}" class="filter-tab {{ $activeTab === 'held' ? 'active' : '' }}" @if($activeTab === 'held') aria-current="page" @endif>
      <span>Needs attention</span><span class="tab-badge">{{ number_format($counts['held']) }}</span>
    </a>
  </nav>

  <form class="records-filters" method="get" action="{{ route('admin.records') }}">
    @if(request('tab'))
      <input type="hidden" name="tab" value="{{ request('tab') }}">
    @endif
    <input type="hidden" name="per_page" value="{{ $perPage }}">
    <div class="records-search-row">
      <div class="records-search-field">
        <label for="records-search">Find a card</label>
        <div class="records-search-input">
          <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"></circle><path d="m16 16 4.5 4.5"></path></svg>
          <input id="records-search" type="search" name="search" inputmode="numeric" autocomplete="off" placeholder="Enter a card number" value="{{ request('search', request('q')) }}">
        </div>
      </div>
      <button type="submit" class="btn primary records-search-submit">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.4-4.4"></path></svg>
        Search records
      </button>
    </div>
    <details class="records-advanced" @if(request()->hasAny(['store','classification','status','has_name','outcome','from','to'])) open @endif>
        <summary>
          <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
          More filters
        </summary>
        <div class="records-advanced__body">
          <div class="filter-group">
            <label class="filter-label" for="records-store">Store branch</label>
            <select id="records-store" name="store">
              <option value="">All stores</option>
              @foreach($stores as $s)
                <option value="{{ $s->store_id }}" @selected(request('store') === $s->store_id)>Store {{ $s->store_id }} — {{ $s->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="records-classification">Customer type</label>
            <select id="records-classification" name="classification">
              <option value="">Any classification</option>
              @foreach($classificationLabels as $key => $lbl)
                <option value="{{ $key }}" @selected(request('classification') === $key)>{{ $lbl }}</option>
              @endforeach
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="records-status">Sync status</label>
            <select id="records-status" name="status">
              <option value="">Any sync status</option>
              @foreach($statuses as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ $statusLabels[$st] ?? ucwords(str_replace('_', ' ', $st)) }}</option>
              @endforeach
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="records-has-name">Contact profile</label>
            <select id="records-has-name" name="has_name">
              <option value="">All profiles</option>
              <option value="yes" @selected(request('has_name') === 'yes')>Identified</option>
              <option value="no" @selected(request('has_name') === 'no')>Anonymous cards</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label" for="records-outcome">Hold reason</label>
            <select id="records-outcome" name="outcome">
              <option value="">Any reason</option>
              @foreach($holdReasons as $hr)
                <option value="{{ $hr }}" @selected(request('outcome') === $hr)>{{ $holdReasonLabels[$hr] ?? ucwords(str_replace('_', ' ', $hr)) }}</option>
              @endforeach
            </select>
          </div>
          <div class="filter-group" role="group" aria-labelledby="records-date-range-label">
            <span id="records-date-range-label" class="filter-label">Observed date range</span>
            <div class="filter-dates">
              <label class="records-sr-only" for="records-from">From date</label>
              <input id="records-from" type="date" name="from" value="{{ request('from') }}">
              <label class="records-sr-only" for="records-to">To date</label>
              <input id="records-to" type="date" name="to" value="{{ request('to') }}">
            </div>
          </div>
        </div>
        <div class="records-filter-actions">
          <p>Filters combine with the selected category and card search.</p>
          <div class="filter-buttons">
            <button type="submit" class="btn primary">Apply filters</button>
            <a class="btn" href="{{ route('admin.records') }}">Reset all</a>
          </div>
        </div>
    </details>
  </form>
{{-- Active Filter Chips --}}
@php
  $activeFilters = [];
  $searchFilter = request('search', request('q'));
  if ($searchFilter) $activeFilters['search'] = 'Card: ' . $searchFilter;
  if (request('store')) {
    $stObj = $stores->firstWhere('store_id', request('store'));
    $activeFilters['store'] = 'Store: ' . ($stObj ? $stObj->name : request('store'));
  }
  if (request('classification')) {
    $activeFilters['classification'] = 'Type: ' . ($classificationLabels[request('classification')] ?? request('classification'));
  }
  if (request('status')) {
    $activeFilters['status'] = 'Status: ' . ($statusLabels[request('status')] ?? request('status'));
  }
  if (request('has_name')) {
    $activeFilters['has_name'] = request('has_name') === 'yes' ? 'Has Name' : 'Anonymous';
  }
  if (request('outcome')) {
    $activeFilters['outcome'] = 'Reason: ' . ($holdReasonLabels[request('outcome')] ?? request('outcome'));
  }
  if (request('from') || request('to')) {
    $activeFilters['date'] = 'Date: ' . (request('from') ?: 'Any') . ' → ' . (request('to') ?: 'Now');
  }
@endphp

@if(count($activeFilters) > 0)
  <div class="active-chips-bar">
    <span class="chip-label">Active filters</span>
    @foreach($activeFilters as $key => $txt)
      @php
        $removeKeys = match ($key) {
          'date' => ['from', 'to', 'page'],
          'search' => ['search', 'q', 'page'],
          default => [$key, 'page'],
        };
        $filterQuery = request()->except($removeKeys);
      @endphp
      <span class="filter-chip">
        {{ $txt }}
        <a href="{{ route('admin.records', $filterQuery) }}" class="chip-remove" title="Remove filter" aria-label="Remove {{ $txt }} filter">×</a>
      </span>
    @endforeach
    <a href="{{ route('admin.records') }}" class="btn sm">Clear all</a>
  </div>
@endif

<section class="records-table-card" aria-labelledby="records-results-title">
  <div class="records-results-head">
    <div>
      <h2 id="records-results-title">Customer records</h2>
      <p>Balance, eligibility and contact sync for each card.</p>
    </div>
    <p class="records-results-count" aria-live="polite">
      Showing <strong>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($records->total()) }}</strong> matches
    </p>
  </div>
  <div class="table-scroll" tabindex="0" aria-label="Customer records results">
    <table class="records-table">
      <caption class="records-sr-only">Customer records with LaundryCard branch, account balance and GoHighLevel sync status.</caption>
      <thead>
        <tr>
          <th scope="col" style="width:125px">Store</th>
          <th scope="col" style="width:115px">Card #</th>
          <th scope="col">Customer details</th>
          <th scope="col" style="width:95px">Balance</th>
          <th scope="col" style="width:125px">Customer type</th>
          <th scope="col" style="width:125px">Eligibility</th>
          <th scope="col" style="width:165px">Sync status</th>
          <th scope="col" style="width:135px">Last activity (UTC)</th>
          <th scope="col" style="width:120px">GHL contact</th>
        </tr>
      </thead>
      <tbody>
        @forelse($records as $r)
        <tr>
          {{-- Store --}}
          <td data-label="Store">
            <div style="font-weight:700;font-size:12.5px;color:var(--text-title)">{{ $r->store->name ?? 'Store ' . $r->store->store_id }}</div>
            <div class="muted mono" style="font-size:11px;margin-top:2px">#{{ $r->store->store_id }}</div>
          </td>

          {{-- Card # --}}
          <td data-label="Card number">
            <a class="card-mono" href="{{ route('admin.transactions', ['card' => $r->card_number, 'store' => $r->store->store_id ?? null]) }}" title="View credit history">
              {{ $r->card_number }}
            </a>
          </td>

          {{-- Customer Name & Details --}}
          <td data-label="Customer details">
            <div class="user-cell">
              @php
                $hasName = filled($r->customer_name);
                $hasEmail = filled($r->customer_email);
                $hasPhone = filled($r->customer_phone);
                $hasMach = app(\App\Services\LaundryCardClient::class)->hasMachNo($r->store->store_id ?? '');
                $previewTags = app(\App\Services\ContactClassificationService::class)->managedTags($r);
              @endphp
              @if($hasName || $hasEmail || $hasPhone)
                <div class="user-name">
                  <span class="user-avatar">{{ strtoupper(substr(trim($r->customer_name ?: ($r->customer_email ?: 'C')), 0, 1)) }}</span>
                  <span>{{ $hasName ? ucwords(strtolower($r->customer_name)) : 'Cardholder #' . $r->card_number }}</span>
                  <span class="badge b-ok" style="font-size:10px;margin-left:4px" title="Customer contact details available">Identified</span>
                </div>
                <div class="user-meta">
                  @if($r->customer_email)
                    <span class="user-meta-item">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                      {{ $r->customer_email }}
                    </span>
                  @endif
                  @if($r->customer_phone)
                    <span class="user-meta-item">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                      {{ $r->customer_phone }}
                    </span>
                  @endif
                </div>
              @else
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                  <span class="anon-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Anonymous
                  </span>
                  @if($hasMach)
                    <span class="badge b-info" style="font-size:10px" title="POS MachNo configured for this store">POS Ready</span>
                  @else
                    <span class="badge b-mute" style="font-size:10px" title="Awaiting store POS MachNo configuration">No MachNo</span>
                  @endif
                </div>
              @endif

              {{-- GHL Tag Preview --}}
              <div class="tag-row">
                @foreach(array_slice($previewTags, 0, 3) as $ptag)
                  <span class="tag-chip">{{ $ptag }}</span>
                @endforeach
                @if(count($previewTags) > 3)
                  <span class="tag-more" title="{{ implode(', ', $previewTags) }}">+{{ count($previewTags) - 3 }}</span>
                @endif
              </div>
            </div>
          </td>

          {{-- Balance & Reload History --}}
          <td data-label="Balance">
            @php $bal = (float) $r->balance; @endphp
            @if($r->balance === null)
              <span class="balance-pill bal-zero">Pending</span>
            @elseif($bal > 0)
              <span class="balance-pill bal-pos">${{ number_format($bal, 2) }}</span>
            @elseif($bal == 0)
              <span class="balance-pill bal-zero">$0.00</span>
            @else
              <span class="balance-pill bal-neg">-${{ number_format(abs($bal), 2) }}</span>
            @endif
            @if(($r->reload_count ?? 0) > 0)
              <div style="margin-top:4px">
                <a href="{{ route('admin.transactions', ['card' => $r->card_number, 'store' => $r->store->store_id ?? null]) }}" style="font-size:10.5px;font-weight:700;color:var(--accent);text-decoration:none" title="View card reload history">
                  +{{ $r->reload_count }} reload{{ $r->reload_count > 1 ? 's' : '' }} (${{ number_format((float) $r->reload_total, 2) }}) →
                </a>
              </div>
            @endif
          </td>

          {{-- Classification --}}
          <td data-label="Customer type">
            @if($r->classification === 'new_observed')
              <span class="badge b-ok" title="Customer had activity within last 90 days">New Active (90d)</span>
            @elseif($r->classification === 'confirmed_new')
              <span class="badge b-info">Confirmed New</span>
            @else
              <span class="badge b-mute" title="Historical baseline cardholder">Historical</span>
            @endif
          </td>

          {{-- Eligibility --}}
          <td data-label="Eligibility">
            <x-badge :value="$r->eligibility_status"/>
            @if($r->eligibility_reason && $r->eligibility_reason !== $r->eligibility_status)
              <div class="muted" style="font-size:11px;margin-top:3px">{{ ucwords(str_replace('_',' ',$r->eligibility_reason)) }}</div>
            @endif
          </td>

          {{-- Routing / Sync --}}
          <td data-label="Sync status">
            <x-badge :value="$r->processing_status"/>
            @if($r->hold_reason)
              <div class="muted" style="font-size:11px;margin-top:3px;color:var(--warn-light);font-weight:600">
                {{ $holdReasonLabels[$r->hold_reason] ?? ucwords(str_replace('_',' ',$r->hold_reason)) }}
              </div>
            @endif
            @if($r->destination_location_id)
              <div class="muted mono" style="font-size:10px;margin-top:2px">Loc: {{ \Illuminate\Support\Str::limit($r->destination_location_id, 12) }}</div>
            @endif
          </td>

          {{-- Activity Timestamp --}}
          <td data-label="Last activity (UTC)">
            @if($r->source_timestamp)
              <div style="font-size:12px;font-weight:600;color:var(--text-title)">{{ $r->source_timestamp->format('M d, Y') }}</div>
              <div class="muted" style="font-size:11px">{{ $r->source_timestamp->format('h:i A') }} ({{ $r->source_timestamp->diffForHumans() }})</div>
            @else
              <div style="font-size:12px;font-weight:600;color:var(--text-title)">{{ $r->first_observed_at->format('M d, Y') }}</div>
              <div class="muted" style="font-size:11px">Observed {{ $r->first_observed_at->diffForHumans() }}</div>
            @endif
          </td>

          {{-- GHL Contact ID --}}
          <td data-label="GHL contact">
            @if($r->contactMapping?->ghl_contact_id)
              <span class="card-mono" style="font-size:11px">{{ \Illuminate\Support\Str::limit($r->contactMapping->ghl_contact_id, 14) }}</span>
            @else
              <span class="muted" style="font-size:12px">—</span>
            @endif
          </td>
        </tr>
        @empty
        <tr class="records-empty-row">
          <td colspan="9">
            <div class="empty-state-box">
              <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </div>
              <div class="empty-state-text">No customer records match these filters</div>
              <p class="muted" style="font-size:12.5px;margin:6px 0 16px">Remove a filter or search another card number.</p>
              <a class="btn primary" href="{{ route('admin.records') }}">Clear all filters</a>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

<div class="records-pagination">
  <div class="pagination-controls">
    <div class="per-page-select">
      <label for="records-per-page">Show</label>
      <form method="get" action="{{ route('admin.records') }}" id="perPageForm">
        @foreach(request()->except(['per_page', 'page']) as $k => $v)
          <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <select id="records-per-page" name="per_page" aria-label="Records per page" onchange="document.getElementById('perPageForm').submit()">
          <option value="15" @selected($perPage == 15)>15</option>
          <option value="25" @selected($perPage == 25)>25</option>
          <option value="50" @selected($perPage == 50)>50</option>
          <option value="100" @selected($perPage == 100)>100</option>
        </select>
      </form>
      <span>per page</span>
    </div>
    <div class="pager" style="margin-top:0">
      {{ $records->links() }}
    </div>
  </div>
</div>

</div>

<script>
(() => {
  const bar        = document.getElementById('records-live-bar');
  const text       = document.getElementById('records-live-text');
  const badge      = document.getElementById('records-new-badge');
  const refreshBtn = document.getElementById('records-refresh-btn');
  const signInLink = document.getElementById('records-signin-link');
  const checkedEl  = document.getElementById('records-live-checked');
  const countEl    = document.getElementById('records-live-count');
  const progress   = document.getElementById('records-live-progress');
  if (!bar) return;

  const apiUrl  = bar.dataset.url;
  const POLL_MS = 6000;
  const POLL_SECONDS = Math.round(POLL_MS / 1000);
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let knownTotal  = {{ $counts['all'] }};
  let lastEventId = 0;
  let newCount    = 0;
  let animFrame   = null;
  let pollTimer   = null;
  let requestTimer = null;
  let controller  = null;
  let stopped     = false;
  let inFlight    = false;

  function animateProgress(startTs) {
    const elapsed = Date.now() - startTs;
    const pct     = Math.min(100, (elapsed / POLL_MS) * 100);
    progress.style.width = pct + '%';
    if (pct < 100 && !document.hidden && !stopped) {
      animFrame = requestAnimationFrame(() => animateProgress(startTs));
    }
  }

  function setState(state) {
    bar.className = 'records-live-bar records-live-bar--' + state;
  }

  function setStatus(message) {
    if (text.textContent !== message) text.textContent = message;
  }

  function checkedTime(value) {
    const date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) return 'UTC';
    return new Intl.DateTimeFormat(undefined, {
      hour: 'numeric', minute: '2-digit', timeZone: 'UTC', timeZoneName: 'short'
    }).format(date);
  }

  function stopPolling() {
    stopped = true;
    clearTimeout(pollTimer);
    clearTimeout(requestTimer);
    cancelAnimationFrame(animFrame);
    controller?.abort();
    progress.style.width = '0%';
  }

  async function poll() {
    if (stopped || document.hidden || inFlight) return;
    cancelAnimationFrame(animFrame);
    progress.style.width = '0%';
    inFlight = true;
    controller = new AbortController();
    requestTimer = setTimeout(() => controller?.abort(), 10000);

    try {
      const res = await fetch(`${apiUrl}?after=${lastEventId}`, {
        signal: controller.signal,
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (res.status === 401) {
        setStatus('Sign in again to reconnect the live feed.');
        signInLink.style.display = 'inline-flex';
        setState('disconnected');
        stopPolling();
        return;
      }
      if (res.status === 403) {
        setStatus('Your account cannot access live record updates.');
        setState('disconnected');
        stopPolling();
        return;
      }
      if (!res.ok) throw new Error('Feed request failed');
      const data = await res.json();

      lastEventId = data.last_event_id || lastEventId;

      const liveTotal = data.stats?.records ?? knownTotal;
      const diff = Number(liveTotal) - Number(knownTotal);

      if (diff > 0) {
        newCount += diff;
        badge.textContent = `+${newCount.toLocaleString()} new record${newCount !== 1 ? 's' : ''}`;
        badge.style.display = 'inline-flex';
        refreshBtn.style.display = 'inline-flex';
        setStatus('New records are ready to load.');
        setState('new');
      } else if (newCount === 0) {
        setStatus('Live feed connected.');
        setState('connected');
      }
      knownTotal = Number(liveTotal);

      if (data.stats) {
        for (const [key, value] of Object.entries(data.stats)) {
          const el = document.querySelector(`[data-live-stat="${key}"]`);
          if (el && value !== null && typeof value !== 'object') el.textContent = Number(value).toLocaleString();
        }
      }

      checkedEl.textContent = `Checked ${checkedTime(data.updated_at)} · every ${POLL_SECONDS}s`;
      countEl.textContent = `${Number(liveTotal).toLocaleString()} records in directory`;
    } catch (err) {
      if (!document.hidden && !stopped) {
        setStatus('Live feed unavailable. Retrying shortly.');
        setState('disconnected');
      }
    } finally {
      clearTimeout(requestTimer);
      controller = null;
      inFlight = false;
      if (!stopped && !document.hidden) {
        if (!reduceMotion) animateProgress(Date.now());
        pollTimer = setTimeout(poll, POLL_MS);
      }
    }
  }

  refreshBtn.addEventListener('click', () => window.location.reload());
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearTimeout(pollTimer);
      clearTimeout(requestTimer);
      cancelAnimationFrame(animFrame);
      controller?.abort();
      progress.style.width = '0%';
    } else if (!stopped && !inFlight) {
      poll();
    }
  });
  window.addEventListener('pagehide', stopPolling, { once: true });
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      stopped = false;
      poll();
    }
  });

  poll();
})();
</script>
@endsection
