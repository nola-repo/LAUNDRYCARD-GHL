@extends('layouts.app')
@section('title', 'Overview')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/overview.css') }}?v={{ filemtime(public_path('css/overview.css')) }}">
@endpush

@section('content')
<div class="overview" data-feed="{{ route('admin.overview') }}">
  <header class="ov-heading" id="sec-hero">
    <div>
      <p class="ov-route"><span>LaundryCard</span><span aria-hidden="true">→</span> GoHighLevel</p>
      <h1>Integration overview</h1>
    </div>
    <div class="ov-feed-tools">
      <div class="ov-feed-info">
        <strong id="dashboard-live-state" role="status" data-state="initial">Page snapshot</strong>
        <small id="ov-checked">Loaded {{ now()->utc()->format('H:i:s') }} UTC</small>
      </div>
      <button class="ov-button" id="ov-pause" type="button" hidden>Pause updates</button>
      <a class="ov-link" id="ov-sign-in" href="{{ route('login') }}" hidden>Sign in</a>
      <button class="ov-button ov-primary" id="manual-refresh-btn" type="button" hidden>
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7v5h-5"/><path d="M20 12a8 8 0 1 0-2 5M20 12a8 8 0 0 0-2-5"/></svg>
        Refresh
      </button>
    </div>
  </header>

  <nav class="ov-nav" aria-label="Overview sections">
    @foreach(['kpis' => 'Summary', 'health' => 'System health', 'pipeline' => 'Record flow', 'customers' => 'Recent cards', 'activity' => 'Activity', 'branches' => 'Store routing'] as $anchor => $label)
      <a href="#sec-{{ $anchor }}" @if($loop->first) aria-current="location" @endif>{{ $label }}</a>
    @endforeach
  </nav>

  <section id="sec-kpis" aria-label="Summary">
    <div class="ov-totals">
      <div class="ov-metric ov-featured">
        <h2>Discovered records</h2>
        <strong class="ov-number" data-stat="records">{{ number_format($stats['records']) }}</strong>
        <p><span data-stat="baseline">{{ number_format($stats['baseline']) }}</span> historical baseline</p>
        <i class="ov-card-art" aria-hidden="true"></i>
      </div>
      <div class="ov-metric">
        <h2>New customers</h2>
        <strong class="ov-number" data-stat="new_customers">{{ number_format($stats['new_customers']) }}</strong>
        <p>Detected after store baseline</p>
      </div>
      <div class="ov-metric">
        <h2>Eligible balances</h2>
        <strong class="ov-number" data-stat="positive_balance">{{ number_format($stats['positive_balance']) }}</strong>
        <p><span data-stat="eligible_pending">{{ number_format($stats['eligible_pending']) }}</span> awaiting sync</p>
      </div>
      <div class="ov-metric">
        <h2>Failed records</h2>
        <strong class="ov-number" data-stat="failed" data-attention="{{ $stats['failed'] ? 'true' : 'false' }}">{{ number_format($stats['failed']) }}</strong>
        <p><span data-stat="failed_attempts">{{ number_format($stats['failed_attempts']) }}</span> failed sync attempts</p>
      </div>
    </div>
    <div class="ov-notice" id="ov-write-notice" @if($syncEnabled) hidden @endif>
      <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
      <div><strong>Contact writes are paused.</strong> Card discovery and balance checks continue.</div>
    </div>
  </section>

  <div class="ov-pair">
    <section id="sec-health" class="ov-panel" aria-labelledby="ov-health-title">
      <div class="ov-panel-heading"><h2 id="ov-health-title">System health</h2></div>
      <div id="ov-health" class="ov-health-list">@include('admin.overview.health')</div>
    </section>
    <section id="sec-pipeline" class="ov-panel" aria-labelledby="ov-flow-title">
      <div class="ov-panel-heading"><h2 id="ov-flow-title">Record flow</h2></div>
      <div class="ov-flow">
        <h3>Balance eligibility</h3>
        <div id="ov-balances">@include('admin.overview.balances')</div>
        <h3 class="ov-outcome-title">Sync outcomes</h3>
        <dl class="ov-outcomes">
          @foreach(['created' => 'Created', 'updated' => 'Updated', 'held' => 'Held', 'skipped' => 'Skipped'] as $key => $label)
            <div><dt>{{ $label }}</dt><dd data-stat="{{ $key }}">{{ number_format($stats[$key]) }}</dd></div>
          @endforeach
        </dl>
      </div>
    </section>
  </div>

  <section id="sec-customers" class="ov-panel" aria-labelledby="ov-cards-title">
    <div class="ov-panel-heading"><div><h2 id="ov-cards-title">Recently detected cards</h2>
      <p>The latest 10 cards first seen after a store’s baseline. Contact details are masked.</p></div>
      <a class="ov-link" href="{{ route('admin.records') }}">All customer records <span aria-hidden="true">→</span></a>
    </div>
    <div class="ov-filter" id="ov-filter" hidden>
      <label for="customer-search-input">Filter recent cards</label>
      <div class="ov-search">
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="10" r="6"/><path d="m15 15 5 5"/></svg>
        <input type="search" id="customer-search-input" placeholder="Store, card or customer" autocomplete="off">
        <button type="button" class="ov-clear" id="ov-clear" aria-label="Clear card filter" hidden>×</button>
      </div>
      <span id="ov-result-count" role="status"></span>
    </div>
    <div class="ov-scroll" role="region" aria-label="Recently detected cards" tabindex="0">
      <table id="dashboard-new-customers" aria-labelledby="ov-cards-title">
        <thead><tr><th scope="col">Card</th><th scope="col">Store</th><th scope="col">Customer</th><th scope="col" class="ov-numeric">Balance</th><th scope="col">Eligibility</th><th scope="col">First seen (UTC)</th></tr></thead>
        <tbody id="ov-customers">@include('admin.overview.customers')</tbody>
        <tbody id="ov-no-match" hidden><tr><td colspan="6" class="ov-empty">No recent cards match your filter.</td></tr></tbody>
      </table>
    </div>
  </section>

  <section id="sec-activity" class="ov-panel" aria-labelledby="ov-activity-title">
    <div class="ov-panel-heading"><div><h2 id="ov-activity-title">Integration activity</h2><p>Latest 25 events, newest first.</p></div>
      <a class="ov-link" href="{{ route('admin.logs') }}">Full log history <span aria-hidden="true">→</span></a>
    </div>
    <div class="ov-scroll" role="region" aria-label="Integration events" tabindex="0">
      <table id="dashboard-events" aria-labelledby="ov-activity-title">
        <thead><tr><th scope="col">Time (UTC)</th><th scope="col">Stage</th><th scope="col">Outcome</th><th scope="col">Store / location</th><th scope="col">Message</th></tr></thead>
        <tbody id="ov-events">@include('admin.overview.events')</tbody>
      </table>
    </div>
  </section>

  <div class="ov-pair ov-routing">
    <section id="sec-branches" class="ov-panel" aria-labelledby="ov-stores-title">
      <div class="ov-panel-heading"><div><h2 id="ov-stores-title">Store routing</h2><p><span data-stat="branches">{{ $stats['branches'] }}</span> active stores · <span data-stat="mappings_verified">{{ $stats['mappings_verified'] }}</span> verified mappings · <span data-stat="unmapped">{{ $stats['unmapped'] }}</span> active stores unmapped</p></div>
        <a class="ov-link" href="{{ route('admin.mappings') }}">All mappings <span aria-hidden="true">→</span></a>
      </div>
      <div class="ov-scroll" role="region" aria-label="Store routing" tabindex="0">
        <table aria-labelledby="ov-stores-title"><thead><tr><th scope="col">Store</th><th scope="col">GHL destination</th><th scope="col" class="ov-numeric">Records</th></tr></thead>
          <tbody id="ov-stores">@include('admin.overview.stores')</tbody>
        </table>
      </div>
    </section>
    <section class="ov-panel" aria-labelledby="ov-runs-title">
      <div class="ov-panel-heading"><div><h2 id="ov-runs-title">Recent runs</h2><p>Latest 8 integration jobs.</p></div></div>
      <div class="ov-scroll" role="region" aria-label="Recent integration runs" tabindex="0">
        <table aria-labelledby="ov-runs-title"><thead><tr><th scope="col">Started (UTC)</th><th scope="col">Job / mode</th><th scope="col">Status</th></tr></thead>
          <tbody id="ov-runs">@include('admin.overview.runs')</tbody>
        </table>
      </div>
    </section>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/overview.js') }}?v={{ filemtime(public_path('js/overview.js')) }}" defer></script>
@endpush
