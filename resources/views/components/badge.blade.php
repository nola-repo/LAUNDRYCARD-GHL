@props(['value'])
@php
  $v = (string) $value;
  $map = [
    'ok'=>'ok','active'=>'ok','verified'=>'ok','valid'=>'ok','completed'=>'ok','success'=>'ok','synced_created'=>'ok','synced_updated'=>'ok','created'=>'ok','updated'=>'ok','routed'=>'ok',
    'proposed'=>'warn','held'=>'warn','pending'=>'warn','queued'=>'info','processing'=>'info','expiring'=>'warn','partial'=>'warn','warning'=>'warn','unmapped'=>'warn','new_observed'=>'warn','running'=>'info','read_only'=>'info',
    'failed'=>'bad','error'=>'bad','revoked'=>'bad','expired'=>'bad','disconnected'=>'bad','failure'=>'bad','missing'=>'bad',
    'confirmed_new'=>'info','baseline'=>'mute','baseline_skipped'=>'mute','skipped'=>'mute','disabled'=>'mute','unknown'=>'mute','info'=>'mute',
  ];
  $cls = $map[$v] ?? 'mute';
@endphp
<span class="badge b-{{ $cls }}">{{ str_replace('_',' ',$v) }}</span>
