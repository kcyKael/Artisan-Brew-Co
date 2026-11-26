@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-white/10 text-sm">
        <div class="text-[#BDB5AA] tabular-nums">
            Showing <span class="font-medium text-[#F5F0E8]">{{ $paginator->firstItem() }}</span>
            to <span class="font-medium text-[#F5F0E8]">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-[#F5F0E8]">{{ $paginator->total() }}</span> results
        </div>

        <div class="flex items-center gap-1.5">
            @if ($paginator->onFirstPage())
                <span class="abc-btn-secondary opacity-40 cursor-not-allowed py-1.5 px-3 text-xs">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="abc-btn-secondary py-1.5 px-3 text-xs">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-[#BDB5AA]">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="abc-btn-primary py-1.5 px-3 text-xs tabular-nums">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="abc-btn-secondary py-1.5 px-3 text-xs tabular-nums">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="abc-btn-secondary py-1.5 px-3 text-xs">Next</a>
            @else
                <span class="abc-btn-secondary opacity-40 cursor-not-allowed py-1.5 px-3 text-xs">Next</span>
            @endif
        </div>
    </nav>
@endif
