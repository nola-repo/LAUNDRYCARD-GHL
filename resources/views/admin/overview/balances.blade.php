@php
  $buckets = [
      'eligible' => ['Positive', $stats['positive_balance']],
      'zero' => ['Zero', $stats['zero_balance']],
      'unknown' => ['Unverified', $stats['balance_unknown']],
      'other' => ['Other', max(0, $stats['records'] - $stats['positive_balance'] - $stats['zero_balance'] - $stats['balance_unknown'])],
  ];
@endphp
<div class="ov-bar" aria-hidden="true">
  @foreach($buckets as $tone => [$label, $count])
    <span class="ov-segment ov-{{ $tone }}" style="flex-grow: {{ (int) $count }}"></span>
  @endforeach
</div>
<dl class="ov-legend">
  @foreach($buckets as $tone => [$label, $count])
    <div><dt><i class="ov-{{ $tone }}" aria-hidden="true"></i>{{ $label }}</dt><dd>{{ number_format($count) }}</dd></div>
  @endforeach
</dl>
