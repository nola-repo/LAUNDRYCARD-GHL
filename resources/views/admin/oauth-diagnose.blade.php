@extends('layouts.app')
@section('title','OAuth Diagnostics')
@section('content')

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
      OAuth Handshake Inspector
    </span>
    <h1>OAuth Diagnostics</h1>
    <p class="sub">Inspect the GoHighLevel OAuth configuration, authorization URL parameters, and recent handshake events.</p>
  </div>
  <a href="{{ route('admin.installations') }}" class="btn">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"></path></svg>
    <span>Back to installations</span>
  </a>
</div>

<div class="grid g2" style="margin-bottom:22px">
  {{-- Config status --}}
  <div class="table-card" style="margin-bottom:0">
    <div class="table-card-header">
      <div>
        <h2>GHL OAuth configuration</h2>
        <span class="muted" style="font-size:12px">Loaded environment variables &amp; endpoint targets</span>
      </div>
      <x-badge :value="$isConfigured ? 'ok' : 'error'"/>
    </div>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th style="width:150px">Key</th>
            <th>Value</th>
            <th style="width:90px">Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="font-weight:700;color:var(--text-title)">Client ID</td>
            <td class="mono">{{ $safeConfig['client_id'] }}</td>
            <td><x-badge :value="$safeConfig['client_id'] !== '(missing)' ? 'ok' : 'error'"/></td>
          </tr>
          <tr>
            <td style="font-weight:700;color:var(--text-title)">Client Secret</td>
            <td class="mono">{{ $safeConfig['client_secret'] }}</td>
            <td><x-badge :value="$safeConfig['client_secret'] !== '(missing)' ? 'ok' : 'error'"/></td>
          </tr>
          <tr>
            <td style="font-weight:700;color:var(--text-title)">Redirect URI</td>
            <td class="mono" style="word-break:break-all">{{ $safeConfig['redirect_uri'] }}</td>
            <td><x-badge :value="$safeConfig['redirect_uri'] !== '(missing)' ? 'ok' : 'error'"/></td>
          </tr>
          <tr>
            <td style="font-weight:700;color:var(--text-title)">Scopes</td>
            <td>
              <div class="tag-row" style="margin-top:0">
                @foreach($safeConfig['scopes'] as $scope)
                  <span class="tag-chip">{{ $scope }}</span>
                @endforeach
                @if(!in_array('oauth.readonly', $safeConfig['scopes']))
                  <span class="badge b-warn">oauth.readonly missing</span>
                @endif
              </div>
            </td>
            <td><x-badge :value="in_array('oauth.readonly', $safeConfig['scopes']) ? 'ok' : 'warning'"/></td>
          </tr>
          <tr>
            <td style="font-weight:700;color:var(--text-title)">Authorize URL</td>
            <td class="mono" style="font-size:11px;word-break:break-all" colspan="2">{{ $safeConfig['authorize_url'] }}</td>
          </tr>
          <tr>
            <td style="font-weight:700;color:var(--text-title)">Token URL</td>
            <td class="mono" style="font-size:11px;word-break:break-all" colspan="2">{{ $safeConfig['token_url'] }}</td>
          </tr>
          <tr>
            <td style="font-weight:700;color:var(--text-title)">API Base</td>
            <td class="mono" style="font-size:11px;word-break:break-all" colspan="2">{{ $safeConfig['api_base'] }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:22px">
    {{-- Auth URL preview --}}
    <div class="card" style="margin-bottom:0">
      <h2>Authorization URL preview <span class="hint">(state redacted)</span></h2>
      @if($authUrlRedacted)
        <div class="code-box">{{ $authUrlRedacted }}</div>
        <p class="muted" style="font-size:12px;margin:12px 0 0">
          Ensure that <code>redirect_uri</code> and <code>client_id</code> exactly match what is registered in your GoHighLevel Marketplace app settings.
        </p>
      @elseif($authUrlError)
        <div class="notice err" style="margin:0">Could not generate authorization URL: {{ $authUrlError }}</div>
      @else
        <p class="muted" style="margin:0">OAuth is not configured; cannot generate authorization URL.</p>
      @endif
    </div>

    {{-- Test token endpoint reachability --}}
    <div class="card" style="margin-bottom:0">
      <h2>Test token endpoint</h2>
      <p class="muted" style="margin-bottom:16px">Check that the OAuth configuration loads and a signed state parameter can be generated dynamically.</p>
      <button type="button" onclick="testEndpoint(this)" class="btn primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>Test config</span>
      </button>
      <div id="test-result" style="margin-top:16px;display:none"></div>
    </div>
  </div>
</div>

{{-- Recent OAuth events --}}
<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2>Recent OAuth events <span class="hint" style="font-size:12px;font-weight:500;color:var(--text-muted)">(last 20)</span></h2>
      <span class="muted" style="font-size:12px">Token rotations, installations, and state validations</span>
    </div>
    <a href="{{ route('admin.logs') }}?stage=oauth" class="btn sm">View all OAuth events →</a>
  </div>

  @if($recentOauthEvents->isEmpty())
    <div class="empty-state-box">
      <div class="empty-state-text">No OAuth events recorded yet.</div>
    </div>
  @else
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Time</th>
          <th>Stage</th>
          <th>Level</th>
          <th>Outcome</th>
          <th>Location</th>
          <th>Message</th>
        </tr>
      </thead>
      <tbody>
        @foreach($recentOauthEvents as $ev)
        <tr>
          <td class="mono" style="font-size:11px">#{{ $ev->id }}</td>
          <td style="font-size:12px;white-space:nowrap;font-weight:600;color:var(--text-title)">{{ $ev->created_at->diffForHumans() }}</td>
          <td><span class="terminal-stage-badge stage-oauth">{{ $ev->stage }}</span></td>
          <td><x-badge :value="$ev->level ?? 'info'"/></td>
          <td><x-badge :value="$ev->outcome ?? ''"/></td>
          <td class="mono" style="font-size:11.5px">{{ $ev->ghl_location_id ?? '—' }}</td>
          <td style="font-size:12.5px;max-width:360px">{{ \Illuminate\Support\Str::limit($ev->message, 100) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>

<script>
function testEndpoint(btn) {
  const result = document.getElementById('test-result');
  btn.disabled = true;
  const origHtml = btn.innerHTML;
  btn.textContent = 'Testing…';
  result.style.display = 'none';

  fetch('{{ route('oauth.auth-url') }}')
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        result.innerHTML = '<div class="notice err" style="margin:0">Config error: ' + data.error + '</div>';
      } else {
        result.innerHTML = '<div class="notice ok" style="margin:0;display:block"><div>OAuth config loaded. Authorization URL generated (state redacted):</div><div class="code-box" style="margin-top:10px">' + data.redacted + '</div></div>';
      }
      result.style.display = 'block';
    })
    .catch(e => {
      result.innerHTML = '<div class="notice err" style="margin:0">Request failed: ' + e + '</div>';
      result.style.display = 'block';
    })
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = origHtml;
    });
}
</script>
@endsection
