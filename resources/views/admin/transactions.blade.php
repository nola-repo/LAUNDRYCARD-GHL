@extends('layouts.app')
@section('title','Credit History')
@section('content')
@php
  $typeLabels = ['reload' => 'Reload', 'bonus' => 'Bonus', 'vend' => 'Machine use', 'adjustment' => 'Adjustment', 'unknown' => 'Other'];
  $typeBadge = ['reload' => 'b-ok', 'bonus' => 'b-info', 'vend' => 'b-mute', 'adjustment' => 'b-warn', 'unknown' => 'b-mute'];
  $filtered = collect(request()->only(['card','store','type','from','to']))->contains(fn($value) => filled($value));
@endphp
<x-admin-workspace title="Credit history" eyebrow="LaundryCard · Transaction ledger" description="Trace card reloads, bonuses, adjustments, and machine use across your branches.">
  <x-slot:context><strong>{{ number_format($totalStored) }} stored transactions</strong>Amounts below reflect the applied filters.</x-slot:context>
  <x-slot:navigation><a href="#ledger-summary">Amounts</a><a href="#workspace-results">Transactions</a></x-slot:navigation>
  <x-slot:actions><a class="admin-action" href="{{ route('admin.records') }}">Customer records</a><a class="admin-action admin-action-primary" href="{{ route('admin.transactions.export', request()->except(['page'])) }}">Export filtered CSV</a></x-slot:actions>
  @if($record)
    <aside class="workspace-card-context" aria-label="Card context"><div><span class="workspace-kicker">Card context</span><h2>{{ $record->card_number }}</h2><p class="workspace-note">{{ $record->store?->name ?? 'Store unavailable' }} · Store {{ $record->store?->store_id ?? 'unavailable' }}@if($record->customer_name) · {{ ucwords(strtolower($record->customer_name)) }}@endif</p></div><div><span class="workspace-caption">Current card balance</span><strong class="workspace-money"><x-workspace-amount :value="$record->balance" empty="Pending"/></strong></div></aside>
  @endif
  <section id="ledger-summary" aria-labelledby="ledger-summary-title"><div class="workspace-section-head"><div><span class="workspace-kicker">Filtered activity</span><h2 id="ledger-summary-title">Where the credit moved</h2></div><p>Totals across all matching pages</p></div>
    <dl class="workspace-stats">@foreach($typeLabels as $key => $label)<div class="{{ $key === 'reload' ? 'workspace-stat-positive' : ($key === 'bonus' ? 'workspace-stat-bonus' : '') }}"><dt>{{ $label }}</dt><dd>${{ number_format($summary[$key]['total'] ?? 0, 2) }}</dd><small>{{ number_format($summary[$key]['n'] ?? 0) }} transactions</small></div>@endforeach</dl>
  </section>
  <section class="workspace-panel" aria-labelledby="workspace-results">
    <form class="workspace-filters" method="get" action="{{ route('admin.transactions') }}" data-workspace-form novalidate>
      <div class="workspace-filter-grid">
        <x-workspace-search name="card" label="Card number" placeholder="Find a card"/>
        <div class="workspace-field"><label for="ledger-store">Store branch</label><select id="ledger-store" name="store"><option value="">All stores</option>@foreach($stores as $store)<option value="{{ $store->store_id }}" @selected(request('store') === $store->store_id)>{{ $store->store_id }} · {{ $store->name }}</option>@endforeach</select></div>
        <div class="workspace-field"><label for="ledger-type">Transaction type</label><select id="ledger-type" name="type"><option value="">All types</option>@foreach($typeLabels as $key => $label)<option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>@endforeach</select></div>
        <fieldset class="workspace-field workspace-dates"><legend>Transaction dates · UTC</legend><div><label class="workspace-sr-only" for="ledger-from">From date</label><input id="ledger-from" type="date" name="from" value="{{ request('from') }}"><label class="workspace-sr-only" for="ledger-to">To date</label><input id="ledger-to" type="date" name="to" value="{{ request('to') }}"></div></fieldset>
      </div>
      <p class="workspace-form-error" id="ledger-filter-error" data-form-error role="alert" hidden></p>
      <div class="workspace-filter-footer"><div><button class="admin-action admin-action-primary" type="submit"><span>Apply filters</span></button><a class="admin-action" href="{{ route('admin.transactions') }}">Reset all</a></div><p>CSV exports include all matching pages using your applied filters.</p></div>
    </form>
    <div class="workspace-panel-head"><div><h2 id="workspace-results" tabindex="-1">Transaction ledger</h2><p>Newest activity first · All times in UTC</p></div><span class="workspace-count">{{ number_format($transactions->total()) }} matches</span></div>
    <div class="workspace-table-wrap"><table class="workspace-table workspace-table--ledger"><caption class="workspace-sr-only">Card transactions and balance history</caption><thead><tr><th scope="col">Card / customer</th><th scope="col">Branch</th><th scope="col">Activity</th><th scope="col">Amount / balance</th><th scope="col">Recorded · UTC</th><th scope="col">Machine / detail</th></tr></thead><tbody>
    @forelse($transactions as $transaction)
      @php
        $type = $transaction->transaction_type ?? 'unknown';
        $info = is_array($transaction->raw_payload) ? ($transaction->raw_payload['AdditionalInfo'] ?? '') : '';
        [$first, $last] = \App\Support\Normalizer::splitName($transaction->sourceRecord?->customer_name);
      @endphp
      <tr><th scope="row" data-label="Card / customer"><a class="workspace-link workspace-mono" href="{{ route('admin.transactions', ['card' => $transaction->card_number, 'store' => $transaction->store?->store_id]) }}">{{ $transaction->card_number }}</a><p class="workspace-note">@if($first || $last)<span aria-label="First Name">{{ ucwords(strtolower($first ?? '')) }}</span> <span aria-label="Last Name">{{ ucwords(strtolower($last ?? '')) }}</span>@else Anonymous card @endif</p></th>
        <td data-label="Branch"><strong>{{ $transaction->store?->name ?? 'Store unavailable' }}</strong><span class="workspace-id">Store {{ $transaction->store?->store_id ?? 'unavailable' }}</span></td>
        <td data-label="Activity"><span class="badge {{ $typeBadge[$type] ?? 'b-mute' }}">{{ $typeLabels[$type] ?? $type }}</span>@if($type === 'unknown')<span class="workspace-note">Source code {{ $transaction->transaction_type_raw ?? 'unavailable' }}</span>@endif</td>
        <td data-label="Amount / balance"><span class="workspace-money"><x-workspace-amount :value="$transaction->amount"/></span><p class="workspace-note">Balance after <strong><x-workspace-amount :value="$transaction->balance_after"/></strong></p></td>
        <td data-label="Recorded · UTC"><x-workspace-time :value="$transaction->occurred_at"/></td>
        <td data-label="Machine / detail"><span class="workspace-mono">{{ $transaction->mach_no ? 'Machine '.$transaction->mach_no : 'No machine recorded' }}</span>@if(filled($info) && !preg_match('/^[01]+$/', (string)$info))<details class="workspace-disclosure"><summary>Transaction detail</summary><p class="workspace-note">{{ $info }}</p></details>@endif</td>
      </tr>
    @empty<tr class="workspace-empty-row"><td colspan="6"><x-workspace-empty :title="$filtered ? 'No transactions match these filters' : 'No transactions recorded yet'" :description="$filtered ? 'Try another card, branch, or date range.' : 'Reloads and machine activity will appear after transaction discovery runs.'" :reset="$filtered ? route('admin.transactions') : null"/></td></tr>@endforelse
    </tbody></table></div>
    <footer class="workspace-pagination"><p>{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }} of {{ number_format($transactions->total()) }} transactions</p><div class="pager">{{ $transactions->links() }}</div></footer>
  </section>
</x-admin-workspace>
@endsection
