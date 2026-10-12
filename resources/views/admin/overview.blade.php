@extends('layouts.app')
@section('title','Overview')
@section('content')
<h1>Overview</h1>
<p class="sub">Live state from the database and recorded API outcomes.</p>

<div class="grid g4">
  <div class="card stat"><div class="l">LaundryCard health</div>
    <div class="n" style="font-size:18px;margin-top:6px">
      @if(!$lcConfigured)<span class="dot bad"></span>Credentials missing
      @elseif($lastRun && in_array($lastRun->status,['completed','partial']))<span class="dot ok"></span>Last scan {{ $lastRun->status }}
      @elseif($lastRun)<span class="dot bad"></span>Last scan {{ $lastRun->status }}
      @else<span class="dot warn"></span>Not yet verified @endif
    </div>
    <div class="muted" style="font-size:12px;margin-top:6px">Last API call: {{ $lastLcApi? $lastLcApi->created_at->diffForHumans().' (HTTP '.($lastLcApi->http_status ?? 'n/a').')' : 'never' }}</div>
  </div>
  <div class="card stat"><div class="l">Discovered branches</div><div class="n">{{ $stats['branches'] }}</div></div>
  <div class="card stat"><div class="l">Authorized GHL locations</div><div class="n">{{ $stats['locations'] }}</div><div class="muted" style="font-size:12px">{{ $stats['locations_active'] }} active · {{ $stats['unassigned_locations'] }} unassigned</div></div>
  <div class="card stat ok"><div class="l">Verified mappings</div><div class="n">{{ $stats['mappings_verified'] }}</div></div>
  <div class="card stat {{ $stats['unmapped'] ? 'warn':'' }}"><div class="l">Unmapped branches</div><div class="n">{{ $stats['unmapped'] }}</div></div>
  <div class="card stat"><div class="l">Discovered customer records</div><div class="n">{{ $stats['records'] }}</div><div class="muted" style="font-size:12px">{{ $stats['baseline'] }} baseline (not imported)</div></div>
  <div class="card stat ok"><div class="l">Contacts created / updated</div><div class="n">{{ $stats['created'] }} / {{ $stats['updated'] }}</div></div>
  <div class="card stat {{ $stats['held'] ? 'warn':'' }}"><div class="l">Held / skipped</div><div class="n">{{ $stats['held'] }} / {{ $stats['skipped'] }}</div></div>
  <div class="card stat {{ $stats['failed'] ? 'bad':'' }}"><div class="l">Failed records</div><div class="n">{{ $stats['failed'] }}</div><div class="muted" style="font-size:12px">{{ $stats['failed_attempts'] }} failed attempts total</div></div>
</div>

<div class="grid g2">
  <div class="card"><h2>Last successful discovery</h2>
    <div>{{ $lastDiscovery?->finished_at?->toDayDateTimeString() ?? 'never' }}</div>
    <h2 style="margin-top:14px">Last successful synchronization</h2>
    <div>{{ $lastSync ? \Illuminate\Support\Carbon::parse($lastSync)->toDayDateTimeString() : 'never' }}</div>
  </div>
  <div class="card"><h2>Recent runs</h2>
    <table><tr><th>When</th><th>Type</th><th>Mode</th><th>Status</th></tr>
    @forelse($recentRuns as $r)
      <tr><td>{{ $r->started_at?->diffForHumans() }}</td><td>{{ $r->type }}</td><td><x-badge :value="$r->mode"/></td><td><x-badge :value="$r->status"/></td></tr>
    @empty <tr><td colspan="4" class="muted">No runs yet.</td></tr> @endforelse
    </table>
  </div>
</div>
@endsection
