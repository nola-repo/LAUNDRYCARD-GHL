@extends('layouts.app')
@section('title','Integration Logs')
@section('content')

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
      Audit &amp; Telemetry
    </span>
    <h1>Integration Logs</h1>
    <p class="sub">Structured and sanitized event stream. Tokens and credentials are redacted; personal data is masked before storage.</p>
  </div>
</div>

<div class="filter-panel">
  <div class="filter-title">
    <h3>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filter Telemetry Events
    </h3>
    <span class="muted" style="font-size:12px">Click any correlation prefix in the table to isolate a single execution trace</span>
  </div>

  <form method="get" action="{{ route('admin.logs') }}">
    <div class="filter-grid">
      <div class="filter-group" style="grid-column: span 2">
        <label class="filter-label">
          <span>Search Query</span>
          <span class="filter-hint">Correlation / Message</span>
        </label>
        <input name="q" value="{{ request('q') }}" placeholder="Search correlation id, store, location, message…">
      </div>

      <div class="filter-group">
        <label class="filter-label">
          <span>Severity Level</span>
          <span class="filter-hint">Log Level</span>
        </label>
        <select name="level">
          <option value="">Any level</option>
          @foreach(['info','warning','error'] as $l)
            <option value="{{ $l }}" @selected(request('level')===$l)>{{ ucfirst($l) }}</option>
          @endforeach
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">
          <span>Stage Prefix</span>
          <span class="filter-hint">Subsystem</span>
        </label>
        <input name="stage" value="{{ request('stage') }}" placeholder="e.g. oauth, sync, api">
      </div>

      <div class="filter-group">
        <label class="filter-label">
          <span>Outcome</span>
          <span class="filter-hint">Result</span>
        </label>
        <input name="outcome" value="{{ request('outcome') }}" placeholder="e.g. ok, failed, skipped">
      </div>
    </div>

    <div class="filter-bar-footer">
      <div class="filter-buttons">
        <button class="btn primary" type="submit">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <span>Search Logs</span>
        </button>
        <a class="btn" href="{{ route('admin.logs') }}">Reset</a>
      </div>
      <div class="muted" style="font-size:12px">
        Showing <strong>{{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($events->total()) }}</strong> events
      </div>
    </div>
  </form>
</div>

<div class="table-card">
  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Time</th>
          <th>Correlation</th>
          <th>Store / Record</th>
          <th>Stage</th>
          <th>Location</th>
          <th>Outcome</th>
          <th>HTTP</th>
          <th>Attempts</th>
          <th>Details</th>
        </tr>
      </thead>
      <tbody>
        @forelse($events as $e)
        @php
          $stagePrefix = explode('.', $e->stage)[0] ?? '';
          $stageClass = match($stagePrefix) {
            'api' => 'stage-api',
            'oauth' => 'stage-oauth',
            'sync' => 'stage-sync',
            'eligibility' => 'stage-eligibility',
            default => '',
          };
        @endphp
        <tr>
          <td style="white-space:nowrap;font-weight:600;color:var(--text-title)">{{ $e->created_at?->format('m-d H:i:s') }}</td>
          <td title="{{ $e->correlation_id }}">
            @if($e->correlation_id)
              <a class="card-mono" href="?q={{ $e->correlation_id }}">{{ substr($e->correlation_id,0,8) }}</a>
            @else
              <span class="muted">—</span>
            @endif
          </td>
          <td class="mono">
            @if($e->store_id)
              <span class="store-id-pill">#{{ $e->store_id }}</span>
            @endif
            @if($e->source_record_id)
              <span class="muted" style="font-size:11px">rec:{{ $e->source_record_id }}</span>
            @endif
            @if(!$e->store_id && !$e->source_record_id)
              <span class="muted">—</span>
            @endif
          </td>
          <td><span class="terminal-stage-badge {{ $stageClass }}">{{ $e->stage }}</span></td>
          <td class="mono">{{ $e->ghl_location_id ? \Illuminate\Support\Str::limit($e->ghl_location_id, 12) : '—' }}</td>
          <td>@if($e->outcome)<x-badge :value="$e->outcome"/>@else<span class="muted">—</span>@endif</td>
          <td class="mono">
            @if($e->http_status)
              <span class="badge {{ $e->http_status < 400 ? 'b-ok' : 'b-bad' }}">{{ $e->http_status }}</span>
            @else
              <span class="muted">—</span>
            @endif
          </td>
          <td class="mono">{{ $e->retry_count ?: '—' }}</td>
          <td style="max-width:420px">
            <div style="color:var(--text-title);font-weight:500">{{ $e->message }}</div>
            @if($e->context)
              <details>
                <summary>Inspect payload context</summary>
                <pre>{{ json_encode($e->context, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
              </details>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9">
            <div class="empty-state-box">
              <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
              </div>
              <div class="empty-state-text">No integration events match your criteria</div>
              <p class="muted" style="font-size:12.5px;margin-top:4px">Try broadening your search filters or resetting the query.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pagination-wrapper">
  <div class="pager">{{ $events->links() }}</div>
</div>
@endsection
