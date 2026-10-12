@extends('layouts.app')
@section('title','Integration Logs')
@section('content')
<h1>Integration Logs</h1>
<p class="sub">Structured and sanitized. Tokens and credentials are redacted; personal data is masked before storage.</p>
<form class="filters" method="get">
  <input name="q" value="{{ request('q') }}" placeholder="Search correlation id, store, location, message…" style="min-width:320px">
  <select name="level"><option value="">Any level</option>@foreach(['info','warning','error'] as $l)<option @selected(request('level')===$l)>{{ $l }}</option>@endforeach</select>
  <input name="stage" value="{{ request('stage') }}" placeholder="Stage prefix e.g. oauth, sync, api">
  <input name="outcome" value="{{ request('outcome') }}" placeholder="Outcome">
  <button class="btn primary">Search</button> <a class="btn" href="{{ route('admin.logs') }}">Reset</a>
</form>
<div class="card" style="overflow:auto"><table>
  <tr><th>Time</th><th>Correlation</th><th>Store / record</th><th>Stage</th><th>Location</th><th>Outcome</th><th>HTTP</th><th>Retry</th><th>Details</th></tr>
  @forelse($events as $e)
  <tr>
    <td style="white-space:nowrap">{{ $e->created_at?->format('m-d H:i:s') }}</td>
    <td class="mono" title="{{ $e->correlation_id }}"><a href="?q={{ $e->correlation_id }}">{{ substr($e->correlation_id,0,8) }}</a></td>
    <td class="mono">{{ $e->store_id }} {{ $e->source_record_id ? '#'.$e->source_record_id : '' }}</td>
    <td>{{ $e->stage }}</td>
    <td class="mono">{{ $e->ghl_location_id }}</td>
    <td>@if($e->outcome)<x-badge :value="$e->outcome"/>@endif</td>
    <td>{{ $e->http_status }}</td>
    <td>{{ $e->retry_count }}</td>
    <td style="max-width:380px">{{ $e->message }}@if($e->context)<details><summary class="muted" style="cursor:pointer">context</summary><pre>{{ json_encode($e->context, JSON_PRETTY_PRINT) }}</pre></details>@endif</td>
  </tr>
  @empty<tr><td colspan="9" class="muted">No events.</td></tr>@endforelse
</table></div>
<div class="pager">{{ $events->links() }}</div>
@endsection
