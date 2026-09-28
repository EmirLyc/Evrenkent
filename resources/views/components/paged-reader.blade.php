{{--
    Sayfalı okuma (Faz G4 — "Yayın Yönetimi" belgesi, 2) Sayfa Oranı: "okurun sayfayı büyütmesi
    ile punto büyüklüğünü değiştirebilmesi farklı şeyler; punto üzerinde değişiklik hakkı olursa
    tüm kitap dizgisini bozar; oran kitabın tüm sayfaları için geçerli"). Alpine 'pagedReader'
    (resources/js/paged-reader.js).

    Sayfa her cihazda aynı: 736 px genişlik, 56 px kenar → 624 px metin (editörle aynı ölçü ve
    aynı dizgi: Georgia 16), yükseklik yazarın oranından. Metin CSS sütunlarıyla satır satır
    sayfalara akıyor; okur sayfayı yakınlaştırıyor (transform: scale), dizgi değişmiyor. Alt
    ortada sayfa numarası (mockup 3).

    İçerik: slot'taki <section data-chapter data-title> blokları (kitapta her bölüm; bölüm yeni
    sayfada başlar). $after: sayfanın altındaki alan (not formu, "tamamlandı" düğmesi).
--}}
@props([
    'ratio' => '13x20',
    'storageKey',
    'readBase' => null,
    'positionUrl' => null,
    'initialChapter' => null,
])

@php
    [$w, $h] = array_map('floatval', explode('x', $ratio) + [1 => 20]);
    $pageWidth = 736;
    $padding = 56;
    $pageHeight = (int) round($pageWidth * ($h > 0 && $w > 0 ? $h / $w : 20 / 13));
    $textHeight = $pageHeight - 2 * $padding;
    $config = [
        'pageWidth' => $pageWidth,
        'storageKey' => 'evrenkent.reader.'.$storageKey,
        'readBase' => $readBase,
        'positionUrl' => $positionUrl,
        'initialChapter' => $initialChapter,
    ];
@endphp

<div x-data="pagedReader(@js($config))" @pager-zoom.window="zoom($event.detail)" class="paged-reader">
    <div x-ref="viewport" class="overflow-x-auto overflow-y-hidden pb-2" @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)">
        <div x-ref="sizer" class="relative mx-auto" style="width: {{ $pageWidth }}px; height: {{ $pageHeight }}px" :style="{ width: Math.round({{ $pageWidth }} * scale) + 'px', height: Math.round({{ $pageHeight }} * scale) + 'px' }">
            <div class="rt-page reader-paper absolute left-0 top-0 origin-top-left" style="width: {{ $pageWidth }}px; height: {{ $pageHeight }}px" :style="{ transform: `scale(${scale})` }">
                <div x-ref="clip" @scroll="clipScrolled()" class="absolute overflow-hidden" style="left: {{ $padding }}px; top: {{ $padding }}px; width: {{ $pageWidth - 2 * $padding }}px; height: {{ $textHeight }}px">
                    <div x-ref="flow" class="rt-flow" :class="ready || 'invisible'" style="height: {{ $textHeight }}px; --rt-text-height: {{ $textHeight }}px" :style="{ transform: `translateX(${-page * {{ $pageWidth }}}px)` }">
                        {{ $slot }}
                    </div>
                </div>
                <div class="rt-page-number" x-show="ready" x-text="page + 1" aria-hidden="true"></div>
                <p x-show="!ready" class="absolute inset-x-0 top-1/3 text-center font-reading text-lg text-slate-500">Sayfalar hazırlanıyor…</p>
            </div>
        </div>
    </div>

    <nav class="mx-auto mt-4 flex w-full max-w-[46rem] items-center justify-between gap-3 px-1 font-sans" aria-label="Sayfalar">
        <button type="button" class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-gold/40 bg-white/50 px-3 text-sm text-slate-700 hover:bg-white/80 disabled:opacity-40" @click="prev()" :disabled="page === 0" aria-label="Önceki sayfa">
            <x-heroicon-o-chevron-left class="w-4 h-4" /> <span class="hidden sm:inline">Önceki</span>
        </button>
        <div class="min-w-0 text-center" aria-live="polite">
            <div class="text-sm font-medium text-slate-700 tabular-nums" x-text="`Sayfa ${page + 1} / ${total}`"></div>
            <div class="truncate text-xs text-slate-500" x-show="chapterTitle" x-text="chapterTitle"></div>
        </div>
        <button type="button" class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg bg-navy px-3 text-sm text-white hover:bg-navy/90 disabled:opacity-40" @click="next()" :disabled="page >= total - 1" aria-label="Sonraki sayfa">
            <span class="hidden sm:inline">Sonraki</span> <x-heroicon-o-chevron-right class="w-4 h-4" />
        </button>
    </nav>

    {{ $after ?? '' }}
</div>
