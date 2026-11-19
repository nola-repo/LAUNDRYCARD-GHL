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
<details data-key="source">
  <summary><span class="ov-service">LaundryCard API</span><span class="badge b-{{ $apiTone }}">{{ $apiLabel }}</span></summary>
  <dl class="ov-diagnostics">
    <div><dt>Host</dt><dd>{{ parse_url(config('integration.laundrycard.base_url'), PHP_URL_HOST) ?? 'Unconfigured' }}</dd></div>
    <div><dt>Last call</dt><dd>{{ $stamp($lastLcApi?->created_at) }}</dd></div>
    <div><dt>Last success</dt><dd>{{ $stamp($lastLcApiSuccess?->created_at) }}</dd></div>
    <div><dt>POS machine</dt><dd>{{ config('integration.laundrycard.pos_mach_no') ? 'Configured' : 'Global' }}</dd></div>
  </dl>
</details>
<details data-key="destination">
  <summary><span class="ov-service">GoHighLevel OAuth</span><span class="badge b-{{ !$locations ? 'mute' : ($active === $locations ? 'ok' : 'warn') }}">{{ $locations ? "$active of $locations active" : 'No installations' }}</span></summary>
  <dl class="ov-diagnostics">
    <div><dt>Installed locations</dt><dd data-stat="locations">{{ number_format($locations) }}</dd></div>
    <div><dt>Unassigned locations</dt><dd>{{ number_format($stats['unassigned_locations']) }}</dd></div>
    <div><dt>Token refresh leeway</dt><dd>{{ config('integration.ghl.refresh_leeway') }} s</dd></div>
  </dl>
</details>
<details data-key="discovery">
  <summary><span class="ov-service">Card discovery</span><span class="badge b-{{ $runTone }}">{{ $runStatus ? ucfirst($runStatus) : 'Awaiting first run' }}</span></summary>
  <dl class="ov-diagnostics">
    <div><dt>Schedule</dt><dd>Every {{ config('integration.discovery.schedule_every_minutes') }} min</dd></div>
    <div><dt>Last start</dt><dd>{{ $stamp($lastRun?->started_at) }}</dd></div>
    <div><dt>Next expected</dt><dd>{{ $nextScheduledRun ? $stamp($nextScheduledRun) : 'Awaiting first run' }}</dd></div>
    <div><dt>Last completed live scan</dt><dd>{{ $stamp($lastDiscovery?->finished_at) }} @if($lastDiscovery) ({{ $lastDiscovery->status }}) @endif</dd></div>
    <div><dt>Initial import</dt><dd>{{ config('integration.discovery.initial_import') === 'skip' ? 'Skip historical baseline' : 'Full import' }}</dd></div>
    @if($lastRunDuration !== null)<div><dt>Duration</dt><dd>{{ $lastRunDuration }} s</dd></div>@endif
    @if($lastRun?->stats)<div><dt>Last scan</dt><dd>{{ $lastRun->stats['cards_seen'] ?? 0 }} cards seen · {{ $lastRun->stats['new_records'] ?? 0 }} new · {{ $lastRun->stats['stores_scanned'] ?? 0 }} stores</dd></div>@endif
    @if($lastRun?->error)<div><dt>Last error</dt><dd>{{ $lastRun->error }}</dd></div>@endif
  </dl>
</details>
<details data-key="sync">
  <summary><span class="ov-service">Contact writes</span><span class="badge b-{{ $syncEnabled ? 'ok' : 'warn' }}">{{ $syncEnabled ? 'Enabled' : 'Paused' }}</span></summary>
  <dl class="ov-diagnostics">
    <div><dt>Last contact sync</dt><dd>{{ $lastSync ? $stamp(\Illuminate\Support\Carbon::parse($lastSync)) : 'None recorded' }}</dd></div>
    <div><dt>Eligible and waiting</dt><dd>{{ number_format($stats['eligible_pending']) }}</dd></div>
    <div><dt>Failed sync attempts</dt><dd>{{ number_format($stats['failed_attempts']) }}</dd></div>
  </dl>
</details>
