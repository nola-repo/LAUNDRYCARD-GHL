@extends('layouts.app')
@section('title','Customer Records')
@section('content')

{{-- Live API Feed Indicator Bar --}}
<div id="records-live-bar" class="records-live-bar records-live-bar--connecting"
     data-url="{{ route('admin.monitoring.updates') }}">
  <div class="records-live-bar__inner">
    <span class="records-live-bar__indicator"></span>
    <span id="records-live-text">Connecting to live feed…</span>
    <span id="records-new-badge" class="records-new-badge" style="display:none"></span>
    <button id="records-refresh-btn" class="records-refresh-btn" style="display:none" onclick="window.location.reload()">
      Load New Records
    </button>
    <span class="records-live-bar__right muted" id="records-live-count"></span>
  </div>
  <div class="records-live-bar__progress" id="records-live-progress"></div>
</div>

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
      Customer Directory
    </span>
    <h1 style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      Incoming Customer Records
      <span class="live-pill"><span class="dot ok"></span> Live Database</span>
    </h1>
    <p class="sub" style="margin-bottom:0">Persisted LaundryCard accounts, customer identities, and synchronization status to GoHighLevel.</p>
  </div>
</div>

{{-- Top Metrics Overview --}}
<div class="metric-row">
  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Total Records</span>
      <div class="metric-icon metric-icon--violet">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
      </div>
    </div>
    <div class="metric-num" data-live-stat="records">{{ number_format($counts['all']) }}</div>
    <div class="metric-sub">All discovered accounts</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">New Active (90d)</span>
      <div class="metric-icon metric-icon--emerald">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
      </div>
    </div>
    <div class="metric-num" style="color: var(--ok-light)" data-live-stat="new_customers">{{ number_format($counts['new']) }}</div>
    <div class="metric-sub">Recent card activity</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Named Accounts</span>
      <div class="metric-icon metric-icon--cyan">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
      </div>
    </div>
    <div class="metric-num" style="color: var(--accent-cyan)">{{ number_format($counts['named']) }}</div>
    <div class="metric-sub">Customer identity linked</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Synced to GHL</span>
      <div class="metric-icon metric-icon--violet">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
      </div>
    </div>
    <div class="metric-num" style="color: var(--info-light)">{{ number_format($counts['synced']) }}</div>
    <div class="metric-sub">Contacts in GoHighLevel</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">On Hold / Review</span>
      <div class="metric-icon metric-icon--amber">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      </div>
    </div>
    <div class="metric-num" style="color: var(--warn-light)">{{ number_format($counts['held']) }}</div>
    <div class="metric-sub">Held ($0 balance or unmapped)</div>
  </div>
</div>

{{-- Quick Filter Segment Tabs --}}
<div class="filter-tabs">
  <a href="{{ route('admin.records') }}" class="filter-tab {{ empty($activeTab) && !request()->hasAny(['classification','status','has_name']) ? 'active' : '' }}">
    <span>All Records</span>
    <span class="tab-badge">{{ number_format($counts['all']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','classification']), ['tab' => 'new'])) }}" class="filter-tab {{ $activeTab === 'new' || request('classification') === \App\Models\LaundrycardSourceRecord::NEW_OBSERVED ? 'active' : '' }}">
    <span>New Active Customers</span>
    <span class="tab-badge">{{ number_format($counts['new']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','has_name']), ['tab' => 'named'])) }}" class="filter-tab {{ $activeTab === 'named' || request('has_name') === 'yes' ? 'active' : '' }}">
    <span>With Customer Name</span>
    <span class="tab-badge">{{ number_format($counts['named']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','status']), ['tab' => 'synced'])) }}" class="filter-tab {{ $activeTab === 'synced' ? 'active' : '' }}">
    <span>Synced to GHL</span>
    <span class="tab-badge">{{ number_format($counts['synced']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','status']), ['tab' => 'held'])) }}" class="filter-tab {{ $activeTab === 'held' ? 'active' : '' }}">
    <span>On Hold / Attention</span>
    <span class="tab-badge">{{ number_format($counts['held']) }}</span>
  </a>
</div>

{{-- Clear & Intuitive Filter Panel --}}
<div class="filter-panel">
  <div class="filter-title">
    <h3>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filter &amp; Search Records
    </h3>
    <span class="muted" style="font-size:12px">Refine customer cards by branch, classification, or sync state</span>
  </div>

  <form method="get" action="{{ route('admin.records') }}">
    @if(request('tab'))
      <input type="hidden" name="tab" value="{{ request('tab') }}">
    @endif

    <div class="filter-grid">
      {{-- Card Search --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Card Number</span>
          <span class="filter-hint">Search #</span>
        </label>
        <input type="text" name="search" placeholder="e.g. 100234..." value="{{ request('search', request('q')) }}">
      </div>

      {{-- Store Selection --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Store Branch</span>
          <span class="filter-hint">Location</span>
        </label>
        <select name="store">
          <option value="">All Stores (All Branches)</option>
          @foreach($stores as $s)
            <option value="{{ $s->store_id }}" @selected(request('store') === $s->store_id)>
              Store {{ $s->store_id }} — {{ $s->name }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Customer Classification --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Customer Type</span>
          <span class="filter-hint">Activity</span>
        </label>
        <select name="classification">
          <option value="">Any Classification</option>
          @foreach($classificationLabels as $key => $lbl)
            <option value="{{ $key }}" @selected(request('classification') === $key)>
              {{ $lbl }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Sync Status --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Sync Status</span>
          <span class="filter-hint">GHL State</span>
        </label>
        <select name="status">
          <option value="">Any Sync Status</option>
          @foreach($statuses as $st)
            <option value="{{ $st }}" @selected(request('status') === $st)>
              {{ $statusLabels[$st] ?? ucwords(str_replace('_', ' ', $st)) }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Profile Info --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Contact Profile</span>
          <span class="filter-hint">Identity</span>
        </label>
        <select name="has_name">
          <option value="">All (Named &amp; Anonymous)</option>
          <option value="yes" @selected(request('has_name') === 'yes')>Has Customer Name</option>
          <option value="no" @selected(request('has_name') === 'no')>Anonymous Cards Only</option>
        </select>
      </div>

      {{-- Hold Reason --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Hold Reason</span>
          <span class="filter-hint">Exclusions</span>
        </label>
        <select name="outcome">
          <option value="">Any Outcome / Reason</option>
          @foreach($holdReasons as $hr)
            <option value="{{ $hr }}" @selected(request('outcome') === $hr)>
              {{ $holdReasonLabels[$hr] ?? ucwords(str_replace('_', ' ', $hr)) }}
            </option>
          @endforeach
        </select>
      </div>

      {{-- Date Range --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Observed Date</span>
          <span class="filter-hint">From &amp; To</span>
        </label>
        <div class="filter-dates">
          <input type="date" name="from" value="{{ request('from') }}" title="From Date">
          <input type="date" name="to" value="{{ request('to') }}" title="To Date">
        </div>
      </div>

      {{-- Page Size --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>Items Per Page</span>
          <span class="filter-hint">Pagination</span>
        </label>
        <select name="per_page">
          <option value="15" @selected($perPage == 15)>15 rows per page (Default)</option>
          <option value="25" @selected($perPage == 25)>25 rows per page</option>
          <option value="50" @selected($perPage == 50)>50 rows per page</option>
          <option value="100" @selected($perPage == 100)>100 rows per page</option>
        </select>
      </div>
    </div>

    <div class="filter-bar-footer">
      <div class="filter-buttons">
        <button type="submit" class="btn primary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <span>Apply Filters</span>
        </button>
        <a class="btn" href="{{ route('admin.records') }}">Reset All</a>
      </div>
      <div class="muted" style="font-size:12px">
        Showing <strong>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($records->total()) }}</strong> matching records
      </div>
    </div>
  </form>
</div>

{{-- Active Filter Chips --}}
@php
  $activeFilters = [];
  if (request('search')) $activeFilters['search'] = 'Card: ' . request('search');
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
    <span class="chip-label">Active Filters:</span>
    @foreach($activeFilters as $key => $txt)
      <span class="filter-chip">
        {{ $txt }}
        <a href="{{ route('admin.records', request()->except([$key === 'date' ? 'from' : $key, 'to', 'page'])) }}" class="chip-remove" title="Remove filter">✕</a>
      </span>
    @endforeach
    <a href="{{ route('admin.records') }}" class="btn sm" style="margin-left:4px">Clear All</a>
  </div>
@endif

{{-- Customer Records Table --}}
<div class="table-card">
  <div class="table-scroll">
    <table class="records-table">
      <thead>
        <tr>
          <th style="width:140px">Store</th>
          <th style="width:130px">Card #</th>
          <th>Customer Details</th>
          <th style="width:110px">Balance</th>
          <th style="width:150px">Customer Type</th>
          <th style="width:140px">Eligibility</th>
          <th style="width:175px">Sync Status</th>
          <th style="width:155px">Last Activity</th>
          <th style="width:130px">GHL Contact</th>
        </tr>
      </thead>
      <tbody>
        @forelse($records as $r)
        <tr>
          {{-- Store --}}
          <td>
            <div style="font-weight:700;font-size:12.5px;color:var(--text-title)">{{ $r->store->name ?? 'Store ' . $r->store->store_id }}</div>
            <div class="muted mono" style="font-size:11px;margin-top:2px">#{{ $r->store->store_id }}</div>
          </td>

          {{-- Card # --}}
          <td>
            <a class="card-mono" href="{{ route('admin.transactions', ['card' => $r->card_number, 'store' => $r->store->store_id ?? null]) }}" title="View credit history">
              {{ $r->card_number }}
            </a>
          </td>

          {{-- Customer Name & Details --}}
          <td>
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
          <td>
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
          <td>
            @if($r->classification === 'new_observed')
              <span class="badge b-ok" title="Customer had activity within last 90 days">New Active (90d)</span>
            @elseif($r->classification === 'confirmed_new')
              <span class="badge b-info">Confirmed New</span>
            @else
              <span class="badge b-mute" title="Historical baseline cardholder">Historical</span>
            @endif
          </td>

          {{-- Eligibility --}}
          <td>
            <x-badge :value="$r->eligibility_status"/>
            @if($r->eligibility_reason && $r->eligibility_reason !== $r->eligibility_status)
              <div class="muted" style="font-size:11px;margin-top:3px">{{ ucwords(str_replace('_',' ',$r->eligibility_reason)) }}</div>
            @endif
          </td>

          {{-- Routing / Sync --}}
          <td>
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
          <td>
            @if($r->source_timestamp)
              <div style="font-size:12px;font-weight:600;color:var(--text-title)">{{ $r->source_timestamp->format('M d, Y') }}</div>
              <div class="muted" style="font-size:11px">{{ $r->source_timestamp->format('h:i A') }} ({{ $r->source_timestamp->diffForHumans() }})</div>
            @else
              <div style="font-size:12px;font-weight:600;color:var(--text-title)">{{ $r->first_observed_at->format('M d, Y') }}</div>
              <div class="muted" style="font-size:11px">Observed {{ $r->first_observed_at->diffForHumans() }}</div>
            @endif
          </td>

          {{-- GHL Contact ID --}}
          <td>
            @if($r->contactMapping?->ghl_contact_id)
              <span class="card-mono" style="font-size:11px">{{ \Illuminate\Support\Str::limit($r->contactMapping->ghl_contact_id, 14) }}</span>
            @else
              <span class="muted" style="font-size:12px">—</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9">
            <div class="empty-state-box">
              <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </div>
              <div class="empty-state-text">No customer records match your filter</div>
              <p class="muted" style="font-size:12.5px;margin:6px 0 16px">Try adjusting or clearing your filters to see more results.</p>
              <a class="btn primary" href="{{ route('admin.records') }}">Clear All Filters</a>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- Pagination Footer with 15 Per Page --}}
<div class="pagination-wrapper">
  <div class="pagination-info">
    Showing <strong>{{ $records->firstItem() ?? 0 }}</strong> to <strong>{{ $records->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($records->total()) }}</strong> entries
  </div>
  <div class="pagination-controls">
    <div class="per-page-select">
      <span>Show</span>
      <form method="get" action="{{ route('admin.records') }}" id="perPageForm">
        @foreach(request()->except(['per_page', 'page']) as $k => $v)
          <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <select name="per_page" onchange="document.getElementById('perPageForm').submit()">
          <option value="15" @selected($perPage == 15)>15</option>
          <option value="25" @selected($perPage == 25)>25</option>
          <option value="50" @selected($perPage == 50)>50</option>
          <option value="100" @selected($perPage == 100)>100</option>
        </select>
      </form>
      <span>entries</span>
    </div>
    <div class="pager" style="margin-top:0">
      {{ $records->links() }}
    </div>
  </div>
</div>

<script>
(() => {
  const bar       = document.getElementById('records-live-bar');
  const text      = document.getElementById('records-live-text');
  const badge     = document.getElementById('records-new-badge');
  const refreshBtn= document.getElementById('records-refresh-btn');
  const countEl   = document.getElementById('records-live-count');
  const progress  = document.getElementById('records-live-progress');
  if (!bar) return;

  const apiUrl    = bar.dataset.url;
  const POLL_MS   = 6000;

  let knownTotal  = {{ $records->total() }};
  let lastEventId = 0;
  let newCount    = 0;
  let animFrame   = null;
  let pollStart   = null;

  function animateProgress(startTs) {
    const elapsed = Date.now() - startTs;
    const pct     = Math.min(100, (elapsed / POLL_MS) * 100);
    progress.style.width = pct + '%';
    if (pct < 100) animFrame = requestAnimationFrame(() => animateProgress(startTs));
  }

  function setState(state) {
    bar.className = 'records-live-bar records-live-bar--' + state;
  }

  async function poll() {
    cancelAnimationFrame(animFrame);
    progress.style.width = '0%';
    pollStart = Date.now();

    try {
      const res  = await fetch(`${apiUrl}?after=${lastEventId}`, {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      lastEventId = data.last_event_id || lastEventId;

      const liveTotal = data.stats?.records ?? knownTotal;
      const diff      = liveTotal - knownTotal;

      if (diff > 0) {
        newCount += diff;
        knownTotal = liveTotal;
        badge.textContent = `+${newCount} new record${newCount !== 1 ? 's' : ''} available`;
        badge.style.display = 'inline-flex';
        refreshBtn.style.display = 'inline-flex';
        text.textContent  = 'New incoming records detected —';
        setState('new');
      } else {
        text.textContent = `Live feed connected · checked ${new Date(data.updated_at).toLocaleTimeString()} · polling every ${Math.round(POLL_MS / 1000)}s`;
        setState('connected');
      }

      if (data.stats) {
        for (const [key, value] of Object.entries(data.stats)) {
          const el = document.querySelector(`[data-live-stat="${key}"]`);
          if (el && value !== null && typeof value !== 'object') el.textContent = Number(value).toLocaleString();
        }
      }

      countEl.textContent = `Total in DB: ${Number(liveTotal).toLocaleString()}`;
      animateProgress(pollStart);
    } catch (err) {
      text.textContent = `Live feed disconnected · retrying… ${new Date().toLocaleTimeString()}`;
      setState('disconnected');
      progress.style.width = '0%';
    }

    setTimeout(poll, POLL_MS);
  }

  poll();
})();
</script>
@endsection
