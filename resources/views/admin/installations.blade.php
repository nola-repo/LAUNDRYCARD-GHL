@extends('layouts.app')
@section('title','OAuth Installations')
@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-end">
  <div><h1>OAuth Installations</h1><p class="sub">Authorized GoHighLevel locations. Tokens are encrypted and never displayed.</p></div>
  <form method="post" action="{{ route('admin.connect') }}">@csrf<button id="connect-location" class="btn primary">+ Connect a location</button></form>
</div>
<div class="card" style="overflow:auto">
<table>
  <tr><th>Location ID</th><th>Name</th><th>Status</th><th>Scopes</th><th>Token</th><th>Installed</th><th>Last API success</th><th>Recent errors</th><th>Mapped to</th><th></th></tr>
  @forelse($connections as $c)
  <tr>
    <td class="mono">{{ $c->location_id }}</td>
    <td>{{ $c->location_name ?? '—' }}</td>
    <td><x-badge :value="$c->status"/>@if($c->install_count>1)<div class="muted" style="font-size:11px">reinstalled ×{{ $c->install_count-1 }}</div>@endif</td>
    <td style="max-width:220px">@foreach($c->scopes ?? [] as $s)<span class="badge b-mute" style="margin:1px">{{ $s }}</span>@endforeach</td>
    <td><x-badge :value="$c->tokenExpiryState()"/><div class="muted" style="font-size:11px">{{ $c->expires_at?->diffForHumans() }}</div></td>
    <td>{{ $c->installed_at?->toDateString() }}</td>
    <td>{{ $c->last_success_at?->diffForHumans() ?? 'never' }}</td>
    <td style="max-width:260px">
      @if($c->last_error)<div class="err-txt" style="margin:0">{{ $c->last_error }}</div>@endif
      @foreach($c->recent_errors as $e)<div class="muted" style="font-size:11px">{{ $e->created_at->diffForHumans() }}: {{ \Illuminate\Support\Str::limit($e->message,70) }}</div>@endforeach
    </td>
    <td>@if($c->activeMapping) {{ $c->activeMapping->store->store_id }} <span class="muted">{{ $c->activeMapping->store->name }}</span> @else <x-badge value="unmapped"/> @endif</td>
    <td class="inline">
      <form method="post" action="{{ route('admin.connection.test',$c) }}">@csrf<button class="btn sm">Test</button></form>
      @if($c->status!=='disconnected')
      <form method="post" action="{{ route('admin.connection.disconnect',$c) }}" onsubmit="return confirm('Disconnect this location locally?')">@csrf<input type="hidden" name="confirm" value="1"><button class="btn sm danger">Disconnect</button></form>
      @endif
    </td>
  </tr>
  @empty <tr><td colspan="10" class="muted">No GHL location has authorized the app yet.</td></tr> @endforelse
</table>
</div>
@endsection
