@extends('layouts.app')
@section('title','Failed Activity')
@section('content')

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
      Exception Monitor
    </span>
    <h1>Failed &amp; Held Synchronizations</h1>
    <p class="sub">Read-only exception queue. Eligibility holds, routing safeguards, and automated backoff schedules are managed by the backend pipeline.</p>
  </div>
</div>

<div class="table-card">
  <div class="table-card-header">
    <div>
      <h2>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--warn)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        Quarantined &amp; Held Records
      </h2>
      <span class="muted" style="font-size:12px">Showing records currently in held or failed state with their recent execution trace</span>
    </div>
    <span class="badge b-mute">{{ number_format($records->total()) }} Total</span>
  </div>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Store</th>
          <th>Card</th>
          <th>Balance</th>
          <th>Eligibility</th>
          <th>Status</th>
          <th>Reason</th>
          <th>Attempts</th>
          <th>History</th>
        </tr>
      </thead>
      <tbody>
        @forelse($records as $r)
        @php($last = $r->attempts->first())
        <tr>
          <td><span class="store-id-pill">Store {{ $r->store->store_id }}</span></td>
          <td><span class="card-mono">{{ substr($r->card_number,0,2) }}••••{{ substr($r->card_number,-2) }}</span></td>
          <td>
            @if($r->balance !== null)
              <span class="balance-pill {{ (float)$r->balance > 0 ? 'bal-pos' : 'bal-zero' }}">
                ${{ number_format((float)$r->balance,2) }}
              </span>
            @else
              <span class="balance-pill bal-zero">unknown</span>
            @endif
          </td>
          <td><x-badge :value="$r->eligibility_status"/></td>
          <td><x-badge :value="$r->processing_status"/></td>
          <td style="max-width:260px">
            <div style="font-weight:600;color:var(--text-title)">{{ $r->hold_reason ?? ($last?->error_message ?? '—') }}</div>
            @if($last?->http_status)
              <div class="muted mono" style="font-size:11px;margin-top:2px">HTTP {{ $last->http_status }}</div>
            @endif
          </td>
          <td><span class="badge b-mute">{{ $r->attempt_count }}</span></td>
          <td style="max-width:300px">
            @forelse($r->attempts as $a)
              <div class="muted" style="font-size:11.5px;padding:2px 0">
                <span class="mono" style="color:var(--text-title);font-weight:600">#{{ $a->attempt_no }}</span>
                {{ $a->created_at->diffForHumans() }} — {{ $a->status }}{{ $a->stage ? ' @ '.$a->stage : '' }}
              </div>
            @empty
              <span class="muted" style="font-size:12px">No external dispatch attempts</span>
            @endforelse
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="8">
            <div class="empty-state-box">
              <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
              </div>
              <div class="empty-state-text">No failed or held records.</div>
              <p class="muted" style="font-size:12.5px;margin-top:4px">All eligible records are operating cleanly without synchronization holds.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pagination-wrapper">
  <div class="pager">{{ $records->links() }}</div>
</div>
@endsection
