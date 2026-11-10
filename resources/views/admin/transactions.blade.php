@extends('layouts.app')
@section('title','Credit History')
@section('content')
@php
  $typeLabels = [
    'reload' => 'Reload',
    'bonus' => 'Bonus',
    'vend' => 'Machine Use',
    'adjustment' => 'Adjustment',
    'unknown' => 'Other'
  ];
  $typeBadge = [
    'reload' => 'b-ok',
    'bonus' => 'b-info',
    'vend' => 'b-mute',
    'adjustment' => 'b-warn',
    'unknown' => 'b-mute'
  ];
  $typeIcon = [
    'reload' => 'metric-icon--emerald',
    'bonus' => 'metric-icon--cyan',
    'vend' => 'metric-icon--violet',
    'unknown' => 'metric-icon--amber'
  ];
@endphp

<div class="page-head">
  <div>
    <span class="page-kicker">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
      Transaction Ledger
    </span>
    <h1>Credit History</h1>
    <p class="sub">Card reloads, bonuses, and machine usage pulled from LaundryCard <code>/api/transact</code>. {{ number_format($totalStored) }} transactions stored in total.</p>
  </div>
</div>

@if($record)
<div class="card" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:14px">
    <div class="metric-icon metric-icon--violet">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
    </div>
    <div>
      <h2 style="margin:0">Card <span class="card-mono">{{ $record->card_number }}</span></h2>
      <div class="muted" style="font-size:12.5px;margin-top:3px">
        Store #{{ $record->store->store_id ?? '' }} ({{ $record->store->name ?? '' }})
        @if($record->customer_name) · <strong>{{ ucwords(strtolower($record->customer_name)) }}</strong> @endif
      </div>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <span class="muted" style="font-size:12px;font-weight:600">Current Balance:</span>
    <span class="balance-pill {{ (float)$record->balance > 0 ? 'bal-pos' : 'bal-zero' }}">
      {{ $record->balance === null ? 'unknown' : '$'.number_format((float) $record->balance, 2) }}
    </span>
  </div>
</div>
@endif

<div class="metric-row">
  @foreach(['reload','bonus','vend','unknown'] as $k)
  <div class="metric-box">
    <div class="metric-box-top">
      <span class="metric-lbl">{{ $typeLabels[$k] }}</span>
      <div class="metric-icon {{ $typeIcon[$k] ?? 'metric-icon--violet' }}">
        @if($k === 'reload')
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
        @elseif($k === 'bonus')
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        @elseif($k === 'vend')
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        @else
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        @endif
      </div>
    </div>
    <div class="metric-num">${{ number_format($summary[$k]['total'] ?? 0, 2) }}</div>
    <div class="metric-sub">{{ number_format($summary[$k]['n'] ?? 0) }} transactions</div>
  </div>
  @endforeach
</div>

<div class="filter-panel">
  <div class="filter-title">
    <h3>
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
      Filter Transactions
    </h3>
    <span class="muted" style="font-size:12px">Filter by card number, branch, transaction type, or date window</span>
  </div>

  <form method="get" action="{{ route('admin.transactions') }}">
    <div class="filter-grid">
      <div class="filter-group">
        <label class="filter-label">
          <span>Card Number</span>
          <span class="filter-hint">Search #</span>
        </label>
        <input type="text" name="card" value="{{ request('card') }}" placeholder="e.g. 100234...">
      </div>
      <div class="filter-group">
        <label class="filter-label">
          <span>Store Branch</span>
          <span class="filter-hint">Location</span>
        </label>
        <select name="store">
          <option value="">All stores</option>
          @foreach($stores as $s)
            <option value="{{ $s->store_id }}" @selected(request('store') === $s->store_id)>Store {{ $s->store_id }} — {{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">
          <span>Transaction Type</span>
          <span class="filter-hint">Category</span>
        </label>
        <select name="type">
          <option value="">All types</option>
          @foreach($typeLabels as $k => $l)
            <option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>
          @endforeach
        </select>
      </div>
      <div class="filter-group">
        <label class="filter-label">
          <span>Date Window</span>
          <span class="filter-hint">From &amp; To</span>
        </label>
        <div class="filter-dates">
          <input type="date" name="from" value="{{ request('from') }}">
          <input type="date" name="to" value="{{ request('to') }}">
        </div>
      </div>
    </div>
    <div class="filter-bar-footer">
      <div class="filter-buttons">
        <button type="submit" class="btn primary">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <span>Apply Filters</span>
        </button>
        <a class="btn" href="{{ route('admin.transactions') }}">Reset</a>
      </div>
      <div class="muted" style="font-size:12px">
        Showing <strong>{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }}</strong> of <strong>{{ number_format($transactions->total()) }}</strong> entries
      </div>
    </div>
  </form>
</div>

<div class="table-card">
  <div class="table-scroll">
    <table class="records-table">
      <thead>
        <tr>
          <th>When (UTC)</th>
          <th>Store</th>
          <th>Card #</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Balance After</th>
          <th>Machine</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody>
      @forelse($transactions as $t)
        @php
          $info = is_array($t->raw_payload) ? ($t->raw_payload['AdditionalInfo'] ?? '') : '';
          $tt = $t->transaction_type ?? 'unknown';
        @endphp
        <tr>
          <td style="font-weight:600;color:var(--text-title)">{{ $t->occurred_at->format('M d, Y H:i:s') }}</td>
          <td><span class="store-id-pill">#{{ $t->store->store_id ?? '' }}</span></td>
          <td><a class="card-mono" href="{{ route('admin.transactions', ['card' => $t->card_number]) }}">{{ $t->card_number }}</a></td>
          <td>
            <span class="badge {{ $typeBadge[$tt] ?? 'b-mute' }}">{{ $typeLabels[$tt] ?? $tt }}</span>
            @if($tt === 'unknown')
              <span class="muted mono" style="font-size:10.5px;margin-left:4px">code {{ $t->transaction_type_raw }}</span>
            @endif
          </td>
          <td style="font-weight:700;color:var(--text-title)">{{ $t->amount === null ? '—' : '$'.number_format((float) $t->amount, 2) }}</td>
          <td>
            @if($t->balance_after === null)
              <span class="muted">—</span>
            @else
              <span class="balance-pill {{ (float)$t->balance_after > 0 ? 'bal-pos' : 'bal-zero' }}">
                ${{ number_format((float) $t->balance_after, 2) }}
              </span>
            @endif
          </td>
          <td class="mono">{{ $t->mach_no ?? '—' }}</td>
          <td class="muted" style="font-size:11.5px">{{ preg_match('/^[01]+$/', (string) $info) ? '' : \Illuminate\Support\Str::limit((string) $info, 40) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="8">
            <div class="empty-state-box">
              <div class="empty-state-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
              </div>
              <div class="empty-state-text">No transactions stored yet</div>
              <p class="muted" style="font-size:12.5px;margin-top:6px">
                Run <code>php artisan integration:ingest-transactions --backfill-hours=72</code> to populate transaction history.
              </p>
            </div>
          </td>
        </tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pagination-wrapper">
  <div class="pager">{{ $transactions->links() }}</div>
</div>
@endsection
