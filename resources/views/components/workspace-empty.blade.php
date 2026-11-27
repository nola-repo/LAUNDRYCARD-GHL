@props(['title', 'description', 'reset' => null])
<div class="workspace-empty"><span aria-hidden="true" class="workspace-empty-mark">—</span><strong>{{ $title }}</strong><p>{{ $description }}</p>@if($reset)<a class="admin-action" href="{{ $reset }}">Clear filters</a>@endif</div>
