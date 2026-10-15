@extends('layouts.app')
@section('title','API Activity')
@section('content')
<h1>API Activity</h1>
<p class="sub">Persisted request outcomes and discovery runs. Admin access is monitoring only.</p>

<div class="card" style="overflow:auto"><h2>Recent runs</h2>
<table><tr><th>Started</th><th>Type</th><th>Trigger</th><th>Mode</th><th>Status</th><th>Summary</th></tr>
@forelse($runs as $run)
<tr><td>{{ $run->started_at?->toDayDateTimeString() }}</td><td>{{ $run->type }}</td><td>{{ $run->trigger }}</td><td>{{ $run->mode }}</td><td><x-badge :value="$run->status"/></td><td>{{ \Illuminate\Support\Str::limit($run->error ?? json_encode(collect($run->stats ?? [])->except('stores')->all()), 180) }}</td></tr>
@empty<tr><td colspan="6" class="muted">No runs recorded.</td>@endforelse</table>
</div>

<div class="card" style="overflow:auto"><h2>Sanitized request outcomes</h2>
<table><tr><th>Time</th><th>System</th><th>Request</th><th>HTTP</th><th>ms</th><th>Location / store</th></tr>
@forelse($diagnostics as $event)
<tr><td>{{ $event->created_at->format('m-d H:i:s') }}</td><td>{{ str_replace('api.','',$event->stage) }}</td><td class="mono">{{ $event->message }}</td><td>{{ $event->http_status ?? 'timeout' }}</td><td>{{ $event->context['duration_ms'] ?? '' }}</td><td class="mono">{{ $event->ghl_location_id ?? $event->store_id }}</td></tr>
@empty<tr><td colspan="6" class="muted">No API calls recorded yet.</td>@endforelse</table>
<p class="muted" style="font-size:12px">Request bodies and authorization headers are not stored.</p></div>
@endsection
