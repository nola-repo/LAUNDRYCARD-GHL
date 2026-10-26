@extends('layouts.app')
@section('title','Customer Records')
@section('content')

<div class="records-header">
  <div>
    <h1>
      Incoming Customer Records
      <span class="live-pill"><span class="dot ok"></span> Live Database</span>
    </h1>
    <p class="sub" style="margin-bottom:0">Persisted LaundryCard accounts, customer identities, and synchronization status to GoHighLevel.</p>
  </div>
</div>

{{-- Top Metrics Overview --}}
<div class="metric-row">
  <div class="metric-box">
    <div class="metric-lbl">📊 Total Records</div>
    <div class="metric-num">{{ number_format($counts['all']) }}</div>
    <div class="muted" style="font-size:11px">All discovered accounts</div>
  </div>
  <div class="metric-box" style="border-color: rgba(52,211,153,.3); background: rgba(52,211,153,.04);">
    <div class="metric-lbl" style="color: var(--ok)">✨ New Active (90 Days)</div>
    <div class="metric-num" style="color: var(--ok)">{{ number_format($counts['new']) }}</div>
    <div class="muted" style="font-size:11px">Recent card activity</div>
  </div>
  <div class="metric-box">
    <div class="metric-lbl">👤 Named Accounts</div>
    <div class="metric-num" style="color: var(--accent2)">{{ number_format($counts['named']) }}</div>
    <div class="muted" style="font-size:11px">Customer name linked</div>
  </div>
  <div class="metric-box">
    <div class="metric-lbl">🔗 Synced to GHL</div>
    <div class="metric-num" style="color: var(--info)">{{ number_format($counts['synced']) }}</div>
    <div class="muted" style="font-size:11px">Contacts in GoHighLevel</div>
  </div>
  <div class="metric-box">
    <div class="metric-lbl">⚠️ On Hold / Attention</div>
    <div class="metric-num" style="color: var(--warn)">{{ number_format($counts['held']) }}</div>
    <div class="muted" style="font-size:11px">Held ($0 balance or unmapped)</div>
  </div>
</div>

{{-- Quick Filter Segment Tabs --}}
<div class="filter-tabs">
  <a href="{{ route('admin.records') }}" class="filter-tab {{ empty($activeTab) && !request()->hasAny(['classification','status','has_name']) ? 'active' : '' }}">
    <span>🌟 All Records</span>
    <span class="tab-badge">{{ number_format($counts['all']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','classification']), ['tab' => 'new'])) }}" class="filter-tab {{ $activeTab === 'new' || request('classification') === \App\Models\LaundrycardSourceRecord::NEW_OBSERVED ? 'active' : '' }}">
    <span>🟢 New Active Customers</span>
    <span class="tab-badge">{{ number_format($counts['new']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','has_name']), ['tab' => 'named'])) }}" class="filter-tab {{ $activeTab === 'named' || request('has_name') === 'yes' ? 'active' : '' }}">
    <span>👤 With Customer Name</span>
    <span class="tab-badge">{{ number_format($counts['named']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','status']), ['tab' => 'synced'])) }}" class="filter-tab {{ $activeTab === 'synced' ? 'active' : '' }}">
    <span>🔗 Synced to GHL</span>
    <span class="tab-badge">{{ number_format($counts['synced']) }}</span>
  </a>
  <a href="{{ route('admin.records', array_merge(request()->except(['page','tab','status']), ['tab' => 'held'])) }}" class="filter-tab {{ $activeTab === 'held' ? 'active' : '' }}">
    <span>⚠️ On Hold / Attention</span>
    <span class="tab-badge">{{ number_format($counts['held']) }}</span>
  </a>
</div>

{{-- Clear & Intuitive Filter Panel --}}
<div class="filter-panel">
  <div class="filter-title">
    <h3>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filter & Search Records
    </h3>
    <span class="muted" style="font-size:12px">Filters update results instantly upon applying</span>
  </div>

  <form method="get" action="{{ route('admin.records') }}">
    @if(request('tab'))
      <input type="hidden" name="tab" value="{{ request('tab') }}">
    @endif

    <div class="filter-grid">
      {{-- Card Search --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>🔍 Card Number</span>
          <span class="filter-hint">Search #</span>
        </label>
        <input type="text" name="search" placeholder="e.g. 100234..." value="{{ request('search', request('q')) }}">
      </div>

      {{-- Store Selection --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>🏪 Store Branch</span>
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
          <span>🏷️ Customer Type</span>
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
          <span>🔄 Sync Status</span>
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
          <span>👤 Contact Profile</span>
          <span class="filter-hint">Identity</span>
        </label>
        <select name="has_name">
          <option value="">All (Named & Anonymous)</option>
          <option value="yes" @selected(request('has_name') === 'yes')>👤 Has Customer Name</option>
          <option value="no" @selected(request('has_name') === 'no')>⚪ Anonymous Cards Only</option>
        </select>
      </div>

      {{-- Hold Reason --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>⚠️ Hold Reason</span>
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
          <span>📅 Observed Date</span>
          <span class="filter-hint">From & To</span>
        </label>
        <div class="filter-dates">
          <input type="date" name="from" value="{{ request('from') }}" title="From Date">
          <input type="date" name="to" value="{{ request('to') }}" title="To Date">
        </div>
      </div>

      {{-- Page Size --}}
      <div class="filter-group">
        <label class="filter-label">
          <span>📄 Items Per Page</span>
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
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Apply Filters
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
    <a href="{{ route('admin.records') }}" class="btn sm" style="margin-left:6px; font-size:11px">Clear All</a>
  </div>
@endif

{{-- Customer Records Table --}}
<div class="card" style="overflow:auto; padding:0; border-radius:12px">
  <table class="records-table">
    <thead>
      <tr>
        <th style="width:130px">Store</th>
        <th style="width:130px">Card #</th>
        <th>Customer Details</th>
        <th style="width:100px">Balance</th>
        <th style="width:140px">Customer Type</th>
        <th style="width:140px">Eligibility</th>
        <th style="width:170px">Sync Status</th>
        <th style="width:150px">Last Activity</th>
        <th style="width:130px">GHL Contact</th>
      </tr>
    </thead>
    <tbody>
      @forelse($records as $r)
      <tr>
        {{-- Store --}}
        <td>
          <div style="font-weight:600; font-size:12px">{{ $r->store->name ?? 'Store ' . $r->store->store_id }}</div>
          <div class="muted mono" style="font-size:11px">#{{ $r->store->store_id }}</div>
        </td>

        {{-- Card # --}}
        <td>
          <span class="card-mono">{{ $r->card_number }}</span>
        </td>

        {{-- Customer Name & Details --}}
        <td>
          <div class="user-cell">
            @if($r->customer_name)
              <div class="user-name">
                <span class="user-avatar">{{ strtoupper(substr(trim($r->customer_name), 0, 1)) }}</span>
                <span>{{ ucwords(strtolower($r->customer_name)) }}</span>
              </div>
              <div class="user-meta">
                @if($r->customer_email)
                  <span>✉️ {{ $r->customer_email }}</span>
                @endif
                @if($r->customer_phone)
                  <span>📞 {{ $r->customer_phone }}</span>
                @endif
              </div>
            @else
              <span class="anon-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Anonymous Cardholder
              </span>
            @endif
          </div>
        </td>

        {{-- Balance --}}
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
        </td>

        {{-- Classification --}}
        <td>
          @if($r->classification === 'new_observed')
            <span class="badge b-ok" title="Customer had activity within last 90 days">✨ New Active (90d)</span>
          @elseif($r->classification === 'confirmed_new')
            <span class="badge b-info">⭐ Confirmed New</span>
          @else
            <span class="badge b-mute" title="Historical baseline cardholder">📁 Historical</span>
          @endif
        </td>

        {{-- Eligibility --}}
        <td>
          <x-badge :value="$r->eligibility_status"/>
          @if($r->eligibility_reason && $r->eligibility_reason !== $r->eligibility_status)
            <div class="muted" style="font-size:11px; margin-top:2px">{{ ucwords(str_replace('_',' ',$r->eligibility_reason)) }}</div>
          @endif
        </td>

        {{-- Routing / Sync --}}
        <td>
          <x-badge :value="$r->processing_status"/>
          @if($r->hold_reason)
            <div class="muted" style="font-size:11px; margin-top:3px; color: var(--warn)">
              ⚠️ {{ $holdReasonLabels[$r->hold_reason] ?? ucwords(str_replace('_',' ',$r->hold_reason)) }}
            </div>
          @endif
          @if($r->destination_location_id)
            <div class="muted mono" style="font-size:10px; margin-top:2px">Loc: {{ \Illuminate\Support\Str::limit($r->destination_location_id, 12) }}</div>
          @endif
        </td>

        {{-- Activity Timestamp --}}
        <td>
          @if($r->source_timestamp)
            <div style="font-size:12px">{{ $r->source_timestamp->format('M d, Y') }}</div>
            <div class="muted" style="font-size:11px">{{ $r->source_timestamp->format('h:i A') }} ({{ $r->source_timestamp->diffForHumans() }})</div>
          @else
            <div style="font-size:12px">{{ $r->first_observed_at->format('M d, Y') }}</div>
            <div class="muted" style="font-size:11px">Observed {{ $r->first_observed_at->diffForHumans() }}</div>
          @endif
        </td>

        {{-- GHL Contact ID --}}
        <td>
          @if($r->contactMapping?->ghl_contact_id)
            <span class="mono" style="font-size:11px; color: var(--accent2)">{{ \Illuminate\Support\Str::limit($r->contactMapping->ghl_contact_id, 14) }}</span>
          @else
            <span class="muted" style="font-size:12px">—</span>
          @endif
        </td>
      </tr>
      @empty
      <tr>
        <td colspan="9" style="text-align:center; padding:40px 20px">
          <div style="font-size:24px; margin-bottom:8px">🔍</div>
          <div style="font-weight:600; font-size:15px; margin-bottom:4px">No customer records match your filter</div>
          <div class="muted" style="font-size:13px; margin-bottom:14px">Try adjusting or clearing your filters to see more results.</div>
          <a class="btn primary" href="{{ route('admin.records') }}">Clear All Filters</a>
        </td>
      </tr>
      @endforelse
    </tbody>
  </table>
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

@endsection
