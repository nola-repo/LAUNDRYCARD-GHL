@extends('layouts.app')
@section('title','OAuth Diagnostics')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem;flex-wrap:wrap;gap:12px">
  <div>
    <h1 style="margin:0">OAuth Diagnostics</h1>
    <p class="sub" style="margin:4px 0 0">Inspect GHL OAuth configuration and recent authorization events.</p>
  </div>
  <a href="{{ route('admin.installations') }}" style="padding:8px 16px;background:#f3f4f6;color:#374151;border-radius:6px;text-decoration:none;font-weight:600;border:1px solid #d1d5db;">
    ← Back to Installations
  </a>
</div>

{{-- Config status --}}
<div class="card" style="margin-bottom:1rem">
  <h2 style="margin-top:0;font-size:15px">GHL OAuth Configuration</h2>
  <table>
    <tr><th style="width:200px">Key</th><th>Value</th><th>Status</th></tr>
    <tr>
      <td>Client ID</td>
      <td class="mono">{{ $safeConfig['client_id'] }}</td>
      <td><x-badge :value="$safeConfig['client_id'] !== '(missing)' ? 'ok' : 'error'"/></td>
    </tr>
    <tr>
      <td>Client Secret</td>
      <td class="mono">{{ $safeConfig['client_secret'] }}</td>
      <td><x-badge :value="$safeConfig['client_secret'] !== '(missing)' ? 'ok' : 'error'"/></td>
    </tr>
    <tr>
      <td>Redirect URI</td>
      <td class="mono">{{ $safeConfig['redirect_uri'] }}</td>
      <td><x-badge :value="$safeConfig['redirect_uri'] !== '(missing)' ? 'ok' : 'error'"/></td>
    </tr>
    <tr>
      <td>Scopes</td>
      <td>
        @foreach($safeConfig['scopes'] as $scope)
          <span class="badge b-mute" style="margin:1px">{{ $scope }}</span>
        @endforeach
        @if(!in_array('oauth.readonly', $safeConfig['scopes']))
          <span class="badge" style="background:#fef3c7;color:#92400e;margin:1px">⚠ oauth.readonly missing</span>
        @endif
      </td>
      <td><x-badge :value="in_array('oauth.readonly', $safeConfig['scopes']) ? 'ok' : 'warning'"/></td>
    </tr>
    <tr><td>Authorize URL</td><td class="mono" style="font-size:11px">{{ $safeConfig['authorize_url'] }}</td><td></td></tr>
    <tr><td>Token URL</td><td class="mono" style="font-size:11px">{{ $safeConfig['token_url'] }}</td><td></td></tr>
    <tr><td>API Base</td><td class="mono" style="font-size:11px">{{ $safeConfig['api_base'] }}</td><td></td></tr>
  </table>
</div>

{{-- Auth URL preview --}}
<div class="card" style="margin-bottom:1rem">
  <h2 style="margin-top:0;font-size:15px">Authorization URL Preview <span style="font-size:12px;font-weight:400;color:#6b7280">(state redacted)</span></h2>
  @if($authUrlRedacted)
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:12px;font-family:monospace;font-size:11px;word-break:break-all">
      {{ $authUrlRedacted }}
    </div>
    <p style="font-size:12px;color:#6b7280;margin-top:8px">
      Verify that the <code>redirect_uri</code> and <code>client_id</code> exactly match what is registered in your GHL Marketplace app settings.
    </p>
  @elseif($authUrlError)
    <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:12px;color:#991b1b;font-size:13px">
      ❌ Could not generate authorization URL: {{ $authUrlError }}
    </div>
  @else
    <p class="muted">OAuth is not configured; cannot generate authorization URL.</p>
  @endif
</div>

{{-- Test token endpoint reachability --}}
<div class="card" style="margin-bottom:1rem">
  <h2 style="margin-top:0;font-size:15px">Test Token Endpoint</h2>
  <p style="font-size:13px;color:#6b7280">Click the button below to verify that the GHL token endpoint is reachable from the server (sends a HEAD-like OPTIONS probe).</p>
  <button onclick="testEndpoint()" style="padding:8px 16px;background:#2563eb;color:#fff;border-radius:6px;border:none;cursor:pointer;font-weight:600;">
    Test Config
  </button>
  <div id="test-result" style="margin-top:12px;display:none"></div>
</div>

{{-- Recent OAuth events --}}
<div class="card">
  <h2 style="margin-top:0;font-size:15px">Recent OAuth Events <span style="font-size:12px;font-weight:400;color:#6b7280">(last 20)</span></h2>
  @if($recentOauthEvents->isEmpty())
    <p class="muted">No OAuth events recorded yet.</p>
  @else
  <table>
    <tr><th>ID</th><th>Time</th><th>Stage</th><th>Level</th><th>Outcome</th><th>Location</th><th>Message</th></tr>
    @foreach($recentOauthEvents as $ev)
    <tr>
      <td class="mono" style="font-size:11px">{{ $ev->id }}</td>
      <td style="font-size:11px;white-space:nowrap">{{ $ev->created_at->diffForHumans() }}</td>
      <td class="mono" style="font-size:11px">{{ $ev->stage }}</td>
      <td><x-badge :value="$ev->level ?? 'info'"/></td>
      <td><x-badge :value="$ev->outcome ?? ''"/></td>
      <td class="mono" style="font-size:11px">{{ $ev->ghl_location_id ?? '—' }}</td>
      <td style="font-size:12px;max-width:300px">{{ \Illuminate\Support\Str::limit($ev->message, 100) }}</td>
    </tr>
    @endforeach
  </table>
  <p style="margin-top:8px;font-size:12px">
    <a href="{{ route('admin.logs') }}?stage=oauth" style="color:#2563eb">View all OAuth events in the Activity Log →</a>
  </p>
  @endif
</div>

<script>
function testEndpoint() {
  const btn = event.target;
  const result = document.getElementById('test-result');
  btn.disabled = true;
  btn.textContent = 'Testing…';
  result.style.display = 'none';

  fetch('{{ route('oauth.auth-url') }}')
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        result.innerHTML = '<div style="color:#991b1b;background:#fef2f2;padding:10px;border-radius:6px">❌ Config error: ' + data.error + '</div>';
      } else {
        result.innerHTML = '<div style="color:#065f46;background:#d1fae5;padding:10px;border-radius:6px">✅ OAuth config loaded successfully. Authorization URL generated (state redacted):<br><code style="font-size:11px;word-break:break-all">' + data.redacted + '</code></div>';
      }
      result.style.display = 'block';
    })
    .catch(e => {
      result.innerHTML = '<div style="color:#991b1b;background:#fef2f2;padding:10px;border-radius:6px">❌ Request failed: ' + e + '</div>';
      result.style.display = 'block';
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = 'Test Config';
    });
}
</script>
@endsection
