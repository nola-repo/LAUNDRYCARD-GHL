@extends('layouts.app')
@section('title','Failed Jobs')
@section('content')
<h1>Failed &amp; Held Synchronizations</h1>
<p class="sub">Retrying re-queues the record through the same mapping and idempotency checks; it can never create a duplicate contact or choose another location.</p>
<div class="card" style="overflow:auto"><table>
  <tr><th>Store</th><th>Card</th><th>Status</th><th>Reason</th><th>Retry eligible</th><th>Attempts</th><th>History</th><th></th></tr>
  @forelse($records as $r)
  @php $last = $r->attempts->first(); @endphp
  <tr>
    <td class="mono">{{ $r->store->store_id }}</td><td class="mono">{{ $r->card_number }}</td>
    <td><x-badge :value="$r->processing_status"/></td>
    <td>{{ $r->hold_reason ?? ($last?->error_message ?? '—') }}@if($last?->http_status)<div class="muted" style="font-size:11px">HTTP {{ $last->http_status }}</div>@endif</td>
    <td>@if($r->processing_status==='failed'){{ $last?->retry_eligible ? 'yes (transient)' : 'review first' }}@else yes, after review @endif</td>
    <td>{{ $r->attempt_count }}</td>
    <td style="max-width:260px">@foreach($r->attempts as $a)<div class="muted" style="font-size:11px">#{{ $a->attempt_no }} {{ $a->created_at->diffForHumans() }} — {{ $a->status }}{{ $a->stage ? ' @ '.$a->stage : '' }}</div>@endforeach</td>
    <td><form class="inline" method="post" action="{{ route('admin.records.retry',$r) }}">@csrf
      <label class="chk"><input type="checkbox" name="confirm" value="1" required> reviewed</label><button class="btn sm primary">Retry</button></form></td>
  </tr>
  @empty<tr><td colspan="8" class="muted">Nothing failed or held. 🎉</td></tr>@endforelse
</table></div>
<div class="pager">{{ $records->links() }}</div>
@endsection
