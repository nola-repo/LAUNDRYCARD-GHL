@extends('layouts.app')
@section('title','OAuth Installations')
@section('content')
<h1>OAuth Installations</h1>
<p class="sub">Location-scoped GHL connections registered through OAuth. Tokens are encrypted and never displayed. Installations and revocations are managed by the OAuth lifecycle.</p>
<div class="card" style="overflow:auto">
<table>
  <tr><th>Location ID</th><th>Name</th><th>Status</th><th>Granted scopes</th><th>Token health</th><th>Installed</th><th>Last API success</th><th>Recent errors</th><th>Mapped store</th></tr>
  @forelse($connections as $c)
  <tr>
    <td class="mono">{{ $c->location_id }}</td><td>{{ $c->location_name ?? '—' }}</td>
    <td><x-badge :value="$c->status"/></td>
    <td style="max-width:220px">@foreach($c->scopes ?? [] as $scope)<span class="badge b-mute" style="margin:1px">{{ $scope }}</span>@endforeach</td>
    <td><x-badge :value="$c->tokenExpiryState()"/><div class="muted" style="font-size:11px">{{ $c->expires_at?->diffForHumans() }}</div></td>
    <td>{{ $c->installed_at?->toDateString() }}</td><td>{{ $c->last_success_at?->diffForHumans() ?? 'never' }}</td>
    <td style="max-width:260px">@if($c->last_error)<div class="err-txt" style="margin:0">{{ $c->last_error }}</div>@endif
      @foreach($c->recent_errors as $e)<div class="muted" style="font-size:11px">{{ $e->created_at->diffForHumans() }}: {{ \Illuminate\Support\Str::limit($e->message,70) }}</div>@endforeach
    </td>
    <td>@if($c->activeMapping) {{ $c->activeMapping->store->store_id }} <span class="muted">{{ $c->activeMapping->store->name }}</span> @else <x-badge value="unmapped"/> @endif</td>
  </tr>
  @empty<tr><td colspan="9" class="muted">No GHL location has authorized the app yet.</td>@endforelse
</table>
</div>
@endsection
