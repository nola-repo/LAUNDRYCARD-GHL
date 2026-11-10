@if ($paginator->hasPages())
<nav class="pg" role="navigation" aria-label="Pagination">
  <p class="pg-info">Showing <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong> of <strong>{{ number_format($paginator->total()) }}</strong></p>
  <ul class="pg-list">
    @if ($paginator->onFirstPage())
      <li><span class="pg-item disabled" aria-disabled="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></span></li>
    @else
      <li><a class="pg-item" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></a></li>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))
        <li><span class="pg-item disabled">{{ $element }}</span></li>
      @endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          <li>
            @if ($page == $paginator->currentPage())
              <span class="pg-item active" aria-current="page">{{ $page }}</span>
            @else
              <a class="pg-item" href="{{ $url }}">{{ $page }}</a>
            @endif
          </li>
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <li><a class="pg-item" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></a></li>
    @else
      <li><span class="pg-item disabled" aria-disabled="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></span></li>
    @endif
  </ul>
</nav>
@endif

