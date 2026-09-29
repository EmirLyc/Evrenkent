{{--
    Sayfalı okuma (Faz G4 — "Yayın Yönetimi" belgesi, 2) Sayfa Oranı: "okurun sayfayı büyütmesi
    ile punto büyüklüğünü değiştirebilmesi farklı şeyler; punto üzerinde değişiklik hakkı olursa
    tüm kitap dizgisini bozar; oran kitabın tüm sayfaları için geçerli"). Alpine 'pagedReader'
    (resources/js/paged-reader.js).

    Sayfa her cihazda aynı: 736 px genişlik; yanlarda 96, üstte ve altta 56 px boşluk → 544 px metin
    (editörle aynı ölçü ve aynı dizgi: Georgia 16), yükseklik yazarın oranından. Metin CSS
    sütunlarıyla satır satır sayfalara akıyor; okur sayfayı yakınlaştırıyor (transform: scale),
    dizgi değişmiyor. Alt ortada sayfa numarası (mockup 3); giriş bölümünün sayfaları roma rakamıyla.

    Faz H1 ("Okurun Gözünden"): İçindekiler çekmecesi ($contents — WorkOutline::contents; sayfa
    numaraları sayfalar dizilince yazılıyor, "Sayfaya git") ve kitap içi arama (giriş yapmış okura).
    Başlıktaki ☰ ve 🔍 düğmeleri reader-toc / reader-search olaylarıyla açıyor.

    İçerik: slot'taki <section data-chapter data-title> blokları (kitapta her bölüm; bölüm yeni
    sayfada başlar; giriş bölümünde data-preface). $after: sayfanın altındaki alan.
--}}
@props([
    'ratio' => '13x20',
    'storageKey',
    'readBase' => null,
    'positionUrl' => null,
    'initialChapter' => null,
    'contents' => [],
])

@php
    [$w, $h] = array_map('floatval', explode('x', $ratio) + [1 => 20]);
    $pageWidth = 736;
    $marginX = 96;
    $marginY = 56;
    $pageHeight = (int) round($pageWidth * ($h > 0 && $w > 0 ? $h / $w : 20 / 13));
    $textHeight = $pageHeight - 2 * $marginY;

    // İçindekiler satırları düz sırayla (sayfa numarası ve "okunan yer" bu sırayla eşleşiyor).
    $toc = [];
    foreach ($contents as $g => $group) {
        $toc[] = ['url' => $group['url'], 'group' => $g];
        foreach ($group['items'] as $item) {
            $toc[] = ['url' => $item['url'], 'group' => $g];
        }
    }

    $config = [
        'pageWidth' => $pageWidth,
        'storageKey' => 'evrenkent.reader.'.$storageKey,
        'readBase' => $readBase,
        'positionUrl' => $positionUrl,
        'initialChapter' => $initialChapter,
        'toc' => $toc,
        'canSearch' => auth()->check(),
    ];
    $tocIndex = 0;
@endphp

<div x-data="pagedReader(@js($config))"
     @pager-zoom.window="zoom($event.detail)"
     @pager-zoom-reset.window="zoomReset()"
     @reader-toc.window="openToc()"
     @reader-search.window="openSearch()"
     class="paged-reader">
    <div x-ref="viewport" class="overflow-x-auto overflow-y-hidden pb-2" @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)" @click="tap($event)">
        <div x-ref="sizer" class="relative mx-auto" style="width: {{ $pageWidth }}px; height: {{ $pageHeight }}px" :style="{ width: Math.round({{ $pageWidth }} * scale) + 'px', height: Math.round({{ $pageHeight }} * scale) + 'px' }">
            <div class="rt-page reader-paper absolute left-0 top-0 origin-top-left" style="width: {{ $pageWidth }}px; height: {{ $pageHeight }}px" :style="{ transform: `scale(${scale})` }">
                <div x-ref="clip" @scroll="clipScrolled()" class="absolute overflow-hidden" style="left: {{ $marginX }}px; top: {{ $marginY }}px; width: {{ $pageWidth - 2 * $marginX }}px; height: {{ $textHeight }}px">
                    <div x-ref="flow" class="rt-flow" :class="ready || 'invisible'" style="height: {{ $textHeight }}px; --rt-text-height: {{ $textHeight }}px" :style="{ transform: `translateX(${-page * {{ $pageWidth }}}px)` }">
                        {{ $slot }}
                    </div>
                </div>
                <div class="rt-page-number" x-show="ready" x-text="label(page)" aria-hidden="true"></div>
                <p x-show="!ready" class="reader-muted absolute inset-x-0 top-1/3 text-center font-reading text-lg">Sayfalar hazırlanıyor…</p>
            </div>
        </div>
    </div>

    <nav data-reader-chrome class="mx-auto mt-4 flex w-full max-w-[46rem] items-center justify-between gap-3 px-1 font-sans transition-opacity duration-300" :class="$store.pager.chrome || 'opacity-0 pointer-events-none'" aria-label="Sayfalar">
        <button type="button" class="reader-btn" @click="prev()" :disabled="page === 0" aria-label="Önceki sayfa">
            <x-heroicon-o-chevron-left class="w-4 h-4" /> <span class="hidden sm:inline">Önceki</span>
        </button>
        <div class="min-w-0 text-center" aria-live="polite">
            <div class="reader-heading text-sm font-medium tabular-nums" x-text="`Sayfa ${label(page)} / ${total - front}`"></div>
            <div class="reader-muted truncate text-xs" x-show="chapterTitle" x-text="chapterTitle"></div>
        </div>
        <button type="button" class="reader-btn-primary" @click="next()" :disabled="page >= total - 1" aria-label="Sonraki sayfa">
            <span class="hidden sm:inline">Sonraki</span> <x-heroicon-o-chevron-right class="w-4 h-4" />
        </button>
    </nav>

    {{ $after ?? '' }}

    {{-- İçindekiler (Okuma modu 9): soldan açılır; bölümler açılıp kapanır, okunan yer altın çizgili. --}}
    <div x-show="tocOpen" x-cloak class="fixed inset-0 z-40 font-sans" @keydown.escape.window="tocOpen = false">
        <div class="absolute inset-0 bg-slate-950/45" x-show="tocOpen" x-transition.opacity @click="tocOpen = false"></div>
        <nav class="reader-panel absolute inset-y-0 left-0 flex w-[26rem] max-w-[88vw] flex-col rounded-none border-y-0 border-l-0" x-show="tocOpen"
             x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="-translate-x-full"
             aria-label="İçindekiler">
            <div class="flex items-center justify-between px-6 pb-3 pt-5">
                <span class="reader-heading font-reading text-[1.7rem] font-semibold">İçindekiler</span>
                <button type="button" class="reader-icon-btn" @click="tocOpen = false" aria-label="Kapat"><x-heroicon-o-x-mark class="w-6 h-6" /></button>
            </div>

            <div x-ref="toc" class="flex-1 overflow-y-auto overscroll-contain px-4 pb-4">
                @if ($contents === [])
                    <p class="reader-muted px-2 py-6 text-center text-sm">Bu metinde başlık yok.</p>
                @else
                    <ol class="font-reading text-[1.05rem] leading-snug">
                        @foreach ($contents as $g => $group)
                            <li class="reader-rule {{ $loop->first ? '' : 'mt-2 border-t pt-2' }}">
                                <div class="flex items-start">
                                    @if ($group['items'])
                                        <button type="button" class="reader-muted mt-1 grid h-7 w-6 shrink-0 place-items-center rounded hover:opacity-70" @click="tocToggle({{ $g }})" :aria-expanded="tocGroupOpen({{ $g }}).toString()" aria-label="{{ $group['text'] }} alt başlıkları">
                                            <x-heroicon-o-chevron-right class="w-4 h-4 transition-transform" ::class="tocGroupOpen({{ $g }}) && 'rotate-90'" />
                                        </button>
                                    @else
                                        <span class="w-6 shrink-0"></span>
                                    @endif
                                    <a href="{{ $group['url'] }}" class="reader-toc-link font-medium" :class="tocCurrent === {{ $tocIndex }} && 'is-current'">
                                        <span class="min-w-0 flex-1 break-words">@if ($group['number'] !== '')<span class="mr-1.5 tabular-nums">{{ $group['number'] }}</span>@endif{{ $group['text'] }}</span>
                                        <span class="reader-muted shrink-0 pl-2 text-sm tabular-nums" x-text="tocLabel({{ $tocIndex }})"></span>
                                    </a>
                                </div>
                                @php $tocIndex++; @endphp
                                @if ($group['items'])
                                    <ol x-show="tocGroupOpen({{ $g }})" class="pb-1 pl-6">
                                        @foreach ($group['items'] as $item)
                                            <li style="padding-left: {{ max(0, $item['level'] - 2) * 1.25 }}rem">
                                                <a href="{{ $item['url'] }}" class="reader-toc-link text-[0.97rem] {{ empty($item['concept']) ? '' : 'italic' }}" :class="tocCurrent === {{ $tocIndex }} && 'is-current'">
                                                    <span class="min-w-0 flex-1 break-words">@if ($item['number'] !== '')<span class="reader-muted mr-2 text-[0.9em] tabular-nums">{{ rtrim($item['number'], '.') }}</span>@endif{{ $item['text'] }}</span>
                                                    <span class="reader-muted shrink-0 pl-2 text-sm tabular-nums" x-text="tocLabel({{ $tocIndex }})"></span>
                                                </a>
                                            </li>
                                            @php $tocIndex++; @endphp
                                        @endforeach
                                    </ol>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            <form class="reader-rule border-t px-6 pb-2 pt-4" @submit.prevent="goToPage()">
                <div class="flex items-center gap-3">
                    <label for="reader-goto" class="reader-heading shrink-0 font-reading text-lg">Sayfaya git:</label>
                    <input id="reader-goto" x-model="gotoValue" type="text" inputmode="numeric" autocomplete="off" placeholder="Sayfa no." class="reader-input h-11 min-w-0 flex-1 font-reading text-base" :aria-invalid="gotoError ? 'true' : 'false'">
                    <button type="submit" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-500 text-white hover:bg-brand-600" aria-label="Sayfaya git"><x-heroicon-o-chevron-right class="w-5 h-5" /></button>
                </div>
                <p x-show="gotoError" x-cloak x-text="gotoError" class="mt-2 text-sm text-red-600" role="alert"></p>
            </form>
            <div class="px-6 pb-6 pt-3 text-center">
                <x-reader-ornament />
                <p class="reader-heading mt-2 font-reading text-sm tracking-[0.35em]">EVRENKENT</p>
                <p class="reader-muted mt-0.5 font-reading text-sm italic tracking-wide">Okumanın yeni bir evreni var.</p>
            </div>
        </nav>
    </div>

    {{-- Kitap içi arama (başlıktaki 🔍): sonuç sayfa numarası ve çevresiyle; tıklayınca o sayfa, bulunan yer boyalı. --}}
    <div x-show="searchOpen" x-cloak class="fixed inset-x-0 top-14 z-40 flex justify-end px-2 font-sans sm:px-6" @keydown.escape.window="closeSearch()">
        <div class="reader-panel mt-2 flex max-h-[min(34rem,calc(100vh-5rem))] w-full flex-col sm:w-[26rem]" @click.outside="if (!$event.target.closest('[data-reader-search-toggle]')) closeSearch()" role="dialog" aria-label="Kitapta ara">
            @if (auth()->check())
                <div class="flex items-center gap-2 p-3">
                    <div class="relative min-w-0 flex-1">
                        <x-heroicon-o-magnifying-glass class="reader-muted pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <input x-ref="searchInput" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="openResult(0)" type="search" autocomplete="off" placeholder="Bu metinde ara…" class="reader-input h-10 w-full pl-9">
                    </div>
                    <button type="button" class="reader-icon-btn" @click="closeSearch()" aria-label="Aramayı kapat"><x-heroicon-o-x-mark class="w-5 h-5" /></button>
                </div>
                <p class="reader-muted px-4 pb-2 text-xs" x-show="query.trim().length >= 2" x-text="results.length ? (results.length >= 200 ? 'İlk 200 sonuç' : results.length + ' sonuç') : 'Sonuç bulunamadı.'"></p>
                <ol class="reader-rule flex-1 overflow-y-auto overscroll-contain border-t" x-show="results.length">
                    <template x-for="(result, index) in results" :key="index">
                        <li>
                            <button type="button" class="reader-rule block w-full border-b px-4 py-2.5 text-left hover:bg-black/5" @click="openResult(index)">
                                <span class="reader-muted flex justify-between gap-3 text-xs">
                                    <span class="truncate" x-text="result.chapter"></span>
                                    <span class="shrink-0 tabular-nums" x-text="'s. ' + label(result.page)"></span>
                                </span>
                                <span class="mt-0.5 block font-reading text-[0.98rem] leading-snug">
                                    <span x-text="result.before"></span><mark class="reader-search-mark" x-text="result.match"></mark><span x-text="result.after"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                </ol>
            @else
                <div class="p-5 text-center text-sm">
                    <p>Metnin içinde arama yapmak için giriş yapın.</p>
                    <a href="{{ route('login') }}" class="reader-btn-primary mt-3">Giriş Yap</a>
                </div>
            @endif
        </div>
    </div>
</div>
