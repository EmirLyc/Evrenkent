{{-- Mockup 1.1'deki sade sayfalama: ‹ 1 2 3 › (etkin sayfa marka renginde). --}}
@if ($paginator->hasPages())
    <nav class="flex items-center gap-1.5" aria-label="Sayfalama">
        @php $base = 'flex items-center justify-center min-w-[2.25rem] h-9 px-2 rounded-md border text-sm'; @endphp
        @if ($paginator->onFirstPage())
            <span class="{{ $base }} border-slate-200 text-slate-300" aria-hidden="true"><x-heroicon-o-chevron-left class="w-4 h-4" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="{{ $base }} border-slate-200 text-slate-600 hover:bg-slate-50" aria-label="Önceki sayfa"><x-heroicon-o-chevron-left class="w-4 h-4" /></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-slate-400">…</span>
            @else
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $base }} border-brand-400 bg-brand-400 text-white font-medium" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $base }} border-slate-200 text-slate-600 hover:bg-slate-50">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="{{ $base }} border-slate-200 text-slate-600 hover:bg-slate-50" aria-label="Sonraki sayfa"><x-heroicon-o-chevron-right class="w-4 h-4" /></a>
        @else
            <span class="{{ $base }} border-slate-200 text-slate-300" aria-hidden="true"><x-heroicon-o-chevron-right class="w-4 h-4" /></span>
        @endif
    </nav>
@endif
