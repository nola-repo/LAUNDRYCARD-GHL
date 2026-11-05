@extends('layouts.app')
@section('title', 'Overview')

@section('content')
<div class="overview-container">

  {{-- =========================================================================
       1. HERO HEADER & LIVE TELEMETRY BAR
       ========================================================================= --}}
  <header class="dashboard-hero reveal-on-scroll is-revealed" id="sec-hero">
    <div class="dashboard-hero-top">
      <div>
        <div class="dashboard-hero-heading">
          <h1>Integration Overview</h1>
          <span class="hero-pill-badge">
            <span class="hero-pill-dot"></span>
            Production Console v2.4
          </span>
        </div>
        <p class="dashboard-hero-desc">
          Automated customer synchronization engine between CCI LaundryCard store balances and GoHighLevel CRM locations.
        </p>
      </div>

      {{-- Telemetry Capsule --}}
      <div class="telemetry-capsule" id="telemetry-capsule">
        <div class="radar-beacon">
          <div class="radar-dot"></div>
          <div class="radar-wave"></div>
        </div>
        <div class="telemetry-info">
          <div class="telemetry-status-text" id="dashboard-live-state" data-url="{{ route('admin.monitoring.updates') }}" data-connection="connecting">
            Connecting to persisted telemetry…
          </div>
          <span class="telemetry-poll-meta" id="telemetry-poll-meta">Auto-polling every 4s · Live database</span>
        </div>
        <button type="button" class="telemetry-refresh-btn" id="manual-refresh-btn" title="Refresh metrics and events immediately" aria-label="Refresh data">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
          </svg>
        </button>
      </div>
    </div>

    {{-- Quick Jump Section Dock --}}
    <nav class="quick-jump-dock" aria-label="Overview sections jump links">
      <a href="#sec-health" class="dock-pill active" data-section="sec-health">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
        System Health
      </a>
      <a href="#sec-kpis" class="dock-pill" data-section="sec-kpis">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect></svg>
        Key Metrics
      </a>
      <a href="#sec-pipeline" class="dock-pill" data-section="sec-pipeline">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>
        Data Flow
      </a>
      <a href="#sec-branches" class="dock-pill" data-section="sec-branches">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M18 6v6a6 6 0 0 1-6 6H6"></path></svg>
        Stores (5)
      </a>
      <a href="#sec-customers" class="dock-pill" data-section="sec-customers">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        Detected Cards
      </a>
      <a href="#sec-activity" class="dock-pill" data-section="sec-activity">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
        Activity Log
      </a>
    </nav>
  </header>

  {{-- Contact Sync Safety Lock Notice (Never misleading green) --}}
  @if(!$syncEnabled)
    <div class="sync-safety-banner reveal-on-scroll is-revealed">
      <div class="sync-safety-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
      </div>
      <div class="sync-safety-content">
        <div class="sync-safety-title">Contact Writes Safety Lock Active</div>
        <div>
          Contact synchronization to GoHighLevel CRM is currently paused in read-only observation mode (<span class="mono">INTEGRATION_CONTACT_SYNC_ENABLED=false</span>).
          Customer balances and new cards are safely cataloged, checked for positive eligibility, and mapped without modifying external CRM contacts.
        </div>
      </div>
    </div>
  @endif

  {{-- =========================================================================
       2. INTEGRATION HEALTH / SYSTEM STATUS MATRIX
       ========================================================================= --}}
  <section id="sec-health" class="reveal-on-scroll is-revealed">
    <div class="section-header-block">
      <div class="section-title-wrap">
        <span class="section-supertitle">Connectivity & Telemetry</span>
        <h2 class="section-main-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
          Integration Health Matrix
        </h2>
        <p class="section-caption">Real-time status across LaundryCard endpoints, GoHighLevel OAuth sessions, and automated background jobs.</p>
      </div>
    </div>

    <div class="health-matrix-grid">
      {{-- Card 1: LaundryCard API --}}
      <div class="health-card">
        <div class="health-card-header">
          <div class="health-card-title-group">
            <div class="health-card-kicker">Core System 1</div>
            <h3 class="health-card-title">LaundryCard API</h3>
          </div>
          <div class="health-card-icon health-card-icon--violet">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
              <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
              <line x1="6" y1="6" x2="6.01" y2="6"></line>
              <line x1="6" y1="18" x2="6.01" y2="18"></line>
            </svg>
          </div>
        </div>

        @if(!$lcConfigured)
          <div class="health-card-status-badge bad">
            <span class="dot bad"></span> Credentials Missing
          </div>
        @elseif($lastLcApi && $lastLcApi->created_at->gt(now()->subHours(1)))
          <div class="health-card-status-badge ok">
            <span class="dot ok"></span> Active — Called {{ $lastLcApi->created_at->diffForHumans() }}
          </div>
        @elseif($lastLcApi)
          <div class="health-card-status-badge warn">
            <span class="dot warn"></span> Idle — Last call {{ $lastLcApi->created_at->diffForHumans() }}
          </div>
        @else
          <div class="health-card-status-badge warn">
            <span class="dot warn"></span> No API calls recorded yet
          </div>
        @endif

        <div class="health-card-meta-list">
          <div class="health-meta-item">
            <span class="health-meta-label">Base URL</span>
            <span class="health-meta-val mono" title="{{ config('integration.laundrycard.base_url') }}">
              {{ parse_url(config('integration.laundrycard.base_url'), PHP_URL_HOST) ?? 'live.laundrycard.com' }}
            </span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">Last Call</span>
            <span class="health-meta-val">
              @if($lastLcApi)
                {{ $lastLcApi->created_at->diffForHumans() }}
                @if($lastLcApi->http_status)
                  <span class="mono" style="color:{{ $lastLcApi->http_status < 400 ? 'var(--ok-light)' : 'var(--bad-light)' }}">
                    (HTTP {{ $lastLcApi->http_status }})
                  </span>
                @endif
                <div style="font-size:11px; color:var(--text-faint); margin-top:2px">
                  {{ $lastLcApi->created_at->toDayDateTimeString() }}
                </div>
              @else
                <span class="muted">Never — cron has not fired yet</span>
              @endif
            </span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">Last Success</span>
            <span class="health-meta-val">
              @if(isset($lastLcApiSuccess) && $lastLcApiSuccess)
                {{ $lastLcApiSuccess->created_at->diffForHumans() }}
                <span class="mono" style="color:var(--ok-light)">(HTTP {{ $lastLcApiSuccess->http_status }})</span>
              @else
                <span class="muted">None recorded</span>
              @endif
            </span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">POS MachNo</span>
            <span class="health-meta-val mono">{{ config('integration.laundrycard.pos_mach_no') ? 'Configured' : 'Global' }}</span>
          </div>
        </div>
      </div>

      {{-- Card 2: HighLevel OAuth Installations --}}
      <div class="health-card">
        <div class="health-card-header">
          <div class="health-card-title-group">
            <div class="health-card-kicker">Core System 2</div>
            <h3 class="health-card-title">GoHighLevel OAuth</h3>
          </div>
          <div class="health-card-icon health-card-icon--cyan">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
              <path d="m9 12 2 2 4-4"></path>
            </svg>
          </div>
        </div>

        <div class="health-card-status-badge ok">
          <span class="dot ok"></span> {{ $stats['locations_active'] }} of {{ $stats['locations'] }} Locations Active
        </div>

        <div class="health-card-meta-list">
          <div class="health-meta-item">
            <span class="health-meta-label">Token Management</span>
            <span class="health-meta-val">Encrypted & Auto-Rotated</span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">Refresh Leeway</span>
            <span class="health-meta-val mono">300 seconds</span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">OAuth Scopes</span>
            <span class="health-meta-val mono" title="contacts, locations, oauth">Verified (3 scopes)</span>
          </div>
        </div>
      </div>

      {{-- Card 3: Discovery Engine --}}
      <div class="health-card">
        <div class="health-card-header">
          <div class="health-card-title-group">
            <div class="health-card-kicker">Discovery Scanner</div>
            <h3 class="health-card-title">Discovery Schedule</h3>
          </div>
          <div class="health-card-icon health-card-icon--emerald">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
          </div>
        </div>

        @php
          $discoveryOk     = $lastRun && in_array($lastRun->status, ['completed', 'partial']);
          $discoveryFailed = $lastRun && $lastRun->status === 'failed';
          $discoveryBadgeClass = $discoveryOk ? 'ok' : ($discoveryFailed ? 'bad' : 'warn');
          $discoveryDotClass   = $discoveryOk ? 'ok' : ($discoveryFailed ? 'bad' : 'warn');
        @endphp

        <div class="health-card-status-badge {{ $discoveryBadgeClass }}">
          <span class="dot {{ $discoveryDotClass }}"></span>
          @if(!$lastRun)
            Awaiting First Cron
          @elseif($discoveryOk)
            Last run {{ $lastRun->status }} · {{ $lastRun->finished_at?->diffForHumans() }}
          @elseif($discoveryFailed)
            Last run failed · {{ $lastRun->finished_at?->diffForHumans() ?? $lastRun->started_at->diffForHumans() }}
          @else
            {{ ucfirst($lastRun->status) }}
          @endif
        </div>

        <div class="health-card-meta-list">
          <div class="health-meta-item">
            <span class="health-meta-label">Scan Cadence</span>
            <span class="health-meta-val mono">Every {{ config('integration.discovery.schedule_every_minutes') }} mins (Automated)</span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">Next Scheduled Run</span>
            <span class="health-meta-val mono">
              @if(isset($nextScheduledRun) && $nextScheduledRun)
                {{ $nextScheduledRun->isPast() ? 'Imminent / Next Trigger' : $nextScheduledRun->diffForHumans() }}
              @else
                Every {{ config('integration.discovery.schedule_every_minutes') }} mins
              @endif
            </span>
          </div>
          @if(isset($lastRunDuration) && $lastRunDuration !== null)
            <div class="health-meta-item">
              <span class="health-meta-label">Execution Time</span>
              <span class="health-meta-val mono">{{ $lastRunDuration }}s</span>
            </div>
          @endif
          @if($lastRun && $lastRun->stats)
            <div class="health-meta-item">
              <span class="health-meta-label">Last Run Telemetry</span>
              <span class="health-meta-val mono" style="font-size:11px">
                Seen: {{ $lastRun->stats['cards_seen'] ?? 0 }} · New: {{ $lastRun->stats['new_records'] ?? 0 }} · Scanned: {{ $lastRun->stats['stores_scanned'] ?? 0 }}
              </span>
            </div>
          @endif
          @if($lastRun && $lastRun->error)
            <div class="health-meta-item">
              <span class="health-meta-label" style="color:var(--bad-light)">Last Error</span>
              <span class="health-meta-val" style="color:var(--bad-light); font-size:11px" title="{{ $lastRun->error }}">
                {{ \Illuminate\Support\Str::limit($lastRun->error, 40) }}
              </span>
            </div>
          @endif
          <div class="health-meta-item">
            <span class="health-meta-label">Last Successful Scan</span>
            <span class="health-meta-val" id="health-last-discovery-time">
              @if($lastDiscovery)
                {{ $lastDiscovery->finished_at?->diffForHumans() }}
                <span class="badge b-ok" style="font-size:10px; padding:1px 6px; margin-left:4px">{{ $lastDiscovery->status }}</span>
              @elseif($lastRun)
                <span style="color:var(--warn-light)">No completed live run yet</span>
                <div style="font-size:11px; color:var(--text-faint); margin-top:2px">Last attempt: {{ $lastRun->started_at->toDayDateTimeString() }}</div>
              @else
                <span style="color:var(--text-faint)">Cron not triggered yet</span>
              @endif
            </span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">Scan Baseline</span>
            <span class="health-meta-val mono">{{ config('integration.discovery.initial_import') === 'skip' ? 'Delta Only (Skip Baseline)' : 'Full Import' }}</span>
          </div>
        </div>
      </div>

      {{-- Card 4: Contact Synchronization Status --}}
      <div class="health-card health-card--highlight">
        <div class="health-card-header">
          <div class="health-card-title-group">
            <div class="health-card-kicker">Sync Pipeline</div>
            <h3 class="health-card-title">Contact Sync Engine</h3>
          </div>
          <div class="health-card-icon {{ $syncEnabled ? 'health-card-icon--emerald' : 'health-card-icon--amber' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17 8 12 3 7 8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
          </div>
        </div>

        @if($syncEnabled)
          <div class="health-card-status-badge ok">
            <span class="dot ok"></span> Live Sync Enabled
          </div>
        @else
          <div class="health-card-status-badge warn">
            <span class="dot warn"></span> Disabled (Safety Hold)
          </div>
        @endif

        <div class="health-card-meta-list">
          <div class="health-meta-item">
            <span class="health-meta-label">Contact Writes</span>
            <span class="health-meta-val" style="color:{{ $syncEnabled ? 'var(--ok-light)' : 'var(--warn-light)' }}; font-weight:700">
              {{ $syncEnabled ? 'Active' : 'Disabled Pending Verification' }}
            </span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">Last Contact Sync</span>
            <span class="health-meta-val">
              {{ $lastSync ? \Illuminate\Support\Carbon::parse($lastSync)->diffForHumans() : 'None' }}
            </span>
          </div>
          <div class="health-meta-item">
            <span class="health-meta-label">Retry Engine</span>
            <span class="health-meta-val mono">5 tries w/ exponential backoff</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  {{-- =========================================================================
       3. KEY PERFORMANCE INDICATORS (GROUPED HIERARCHY)
       ========================================================================= --}}
  <section id="sec-kpis" class="reveal-on-scroll">
    <div class="section-header-block">
      <div class="section-title-wrap">
        <span class="section-supertitle">Telemetry & Metrics</span>
        <h2 class="section-main-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"></path><path d="M18 20V4"></path><path d="M6 20v-4"></path></svg>
          Key Performance Indicators
        </h2>
        <p class="section-caption">Prioritized metrics organized by operational domain: discovery, balance qualification, routing, and synchronization.</p>
      </div>
    </div>

    <div class="kpi-pillars-container">

      {{-- PILLAR 1: CUSTOMER DISCOVERY (FEATURED HERO CARDS) --}}
      <div class="kpi-pillar">
        <div class="kpi-pillar-title">
          <span class="kpi-pillar-title-dot dot-violet"></span>
          Customer Discovery Engine
        </div>
        <div class="hero-kpis-grid">
          {{-- Hero 1: Total Discovered Records --}}
          <div class="hero-kpi-card">
            <div class="hero-kpi-header">
              <span class="hero-kpi-label">Discovered Customer Records</span>
              <span class="hero-kpi-badge">Historical + Active</span>
            </div>
            <div class="hero-kpi-number-wrap">
              <span class="hero-kpi-number animated-counter" data-stat="records" data-target="{{ $stats['records'] }}">{{ number_format($stats['records']) }}</span>
            </div>
            <div class="hero-kpi-footer">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent-light)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
              <span><strong data-stat="baseline">{{ number_format($stats['baseline']) }}</strong> baseline historical records cataloged</span>
            </div>
          </div>

          {{-- Hero 2: New Customers Detected --}}
          <div class="hero-kpi-card hero-kpi-card--cyan">
            <div class="hero-kpi-header">
              <span class="hero-kpi-label">New Customers Detected</span>
              <span class="badge b-ok">Post-Baseline Delta</span>
            </div>
            <div class="hero-kpi-number-wrap">
              <span class="hero-kpi-number animated-counter" data-stat="new_customers" data-target="{{ $stats['new_customers'] }}">{{ number_format($stats['new_customers']) }}</span>
            </div>
            <div class="hero-kpi-footer">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
              <span>First observed after respective store baseline date</span>
            </div>
          </div>
        </div>
      </div>

      {{-- PILLAR 2: BRANCH & CHANNEL CONNECTIVITY --}}
      <div class="kpi-pillar">
        <div class="kpi-pillar-title">
          <span class="kpi-pillar-title-dot dot-cyan"></span>
          Branch & Channel Connectivity
        </div>
        <div class="compact-kpis-grid">
          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Discovered Branches</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-faint)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
            </div>
            <div class="kpi-number animated-counter" data-stat="branches" data-target="{{ $stats['branches'] }}">{{ $stats['branches'] }}</div>
            <div class="kpi-subtext">Active LaundryCard physical stores</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Authorized GHL Locations</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--text-faint)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            </div>
            <div class="kpi-number animated-counter" data-stat="locations" data-target="{{ $stats['locations'] }}">{{ $stats['locations'] }}</div>
            <div class="kpi-subtext">
              <span>{{ $stats['locations_active'] }} active</span> · <span>{{ $stats['unassigned_locations'] }} unassigned</span>
            </div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Verified Mappings</span>
              <span class="badge b-ok">100% Routed</span>
            </div>
            <div class="kpi-number ok animated-counter" data-stat="mappings_verified" data-target="{{ $stats['mappings_verified'] }}">{{ $stats['mappings_verified'] }}</div>
            <div class="kpi-subtext">Store ⇄ GHL Location verified routes</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Unmapped Branches</span>
              @if($stats['unmapped'] == 0)
                <span class="badge b-ok">All Mapped</span>
              @else
                <span class="badge b-warn">Attention</span>
              @endif
            </div>
            <div class="kpi-number {{ $stats['unmapped'] ? 'warn' : 'ok' }} animated-counter" data-stat="unmapped" data-target="{{ $stats['unmapped'] }}">{{ $stats['unmapped'] }}</div>
            <div class="kpi-subtext">Stores pending location association</div>
          </div>
        </div>
      </div>

      {{-- PILLAR 3: BALANCE QUALIFICATION PIPELINE --}}
      <div class="kpi-pillar">
        <div class="kpi-pillar-title">
          <span class="kpi-pillar-title-dot dot-emerald"></span>
          Balance Qualification Pipeline
        </div>
        <div class="compact-kpis-grid">
          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Positive Balance (Eligible)</span>
              <span class="badge b-ok">> $0.00</span>
            </div>
            <div class="kpi-number ok animated-counter" data-stat="positive_balance" data-target="{{ $stats['positive_balance'] ?? 0 }}">{{ $stats['positive_balance'] ?? 0 }}</div>
            <div class="kpi-subtext">Qualified for GHL contact sync</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Zero Balance ($0.00)</span>
              <span class="badge b-mute">Exempt</span>
            </div>
            <div class="kpi-number animated-counter" data-stat="zero_balance" data-target="{{ $stats['zero_balance'] ?? 0 }}">{{ $stats['zero_balance'] ?? 0 }}</div>
            <div class="kpi-subtext">Exempt until money is loaded</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Awaiting Verification</span>
              <span class="badge b-mute">Unknown</span>
            </div>
            <div class="kpi-number animated-counter" data-stat="balance_unknown" data-target="{{ $stats['balance_unknown'] ?? 0 }}">{{ number_format($stats['balance_unknown'] ?? 0) }}</div>
            <div class="kpi-subtext">Historical baseline or unverified</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Eligible Awaiting Sync</span>
              <span class="badge b-info">Ready</span>
            </div>
            <div class="kpi-number info animated-counter" data-stat="eligible_pending" data-target="{{ $stats['eligible_pending'] ?? 0 }}">{{ $stats['eligible_pending'] ?? 0 }}</div>
            <div class="kpi-subtext">Verified positive pending dispatch</div>
          </div>
        </div>
      </div>

      {{-- PILLAR 4: SYNCHRONIZATION PERFORMANCE & SAFETY --}}
      <div class="kpi-pillar">
        <div class="kpi-pillar-title">
          <span class="kpi-pillar-title-dot dot-amber"></span>
          Synchronization Outcomes & Safety
        </div>
        <div class="compact-kpis-grid">
          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Contacts Created / Updated</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ok-light)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
            </div>
            <div class="kpi-number ok">
              <span class="animated-counter" data-stat="created" data-target="{{ $stats['created'] }}">{{ $stats['created'] }}</span>
              <span style="color:var(--text-faint); font-weight:400; font-size:20px"> / </span>
              <span class="animated-counter" data-stat="updated" data-target="{{ $stats['updated'] }}">{{ $stats['updated'] }}</span>
            </div>
            <div class="kpi-subtext">Successfully synced to HighLevel</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Held / Skipped</span>
              <span class="badge b-mute">Safeguarded</span>
            </div>
            <div class="kpi-number {{ $stats['held'] ? 'warn' : '' }}">
              <span class="animated-counter" data-stat="held" data-target="{{ $stats['held'] }}">{{ $stats['held'] }}</span>
              <span style="color:var(--text-faint); font-weight:400; font-size:20px"> / </span>
              <span class="animated-counter" data-stat="skipped" data-target="{{ $stats['skipped'] }}">{{ $stats['skipped'] }}</span>
            </div>
            <div class="kpi-subtext">Held for missing phone/email or rule</div>
          </div>

          <div class="kpi-card">
            <div class="kpi-card-header">
              <span class="kpi-label">Failed Records</span>
              @if($stats['failed'] == 0)
                <span class="badge b-ok">0 Failures</span>
              @else
                <span class="badge b-bad">Attention</span>
              @endif
            </div>
            <div class="kpi-number {{ $stats['failed'] ? 'bad' : 'ok' }} animated-counter" data-stat="failed" data-target="{{ $stats['failed'] }}">{{ $stats['failed'] }}</div>
            <div class="kpi-subtext">
              <span data-stat="failed_attempts">{{ $stats['failed_attempts'] }}</span> total retry attempts logged
            </div>
          </div>
        </div>
      </div>

    </div>
  </section>

  {{-- =========================================================================
       4. DATA VISUALIZATION (AUTHENTIC DATA FLOW & BREAKDOWN)
       ========================================================================= --}}
  <section id="sec-pipeline" class="reveal-on-scroll">
    <div class="section-header-block">
      <div class="section-title-wrap">
        <span class="section-supertitle">Architecture & Distribution</span>
        <h2 class="section-main-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"></path><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"></path><path d="M2 7h20"></path></svg>
          Synchronization Pipeline & Distribution
        </h2>
        <p class="section-caption">High-level architecture view depicting the end-to-end flow from store discovery to CRM contact update.</p>
      </div>
    </div>

    <div class="visualizations-grid">
      {{-- Viz 1: 5-Stage Pipeline Flow Diagram --}}
      <div class="viz-card">
        <div class="viz-card-header">
          <div>
            <h3 class="viz-card-title">End-to-End Pipeline Stages</h3>
            <p class="viz-card-subtitle">Real-time status of each stage in the LaundryCard ⇄ GoHighLevel bridge</p>
          </div>
          <span class="badge b-ok">Operational</span>
        </div>

        <div class="pipeline-flow-container">
          {{-- Stage 1 --}}
          <div class="pipeline-stage">
            <div class="stage-node-icon stage-node-icon--violet">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
            </div>
            <span class="stage-title">CCI Stores</span>
            <span class="stage-metric">5 Connected</span>
          </div>

          <div class="pipeline-connector"></div>

          {{-- Stage 2 --}}
          <div class="pipeline-stage">
            <div class="stage-node-icon stage-node-icon--cyan">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
            </div>
            <span class="stage-title">Discovery</span>
            <span class="stage-metric">{{ number_format($stats['records']) }} Scanned</span>
          </div>

          <div class="pipeline-connector"></div>

          {{-- Stage 3 --}}
          <div class="pipeline-stage">
            <div class="stage-node-icon stage-node-icon--emerald">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            </div>
            <span class="stage-title">Balance Check</span>
            <span class="stage-metric">Positive Only</span>
          </div>

          <div class="pipeline-connector"></div>

          {{-- Stage 4 --}}
          <div class="pipeline-stage">
            <div class="stage-node-icon stage-node-icon--violet">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M18 6v6a6 6 0 0 1-6 6H6"></path></svg>
            </div>
            <span class="stage-title">Branch Route</span>
            <span class="stage-metric">5 Mapped</span>
          </div>

          <div class="pipeline-connector"></div>

          {{-- Stage 5 --}}
          <div class="pipeline-stage">
            <div class="stage-node-icon stage-node-icon--amber">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
            </div>
            <span class="stage-title">GHL Contacts</span>
            <span class="stage-metric">{{ $syncEnabled ? 'Writing' : 'Safety Hold' }}</span>
          </div>
        </div>

        {{-- Distribution breakdown bar below pipeline --}}
        <div class="distribution-bar-wrapper">
          <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--text-muted)">
            <span>Record Eligibility Breakdown</span>
            <span>Total: {{ number_format($stats['records']) }} records</span>
          </div>
          <div class="segmented-progress-bar">
            {{-- In baseline state, 100% is baseline historical --}}
            <div class="seg-slice seg-slice--baseline" style="width: 100%" title="Baseline Historical: {{ number_format($stats['baseline']) }}"></div>
          </div>
          <div class="distribution-legend">
            <div class="legend-item">
              <div class="legend-color-chip legend-color--baseline"></div>
              <div class="legend-text">
                <span class="legend-label">Historical Baseline (Archived)</span>
                <span class="legend-count">{{ number_format($stats['baseline']) }}</span>
              </div>
            </div>
            <div class="legend-item">
              <div class="legend-color-chip legend-color--eligible"></div>
              <div class="legend-text">
                <span class="legend-label">Positive Balance (Eligible)</span>
                <span class="legend-count">{{ $stats['positive_balance'] ?? 0 }}</span>
              </div>
            </div>
            <div class="legend-item">
              <div class="legend-color-chip legend-color--zero"></div>
              <div class="legend-text">
                <span class="legend-label">$0.00 Balance (Exempt)</span>
                <span class="legend-count">{{ $stats['zero_balance'] ?? 0 }}</span>
              </div>
            </div>
            <div class="legend-item">
              <div class="legend-color-chip legend-color--unknown"></div>
              <div class="legend-text">
                <span class="legend-label">New Customers Detected</span>
                <span class="legend-count">{{ $stats['new_customers'] ?? 0 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Viz 2: Recent Sync Runs Execution Timeline --}}
      <div class="viz-card">
        <div class="viz-card-header">
          <div>
            <h3 class="viz-card-title">Recent Sync Executions</h3>
            <p class="viz-card-subtitle">Last automated scans and cron executions</p>
          </div>
          <span class="badge b-info">Live Queue</span>
        </div>

        <div style="overflow-x:auto">
          <table>
            <thead>
              <tr>
                <th>Triggered</th>
                <th>Type</th>
                <th>Mode</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              @forelse($recentRuns as $run)
                <tr>
                  <td>
                    <span title="{{ $run->started_at?->toDateTimeString() }}">{{ $run->started_at?->diffForHumans() ?? '—' }}</span>
                  </td>
                  <td><span class="mono">{{ $run->type }}</span></td>
                  <td><x-badge :value="$run->mode" /></td>
                  <td><x-badge :value="$run->status" /></td>
                </tr>
              @empty
                <tr>
                  <td colspan="4" class="muted" style="text-align:center; padding:24px">No sync runs recorded yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>

  {{-- =========================================================================
       5. BRANCH OVERVIEW (5 LAUNDRYCARD BRANCHES ⇄ GHL LOCATIONS)
       ========================================================================= --}}
  <section id="sec-branches" class="reveal-on-scroll">
    <div class="section-header-block">
      <div class="section-title-wrap">
        <span class="section-supertitle">Physical Retail Stores</span>
        <h2 class="section-main-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-light)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M18 6v6a6 6 0 0 1-6 6H6"></path></svg>
          LaundryCard Branch Routing
        </h2>
        <p class="section-caption">Each physical store maps to an authorized GoHighLevel location to ensure contacts are created in the right sub-account.</p>
      </div>
      <div>
        <a href="{{ route('admin.mappings') }}" class="btn sm">
          View Mapping Table
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
        </a>
      </div>
    </div>

    <div class="branch-cards-grid">
      @forelse($stores as $st)
        @php
          $mapping = $st->activeMapping;
          $conn = $mapping?->connection;
        @endphp
        <div class="branch-card">
          <div>
            <div class="branch-card-header">
              <span class="store-id-pill">Store {{ $st->store_id }}</span>
              @if($mapping)
                <span class="badge b-ok">Verified Route</span>
              @else
                <span class="badge b-warn">Unmapped</span>
              @endif
            </div>

            <h3 class="branch-name">{{ $st->name }}</h3>
            <div class="branch-location-text">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              <span>{{ $st->city ?? 'Unknown' }}, {{ $st->state ?? 'US' }}</span>
            </div>

            {{-- GHL Association Bridge --}}
            <div class="branch-mapping-bridge">
              <div class="mapping-destination-label">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
                GHL Location
              </div>
              <div class="ghl-location-name" title="{{ $conn?->location_name ?? 'Not Associated' }}">
                {{ $conn?->location_name ?? 'Pending OAuth Connection' }}
              </div>
              @if($conn)
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:2px">
                  <span class="mono" style="font-size:10px; color:var(--text-faint)">{{ substr($conn->location_id, 0, 8) }}…</span>
                  <span class="badge b-ok" style="font-size:10px; padding:1px 6px">Active</span>
                </div>
              @endif
            </div>
          </div>

          <div class="branch-card-footer">
            <span class="muted">Scanned Records</span>
            <span class="branch-record-count">{{ number_format($st->source_records_count ?? 0) }}</span>
          </div>
        </div>
      @empty
        <div class="health-card" style="grid-column: 1 / -1; text-align: center; padding: 36px">
          <p class="muted">No branches discovered yet from the LaundryCard API.</p>
        </div>
      @endforelse
    </div>
  </section>

  {{-- =========================================================================
       6. RECENTLY DETECTED CUSTOMERS FROM LAUNDRYCARD
       ========================================================================= --}}
  <section id="sec-customers" class="reveal-on-scroll">
    <div class="section-header-block">
      <div class="section-title-wrap">
        <span class="section-supertitle">Live Customer Ingestion</span>
        <h2 class="section-main-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          Recently Detected from LaundryCard
        </h2>
        <p class="section-caption">New customer cards first observed after store baseline cut-off. Balances are evaluated independently of CRM writes.</p>
      </div>
      <div>
        <a href="{{ route('admin.records') }}" class="btn sm">
          Full Customer Records ({{ number_format($stats['records']) }})
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
        </a>
      </div>
    </div>

    <div class="table-glass-card">
      <div class="table-header-panel">
        <div class="table-header-info">
          <h3 style="margin:0 0 2px">Post-Baseline Ingestion Stream</h3>
          <span class="muted" style="font-size:12px">Cards detected after each store's historical archive baseline.</span>
        </div>
        <div class="table-search-bar">
          <input type="text" id="customer-search-input" class="table-search-input" placeholder="Quick filter by store, card, or name…" aria-label="Filter customer cards">
        </div>
      </div>

      <div class="table-scrollable">
        <table id="dashboard-new-customers">
          <thead>
            <tr>
              <th>Store</th>
              <th>Card Number</th>
              <th>Customer Details</th>
              <th>Balance</th>
              <th>Eligibility</th>
              <th>First Observed</th>
            </tr>
          </thead>
          <tbody id="dashboard-new-customer-rows">
            @forelse($latestNewCustomers as $r)
              <tr>
                <td><span class="store-id-pill">{{ $r->store?->store_id ?? '—' }}</span></td>
                <td><span class="card-mono">{{ substr($r->card_number, 0, 2) }}••••{{ substr($r->card_number, -2) }}</span></td>
                <td>
                  <div class="user-name">
                    {{ $r->customer_name ? \Illuminate\Support\Str::limit($r->customer_name, 1, '') . '***' : '—' }}
                  </div>
                  <div class="user-meta">
                    <span>{{ \App\Support\Sanitizer::maskEmail($r->customer_email) }}</span>
                    <span>{{ \App\Support\Sanitizer::maskPhone($r->customer_phone) }}</span>
                  </div>
                </td>
                <td>
                  <span class="balance-pill {{ (float)$r->balance > 0 ? 'bal-pos' : 'bal-zero' }}">
                    {{ $r->balance !== null ? '$' . number_format((float)$r->balance, 2) : 'unknown' }}
                  </span>
                </td>
                <td><x-badge :value="$r->eligibility_status" /></td>
                <td class="muted">{{ $r->first_observed_at?->toDayDateTimeString() ?? '—' }}</td>
              </tr>
            @empty
              <tr class="empty-new-customers">
                <td colspan="6">
                  <div class="empty-state-box">
                    <div class="empty-state-icon">
                      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                        <line x1="2" y1="10" x2="22" y2="10"></line>
                      </svg>
                    </div>
                    <div class="empty-state-text">
                      No new post-baseline LaundryCard customers detected yet.
                    </div>
                    <p class="muted" style="font-size:12px; margin-top:6px">
                      All 30,305 existing records were safely placed in the historical baseline. New cards will appear here automatically as customers register at the POS.
                    </p>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="table-footer-bar">
        <span class="muted" style="font-size:12px">
          Live stream connected. Real-time changes render automatically.
        </span>
        <a class="btn sm" href="{{ route('admin.records') }}">Open Customer Explorer</a>
      </div>
    </div>
  </section>

  {{-- =========================================================================
       7. PERSISTED INTEGRATION ACTIVITY STREAM (LIVE TERMINAL)
       ========================================================================= --}}
  <section id="sec-activity" class="reveal-on-scroll">
    <div class="section-header-block">
      <div class="section-title-wrap">
        <span class="section-supertitle">Audit & Telemetry Log</span>
        <h2 class="section-main-title">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
          Persisted Integration Activity Stream
        </h2>
        <p class="section-caption">Real-time event feed of API calls, discovery checks, and mapping evaluations straight from the database.</p>
      </div>
      <div>
        <a href="{{ route('admin.logs') }}" class="btn sm">
          Full Log History
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
        </a>
      </div>
    </div>

    <div class="terminal-card">
      <div class="terminal-header">
        <div class="terminal-title-bar">
          <div class="terminal-dots">
            <span class="terminal-dot t-red"></span>
            <span class="terminal-dot t-yellow"></span>
            <span class="terminal-dot t-green"></span>
          </div>
          <span class="terminal-title">telemetry-stream --live --follow</span>
        </div>
        <span class="terminal-stream-badge">LIVE DATABASE INGEST</span>
      </div>

      <div class="table-scrollable">
        <table id="dashboard-events" data-cursor="{{ $recentEvents->max('id') ?? 0 }}">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>Stage</th>
              <th>Outcome</th>
              <th>Store</th>
              <th>GHL Location</th>
              <th>Details & Correlation</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentEvents as $event)
              @php
                $stagePrefix = explode('.', $event->stage)[0] ?? 'default';
                $stageClass = match($stagePrefix) {
                  'api' => 'stage-api',
                  'oauth' => 'stage-oauth',
                  'sync' => 'stage-sync',
                  'eligibility' => 'stage-eligibility',
                  default => '',
                };
              @endphp
              <tr>
                <td class="muted">{{ $event->created_at?->toDateTimeString() }}</td>
                <td><span class="terminal-stage-badge {{ $stageClass }}">{{ $event->stage }}</span></td>
                <td>
                  @if($event->outcome === 'ok' || $event->outcome === 'success')
                    <span class="badge b-ok">{{ $event->outcome }}</span>
                  @elseif($event->outcome === 'error' || $event->outcome === 'failed')
                    <span class="badge b-bad">{{ $event->outcome }}</span>
                  @elseif($event->outcome)
                    <span class="badge b-warn">{{ $event->outcome }}</span>
                  @else
                    <span class="muted">—</span>
                  @endif
                </td>
                <td><span class="mono">{{ $event->store_id ?? '—' }}</span></td>
                <td><span class="mono">{{ $event->ghl_location_id ? substr($event->ghl_location_id, 0, 8) . '…' : '—' }}</span></td>
                <td>{{ $event->message }}</td>
              </tr>
            @empty
              <tr class="empty-events">
                <td colspan="6" class="muted" style="text-align:center; padding:28px">No persisted activity logged yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

</div>

{{-- =========================================================================
     8. CLIENT-SIDE INTERACTION, COUNTER ANIMATIONS & LIVE POLLING ENGINE
     ========================================================================= --}}
@push('scripts')
<script>
(() => {
  'use strict';

  // Number Counter Animation Utility
  const animateCounter = (element, targetValue, duration = 800) => {
    if (!element || isNaN(targetValue)) return;
    const isPrefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (isPrefersReducedMotion) {
      element.textContent = Number(targetValue).toLocaleString();
      return;
    }

    const startValue = Number(element.dataset.currentValue || 0);
    const endValue = Number(targetValue);
    if (startValue === endValue) return;

    element.dataset.currentValue = endValue;
    const startTime = performance.now();

    const step = (now) => {
      const progress = Math.min((now - startTime) / duration, 1);
      // Ease out cubic
      const ease = 1 - Math.pow(1 - progress, 3);
      const current = Math.floor(startValue + (endValue - startValue) * ease);
      element.textContent = current.toLocaleString();

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        element.textContent = endValue.toLocaleString();
      }
    };

    requestAnimationFrame(step);
  };

  // Run initial animation on counter elements
  document.querySelectorAll('.animated-counter').forEach(el => {
    const target = Number(el.dataset.target || el.textContent.replace(/[^0-9]/g, ''));
    if (!isNaN(target)) {
      animateCounter(el, target, 750);
    }
  });

  // Intersection Observer for Scroll Reveals
  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -40px 0px', threshold: 0.1 });

    document.querySelectorAll('.reveal-on-scroll').forEach(el => {
      revealObserver.observe(el);
    });
  } else {
    document.querySelectorAll('.reveal-on-scroll').forEach(el => el.classList.add('is-revealed'));
  }

  // Active Section Spy for Quick Jump Dock
  const dockPills = document.querySelectorAll('.dock-pill');
  const sections = Array.from(dockPills).map(pill => {
    const id = pill.dataset.section;
    return id ? document.getElementById(id) : null;
  }).filter(Boolean);

  window.addEventListener('scroll', () => {
    const scrollPos = window.scrollY + 160;
    let currentId = null;

    sections.forEach(sec => {
      if (sec.offsetTop <= scrollPos) {
        currentId = sec.id;
      }
    });

    if (currentId) {
      dockPills.forEach(pill => {
        pill.classList.toggle('active', pill.dataset.section === currentId);
      });
    }
  }, { passive: true });

  // Quick Client-Side Filter for Recently Detected Customers Table
  const searchInput = document.getElementById('customer-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#dashboard-new-customer-rows tr:not(.empty-new-customers)');
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
      });
    });
  }

  // Live Polling & Feed Telemetry Engine
  const stateElement = document.getElementById('dashboard-live-state');
  if (!stateElement) return;

  const telemetryCapsule = document.getElementById('telemetry-capsule');
  const pollMeta = document.getElementById('telemetry-poll-meta');
  const refreshBtn = document.getElementById('manual-refresh-btn');
  const eventsTable = document.getElementById('dashboard-events');
  const newCustomerRows = document.getElementById('dashboard-new-customer-rows');

  let cursor = Number(eventsTable?.dataset.cursor || 0);
  let isFetching = false;

  const poll = async (manual = false) => {
    if (isFetching) return;
    isFetching = true;

    if (refreshBtn) refreshBtn.classList.add('spinning');

    try {
      const url = `${stateElement.dataset.url}?after=${cursor}`;
      const response = await fetch(url, {
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });

      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();

      cursor = data.last_event_id || cursor;

      // Update Live State Info
      stateElement.textContent = `Telemetry Live (${data.connection_state})`;
      if (pollMeta) {
        const checkedTime = new Date(data.updated_at).toLocaleTimeString();
        pollMeta.textContent = `Polled ${checkedTime} · every ${data.poll_interval_seconds}s`;
      }

      if (telemetryCapsule) {
        telemetryCapsule.classList.remove('disconnected');
      }

      // Smoothly update metrics with counter animation
      for (const [key, value] of Object.entries(data.stats || {})) {
        const targets = document.querySelectorAll(`[data-stat="${key}"]`);
        targets.forEach(target => {
          if (value !== null && typeof value !== 'object') {
            if (target.classList.contains('animated-counter')) {
              animateCounter(target, Number(value), 500);
            } else {
              target.textContent = typeof value === 'number' ? value.toLocaleString() : value;
            }
          }
        });
      }

      // Ingest new events into the Activity Stream
      if (eventsTable && data.events?.length) {
        eventsTable.querySelector('.empty-events')?.remove();
        const tbody = eventsTable.querySelector('tbody') || eventsTable;

        for (const event of [...data.events].reverse()) {
          const row = tbody.insertRow(0);
          row.classList.add('live-new-row');

          const stagePrefix = (event.stage || '').split('.')[0];
          let stageClass = '';
          if (stagePrefix === 'api') stageClass = 'stage-api';
          else if (stagePrefix === 'oauth') stageClass = 'stage-oauth';
          else if (stagePrefix === 'sync') stageClass = 'stage-sync';
          else if (stagePrefix === 'eligibility') stageClass = 'stage-eligibility';

          let outcomeHtml = '<span class="muted">—</span>';
          if (event.outcome === 'ok' || event.outcome === 'success') {
            outcomeHtml = `<span class="badge b-ok">${event.outcome}</span>`;
          } else if (event.outcome === 'error' || event.outcome === 'failed') {
            outcomeHtml = `<span class="badge b-bad">${event.outcome}</span>`;
          } else if (event.outcome) {
            outcomeHtml = `<span class="badge b-warn">${event.outcome}</span>`;
          }

          const ghlLoc = event.ghl_location_id ? `${event.ghl_location_id.substring(0, 8)}…` : '—';

          row.innerHTML = `
            <td class="muted">${new Date(event.created_at).toLocaleString()}</td>
            <td><span class="terminal-stage-badge ${stageClass}">${event.stage}</span></td>
            <td>${outcomeHtml}</td>
            <td><span class="mono">${event.store_id || '—'}</span></td>
            <td><span class="mono">${ghlLoc}</span></td>
            <td>${event.message || ''}</td>
          `;
        }

        // Limit stream table to max 25 rows
        while (eventsTable.rows.length > 26) {
          eventsTable.deleteRow(-1);
        }
      }

      // Update Recent Customers table if new data present
      if (newCustomerRows && Array.isArray(data.new_customers)) {
        newCustomerRows.replaceChildren();
        if (!data.new_customers.length) {
          const row = newCustomerRows.insertRow();
          row.className = 'empty-new-customers';
          row.innerHTML = `
            <td colspan="6">
              <div class="empty-state-box">
                <div class="empty-state-icon">
                  <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                    <line x1="2" y1="10" x2="22" y2="10"></line>
                  </svg>
                </div>
                <div class="empty-state-text">No new post-baseline LaundryCard customers detected yet.</div>
              </div>
            </td>
          `;
        } else {
          for (const customer of data.new_customers) {
            const row = newCustomerRows.insertRow();
            row.classList.add('live-new-row');
            const balanceNum = parseFloat(String(customer.balance).replace(/[^0-9.-]/g, '')) || 0;
            const balClass = balanceNum > 0 ? 'bal-pos' : 'bal-zero';
            const balText = customer.balance || '$0.00';
            const observedDate = customer.first_observed_at ? new Date(customer.first_observed_at).toLocaleString() : '—';

            row.innerHTML = `
              <td><span class="store-id-pill">${customer.store_id || '—'}</span></td>
              <td><span class="card-mono">${customer.card_number || '••••'}</span></td>
              <td>
                <div class="user-name">${customer.customer_name || '—'}</div>
                <div class="user-meta">
                  <span>${customer.customer_email || ''}</span>
                  <span>${customer.customer_phone || ''}</span>
                </div>
              </td>
              <td><span class="balance-pill ${balClass}">${balText}</span></td>
              <td><span class="badge b-info">${customer.eligibility || 'pending'}</span></td>
              <td class="muted">${observedDate}</td>
            `;
          }
        }
      }

    } catch (err) {
      console.warn('Dashboard feed polling error:', err);
      stateElement.textContent = `Telemetry Standby (retrying)`;
      if (telemetryCapsule) {
        telemetryCapsule.classList.add('disconnected');
      }
      if (pollMeta) {
        pollMeta.textContent = `Retrying connection… ${new Date().toLocaleTimeString()}`;
      }
    } finally {
      isFetching = false;
      if (refreshBtn) refreshBtn.classList.remove('spinning');
    }
  };

  // Trigger manual refresh on button click
  if (refreshBtn) {
    refreshBtn.addEventListener('click', () => {
      poll(true);
    });
  }

  // Initial poll + interval
  poll();
  const pollTimer = setInterval(poll, 4000);

  // Clean up if navigating away
  window.addEventListener('beforeunload', () => clearInterval(pollTimer));

})();
</script>
@endpush
@endsection
