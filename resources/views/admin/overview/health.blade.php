@php
  $stamp = fn ($date) => $date?->copy()->utc()->format('M j, Y · H:i T') ?? 'None recorded';
  $apiError = $lastLcApi?->http_status >= 400;
  $apiRecent = $lastLcApi?->created_at?->gt(now()->subHour());
  $apiLabel = match (true) {
      ! $lcConfigured => 'Missing credentials',
      $apiError => 'HTTP '.$lastLcApi->http_status,
      ! $lastLcApi => 'Awaiting first call',
      ! $apiRecent => 'Idle',
      ! $lastLcApi->http_status => 'Status unknown',
      default => 'Responding',
  };
  $apiTone = (! $lcConfigured || $apiError) ? 'bad' : ($apiLabel === 'Responding' ? 'ok' : 'mute');
  $locations = $stats['locations'];
  $active = $stats['locations_active'];
  $runStatus = $lastRun?->status;
  $runTone = match ($runStatus) {
      'completed' => 'ok', 'failed' => 'bad', 'partial' => 'warn', 'running' => 'info', default => 'mute',
  };
@endphp
<article class="ov-health-card" data-key="source">
  <div class="ov-health-card-top"><span class="ov-service">LaundryCard API</span><span class="badge b-{{ $apiTone }}">{{ $apiLabel }}</span></div>
  <p class="ov-health-hint">{{ parse_url(config('integration.laundrycard.base_url'), PHP_URL_HOST) ?? 'Host not configured' }}</p>
  <button class="ov-row-details" type="button" data-detail-open data-detail-title="LaundryCard API" data-detail-summary="Connection health and API configuration." aria-label="Open details for LaundryCard API" title="View service details" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></button>
  <template class="ov-detail-template"><dl class="ov-detail-list">
    <div><dt>Host</dt><dd>{{ parse_url(config('integration.laundrycard.base_url'), PHP_URL_HOST) ?? 'Unconfigured' }}</dd></div>
    <div><dt>Last call</dt><dd>{{ $stamp($lastLcApi?->created_at) }}</dd></div>
    <div><dt>Last success</dt><dd>{{ $stamp($lastLcApiSuccess?->created_at) }}</dd></div>
    <div><dt>POS machine</dt><dd>{{ config('integration.laundrycard.pos_mach_no') ? 'Configured' : 'Global' }}</dd></div>
  </dl></template>
</article>
<article class="ov-health-card" data-key="destination">
  <div class="ov-health-card-top"><span class="ov-service">GoHighLevel OAuth</span><span class="badge b-{{ !$locations ? 'mute' : ($active === $locations ? 'ok' : 'warn') }}">{{ $locations ? "$active of $locations active" : 'No installations' }}</span></div>
  <p class="ov-health-hint">{{ number_format($stats['unassigned_locations']) }} unassigned locations</p>
  <button class="ov-row-details" type="button" data-detail-open data-detail-title="GoHighLevel OAuth" data-detail-summary="Installed locations and token settings." aria-label="Open details for GoHighLevel OAuth" title="View service details" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></button>
  <template class="ov-detail-template"><dl class="ov-detail-list">
    <div><dt>Installed locations</dt><dd data-stat="locations">{{ number_format($locations) }}</dd></div>
    <div><dt>Active locations</dt><dd>{{ number_format($active) }}</dd></div>
    <div><dt>Unassigned locations</dt><dd>{{ number_format($stats['unassigned_locations']) }}</dd></div>
    <div><dt>Token refresh leeway</dt><dd>{{ config('integration.ghl.refresh_leeway') }} s</dd></div>
  </dl></template>
</article>
<article class="ov-health-card" data-key="discovery">
  <div class="ov-health-card-top"><span class="ov-service">Card discovery</span><span class="badge b-{{ $runTone }}">{{ $runStatus ? ucfirst($runStatus) : 'Awaiting first run' }}</span></div>
  <p class="ov-health-hint">Every {{ config('integration.discovery.schedule_every_minutes') }} min · {{ $stamp($lastRun?->started_at) }}</p>
  <button class="ov-row-details" type="button" data-detail-open data-detail-title="Card discovery" data-detail-summary="Schedule and latest discovery run." aria-label="Open details for card discovery" title="View service details" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></button>
  <template class="ov-detail-template"><dl class="ov-detail-list">
    <div><dt>Schedule</dt><dd>Every {{ config('integration.discovery.schedule_every_minutes') }} min</dd></div>
    <div><dt>Last start</dt><dd>{{ $stamp($lastRun?->started_at) }}</dd></div>
    <div><dt>Next expected</dt><dd>{{ $nextScheduledRun ? $stamp($nextScheduledRun) : 'Awaiting first run' }}</dd></div>
    <div><dt>Last completed live scan</dt><dd>{{ $stamp($lastDiscovery?->finished_at) }} @if($lastDiscovery) ({{ $lastDiscovery->status }}) @endif</dd></div>
    <div><dt>Initial import</dt><dd>{{ config('integration.discovery.initial_import') === 'skip' ? 'Skip historical baseline' : 'Full import' }}</dd></div>
    @if($lastRunDuration !== null)<div><dt>Duration</dt><dd>{{ $lastRunDuration }} s</dd></div>@endif
    @if($lastRun?->stats)<div><dt>Last scan</dt><dd>{{ $lastRun->stats['cards_seen'] ?? 0 }} cards seen · {{ $lastRun->stats['new_records'] ?? 0 }} new · {{ $lastRun->stats['stores_scanned'] ?? 0 }} stores</dd></div>@endif
    @if($lastRun?->error)<div><dt>Last error</dt><dd>{{ $lastRun->error }}</dd></div>@endif
  </dl></template>
</article>
<article class="ov-health-card" data-key="sync">
  <div class="ov-health-card-top"><span class="ov-service">Contact writes</span><span class="badge b-{{ $syncEnabled ? 'ok' : 'warn' }}">{{ $syncEnabled ? 'Enabled' : 'Paused' }}</span></div>
  <p class="ov-health-hint">{{ number_format($stats['eligible_pending']) }} eligible records waiting</p>
  <button class="ov-row-details" type="button" data-detail-open data-detail-title="Contact writes" data-detail-summary="Latest sync activity and pending records." aria-label="Open details for contact writes" title="View service details" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></button>
  <template class="ov-detail-template"><dl class="ov-detail-list">
    <div><dt>Contact writes</dt><dd>{{ $syncEnabled ? 'Enabled' : 'Paused' }}</dd></div>
    <div><dt>Last contact sync</dt><dd>{{ $lastSync ? $stamp(\Illuminate\Support\Carbon::parse($lastSync)) : 'None recorded' }}</dd></div>
    <div><dt>Eligible and waiting</dt><dd>{{ number_format($stats['eligible_pending']) }}</dd></div>
    <div><dt>Failed sync attempts</dt><dd>{{ number_format($stats['failed_attempts']) }}</dd></div>
  </dl></template>
</article>
