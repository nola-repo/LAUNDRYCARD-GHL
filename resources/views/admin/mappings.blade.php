@extends('layouts.app')
@section('title','Branch Mapping')
@section('content')
<h1>Branch Mapping</h1>
<p class="sub">Routing uses persisted Store ID → verified Location ID only. Name similarity is a suggestion for you to review, never applied automatically.</p>

<div class="card" style="overflow:auto">
<table>
  <tr><th>Store ID</th><th>Branch</th><th>GHL Location ID</th><th>GHL location</th><th>Mapping</th><th>OAuth</th><th>Last sync</th><th>Verification</th><th>Actions</th></tr>
  @forelse($rows as $r)
    @php $m = $r['verified'] ?? $r['proposed']; @endphp
    <tr>
      <td class="mono">{{ $r['store']->store_id }}@if($r['store']->status==='missing') <x-badge value="missing"/>@endif</td>
      <td>{{ $r['store']->name }}</td>
      <td class="mono">{{ $m?->connection->location_id ?? '—' }}</td>
      <td>{{ $m?->connection->location_name ?? '—' }}</td>
      <td>@if($m)<x-badge :value="$m->status"/>@else<x-badge value="unmapped"/>@endif</td>
      <td>@if($m)<x-badge :value="$m->connection->status"/>@else — @endif</td>
      <td>{{ $m?->last_synced_at?->diffForHumans() ?? '—' }}</td>
      <td>@if($r['verified'])<span class="muted" style="font-size:12px">by {{ $r['verified']->verifier->email ?? 'system' }}<br>{{ $r['verified']->verified_at?->toDateString() }}</span>@elseif($r['proposed'])<span class="muted">awaiting verification</span>@else — @endif</td>
      <td>
        @if($r['verified'])
          <form class="inline" method="post" action="{{ route('admin.mappings.disable',$r['verified']) }}">@csrf
            <label class="chk"><input type="checkbox" name="confirm" value="1" required> confirm</label><button class="btn sm danger">Disable</button></form>
        @elseif($r['proposed'])
          <form class="inline" method="post" action="{{ route('admin.mappings.verify',$r['proposed']) }}">@csrf
            <label class="chk"><input type="checkbox" name="confirm" value="1" required> same business</label><button class="btn sm primary">Verify</button></form>
          <form class="inline" method="post" action="{{ route('admin.mappings.disable',$r['proposed']) }}">@csrf<input type="hidden" name="confirm" value="1"><button class="btn sm">Reject</button></form>
        @endif
        @if(!$r['verified'])
          <form class="inline" method="post" action="{{ route('admin.mappings.assign') }}" style="margin-top:6px">@csrf
            <input type="hidden" name="laundrycard_store_id" value="{{ $r['store']->id }}">
            <select name="ghl_connection_id" required>
              <option value="">Assign location…</option>
              @foreach($connections as $c)<option value="{{ $c->id }}" @selected($r['suggestion'] && $r['suggestion']['connection']->id===$c->id)>{{ $c->location_name ?? $c->location_id }}</option>@endforeach
            </select>
            @if($r['suggestion'])<input type="hidden" name="score" value="{{ $r['suggestion']['score'] }}"><span class="badge b-info" title="Name similarity suggestion only">suggested {{ $r['suggestion']['score'] }}%</span>@endif
            <label class="chk"><input type="checkbox" name="confirm" value="1" required> confirm</label>
            <button class="btn sm">Propose</button>
          </form>
        @else
          <form class="inline" method="post" action="{{ route('admin.mappings.assign') }}" style="margin-top:6px">@csrf
            <input type="hidden" name="laundrycard_store_id" value="{{ $r['store']->id }}">
            <select name="ghl_connection_id" required><option value="">Change location…</option>@foreach($connections as $c)@if($c->id!==$r['verified']->ghl_connection_id)<option value="{{ $c->id }}">{{ $c->location_name ?? $c->location_id }}</option>@endif @endforeach</select>
            <label class="chk"><input type="checkbox" name="confirm" value="1" required> I understand routing stops until re-verified</label>
            <button class="btn sm">Change</button>
          </form>
        @endif
      </td>
    </tr>
  @empty <tr><td colspan="9" class="muted">No stores discovered yet. Use Developer Controls → Refresh store list.</td></tr> @endforelse
</table>
</div>

<div class="grid g2">
  <div class="card"><h2>GHL locations without a verified mapping ({{ $unassigned->count() }})</h2>
    @forelse($unassigned as $c)<div>{{ $c->location_name ?? '—' }} <span class="mono muted">{{ $c->location_id }}</span> <x-badge :value="$c->status"/></div>
    @empty<div class="muted">None.</div>@endforelse
  </div>
  <div class="card"><h2>Mapping audit trail</h2>
    <table><tr><th>When</th><th>Who</th><th>Action</th><th>Store</th></tr>
    @forelse($audit as $a)<tr><td>{{ $a->created_at->diffForHumans() }}</td><td>{{ $a->user?->email ?? 'system' }}</td><td><x-badge :value="$a->action"/></td><td class="mono">{{ $a->store?->store_id }}</td></tr>
    @empty<tr><td colspan="4" class="muted">No changes recorded.</td></tr>@endforelse</table>
  </div>
</div>
@endsection
