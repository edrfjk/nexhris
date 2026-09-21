@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-sand-500">
            Showing <span class="font-medium text-sand-700">{{ $paginator->firstItem() ?? 0 }}</span>
            to <span class="font-medium text-sand-700">{{ $paginator->lastItem() ?? 0 }}</span>
            of <span class="font-medium text-sand-700">{{ $paginator->total() }}</span>
        </p>

        <div class="inline-flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm btn-secondary cursor-not-allowed opacity-50" aria-disabled="true">Previous</span>
            @else
                <a class="btn btn-sm btn-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            <div class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="px-2 text-xs text-sand-400">{{ $element }}</span>
                    @else
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="btn btn-sm btn-primary cursor-default" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="btn btn-sm btn-secondary" href="{{ $url }}"
                                   aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a class="btn btn-sm btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="btn btn-sm btn-secondary cursor-not-allowed opacity-50" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
