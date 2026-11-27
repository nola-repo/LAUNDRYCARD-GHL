@extends('layouts.app')
@section('title','Failed Activity')
@section('content')
@php($reasonLabels = ['balance_not_positive' => 'Card balance must be positive', 'no_branch_mapping' => 'No verified branch mapping', 'missing_contact_info' => 'Contact email or phone is missing', 'circuit_breaker_tripped' => 'Sync is paused for review'])
<x-admin-workspace title="Failed activity" eyebrow="LaundryCard · Exceptions" description="Review held and failed records, the reason for each stop, and recent sync attempts.">
  <x-slot:context><strong>{{ number_format($records->total()) }} records need attention</strong>Retries and eligibility checks follow the integration schedule.</x-slot:context>
  <x-slot:navigation><a href="#workspace-results">Held &amp; failed records</a></x-slot:navigation>
  <x-slot:actions><a class="admin-action" href="{{ route('admin.mappings') }}">Branch mapping</a><a class="admin-action admin-action-primary" href="{{ route('admin.logs') }}">Review logs</a></x-slot:actions>
  <section class="workspace-panel" aria-labelledby="workspace-results"><div class="workspace-panel-head"><div><h2 id="workspace-results" tabindex="-1">What needs attention</h2><p>Latest changes first · Read-only exception queue</p></div><span class="workspace-count">{{ number_format($records->total()) }} records</span></div>
    <div class="workspace-table-wrap"><table class="workspace-table workspace-table--failed"><caption class="workspace-sr-only">Held and failed customer records with their recent attempts</caption><thead><tr><th scope="col">Card / branch</th><th scope="col">Reason / status</th><th scope="col">Balance / eligibility</th><th scope="col">Latest attempt</th><th scope="col">Attempt history</th></tr></thead><tbody>
      @forelse($records as $record)
      @php($last = $record->attempts->first())
      <tr><th scope="row" data-label="Card / branch"><strong class="workspace-mono">{{ substr($record->card_number,0,2) }}••••{{ substr($record->card_number,-2) }}</strong><p class="workspace-note">{{ $record->store?->name ?? 'Store unavailable' }}</p><span class="workspace-id">Store {{ $record->store?->store_id ?? 'unavailable' }}</span></th>
        <td data-label="Reason / status" class="workspace-event-main"><x-badge :value="$record->processing_status"/><strong class="workspace-note">{{ $reasonLabels[$record->hold_reason] ?? ($record->hold_reason ? ucwords(str_replace('_',' ',$record->hold_reason)) : ($last?->error_message ?? 'No reason recorded')) }}</strong>@if($last?->http_status)<span class="workspace-note">HTTP {{ $last->http_status }}</span>@endif @if($last?->error_message && $record->hold_reason)<details class="workspace-disclosure"><summary>Last error</summary><p class="workspace-note">{{ $last->error_message }}</p></details>@endif</td>
        <td data-label="Balance / eligibility"><span class="workspace-money"><x-workspace-amount :value="$record->balance" empty="Pending"/></span><div class="workspace-note"><x-badge :value="$record->eligibility_status"/></div></td>
        <td data-label="Latest attempt">@if($last)<x-workspace-time :value="$last->created_at"/><span class="workspace-note">{{ ucwords(str_replace('_',' ',$last->status)) }}</span>@else<span class="workspace-note">No external dispatch attempts</span>@endif</td>
        <td data-label="Attempt history"><strong>{{ number_format($record->attempt_count) }} attempts</strong>@if($record->attempts->isNotEmpty())<details class="workspace-disclosure"><summary>Recent {{ $record->attempts->count() }}</summary><ol class="workspace-attempts">@foreach($record->attempts as $attempt)<li><strong>Attempt {{ $attempt->attempt_no }} · {{ ucwords(str_replace('_',' ',$attempt->status)) }}</strong><span class="workspace-id">{{ $attempt->stage ?? 'Stage not recorded' }}</span><x-workspace-time :value="$attempt->created_at"/></li>@endforeach</ol></details>@endif</td>
      </tr>@empty<tr class="workspace-empty-row"><td colspan="5"><x-workspace-empty title="No held or failed records" description="There are no records currently waiting in this exception queue."/></td></tr>@endforelse
    </tbody></table></div><footer class="workspace-pagination"><p>{{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} records</p><div class="pager">{{ $records->links() }}</div></footer>
  </section>
</x-admin-workspace>
@endsection
