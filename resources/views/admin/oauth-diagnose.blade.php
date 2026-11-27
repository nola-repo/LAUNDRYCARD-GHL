@extends('layouts.app')
@section('title','OAuth Diagnostics')
@section('content')
<x-admin-workspace title="OAuth diagnostics" eyebrow="GoHighLevel · Connection inspection" description="Review OAuth settings, inspect the authorization preview, and follow recent connection events.">
  <x-slot:context><strong>{{ $isConfigured ? 'Configuration present' : 'Configuration incomplete' }}</strong>Secrets remain hidden throughout this inspection.</x-slot:context>
  <x-slot:navigation><a href="#oauth-configuration">Configuration</a><a href="#oauth-preview">Authorization</a><a href="#workspace-results">Events</a></x-slot:navigation>
  <x-slot:actions><a class="admin-action admin-action-primary" href="{{ route('admin.installations') }}">OAuth installations</a></x-slot:actions>
  <div class="workspace-split">
    <section class="workspace-panel" id="oauth-configuration" aria-labelledby="oauth-configuration-title"><div class="workspace-panel-head"><div><h2 id="oauth-configuration-title">Connection settings</h2><p>Configured credentials and endpoint targets</p></div><x-badge :value="$isConfigured ? 'ok' : 'error'"/></div>
      <dl class="workspace-config">
        @foreach(['client_id' => 'Client ID', 'client_secret' => 'Client secret', 'redirect_uri' => 'Redirect URI'] as $key => $label)<div><dt>{{ $label }}</dt><dd><span class="workspace-mono">{{ $safeConfig[$key] }}</span><div class="workspace-badges"><x-badge :value="$safeConfig[$key] !== '(missing)' ? 'ok' : 'error'"/></div></dd></div>@endforeach
        <div><dt>Scopes</dt><dd><div class="workspace-badges">@foreach($safeConfig['scopes'] as $scope)<span class="badge b-mute">{{ $scope }}</span>@endforeach</div>@if(!in_array('oauth.readonly',$safeConfig['scopes']))<p class="workspace-note">oauth.readonly is missing.</p><x-badge value="warning"/>@else<div class="workspace-badges"><x-badge value="ok"/></div>@endif</dd></div>
        @foreach(['authorize_url' => 'Authorize URL', 'token_url' => 'Token URL', 'api_base' => 'API base'] as $key => $label)<div><dt>{{ $label }}</dt><dd class="workspace-mono">{{ $safeConfig[$key] }}</dd></div>@endforeach
      </dl>
    </section>
    <div class="workspace-stack">
      <section class="workspace-panel" id="oauth-preview" aria-labelledby="oauth-preview-title"><div class="workspace-panel-head"><div><h2 id="oauth-preview-title">Authorization preview</h2><p>State is redacted</p></div></div><div class="workspace-panel-body">
        @if($authUrlRedacted)<code class="workspace-code">{{ $authUrlRedacted }}</code><p class="workspace-note">The redirect URI and client ID must match your GoHighLevel Marketplace app settings.</p>@elseif($authUrlError)<p class="workspace-alert" data-tone="error">Could not generate the authorization URL: {{ $authUrlError }}</p>@else<p class="workspace-note">Complete the OAuth configuration before generating an authorization URL.</p>@endif
      </div></section>
      <section class="workspace-panel" aria-labelledby="oauth-check-title"><div class="workspace-panel-body"><span class="workspace-kicker">Configuration check</span><h2 id="oauth-check-title">Verify authorization setup</h2><p class="workspace-note">Check that settings load and a signed authorization URL can be generated. This check does not contact the token endpoint.</p><div class="workspace-actions"><button type="button" class="admin-action admin-action-primary" data-test-oauth="{{ route('oauth.auth-url') }}"><span>Test configuration</span></button></div><div class="workspace-alert" data-oauth-result role="status" aria-live="polite" hidden></div></div></section>
    </div>
  </div>
  <section class="workspace-section workspace-panel" aria-labelledby="workspace-results"><div class="workspace-panel-head"><div><h2 id="workspace-results" tabindex="-1">Recent OAuth events</h2><p>Latest {{ $recentOauthEvents->count() }} events · Token rotations, installations, and state validation</p></div><a class="admin-action" href="{{ route('admin.logs',['stage'=>'oauth']) }}">All OAuth events</a></div>
    <div class="workspace-table-wrap"><table class="workspace-table workspace-table--events"><caption class="workspace-sr-only">Recent OAuth connection events</caption><thead><tr><th scope="col">Recorded · UTC</th><th scope="col">Event / message</th><th scope="col">Level / outcome</th><th scope="col">Location</th></tr></thead><tbody>
      @forelse($recentOauthEvents as $event)<tr><td data-label="Recorded · UTC"><x-workspace-time :value="$event->created_at"/><span class="workspace-id">Event {{ $event->id }}</span></td><th scope="row" class="workspace-event-main" data-label="Event / message"><span class="workspace-id">{{ $event->stage }}</span><strong>{{ $event->message }}</strong></th><td data-label="Level / outcome"><div class="workspace-badges"><x-badge :value="$event->level ?? 'info'"/>@if($event->outcome)<x-badge :value="$event->outcome"/>@endif</div></td><td data-label="Location"><span class="workspace-mono">{{ $event->ghl_location_id ?? 'Not recorded' }}</span></td></tr>
      @empty<tr class="workspace-empty-row"><td colspan="4"><x-workspace-empty title="No OAuth events recorded yet" description="Connection activity will appear after an installation or token refresh."/></td></tr>@endforelse
    </tbody></table></div>
  </section>
</x-admin-workspace>
@endsection
