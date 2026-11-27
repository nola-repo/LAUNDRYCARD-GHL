@props(['title', 'eyebrow', 'description'])
@once
  @push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-actions.css') }}?v={{ filemtime(public_path('css/admin-actions.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-workspace.css') }}?v={{ filemtime(public_path('css/admin-workspace.css')) }}">
  @endpush
  @push('scripts')
    <script src="{{ asset('js/sticky-banner.js') }}?v={{ filemtime(public_path('js/sticky-banner.js')) }}" defer></script>
    <script src="{{ asset('js/admin-workspace.js') }}?v={{ filemtime(public_path('js/admin-workspace.js')) }}" defer></script>
  @endpush
@endonce
<div {{ $attributes->class(['admin-workspace']) }} data-sticky-page>
  <header class="workspace-hero" id="workspace-top">
    <div><p class="workspace-eyebrow">{{ $eyebrow }}</p><h1>{{ $title }}</h1><p class="workspace-intro">{{ $description }}</p></div>
    @isset($context)<div class="workspace-hero-context">{{ $context }}</div>@endisset
  </header>
  <div class="workspace-toolbar" data-sticky-banner>
    <a class="workspace-toolbar-title" href="#workspace-top">{{ $title }}</a>
    @isset($navigation)<nav aria-label="Page sections">{{ $navigation }}</nav>@endisset
    @isset($actions)<div class="workspace-toolbar-actions">{{ $actions }}</div>@endisset
  </div>
  {{ $slot }}
</div>
