@extends('layouts.app')
@section('title','Integration Logs')
@section('content')
@php($filtered = collect(request()->only(['q','level','stage','outcome']))->contains(fn($value) => filled($value)))
<x-admin-workspace title="Integration logs" eyebrow="LaundryCard · Execution history" description="Follow discovery, routing, and sync events through each execution trace.">
  <x-slot:context><strong>Sanitized event history</strong>Credentials are redacted and personal data is masked before storage.</x-slot:context>
  <x-slot:navigation><a href="#workspace-results">Event stream</a></x-slot:navigation>
  <x-slot:actions><a class="admin-action" href="{{ route('admin.developer') }}">API activity</a><a class="admin-action admin-action-primary" href="{{ route('admin.failed') }}">Failed activity</a></x-slot:actions>
  <section class="workspace-panel" aria-labelledby="workspace-results">
    <form class="workspace-filters" method="get" action="{{ route('admin.logs') }}" data-workspace-form novalidate>
      <div class="workspace-filter-grid workspace-filter-grid--logs">
        <x-workspace-search name="q" label="Search events" placeholder="Trace, branch, location, or message"/>
        <div class="workspace-field"><label for="logs-level">Severity</label><select name="level" id="logs-level"><option value="">Any level</option>@foreach(['info','warning','error'] as $level)<option value="{{ $level }}" @selected(request('level') === $level)>{{ ucfirst($level) }}</option>@endforeach</select></div>
        <x-workspace-search name="stage" label="Stage prefix" placeholder="oauth, sync, api"/>
        <x-workspace-search name="outcome" label="Outcome" placeholder="ok, failed, skipped"/>
      </div>
      <div class="workspace-filter-footer"><div><button class="admin-action admin-action-primary" type="submit"><span>Search logs</span></button><a class="admin-action" href="{{ route('admin.logs') }}">Reset all</a></div><p>Select a correlation ID to follow its execution trace.</p></div>
    </form>
    <div class="workspace-panel-head"><div><h2 id="workspace-results" tabindex="-1">Event stream</h2><p>Newest events first · All times in UTC</p></div><span class="workspace-count">{{ number_format($events->total()) }} events</span></div>
    <div class="workspace-table-wrap"><table class="workspace-table workspace-table--events"><caption class="workspace-sr-only">Integration events, outcomes and execution context</caption><thead><tr><th scope="col">Recorded · UTC</th><th scope="col">Event / message</th><th scope="col">Branch / location</th><th scope="col">Outcome</th><th scope="col">Execution trace</th></tr></thead><tbody>
      @forelse($events as $event)<tr>
        <td data-label="Recorded · UTC"><x-workspace-time :value="$event->created_at"/><span class="workspace-id">Event {{ $event->id }}</span></td>
        <th scope="row" data-label="Event / message" class="workspace-event-main"><span class="workspace-id">{{ $event->stage }}</span><strong>{{ $event->message }}</strong>@if($event->context)<details class="workspace-disclosure"><summary>Sanitized context</summary><pre>{{ json_encode($event->context, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></details>@endif</th>
        <td data-label="Branch / location"><strong>{{ $event->store_id ? 'Store '.$event->store_id : 'No store recorded' }}</strong>@if($event->source_record_id)<span class="workspace-note">Record {{ $event->source_record_id }}</span>@endif @if($event->ghl_location_id)<span class="workspace-id">Location {{ $event->ghl_location_id }}</span>@endif</td>
        <td data-label="Outcome"><div class="workspace-badges"><x-badge :value="$event->level ?? 'info'"/>@if($event->outcome)<x-badge :value="$event->outcome"/>@endif</div><p class="workspace-note">{{ $event->http_status ? 'HTTP '.$event->http_status : 'No HTTP response recorded' }}</p><span class="workspace-note">{{ $event->retry_count ?? 0 }} retries</span></td>
        <td data-label="Execution trace">@if($event->correlation_id)<a class="workspace-link workspace-mono" href="{{ route('admin.logs', ['q' => $event->correlation_id]) }}">{{ $event->correlation_id }}</a>@else<span class="workspace-note">No correlation ID</span>@endif</td>
      </tr>@empty<tr class="workspace-empty-row"><td colspan="5"><x-workspace-empty :title="$filtered ? 'No events match these filters' : 'No integration events yet'" :description="$filtered ? 'Broaden the search or remove a filter to see more events.' : 'Events will appear as the integration runs.'" :reset="$filtered ? route('admin.logs') : null"/></td></tr>@endforelse
    </tbody></table></div>
    <footer class="workspace-pagination"><p>{{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }} of {{ number_format($events->total()) }} events</p><div class="pager">{{ $events->links() }}</div></footer>
  </section>
</x-admin-workspace>
@endsection
