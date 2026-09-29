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

    Faz H3: okur metni seçince Alıntıla / Not Al / Fosforla ($marks — okurun bu eserdeki işaretleri;
    ziyaretçide null, seçim menüsü çıkmaz). Not simgesi sayfanın sol kenar boşluğunda durduğu için
    kırpma kutusu metnin 40 px solundan başlıyor (akış o kadar içeride; komşu sayfa 192 px uzakta).

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
    'marks' => null,
    'noteableType' => null,
    'noteableId' => null,
])

@php
    [$w, $h] = array_map('floatval', explode('x', $ratio) + [1 => 20]);
    $pageWidth = 736;
    $marginX = 96;
    $marginY = 56;
    $gutter = 40;
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
        'marks' => auth()->check() && $marks !== null ? array_values($marks) : null,
        'noteableType' => $noteableType,
        'noteableId' => $noteableId,
        'marksUrl' => auth()->check() ? route('panel.isaretler.ekle') : null,
        'markUrl' => auth()->check() ? route('panel.isaretler.sil', ['note' => '__ID__']) : null,
        'quotesUrl' => auth()->check() ? route('panel.alintilarim') : null,
        'notesUrl' => auth()->check() ? route('panel.notlarim') : null,
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
                <div x-ref="clip" @scroll="clipScrolled()" class="absolute overflow-hidden" style="left: {{ $marginX - $gutter }}px; top: {{ $marginY }}px; width: {{ $pageWidth - 2 * $marginX + 2 * $gutter }}px; height: {{ $textHeight }}px">
                    <div x-ref="flow" class="rt-flow" :class="ready || 'invisible'" style="margin-left: {{ $gutter }}px; height: {{ $textHeight }}px; --rt-text-height: {{ $textHeight }}px" :style="{ transform: `translateX(${-page * {{ $pageWidth }}}px)` }">
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

    @if ($config['marks'] !== null)
        {{-- Seçim menüsü (Okuma modu 1): Alıntıla · Not Al · Fosforla. --}}
        <div x-show="selBar" x-cloak class="reader-panel fixed z-40 flex items-stretch rounded-xl p-1 font-reading text-[1.02rem] shadow-xl"
             :class="selBar?.above && '-translate-y-full'" :style="selBar && { left: selBar.x + 'px', top: selBar.y + 'px' }"
             @mousedown.prevent role="toolbar" aria-label="Seçilen metin">
            <button type="button" class="reader-mark-btn" @click="quoteSelection()" :disabled="markBusy">
                <span class="font-serif text-xl font-bold leading-none" aria-hidden="true">&ldquo;</span> Alıntıla
            </button>
            <span class="reader-rule my-2 border-l" aria-hidden="true"></span>
            <button type="button" class="reader-mark-btn" @click="startNote()" :disabled="markBusy">
                <x-heroicon-o-document-text class="w-5 h-5" /> Not Al
            </button>
            <span class="reader-rule my-2 border-l" aria-hidden="true"></span>
            <button type="button" class="reader-mark-btn" @click="highlightSelection()" :disabled="markBusy">
                <x-heroicon-o-pencil class="w-5 h-5" /> Fosforla
            </button>
        </div>

        {{-- Not Al (Okuma modu 11) / Notum (Okuma modu 13): seçimin ya da not simgesinin altında. --}}
        <div x-show="notePanel" x-cloak class="fixed inset-0 z-40" @keydown.escape.window="notePanel?.mode === 'new' ? cancelNote() : (notePanel = null)">
            <div class="absolute inset-0" @click="notePanel?.mode === 'new' ? cancelNote() : (notePanel = null)"></div>
            <div class="reader-panel reader-note absolute w-[26rem] max-w-[calc(100vw-1rem)] overflow-hidden font-reading"
                 :class="notePanel?.above && '-translate-y-full'" :style="notePanel && { left: notePanel.x + 'px', top: notePanel.y + 'px' }"
                 role="dialog" :aria-label="notePanel?.mode === 'new' ? 'Not al' : 'Notum'">
                <div class="reader-note-head flex items-center gap-2.5 px-4 py-2.5">
                    <x-heroicon-o-chat-bubble-bottom-center-text class="w-6 h-6" />
                    <span class="font-sans text-sm font-semibold tracking-[0.14em]" x-text="notePanel?.mode === 'new' ? 'NOT AL' : 'NOTUM'"></span>
                    <button type="button" class="ml-auto grid h-8 w-8 place-items-center rounded-md hover:bg-black/5" @click="notePanel?.mode === 'new' ? cancelNote() : (notePanel = null)" aria-label="Kapat"><x-heroicon-o-x-mark class="w-5 h-5" /></button>
                </div>
                <div class="px-5 pb-4 pt-3">
                    <p class="reader-muted reader-rule line-clamp-4 border-b pb-3 italic leading-snug" x-text="'“' + (notePanel?.quote || '').replace(/\n+/g, ' ') + '”'"></p>

                    <template x-if="notePanel && notePanel.mode !== 'view'">
                        <form class="pt-3" @submit.prevent="notePanel.mode === 'new' ? saveNote() : updateNote()">
                            <label for="reader-note-text" class="reader-heading text-base">Notun</label>
                            <textarea id="reader-note-text" x-ref="noteText" rows="3" maxlength="5000" placeholder="Düşünceni yaz…" class="reader-input mt-1.5 block w-full resize-y py-2 font-reading text-base"
                                      x-model="notePanel[notePanel.mode === 'new' ? 'text' : 'draft']" @keydown.meta.enter="$el.form.requestSubmit()" @keydown.ctrl.enter="$el.form.requestSubmit()"></textarea>
                            <div class="mt-3 flex items-center justify-end gap-2">
                                <button type="button" class="reader-heading rounded-md px-3 py-2 text-base hover:opacity-70" @click="notePanel.mode === 'new' ? cancelNote() : (notePanel = { ...notePanel, mode: 'view' })">İptal</button>
                                <button type="submit" class="reader-note-save rounded-md px-5 py-2 text-base" :disabled="markBusy || !(notePanel[notePanel.mode === 'new' ? 'text' : 'draft'] || '').trim()">Kaydet</button>
                            </div>
                        </form>
                    </template>

                    <template x-if="notePanel && notePanel.mode === 'view'">
                        <div class="pt-3">
                            <p class="whitespace-pre-line text-[1.05rem] leading-relaxed" x-text="notePanel.text"></p>
                            <div class="reader-rule mt-4 flex items-center justify-between border-t pt-3 font-sans text-sm">
                                <button type="button" class="reader-note-action inline-flex items-center gap-1.5 rounded-md px-2 py-1 hover:bg-black/5" @click="editNote()">
                                    <x-heroicon-o-pencil class="w-4 h-4" /> Düzenle
                                </button>
                                <div class="flex items-center gap-2" x-show="notePanel.confirm" x-cloak>
                                    <span class="reader-muted">Silinsin mi?</span>
                                    <button type="button" class="rounded-md px-2 py-1 font-medium text-red-600 hover:bg-red-500/10" @click="deleteMark(notePanel.id)">Sil</button>
                                    <button type="button" class="reader-muted rounded-md px-2 py-1 hover:bg-black/5" @click="notePanel.confirm = false">Vazgeç</button>
                                </div>
                                <button type="button" x-show="!notePanel.confirm" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-red-600 hover:bg-red-500/10" @click="notePanel.confirm = true">
                                    <x-heroicon-o-trash class="w-4 h-4" /> Sil
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Alıntıya / fosfora tıklayınca: kaldırma. --}}
        <div x-show="markMenu" x-cloak class="reader-panel fixed z-40 w-60 max-w-[calc(100vw-1rem)] p-1.5 font-sans text-sm"
             :style="markMenu && { left: markMenu.x + 'px', top: markMenu.y + 'px' }" @click.outside="markMenu = null" @keydown.escape.window="markMenu = null" role="menu">
            <p class="reader-muted px-3 pb-1 pt-1.5 text-xs font-semibold uppercase tracking-wider" x-text="markMenu?.type === 'alinti' ? 'Alıntı' : 'Fosfor'"></p>
            <a x-show="markMenu?.type === 'alinti'" href="{{ $config['quotesUrl'] }}" class="reader-heading flex items-center gap-2 rounded-md px-3 py-2 hover:bg-black/5" role="menuitem">
                <x-heroicon-o-chat-bubble-left-right class="w-4 h-4" /> Alıntılarım
            </a>
            <button type="button" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-red-600 hover:bg-red-500/10" @click="deleteMark(markMenu.id)" :disabled="markBusy" role="menuitem">
                <x-heroicon-o-trash class="w-4 h-4" /> <span x-text="markMenu?.type === 'alinti' ? 'Alıntıyı kaldır' : 'Fosforu kaldır'"></span>
            </button>
        </div>

        <div x-show="toast" x-cloak x-transition.opacity class="fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 font-sans" role="status">
            <div class="flex max-w-lg items-center gap-3 rounded-full bg-slate-900 px-5 py-2.5 text-sm text-white shadow-lg">
                <span x-text="toast?.text"></span>
                <a x-show="toast?.link" :href="toast?.link?.href" class="shrink-0 font-semibold text-brand-300 underline" x-text="toast?.link?.text"></a>
            </div>
        </div>
    @endif
</div>
