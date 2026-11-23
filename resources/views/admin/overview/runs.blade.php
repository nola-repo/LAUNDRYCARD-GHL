@php
  $previousDay = null;
@endphp
@forelse($recentRuns as $run)
  @php
    $job = ucfirst(str_replace('_', ' ', $run->type));
    $mode = ucfirst(str_replace('_', ' ', $run->mode));
    $start = $run->started_at?->copy()->utc();
    $day = $start?->format('M j, Y') ?? 'Start date unavailable';
    $showDay = $day !== $previousDay;
    $previousDay = $day;
  @endphp
  <li data-key="{{ $run->id }}">
    @if($showDay)
      <p class="ov-run-day">{{ $day }}</p>
    @endif
    <div class="ov-run-entry">
      <strong class="ov-run-job">{{ $job }}</strong>
      <div class="ov-run-meta">
        @if($start)
          <time class="ov-run-time" datetime="{{ $start->toIso8601String() }}">{{ $start->format('H:i') }}</time>
        @else
          <span class="ov-run-time">Time unavailable</span>
        @endif
        <span class="ov-run-mode">{{ $mode }}</span>
      </div>
      <span class="ov-run-status"><x-badge :value="$run->status" /></span>
      <button class="ov-row-details" type="button" data-detail-open data-detail-title="{{ $job }}"
        data-detail-summary="{{ $mode }} integration run · #{{ $run->id }}"
        aria-label="Open details for {{ $job }}, run {{ $run->id }}" title="View run details"
        aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden>
        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
      </button>
      <template class="ov-detail-template"><dl class="ov-detail-list">
        <div><dt>Job</dt><dd>{{ $job }}</dd></div>
        <div><dt>Mode</dt><dd>{{ $mode }}</dd></div>
        <div><dt>Status</dt><dd>{{ ucfirst($run->status) }}</dd></div>
        <div><dt>Trigger</dt><dd>{{ ucfirst($run->trigger) }}</dd></div>
        <div><dt>Started (UTC)</dt><dd>{{ $start?->format('M j, Y · H:i:s') ?? 'Not recorded' }}</dd></div>
        <div><dt>Finished (UTC)</dt><dd>{{ $run->finished_at?->copy()->utc()->format('M j, Y · H:i:s') ?? ($run->status === 'running' ? 'Still running' : 'Not recorded') }}</dd></div>
      </dl></template>
    </div>
  </li>
@empty
  <li data-key="empty" class="ov-empty">No integration runs recorded yet.</li>
@endforelse
