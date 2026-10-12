@extends('layouts.app')
@section('title','Customer Records')
@section('content')
<h1>Incoming Customer Records</h1>
<p class="sub">Sanitized view: email and phone are masked; full values stay encrypted in the database.</p>

<form class="filters" method="get">
  <select name="store"><option value="">All stores</option>@foreach($stores as $s)<option value="{{ $s->store_id }}" @selected(request('store')===$s->store_id)>{{ $s->store_id }} {{ $s->name }}</option>@endforeach</select>
  <select name="status"><option value="">Any status</option>@foreach($statuses as $s)<option @selected(request('status')===$s)>{{ $s }}</option>@endforeach</select>
  <select name="classification"><option value="">Any classification</option>@foreach(['baseline','new_observed','confirmed_new'] as $s)<option @selected(request('classification')===$s)>{{ $s }}</option>@endforeach</select>
  <select name="outcome"><option value="">Any outcome/hold reason</option>@foreach($holdReasons as $s)<option @selected(request('outcome')===$s)>{{ $s }}</option>@endforeach</select>
  <input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}">
  <button class="btn primary">Filter</button> <a class="btn" href="{{ route('admin.records') }}">Reset</a>
</form>

<div class="card" style="overflow:auto"><table>
  <tr><th>Store</th><th>Card / account</th><th>Customer</th><th>Source time</th><th>First observed</th><th>Classification</th><th>Status</th><th>Destination GHL location</th><th>GHL contact ID</th></tr>
  @forelse($records as $r)
  <tr>
    <td class="mono">{{ $r->store->store_id }}</td>
    <td class="mono">{{ $r->card_number }}</td>
    <td>{{ $r->customer_name ? \Illuminate\Support\Str::limit($r->customer_name,1,'').'***' : '—' }}<div class="muted" style="font-size:11px">{{ \App\Support\Sanitizer::maskEmail($r->customer_email) }} {{ \App\Support\Sanitizer::maskPhone($r->customer_phone) }}</div></td>
    <td>{{ $r->source_timestamp?->toDateTimeString() ?? '—' }}</td>
    <td>{{ $r->first_observed_at->toDateTimeString() }}</td>
    <td><x-badge :value="$r->classification"/></td>
    <td><x-badge :value="$r->processing_status"/>@if($r->hold_reason)<div class="muted" style="font-size:11px">{{ $r->hold_reason }}</div>@endif</td>
    <td class="mono">{{ $r->destination_location_id ?? '—' }}</td>
    <td class="mono">{{ $r->contactMapping?->ghl_contact_id ?? '—' }}</td>
  </tr>
  @empty<tr><td colspan="9" class="muted">No records match.</td></tr>@endforelse
</table></div>
<div class="pager">{{ $records->links() }}</div>
@endsection
