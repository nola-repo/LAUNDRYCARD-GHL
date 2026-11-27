@extends('layouts.app')
@section('title','Customer Records')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-actions.css') }}?v={{ filemtime(public_path('css/admin-actions.css')) }}">
<link rel="stylesheet" href="{{ asset('css/records.css') }}?v={{ @filemtime(public_path('css/records.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/admin-actions.js') }}?v={{ filemtime(public_path('js/admin-actions.js')) }}" defer></script>
<script src="{{ asset('js/sticky-banner.js') }}?v={{ filemtime(public_path('js/sticky-banner.js')) }}" defer></script>
<script src="{{ asset('js/records.js') }}?v={{ filemtime(public_path('js/records.js')) }}" defer></script>
@endpush
@section('content')

<div class="records-page" data-sticky-page>
  <header class="records-hero" id="records-top">
    <div><p class="records-eyebrow">LaundryCard · Customer directory</p><h1>Customer records</h1><p>Follow each card from branch activity to contact sync.</p></div>
    <div class="records-hero-note"><span>Card history</span><strong>Select a card number to review its reloads and credit activity.</strong></div>
  </header>

  <div id="records-live-bar" class="records-live-bar records-live-bar--connecting"
       data-sticky-banner data-total="{{ $counts['all'] }}" data-url="{{ route('admin.monitoring.updates') }}">
    <a class="records-toolbar-title" href="#records-top">Customer records</a>
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
    <button type="button" class="admin-action records-export" data-confirm-open="records-export-dialog" aria-haspopup="dialog" hidden>Export CSV</button>
    <noscript><a href="{{ route('admin.records.export', request()->except(['page', 'per_page', 'columns', 'customize'])) }}" class="admin-action records-export">Export CSV</a></noscript>
    <div class="records-live-bar__progress" id="records-live-progress" aria-hidden="true"></div>
  </div>

  @php
    $categories = [
      '' => ['all', 'All records', 'Discovered cards'],
      'new' => ['new', 'New active', 'Activity within 90 days'],
      'named' => ['named', 'Has a name', 'Name on file'],
      'synced' => ['synced', 'Synced', 'Created or updated in GHL'],
      'held' => ['held', 'Needs attention', 'Held or failed syncs'],
    ];
    $selectedCategory = array_key_exists($activeTab ?? '', $categories) ? ($activeTab ?? '') : '';
  @endphp
  <div class="records-directory-label"><h2>Browse the directory</h2><span>Directory totals before filters</span></div>
  <nav class="records-categories" aria-label="Customer record categories">
    @foreach($categories as $category => [$countKey, $label, $caption])
      @php
        $remove = match ($category) { 'new' => ['page','tab','classification'], 'named' => ['page','tab','has_name'], 'synced', 'held' => ['page','tab','status'], default => ['page','tab'] };
        $categoryQuery = request()->except($remove);
        if ($category !== '') $categoryQuery['tab'] = $category;
      @endphp
      <a href="{{ route('admin.records', $categoryQuery) }}" class="records-category records-category--{{ $countKey }}" @if($selectedCategory === $category) aria-current="page" @endif>
        <span class="records-category-label">{{ $label }}</span><strong>{{ number_format($counts[$countKey]) }}</strong><small>{{ $caption }}</small>
      </a>
    @endforeach
  </nav>

  <section class="records-workbench" aria-label="Customer record search and results">
  <form class="records-filters" novalidate method="get" action="{{ route('admin.records') }}">
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
          <button type="button" class="records-search-clear" aria-label="Clear card search" hidden>×</button>
        </div>
      </div>
      <button type="submit" class="admin-action admin-action-primary records-search-submit"><span>Search records</span></button>
    </div>
    <details class="records-advanced" @if(collect(request()->only(['store','classification','status','has_name','outcome','from','to']))->contains(fn($value) => filled($value))) open @endif>
        <summary>
          <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
          Filters
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
        <p class="records-filter-error" id="records-filter-error" role="alert" hidden></p>
        <div class="records-filter-actions">
          <p>Filters combine with the selected category and card search.</p>
          <div class="filter-buttons">
            <button type="submit" class="admin-action admin-action-primary"><span>Apply filters</span></button>
            <a class="admin-action" href="{{ route('admin.records') }}">Reset all</a>
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
    $activeFilters['date'] = 'Date: ' . (request('from') ?: 'Any') . ' to ' . (request('to') ?: 'Now');
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
      <h2 id="records-results-title" tabindex="-1">Card directory</h2>
      <p>Latest discoveries first · Activity times in UTC</p>
    </div>
    <div class="records-result-tools"><button type="button" class="admin-action records-density" aria-pressed="false" hidden>Compact rows</button>
    <p class="records-results-count" aria-live="polite">
      Showing <strong>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($records->total()) }}</strong> matches
    </p></div>
  </div>
  <div class="table-scroll" tabindex="0" aria-label="Customer records results">
    <table class="records-table">
      <caption class="records-sr-only">Customer records with LaundryCard branch, account balance and GoHighLevel sync status.</caption>
      <thead>
        <tr>
          <th scope="col">Cardholder</th><th scope="col">Branch</th><th scope="col">Balance &amp; eligibility</th><th scope="col">Customer type</th><th scope="col">Contact sync</th><th scope="col">Activity <span>UTC</span></th>
        </tr>
      </thead>
      <tbody>
        @forelse($records as $r)
          @include('admin.records.row', ['record' => $r])
        @empty
        <tr class="records-empty-row">
          <td colspan="6">
            <div class="empty-state-box">
              <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              </div>
              <div class="empty-state-text">{{ $counts['all'] ? 'No records match these filters' : 'Your card directory starts here' }}</div>
              <p>{{ $counts['all'] ? 'Try another card number or remove a filter to broaden the results.' : 'Customer cards will appear after the next branch discovery scan.' }}</p>
              @if($counts['all'])<a class="admin-action" href="{{ route('admin.records') }}">Clear all filters</a>@endif
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
        <select id="records-per-page" name="per_page" aria-label="Records per page" >
          <option value="15" @selected($perPage == 15)>15</option>
          <option value="25" @selected($perPage == 25)>25</option>
          <option value="50" @selected($perPage == 50)>50</option>
          <option value="100" @selected($perPage == 100)>100</option>
        </select>
        <noscript><button type="submit" class="admin-action">Apply</button></noscript>
      </form>
      <span>per page</span>
    </div>
    <div class="pager" style="margin-top:0">
      {{ $records->links() }}
    </div>
  </div>
</div>
</section>
@include('admin.records.export', ['exportFilters' => $activeFilters])
</div>

@endsection
