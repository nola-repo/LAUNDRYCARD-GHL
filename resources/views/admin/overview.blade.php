@extends('layouts.app')
@section('title','Overview')
@section('content')
<h1>Overview</h1>
<p class="sub">Live state from the database and recorded API outcomes.</p>
<p class="muted">Customer contact writes: <strong>{{ $syncEnabled ? 'enabled' : 'disabled pending external verification' }}</strong> · LaundryCard discovery interval: {{ config('integration.discovery.schedule_every_minutes') }} minutes.</p>
<p class="muted" id="dashboard-live-state" data-url="{{ route('admin.monitoring.updates') }}">Connecting to persisted activity…</p>

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
  <div class="card stat"><div class="l">Discovered branches</div><div class="n" data-stat="branches">{{ $stats['branches'] }}</div></div>
  <div class="card stat"><div class="l">Authorized GHL locations</div><div class="n" data-stat="locations">{{ $stats['locations'] }}</div><div class="muted" style="font-size:12px">{{ $stats['locations_active'] }} active · {{ $stats['unassigned_locations'] }} unassigned</div></div>
  <div class="card stat ok"><div class="l">Verified mappings</div><div class="n" data-stat="mappings_verified">{{ $stats['mappings_verified'] }}</div></div>
  <div class="card stat {{ $stats['unmapped'] ? 'warn':'' }}"><div class="l">Unmapped branches</div><div class="n" data-stat="unmapped">{{ $stats['unmapped'] }}</div></div>
  <div class="card stat"><div class="l">Discovered customer records</div><div class="n" data-stat="records">{{ $stats['records'] }}</div><div class="muted" style="font-size:12px">{{ $stats['baseline'] }} baseline (not imported)</div></div>
  <div class="card stat ok"><div class="l">New LaundryCard customers detected</div><div class="n" data-stat="new_customers">{{ $stats['new_customers'] }}</div><div class="muted" style="font-size:12px">First observed after that store’s baseline</div></div>
  <div class="card stat"><div class="l">Positive / zero balance</div><div class="n"><span data-stat="positive_balance">—</span> / <span data-stat="zero_balance">—</span></div></div>
  <div class="card stat"><div class="l">Awaiting balance verification</div><div class="n" data-stat="balance_unknown">—</div></div>
  <div class="card stat"><div class="l">Eligible awaiting sync</div><div class="n" data-stat="eligible_pending">—</div></div>
  <div class="card stat ok"><div class="l">Contacts created / updated</div><div class="n"><span data-stat="created">{{ $stats['created'] }}</span> / <span data-stat="updated">{{ $stats['updated'] }}</span></div></div>
  <div class="card stat {{ $stats['held'] ? 'warn':'' }}"><div class="l">Held / skipped</div><div class="n">{{ $stats['held'] }} / {{ $stats['skipped'] }}</div></div>
  <div class="card stat {{ $stats['failed'] ? 'bad':'' }}"><div class="l">Failed records</div><div class="n">{{ $stats['failed'] }}</div><div class="muted" style="font-size:12px">{{ $stats['failed_attempts'] }} failed attempts total</div></div>
</div>

<div class="card" style="overflow:auto"><h2>Recently detected from LaundryCard</h2>
  <p class="muted">New cards first seen after the store’s baseline. Detection and balance eligibility are shown here independently of contact sync.</p>
  <table id="dashboard-new-customers">
    <tr><th>Store</th><th>Card</th><th>Customer</th><th>Balance</th><th>Eligibility</th><th>First observed</th></tr>
    <tbody id="dashboard-new-customer-rows">
    @forelse($latestNewCustomers as $r)
      <tr>
        <td class="mono">{{ $r->store->store_id }}</td>
        <td class="mono">{{ substr($r->card_number,0,2) }}••••{{ substr($r->card_number,-2) }}</td>
        <td>{{ $r->customer_name ? \Illuminate\Support\Str::limit($r->customer_name,1,'').'***' : '—' }}<div class="muted" style="font-size:11px">{{ \App\Support\Sanitizer::maskEmail($r->customer_email) }} {{ \App\Support\Sanitizer::maskPhone($r->customer_phone) }}</div></td>
        <td>{{ $r->balance !== null ? '$'.number_format((float)$r->balance,2) : 'unknown' }}</td>
        <td><x-badge :value="$r->eligibility_status"/></td>
        <td>{{ $r->first_observed_at?->toDayDateTimeString() ?? '—' }}</td>
      </tr>
    @empty
      <tr class="empty-new-customers"><td colspan="6" class="muted">No new LaundryCard customers detected yet.</td></tr>
    @endforelse
    </tbody>
  </table>
  <p style="margin-bottom:0"><a class="btn" href="{{ route('admin.records') }}">View customer records</a></p>
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
<div class="card" style="overflow:auto"><h2>Persisted integration activity</h2>
  <table id="dashboard-events" data-cursor="{{ $recentEvents->max('id') ?? 0 }}">
    <tr><th>When</th><th>Stage</th><th>Outcome</th><th>Store</th><th>GHL location</th><th>Details</th></tr>
    @forelse($recentEvents as $event)
      <tr><td>{{ $event->created_at?->toDateTimeString() }}</td><td>{{ $event->stage }}</td><td>{{ $event->outcome ?? '—' }}</td><td>{{ $event->store_id ?? '—' }}</td><td>{{ $event->ghl_location_id ?? '—' }}</td><td>{{ $event->message }}</td></tr>
    @empty<tr class="empty-events"><td colspan="6" class="muted">No persisted activity yet.</td></tr>@endforelse
  </table>
</div>
<script>
(() => {
  const state = document.getElementById('dashboard-live-state');
  if (!state) return;
  const table = document.getElementById('dashboard-events');
  const newCustomerRows = document.getElementById('dashboard-new-customer-rows');
  let cursor = Number(table?.dataset.cursor || 0);
  const poll = async () => {
    try {
      const response = await fetch(`${state.dataset.url}?after=${cursor}`, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      cursor = data.last_event_id || cursor;
      if (table && data.events?.length) {
        table.querySelector('.empty-events')?.remove();
        for (const event of [...data.events].reverse()) {
          const row = table.insertRow(1);
          const values = [new Date(event.created_at).toLocaleString(), event.stage, event.outcome || '—', event.store_id || '—', event.ghl_location_id || '—', event.message || ''];
          for (const value of values) { const cell = row.insertCell(); cell.textContent = value; }
        }
        while (table.rows.length > 21) table.deleteRow(-1);
      }
      for (const [key, value] of Object.entries(data.stats || {})) {
        const target = document.querySelector(`[data-stat="${key}"]`);
        if (target && value !== null && typeof value !== 'object') target.textContent = value;
      }
      if (newCustomerRows && Array.isArray(data.new_customers)) {
        newCustomerRows.replaceChildren();
        if (!data.new_customers.length) {
          const row = newCustomerRows.insertRow();
          const cell = row.insertCell();
          cell.colSpan = 6;
          cell.className = 'muted';
          cell.textContent = 'No new LaundryCard customers detected yet.';
        } else {
          for (const customer of data.new_customers) {
            const row = newCustomerRows.insertRow();
            const values = [customer.store_id, customer.card_number, `${customer.customer_name} ${customer.customer_email || ''} ${customer.customer_phone || ''}`.trim(), customer.balance, customer.eligibility, customer.first_observed_at ? new Date(customer.first_observed_at).toLocaleString() : '—'];
            for (const value of values) {
              const cell = row.insertCell();
              cell.textContent = value || '—';
            }
          }
        }
      }
      state.textContent = `Dashboard feed ${data.connection_state}; checked ${new Date(data.updated_at).toLocaleTimeString()} · polling every ${data.poll_interval_seconds}s · LaundryCard discovery runs on its configured schedule.`;
      state.dataset.connection = 'connected';
    } catch (error) {
      state.textContent = `Dashboard feed disconnected; retrying automatically. ${new Date().toLocaleTimeString()}`;
      state.dataset.connection = 'disconnected';
    }
  };
  poll();
  window.setInterval(poll, 4000);
})();
</script>
@endsection
