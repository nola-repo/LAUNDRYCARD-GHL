@extends('layouts.app')
@section('title','API Activity')
@section('content')

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
      System Diagnostics
    </span>
    <h1>API Activity</h1>
    <p class="sub">Persisted request outcomes, discovery executions, and read-only preflight inspection tools. Admin access is monitoring only.</p>
  </div>

  <div class="btn-row">
    <form method="POST" action="{{ route('admin.developer.sync-preview') }}" style="margin:0">
      @csrf
      <button type="submit" class="btn primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span>Sync Preflight Check</span>
      </button>
    </form>

    <form method="POST" action="{{ route('admin.developer.sync-dry-run') }}" style="margin:0">
      @csrf
      <input type="hidden" name="limit" value="10">
      <button type="submit" class="btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
        <span>Simulate Dry-Run (10)</span>
      </button>
    </form>

    <form method="POST" action="{{ route('admin.developer.config-diagnostics') }}" style="margin:0">
      @csrf
      <button type="submit" class="btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        <span>Config Diagnostics</span>
      </button>
    </form>
  </div>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        Recent runs
      </h2>
      <span class="muted" style="font-size:12px">Latest scheduled and background synchronization batches</span>
    </div>
    <span class="badge b-info">Last {{ $runs->count() }} Executions</span>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Started</th>
          <th>Type</th>
          <th>Trigger</th>
          <th>Mode</th>
          <th>Status</th>
          <th>Summary</th>
        </tr>
      </thead>
      <tbody>
        @forelse($runs as $run)
        <tr>
          <td style="font-weight:600;color:var(--text-title);white-space:nowrap">{{ $run->started_at?->toDayDateTimeString() }}</td>
          <td><span class="card-mono">{{ $run->type }}</span></td>
          <td><span class="tag-chip">{{ $run->trigger }}</span></td>
          <td><x-badge :value="$run->mode"/></td>
          <td><x-badge :value="$run->status"/></td>
          <td class="mono" style="font-size:11.5px;max-width:460px;color:var(--text-muted)">
            {{ \Illuminate\Support\Str::limit($run->error ?? json_encode(collect($run->stats ?? [])->except('stores')->all()), 180) }}
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="muted" style="text-align:center;padding:28px">No runs recorded.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--accent-cyan)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
        Sanitized request outcomes
      </h2>
      <span class="muted" style="font-size:12px">Request bodies and authorization headers are never stored</span>
    </div>
    <span class="badge b-ok">Redacted Stream</span>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Time</th>
          <th>System</th>
          <th>Request</th>
          <th>HTTP</th>
          <th>Duration</th>
          <th>Location / Store</th>
        </tr>
      </thead>
      <tbody>
        @forelse($diagnostics as $event)
        <tr>
          <td style="white-space:nowrap;font-weight:600;color:var(--text-title)">{{ $event->created_at->format('m-d H:i:s') }}</td>
          <td><span class="terminal-stage-badge stage-api">{{ str_replace('api.','',$event->stage) }}</span></td>
          <td class="mono" style="color:var(--text-title)">{{ $event->message }}</td>
          <td>
            @if($event->http_status)
              <span class="badge {{ $event->http_status < 400 ? 'b-ok' : 'b-bad' }}">{{ $event->http_status }}</span>
            @else
              <span class="badge b-warn">timeout</span>
            @endif
          </td>
          <td class="mono">
            @if(isset($event->context['duration_ms']))
              {{ $event->context['duration_ms'] }} ms
            @else
              <span class="muted">—</span>
            @endif
          </td>
          <td class="mono">
            @if($event->ghl_location_id)
              <span class="card-mono">{{ $event->ghl_location_id }}</span>
            @elseif($event->store_id)
              <span class="store-id-pill">Store {{ $event->store_id }}</span>
            @else
              <span class="muted">—</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="6" class="muted" style="text-align:center;padding:28px">No API calls recorded yet.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
