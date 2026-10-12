@extends('layouts.app')
@section('title','Developer Controls')
@section('content')
<h1>Developer Controls</h1>
<p class="sub">Operational tools. Results appear at the top of the page and are sanitized.</p>

<div class="grid g2">
  <div class="card"><h2>LaundryCard</h2>
    <div class="inline">
      <form method="post" action="{{ route('admin.dev.test-laundrycard') }}">@csrf<button id="btn-test-lc" class="btn primary">Test API connection</button></form>
      <form method="post" action="{{ route('admin.dev.refresh-stores') }}">@csrf<button id="btn-refresh-stores" class="btn">Refresh store list</button></form>
      <form method="post" action="{{ route('admin.dev.scan') }}">@csrf<button id="btn-scan" class="btn">Run read-only discovery scan</button></form>
    </div>
    <p class="muted" style="font-size:12px">Read-only scan persists nothing, queues nothing and never contacts GHL.</p>
  </div>
  <div class="card"><h2>GHL OAuth</h2>
    <p class="muted">Test each connection from <a href="{{ route('admin.installations') }}">OAuth Installations</a>.</p>
  </div>
  <div class="card"><h2>Preview customer routing</h2>
    <form class="inline" method="post" action="{{ route('admin.dev.routing-preview') }}">@csrf
      <select name="store_id" required><option value="">Store…</option>@foreach($stores as $s)<option value="{{ $s->store_id }}">{{ $s->store_id }} {{ $s->name }}</option>@endforeach</select>
      <button id="btn-preview" class="btn">Preview decision</button></form>
  </div>
  <div class="card"><h2>Process an approved test record</h2>
    <form class="inline" method="post" action="{{ route('admin.dev.process-test') }}">@csrf
      <select name="record_id" required style="max-width:260px"><option value="">Record…</option>@foreach($testable as $r)<option value="{{ $r->id }}">{{ $r->store->store_id }} · {{ $r->card_number }} · {{ $r->processing_status }}</option>@endforeach</select>
      <label class="chk"><input type="checkbox" name="confirm" value="1" required> I approve sending this customer to GHL</label>
      <button id="btn-process" class="btn danger">Process now</button></form>
    <p class="muted" style="font-size:12px">Still requires a verified mapping; otherwise the record is held.</p>
  </div>
</div>

<div class="card" style="overflow:auto"><h2>Sanitized request/response diagnostics (latest 30)</h2>
<table><tr><th>Time</th><th>System</th><th>Request</th><th>HTTP</th><th>ms</th><th>Location / store</th></tr>
@forelse($diagnostics as $d)<tr><td>{{ $d->created_at->format('m-d H:i:s') }}</td><td>{{ str_replace('api.','',$d->stage) }}</td><td class="mono">{{ $d->message }}</td><td>{{ $d->http_status ?? 'timeout' }}</td><td>{{ $d->context['duration_ms'] ?? '' }}</td><td class="mono">{{ $d->ghl_location_id ?? $d->store_id }}</td></tr>
@empty<tr><td colspan="6" class="muted">No API calls recorded yet.</td></tr>@endforelse</table>
<p class="muted" style="font-size:12px">Bodies are never stored: only method, path template, status and timing.</p></div>
@endsection
