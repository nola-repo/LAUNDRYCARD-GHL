@extends('layouts.app')
@section('title','OAuth Installations')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/installations.css') }}?v={{ filemtime(public_path('css/installations.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/sticky-banner.js') }}?v={{ filemtime(public_path('js/sticky-banner.js')) }}" defer></script>
@endpush

@section('content')
<div class="oauth-page" data-sticky-page>
  @if(session('flash_success'))
    <div class="notice ok oauth-flash" role="status">{{ session('flash_success') }}</div>
  @endif

  @php
    $totalConns = $connections->count();
    $activeConns = $connections->where('status', 'active')->count();
    $mappedConns = $connections->filter(fn($c) => $c->activeMapping !== null)->count();
    $unmappedConns = $totalConns - $mappedConns;
  @endphp

  <header class="oauth-hero" id="oauth-top">
    <div class="oauth-hero-top">
      <div class="oauth-hero-copy">
        <p class="oauth-eyebrow"><span aria-hidden="true"></span> Connection management · OAuth 2.0</p>
        <h1>OAuth installations</h1>
        <p>Manage GoHighLevel access, token health, and store routing.</p>
      </div>
    </div>

    <p class="oauth-security-note">
      <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="10" width="18" height="11" rx="2"></rect><path d="M7 10V7a5 5 0 0 1 10 0v3"></path></svg>
      <span>Tokens are encrypted at rest and never displayed.</span>
    </p>
  </header>

  <div class="oauth-toolbar" data-sticky-banner>
      <a class="oauth-toolbar-title" href="#oauth-top">OAuth installations</a>
      <div class="oauth-actions" role="group" aria-label="OAuth installation actions">
        <button type="button" class="btn oauth-btn oauth-btn-secondary" data-open-refresh-dialog aria-label="Refresh all tokens">
          <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7v5h-5"></path><path d="M4 17v-5h5"></path><path d="M5.6 9A7 7 0 0 1 17 6l3 6M4 12l3 6a7 7 0 0 0 11.4-3"></path></svg>
          <span>Refresh all tokens</span>
        </button>
        <a href="{{ route('admin.oauth.diagnose') }}" class="btn oauth-btn oauth-btn-secondary" aria-label="Diagnostics">
          <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
          <span>Diagnostics</span>
        </a>
        <button type="button" class="btn oauth-btn oauth-btn-secondary" id="copy-auth-url" aria-label="Copy auth URL">
          <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
          <span>Copy auth URL</span>
        </button>
        <a href="{{ route('admin.connect') }}" class="btn primary oauth-btn oauth-btn-primary oauth-connect" aria-label="Connect location">
          <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"></path></svg>
          <span>Connect location</span>
        </a>
      </div>
      <div class="oauth-copy-status" id="oauth-copy-status" role="status" aria-live="polite" hidden></div>
  </div>

  <section class="oauth-section" aria-labelledby="oauth-connections-heading">
    <div class="oauth-section-head">
      <div>
        <p class="oauth-section-kicker">Access &amp; routing</p>
        <h2 id="oauth-connections-heading">Connected locations</h2>
        <p>Review token status and branch assignments for each GoHighLevel location.</p>
      </div>
      <span class="oauth-count">{{ $totalConns }} {{ \Illuminate\Support\Str::plural('location', $totalConns) }}</span>
    </div>

    <div class="oauth-connection-grid">
      <section class="oauth-summary" aria-label="OAuth installation summary">
        <article class="oauth-stat oauth-stat--brand">
          <span class="oauth-stat-icon oauth-stat-icon--brand" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path></svg>
          </span>
          <div><span class="oauth-stat-label">Locations</span><strong>{{ number_format($totalConns) }}</strong><small>Registered connections</small></div>
          <span class="oauth-stat-watermark" aria-hidden="true"><svg width="176" height="176" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path></svg></span>
        </article>
        <article class="oauth-stat oauth-stat--green">
          <span class="oauth-stat-icon oauth-stat-icon--green" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>
          </span>
          <div><span class="oauth-stat-label">Active</span><strong>{{ number_format($activeConns) }}</strong><small>Active connections</small></div>
          <span class="oauth-stat-watermark" aria-hidden="true"><svg width="176" height="176" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg></span>
        </article>
        <article class="oauth-stat oauth-stat--blue">
          <span class="oauth-stat-icon oauth-stat-icon--blue" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><path d="M18 9v3a6 6 0 0 1-6 6H9"></path></svg>
          </span>
          <div><span class="oauth-stat-label">Routed</span><strong>{{ number_format($mappedConns) }}</strong><small>Assigned to a store</small></div>
          <span class="oauth-stat-watermark" aria-hidden="true"><svg width="176" height="176" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><path d="M18 9v3a6 6 0 0 1-6 6H9"></path></svg></span>
        </article>
        <article class="oauth-stat oauth-stat--orange">
          <span class="oauth-stat-icon oauth-stat-icon--orange" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4m0 4h.01"></path></svg>
          </span>
          <div><span class="oauth-stat-label">Unassigned</span><strong>{{ number_format($unmappedConns) }}</strong><small>Awaiting assignment</small></div>
          <span class="oauth-stat-watermark" aria-hidden="true"><svg width="176" height="176" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8v4m0 4h.01"></path></svg></span>
        </article>
      </section>

      @forelse($connections as $c)
        @php($expiryState = $c->tokenExpiryState())
        <article class="oauth-connection-card">
          <div class="oauth-connection-head">
            <span class="oauth-location-mark" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01M16 13v.01M16 16v.01M16 19v.01"></path></svg>
            </span>
            <div class="oauth-location-copy">
              <span class="oauth-overline">GoHighLevel location</span>
              <h3>{{ $c->location_name ?? 'Unnamed location' }}</h3>
              <span class="oauth-location-id">Location ID <code>{{ $c->location_id }}</code></span>
            </div>
            <x-badge :value="$c->status" />
          </div>

          <div class="oauth-token-row">
            <div class="oauth-token-state">
              <span class="oauth-detail-label">Token health</span>
              <x-badge :value="$expiryState" />
            </div>
            <div class="oauth-token-expiry">
              <span class="oauth-detail-label">Expires</span>
              <strong title="{{ $c->expires_at?->format('Y-m-d H:i:s') }} UTC">{{ $c->expires_at?->diffForHumans() ?? 'Not available' }}</strong>
              @if($c->expires_at)
                <small>{{ $c->expires_at->format('M d, H:i') }} UTC</small>
              @endif
            </div>
          </div>

          <dl class="oauth-detail-grid">
            <div>
              <dt>Last refreshed</dt>
              <dd>
                @if($c->last_refreshed_at)
                  {{ $c->last_refreshed_at->diffForHumans() }}
                  <small>{{ $c->last_refreshed_at->format('M d, H:i') }} UTC</small>
                @else
                  Never
                @endif
              </dd>
            </div>
            <div>
              <dt>Last API success</dt>
              <dd>{{ $c->last_success_at?->diffForHumans() ?? 'Never' }}</dd>
            </div>
            <div>
              <dt>Installed</dt>
              <dd>{{ $c->installed_at?->toDateString() ?? '—' }}</dd>
            </div>
            <div>
              <dt>Mapped store</dt>
              <dd>
                @if($c->activeMapping)
                  <span class="store-id-pill">Store {{ $c->activeMapping->store->store_id }}</span>
                  <small>{{ $c->activeMapping->store->name }}</small>
                @else
                  <span class="oauth-unassigned">Unassigned</span>
                @endif
              </dd>
            </div>
          </dl>

          <div class="oauth-card-section">
            <h4>Granted scopes</h4>
            @if($c->scopes)
              <div class="oauth-scope-list">
                @foreach($c->scopes as $scope)
                  <span class="tag-chip">{{ $scope }}</span>
                @endforeach
              </div>
            @else
              <p class="oauth-empty-copy">No scopes recorded.</p>
            @endif
          </div>

          <div class="oauth-card-section oauth-errors">
            <h4>Recent errors</h4>
            @if($c->last_error)
              <p class="err-txt">{{ $c->last_error }}</p>
            @endif
            @foreach($c->recent_errors as $e)
              <p class="oauth-error-entry"><time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->diffForHumans() }}</time> · {{ \Illuminate\Support\Str::limit($e->message, 90) }}</p>
            @endforeach
            @if(!$c->last_error && count($c->recent_errors) === 0)
              <p class="oauth-empty-copy">No recent OAuth errors.</p>
            @endif
          </div>

          <div class="oauth-card-actions">
            @if($c->status === 'revoked' || $expiryState === 'expired')
              <a href="{{ route('admin.connect') }}" class="btn sm primary">Reconnect OAuth</a>
            @else
              <form method="post" action="{{ route('admin.connections.refresh', $c) }}">
                @csrf
                <button type="submit" class="btn sm" title="Proactively rotate this location's access token">
                  <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7v5h-5"></path><path d="M4 17v-5h5"></path><path d="M5.6 9A7 7 0 0 1 17 6l3 6M4 12l3 6a7 7 0 0 0 11.4-3"></path></svg>
                  Refresh token
                </button>
              </form>
              <form method="post" action="{{ route('admin.connections.test', $c) }}">
                @csrf
                <button type="submit" class="btn sm">Test connection</button>
              </form>
            @endif
          </div>
        </article>
      @empty
        <div class="oauth-empty-state">
          <span class="oauth-empty-icon" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path></svg>
          </span>
          <h3>No locations connected yet</h3>
          <p>Connect a GoHighLevel location to begin the OAuth handshake. Assign a LaundryCard branch before contact synchronization can run.</p>
          <a href="{{ route('admin.connect') }}" class="btn primary oauth-btn oauth-btn-primary">Connect location</a>
        </div>
      @endforelse
    </div>
  </section>

  @if($recentOauthEvents->isNotEmpty())
    <section class="oauth-events" aria-labelledby="oauth-events-heading">
      <div class="oauth-section-head">
        <div>
          <p class="oauth-section-kicker">Token lifecycle</p>
          <h2 id="oauth-events-heading">Recent OAuth activity</h2>
          <p>Installation, state validation, and token rotation events.</p>
        </div>
        <a href="{{ route('admin.logs') }}?stage=oauth" class="btn sm oauth-events-link">View OAuth logs</a>
      </div>

      <div class="table-card oauth-event-card">
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Time (UTC)</th>
                <th>Location ID</th>
                <th>Stage</th>
                <th>Outcome</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentOauthEvents as $ev)
                <tr>
                  <td class="nowrap oauth-event-time">{{ $ev->created_at->format('M d, Y H:i:s') }}</td>
                  <td class="nowrap"><span class="card-mono">{{ $ev->ghl_location_id ?? 'Global' }}</span></td>
                  <td class="nowrap"><span class="mono oauth-event-stage">{{ $ev->stage }}</span></td>
                  <td class="nowrap"><x-badge :value="$ev->outcome" /></td>
                  <td class="oauth-event-message">{{ $ev->message }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>
  @endif

  <dialog class="oauth-confirm-dialog" id="refresh-all-dialog" aria-labelledby="refresh-all-title" aria-describedby="refresh-all-description">
    <form method="post" action="{{ route('admin.connections.refresh-all') }}">
      @csrf
      <div class="oauth-dialog-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7v5h-5"></path><path d="M4 17v-5h5"></path><path d="M5.6 9A7 7 0 0 1 17 6l3 6M4 12l3 6a7 7 0 0 0 11.4-3"></path></svg>
      </div>
      <h2 id="refresh-all-title">Refresh all active tokens?</h2>
      <p id="refresh-all-description">This sends a token refresh request for each of the {{ $activeConns }} active {{ \Illuminate\Support\Str::plural('location', $activeConns) }}. The next page reports which refreshes succeeded, were skipped, or failed.</p>
      <div class="oauth-dialog-actions">
        <button type="button" class="btn oauth-btn oauth-btn-secondary" data-close-refresh-dialog autofocus>Cancel</button>
        <button type="submit" class="btn primary oauth-btn oauth-btn-primary"><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7v5h-5"></path><path d="M4 17v-5h5"></path><path d="M5.6 9A7 7 0 0 1 17 6l3 6M4 12l3 6a7 7 0 0 0 11.4-3"></path></svg>Refresh all tokens</button>
      </div>
    </form>
  </dialog>
</div>

<script>
(() => {
  const dialog = document.getElementById('refresh-all-dialog');
  const openDialogButton = document.querySelector('[data-open-refresh-dialog]');
  const closeDialogButton = document.querySelector('[data-close-refresh-dialog]');
  const refreshForm = dialog?.querySelector('form');
  const copyButton = document.getElementById('copy-auth-url');
  const copyStatus = document.getElementById('oauth-copy-status');

  if (dialog && openDialogButton && closeDialogButton) {
    openDialogButton.addEventListener('click', () => {
      dialog.showModal();
      closeDialogButton.focus();
    });
    closeDialogButton.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
      if (event.target === dialog) dialog.close();
    });
    refreshForm?.addEventListener('submit', event => {
      if (refreshForm.dataset.submitting === 'true') {
        event.preventDefault();
        return;
      }
      refreshForm.dataset.submitting = 'true';
      const submitButton = refreshForm.querySelector('[type="submit"]');
      submitButton.disabled = true;
      submitButton.setAttribute('aria-busy', 'true');
    });
  }

  if (copyButton && copyStatus) {
    copyButton.addEventListener('click', async () => {
      copyButton.disabled = true;
      copyButton.setAttribute('aria-busy', 'true');
      copyStatus.hidden = true;
      copyStatus.removeAttribute('data-tone');

      try {
        const response = await fetch('{{ route('oauth.auth-url') }}', {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const data = await response.json();
        if (!response.ok || data.error || !data.url) throw new Error('auth-url');
        await navigator.clipboard.writeText(data.url);
        copyStatus.textContent = 'Authorization URL copied to clipboard. It contains a temporary state value.';
        copyStatus.dataset.tone = 'success';
      } catch {
        copyStatus.textContent = 'Could not copy the authorization URL. Check OAuth setup in Diagnostics and allow clipboard access.';
        copyStatus.dataset.tone = 'error';
      } finally {
        copyStatus.hidden = false;
        copyButton.disabled = false;
        copyButton.removeAttribute('aria-busy');
      }
    });
  }
})();
</script>
@endsection
