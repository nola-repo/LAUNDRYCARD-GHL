@extends('layouts.app')
@section('title','Failed Activity')
@section('content')
<h1>Failed &amp; Held Synchronizations</h1>
<p class="sub">Read-only activity. Eligibility, routing holds, and retries are handled by the backend integration.</p>
<div class="card" style="overflow:auto"><table>
  <tr><th>Store</th><th>Card</th><th>Balance</th><th>Eligibility</th><th>Status</th><th>Reason</th><th>Attempts</th><th>History</th></tr>
  @forelse($records as $r)
  @php($last = $r->attempts->first())
  <tr>
    <td class="mono">{{ $r->store->store_id }}</td><td class="mono">{{ substr($r->card_number,0,2) }}••••{{ substr($r->card_number,-2) }}</td>
    <td>{{ $r->balance !== null ? '$'.number_format((float)$r->balance,2) : 'unknown' }}</td>
    <td>{{ $r->eligibility_status }}</td><td><x-badge :value="$r->processing_status"/></td>
    <td>{{ $r->hold_reason ?? ($last?->error_message ?? '—') }}@if($last?->http_status)<div class="muted" style="font-size:11px">HTTP {{ $last->http_status }}</div>@endif</td>
    <td>{{ $r->attempt_count }}</td>
    <td style="max-width:260px">@foreach($r->attempts as $a)<div class="muted" style="font-size:11px">#{{ $a->attempt_no }} {{ $a->created_at->diffForHumans() }} — {{ $a->status }}{{ $a->stage ? ' @ '.$a->stage : '' }}</div>@endforeach</td>
  </tr>
  @empty<tr><td colspan="8" class="muted">No failed or held records.</td>@endforelse
</table></div>
<div class="pager">{{ $records->links() }}</div>
@endsection
