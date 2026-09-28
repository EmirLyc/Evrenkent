{{-- Izgara / liste görünümü (mockup 1.1'deki sağ üst iki düğme). --}}
<div class="flex rounded-md border border-slate-300 bg-white overflow-hidden shrink-0" role="group" aria-label="Görünüm">
    @foreach (['izgara' => ['Izgara görünümü', 'squares-2x2'], 'liste' => ['Liste görünümü', 'bars-3']] as $key => [$label, $icon])
        <a href="{{ request()->fullUrlWithQuery(['gorunum' => $key === 'izgara' ? null : $key, 'page' => null]) }}"
           class="flex items-center justify-center w-10 h-[2.375rem] {{ $view === $key ? 'bg-brand-50 text-brand-700' : 'text-slate-500 hover:bg-slate-50' }}"
           title="{{ $label }}" aria-label="{{ $label }}" @if ($view === $key) aria-current="true" @endif>
            @svg('heroicon-o-'.$icon, 'w-5 h-5')
        </a>
    @endforeach
</div>
