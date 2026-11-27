@php
  $exportColumns = \App\Support\CustomerRecordsCsv::COLUMNS;
  $exportGroups = \App\Support\CustomerRecordsCsv::GROUPS;
  if ($selectedCategory !== '') $exportFilters = ['category' => 'Category: '.$categories[$selectedCategory][1]] + $exportFilters;
@endphp
<x-confirm-action id="records-export-dialog" class="records-export-dialog" title="Export customer records" description-id="records-export-scope" :action="route('admin.records.export')" confirm-label="Download CSV" :download="true" :disabled="$records->total() === 0">
  <div class="records-export-scope" id="records-export-scope">
    <strong>{{ number_format($records->total()) }} <span>matching {{ \Illuminate\Support\Str::plural('record', $records->total()) }}</span></strong>
    <p>Includes all matching pages using your applied filters.</p>
    @if(count($exportFilters))
      <ul aria-label="Export filters">@foreach($exportFilters as $filter)<li>{{ $filter }}</li>@endforeach</ul>
    @else<p>No filters applied. The full directory will be exported.</p>@endif
    <p class="records-export-draft" hidden>There are unapplied filter changes. Close this panel and apply them first to include those changes.</p>
    @if($records->total() === 0)<p>No records to export. Close this panel and adjust your filters.</p>@endif
  </div>
  @foreach(request()->only(['tab','search','q','store','classification','status','has_name','outcome','from','to']) as $key => $value)
    @if(is_scalar($value) && filled($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
  @endforeach
  <input type="hidden" name="customize" value="1">
  <div class="records-export-columns-head"><div><h3>Columns to include</h3><p data-export-count role="status">{{ count($exportColumns) }} selected</p></div><div><button type="button" class="admin-action" data-export-select="all">Select all</button><button type="button" class="admin-action" data-export-select="none">Clear selection</button></div></div>
  <div class="records-export-columns" role="group" aria-label="CSV columns" aria-describedby="records-export-error" tabindex="-1">
    <p class="records-export-error" id="records-export-error" role="alert" hidden></p>
    @foreach($exportGroups as $group => $keys)
      <fieldset><legend>{{ $group }}</legend>
        @foreach($keys as $key)<label><input type="checkbox" name="columns[]" value="{{ $key }}" checked><span>{{ $exportColumns[$key] }}</span></label>@endforeach
      </fieldset>
    @endforeach
  </div>
  <p class="records-export-note">CSV for Excel and other spreadsheets. Dates use UTC. Records are checked again when you download.</p>
</x-confirm-action>
