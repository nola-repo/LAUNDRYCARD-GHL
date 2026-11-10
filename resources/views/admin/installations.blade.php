@extends('layouts.app')
@section('title','OAuth Installations')
@section('content')

@if(session('flash_success'))
<div class="notice ok">{{ session('flash_success') }}</div>
@endif

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
      OAuth 2.0 Security
    </span>
    <h1>OAuth Installations</h1>
    <p class="sub">Location-scoped GoHighLevel connections registered through OAuth. Access and refresh tokens are encrypted at rest and never displayed.</p>
  </div>
  <div class="btn-row">
    <a href="{{ route('admin.connect') }}" class="btn primary">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Connect location</span>
    </a>
    <a href="{{ route('admin.oauth.diagnose') }}" class="btn">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
      <span>Diagnostics</span>
    </a>
    <button type="button" onclick="copyAuthUrl()" class="btn" title="Fetch and copy the authorization URL (state redacted) for inspection">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
      <span>Copy auth URL</span>
    </button>
  </div>
</div>

@php
  $totalConns = $connections->count();
  $activeConns = $connections->where('status', 'active')->count();
  $mappedConns = $connections->filter(fn($c) => $c->activeMapping !== null)->count();
  $unmappedConns = $totalConns - $mappedConns;
@endphp

<div class="metric-row">
  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Registered Locations</span>
      <div class="metric-icon metric-icon--violet">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
      </div>
    </div>
    <div class="metric-num">{{ $totalConns }}</div>
    <div class="metric-sub">Total OAuth handshakes stored</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Active Sessions</span>
      <div class="metric-icon metric-icon--emerald">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
      </div>
    </div>
    <div class="metric-num" style="color:var(--ok-light)">{{ $activeConns }}</div>
    <div class="metric-sub">Auto-rotating token lifecycle</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Routed to Store</span>
      <div class="metric-icon metric-icon--cyan">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="3"></circle><circle cx="6" cy="6" r="3"></circle><path d="M18 6v6a6 6 0 0 1-6 6H6"></path></svg>
      </div>
    </div>
    <div class="metric-num">{{ $mappedConns }}</div>
    <div class="metric-sub">Linked to a LaundryCard branch</div>
  </div>

  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">Unassigned</span>
      <div class="metric-icon metric-icon--amber">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
      </div>
    </div>
    <div class="metric-num">{{ $unmappedConns }}</div>
    <div class="metric-sub">Awaiting store pair resolution</div>
  </div>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
        Connected Sub-Accounts
      </h2>
      <span class="muted" style="font-size:12px">Encrypted OAuth credentials and token rotation telemetry per GoHighLevel location</span>
    </div>
    <span class="badge b-ok"><span class="dot ok"></span> AES-256 Encrypted</span>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Location ID</th>
          <th>Sub-Account Name</th>
          <th>Status</th>
          <th>Granted Scopes</th>
          <th>Token Health</th>
          <th>Installed</th>
          <th>Last API Success</th>
          <th>Recent Errors</th>
          <th>Mapped Store</th>
        </tr>
      </thead>
      <tbody>
        @forelse($connections as $c)
        <tr>
          <td class="nowrap"><span class="card-mono">{{ $c->location_id }}</span></td>
          <td style="font-weight:700;color:var(--text-title);min-width:170px">{{ $c->location_name ?? '—' }}</td>
          <td class="nowrap"><x-badge :value="$c->status"/></td>
          <td style="min-width:210px">
            <div class="tag-row" style="margin-top:0">
              @foreach($c->scopes ?? [] as $scope)
                <span class="tag-chip">{{ $scope }}</span>
              @endforeach
            </div>
          </td>
          <td class="nowrap">
            <x-badge :value="$c->tokenExpiryState()"/>
            <div class="muted" style="font-size:11px;margin-top:3px">{{ $c->expires_at?->diffForHumans() }}</div>
          </td>
          <td class="muted nowrap">{{ $c->installed_at?->toDateString() ?? '—' }}</td>
          <td class="nowrap">{{ $c->last_success_at?->diffForHumans() ?? 'never' }}</td>
          <td style="max-width:220px">
            @if($c->last_error)
              <div class="err-txt" style="margin:0">{{ $c->last_error }}</div>
            @endif
            @foreach($c->recent_errors as $e)
              <div class="muted" style="font-size:11px">{{ $e->created_at->diffForHumans() }}: {{ \Illuminate\Support\Str::limit($e->message,70) }}</div>
            @endforeach
            @if(!$c->last_error && (!isset($c->recent_errors) || count($c->recent_errors) === 0))
              <span class="muted" style="font-size:12px">None</span>
            @endif
          </td>
          <td class="nowrap">
            @if($c->activeMapping)
              <span class="store-id-pill">Store {{ $c->activeMapping->store->store_id }}</span>
              <div class="muted" style="font-size:11.5px;margin-top:3px">{{ $c->activeMapping->store->name }}</div>
            @else
              <x-badge value="unmapped"/>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9">
            <div class="empty-state-box">
              <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
              </div>
              <div class="empty-state-text">No GHL location has authorized the app yet.</div>
              <p class="muted" style="font-size:12.5px;margin-top:4px">Use the Connect location button above to initiate the OAuth handshake.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<script>
function copyAuthUrl() {
  fetch('{{ route('oauth.auth-url') }}')
    .then(r => r.json())
    .then(data => {
      if (data.error) { alert('Error: ' + data.error); return; }
      navigator.clipboard.writeText(data.url).then(() => {
        alert('Auth URL copied to clipboard.\n\nRedacted preview:\n' + data.redacted);
      }).catch(() => {
        prompt('Copy this URL:', data.url);
      });
    })
    .catch(e => alert('Failed to fetch auth URL: ' + e));
}
</script>
@endsection
