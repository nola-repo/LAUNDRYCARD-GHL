@props(['value'])
@if($value)<time class="workspace-time" datetime="{{ $value->toIso8601String() }}"><strong>{{ $value->copy()->utc()->format('M d, Y') }}</strong><span>{{ $value->copy()->utc()->format('H:i:s') }} UTC</span></time>@else<span class="workspace-note">Not recorded</span>@endif
