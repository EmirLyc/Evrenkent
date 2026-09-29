{{--
    Okuma modu (Faz F4, mockup 3 "Okuma modundayken sayfanın görünümü"). Uygulama kabuğu
    (header arama/sepet, sidebar) yok — dikkat metinde.

    Faz H1 ("Okurun Gözünden" — Okuma modu 7, 8, 9): başlıkta solda ← ve ☰ (İçindekiler), sağda 🔍
    (kitap içi arama) ve Aa (Okuma Görünümü: tema, gece modu, sözlük kavramları, sayfa büyüklüğü).
    Sayfalı okumada okur sayfa çevirince başlık ve sayfa düğmeleri çekiliyor ("Okurken arayüz
    mümkün olduğunca ortadan kaybolmalı"); fare kıpırdayınca ya da sayfaya dokununca geri geliyor.
    Telefonda (alt alta sayfalar, H6) aşağı kaydırınca çekiliyor, yukarı kaydırınca geliyor.

    Section'lar: title, reader_back_url, reader_back_label, reader_paged (içerik x-paged-reader),
    content. Sayfasız içerikte klavye ←/→ sayfadaki [data-reader-prev] / [data-reader-next]
    bağlantılarına gider.

    Okurun yazı boyutu ve yazı tipi ayarı yok (G4 / H1 kararı: punto ya da yazı tipi değişirse
    dizgi ve sayfa numaraları bozulur); sayfa büyütülüyor.
--}}
<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Evrenkent') }} — @yield('title', 'Okuma')</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=fraunces:500,600,600i|figtree:400,500,600|eb-garamond:400,400i,500,600,600i&display=swap" rel="stylesheet" />
        {{-- Yazarın seçebildiği yazı tipleri (editördeki liste) — okur da aynı dizgiyi görsün. --}}
        <link href="{{ \App\Support\RichText::WEB_FONTS_URL }}" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="reader-body font-sans antialiased" data-reader-theme="acik" data-concepts="off">
        {{-- Okurun görünüm tercihi ilk boyamadan önce (açık temadan koyuya "yanıp sönme" olmasın). --}}
        <script>
            (() => {
                let prefs = {};
                try { prefs = JSON.parse(localStorage.getItem('evrenkent.reader.prefs')) || {}; } catch (e) {}
                const dark = prefs.auto && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.body.dataset.readerTheme = dark ? 'koyu' : (['acik', 'sepya', 'koyu'].includes(prefs.theme) ? prefs.theme : 'acik');
                document.body.dataset.concepts = prefs.concepts ? 'on' : 'off';
            })();
        </script>

        <div x-data="reader" @keydown.window="keyNav($event)" @scroll.window.throttle.50ms="trackProgress()"
             @pager-turned.window="hideChrome()" @pager-reveal.window="showChrome()" @reader-tap.window="toggleChrome()" @pointermove.window.throttle.200ms="pointerMoved($event)"
             class="min-h-screen flex flex-col">
            <header data-reader-chrome class="reader-chrome sticky top-0 z-30 border-b backdrop-blur transition-opacity duration-300"
                    :class="$store.pager.chrome || 'opacity-0 pointer-events-none'" @focusin="showChrome()">
                {{-- Sayfasız görünümde (sözlük maddesi, Benim Seçkim) geri bağlantısının adı da yazıyor
                     ("← Alıntılarıma Dön"); sayfalı okumada sadece ok (Okuma modu 7). Arama sayfalı okumada
                     ve reader_searchable tanımlayan sayfada (Benim Seçkim — kendi araması reader-search'ü dinler). --}}
                @php $searchable = \Illuminate\Support\Facades\View::hasSection('reader_searchable') ? 'true' : 'false'; @endphp
                <div class="mx-auto flex h-14 max-w-4xl items-center gap-1 px-2 sm:px-6">
                    <a href="@yield('reader_back_url', route('home'))" class="reader-icon-btn !inline-flex items-center gap-2" title="@yield('reader_back_label', 'Geri')" aria-label="Geri: @yield('reader_back_label', 'Geri')">
                        <x-heroicon-o-arrow-left class="w-6 h-6" />
                        <span x-show="!$store.pager.active" class="hidden max-w-[16rem] truncate pr-1 font-reading text-[0.95rem] sm:inline" aria-hidden="true">@yield('reader_back_label', 'Geri')</span>
                    </a>
                    <span x-show="$store.pager.active" x-cloak class="reader-rule mx-1 h-6 border-l" aria-hidden="true"></span>
                    <button type="button" x-show="$store.pager.active" x-cloak class="reader-icon-btn" @click="$dispatch('reader-toc')" aria-label="İçindekiler">
                        <x-heroicon-o-bars-3 class="w-6 h-6" />
                    </button>

                    <div class="ml-auto flex shrink-0 items-center gap-1">
                        <button type="button" x-show="$store.pager.active || {{ $searchable }}" x-cloak data-reader-search-toggle class="reader-icon-btn" @click="$dispatch('reader-search')" aria-label="Metinde ara">
                            <x-heroicon-o-magnifying-glass class="w-6 h-6" />
                        </button>
                        <span x-show="$store.pager.active || {{ $searchable }}" x-cloak class="reader-rule mx-1 h-6 border-l" aria-hidden="true"></span>

                        {{-- Aa: Okuma Görünümü (Okuma modu 8 / 14). --}}
                        <div class="relative" @click.outside="aa = false" @keydown.escape.window="aa = false">
                            <button type="button" class="reader-icon-btn px-2 font-reading text-[1.6rem] leading-none" @click="aa = !aa" :aria-expanded="aa.toString()" aria-haspopup="dialog" aria-label="Okuma görünümü">Aa</button>

                            <div x-show="aa" x-cloak x-transition.origin.top.right class="reader-panel absolute right-0 top-full mt-2 w-[21rem] max-w-[calc(100vw-1rem)] font-reading" role="dialog" aria-label="Okuma Görünümü">
                                <p class="reader-heading reader-rule border-b px-5 pb-3 pt-4 text-xl font-semibold">Okuma Görünümü</p>

                                <div class="reader-rule border-b px-5 py-4">
                                    <p class="reader-heading text-base">Tema</p>
                                    <div class="mt-3 grid grid-cols-3 gap-2 text-center text-[0.95rem]">
                                        @foreach (['acik' => ['Açık', '#FFFFFF', '#E2790E'], 'sepya' => ['Sepya', '#E9DCC8', 'transparent'], 'koyu' => ['Koyu', '#0B1F33', 'transparent']] as $key => [$label, $fill, $ring])
                                            <button type="button" class="group flex flex-col items-center gap-1.5 rounded-lg py-1" @click="setTheme('{{ $key }}')" :aria-pressed="(prefs.theme === '{{ $key }}').toString()">
                                                {{-- :style nesneyle: metin verilirse Alpine sabit style'ı (dolgu rengi) siler. --}}
                                                <span class="h-11 w-11 rounded-full border shadow-sm transition" style="background-color: {{ $fill }}; border-color: rgba(120, 100, 70, 0.3)"
                                                      :style="{ boxShadow: prefs.theme === '{{ $key }}' ? '0 0 0 2px var(--rd-paper), 0 0 0 4px #E2790E' : '' }"></span>
                                                <span :class="prefs.theme === '{{ $key }}' ? 'reader-heading font-semibold' : 'reader-muted'">{{ $label }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Sayfa büyüklüğü (G4): punto değil sayfa büyür, dizgi aynı kalır. Telefonda
                                     (alt alta sayfalar, H6) metin zaten okunur boyda — yok. --}}
                                <div x-show="$store.pager.active && !$store.pager.flowing" x-cloak class="reader-rule flex items-center justify-between gap-3 border-b px-5 py-3.5">
                                    <span class="reader-heading text-base">Sayfa Büyüklüğü</span>
                                    <div class="reader-rule flex items-center rounded-lg border font-sans" role="group" aria-label="Sayfa büyüklüğü">
                                        <button type="button" class="reader-icon-btn h-9 min-w-9" @click="$dispatch('pager-zoom', -1)" :disabled="!$store.pager.canZoomOut" aria-label="Sayfayı küçült"><x-heroicon-o-minus class="w-4 h-4" /></button>
                                        <span class="reader-heading w-12 text-center text-xs tabular-nums" x-text="$store.pager.percent + '%'"></span>
                                        <button type="button" class="reader-icon-btn h-9 min-w-9" @click="$dispatch('pager-zoom', 1)" :disabled="!$store.pager.canZoomIn" aria-label="Sayfayı büyüt"><x-heroicon-o-plus class="w-4 h-4" /></button>
                                    </div>
                                </div>

                                <div class="reader-rule flex items-center gap-3 border-b px-5 py-3.5">
                                    <x-heroicon-o-moon class="reader-heading w-6 h-6 shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="reader-heading text-[0.95rem] leading-snug">Gece Modunu Otomatik Aç</p>
                                        <p x-show="prefs.auto" x-cloak class="reader-muted text-sm italic leading-snug">Cihaz karanlık moddayken Koyu tema açılır.</p>
                                    </div>
                                    <button type="button" role="switch" class="reader-switch" :aria-checked="prefs.auto.toString()" @click="setPref('auto', !prefs.auto)" aria-label="Gece modunu otomatik aç"><span></span></button>
                                </div>

                                <div class="reader-rule flex items-center gap-3 border-b px-5 py-3.5">
                                    <x-heroicon-o-book-open class="w-6 h-6 shrink-0 text-gold" />
                                    <div class="min-w-0 flex-1">
                                        <p class="reader-heading text-[0.95rem] leading-snug">Sözlük Kavramlarını Göster</p>
                                        <p x-show="prefs.concepts" x-cloak class="reader-muted text-sm italic leading-snug">Metindeki sözlükle bağlantılı kelimeleri vurgular.</p>
                                    </div>
                                    <button type="button" role="switch" class="reader-switch" :aria-checked="prefs.concepts.toString()" @click="setPref('concepts', !prefs.concepts)" aria-label="Sözlük kavramlarını göster"><span></span></button>
                                </div>

                                <div class="flex justify-end px-5 py-3">
                                    <button type="button" class="reader-heading inline-flex items-center gap-2 rounded-md px-2 py-1 text-[0.95rem] hover:opacity-75" @click="resetPrefs()">
                                        <x-heroicon-o-arrow-path class="w-5 h-5" /> Varsayılanlara Dön
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Okuma ilerlemesi --}}
                <div class="h-0.5 origin-left bg-gold/70 transition-transform duration-100" :style="`transform: scaleX(${$store.pager.active ? $store.pager.progress : progress})`" aria-hidden="true"></div>
            </header>

            {{-- Dipnot / kaynak (Faz H2, "Okuma modu 3–6"): üstüne gelince kart, tıklayınca pencere. --}}
            {{-- Kart (Okuma modu 5–6): işaretin altında küçük, beyaz, sade — kaynakta satır satır, eser adı eğik. --}}
            <div x-show="hoverRef" x-cloak class="reader-tip pointer-events-none fixed z-40 w-72 max-w-[calc(100vw-1rem)] px-4 py-3 font-reading"
                 :class="hoverRef?.above && '-translate-y-full'" :style="hoverRef && { left: hoverRef.x + 'px', top: hoverRef.y + 'px' }" role="tooltip">
                <p class="text-[0.84rem] leading-relaxed">
                    <template x-for="(line, i) in refLines(hoverRef)" :key="i">
                        <span class="block whitespace-pre-line" :class="line.italic && 'italic'" x-text="line.text"></span>
                    </template>
                </p>
            </div>

            <div x-show="popRef" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="popRef = null" @pager-navigated.window="popRef = null">
                <div class="absolute inset-0 bg-slate-950/45" x-show="popRef" x-transition.opacity @click="popRef = null"></div>
                <div class="reader-panel relative w-[25rem] max-w-full px-7 pb-6 pt-5 font-reading" x-show="popRef" x-transition role="dialog" aria-modal="true" :aria-label="popRef?.title">
                    <button type="button" x-ref="refClose" class="reader-icon-btn absolute right-3 top-3" @click="popRef = null" aria-label="Kapat"><x-heroicon-o-x-mark class="w-6 h-6" /></button>
                    <p class="flex items-start gap-1.5 pr-10">
                        <sup class="mt-2 text-base text-gold" x-text="popRef?.label"></sup>
                        <span class="reader-heading text-[1.75rem] leading-tight" x-text="popRef?.title"></span>
                    </p>
                    {{-- Okuma modu 3–4: pencere boyunca tek ince çizgi, ortasında süs motifi (arkası pencere rengi). --}}
                    <div class="relative mt-3 flex h-5 items-center justify-center text-gold" aria-hidden="true">
                        <span class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-current"></span>
                        <svg viewBox="52 0 56 20" class="relative h-5 w-14 px-0.5" style="background-color: var(--rd-paper)" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round">
                            <circle cx="56.5" cy="10" r="1.4" fill="currentColor" stroke="none"/>
                            <circle cx="103.5" cy="10" r="1.4" fill="currentColor" stroke="none"/>
                            <path d="M61 10c3.5-4.5 9-4.5 12 0-3 4.5-8.5 4.5-12 0Z"/>
                            <path d="M99 10c-3.5-4.5-9-4.5-12 0 3 4.5 8.5 4.5 12 0Z"/>
                            <path d="M80 2.5c-3.2 3-3.2 12 0 15 3.2-3 3.2-12 0-15Z" fill="currentColor" stroke="none"/>
                            <path d="M74.5 10h11"/>
                        </svg>
                    </div>
                    <p class="mt-4 text-[1.08rem] leading-relaxed">
                        <template x-for="(line, i) in refLines(popRef)" :key="i">
                            <span class="block whitespace-pre-line" :class="line.italic && 'italic'" x-text="line.text"></span>
                        </template>
                    </p>
                    <div class="reader-rule mt-5 border-t pt-5 text-center">
                        <a :href="popRef?.href" class="reader-goto relative inline-flex w-full max-w-[20rem] items-center justify-center rounded-full border px-12 py-2.5 font-reading text-[1.02rem] font-medium hover:opacity-85" @click="popRef = null">
                            <span x-text="popRef?.kind === 'dipnot' ? 'Dipnota Git' : 'Kaynakçaya Git'"></span>
                            <x-heroicon-o-arrow-right class="absolute right-5 w-4 h-4" />
                        </a>
                    </div>
                </div>
            </div>

            @hasSection('reader_paged')
                <main class="flex-1 px-2 py-4 sm:px-6 sm:py-8">
                    @if (session('status'))
                        <div class="mx-auto mb-4 max-w-[46rem] rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2.5 font-sans text-sm text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </main>
            @else
                <main class="flex-1 px-0 sm:px-6 sm:py-8">
                    <article class="reader-paper reader-page mx-auto w-full max-w-3xl px-5 py-10 sm:rounded-sm sm:px-14 sm:py-16 sm:shadow-xl sm:shadow-slate-900/10">
                        @if (session('status'))
                            <div class="mb-8 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2.5 font-sans text-sm text-emerald-800">
                                {{ session('status') }}
                            </div>
                        @endif

                        @yield('content')
                    </article>
                </main>
            @endif
        </div>

        <x-document-viewer />
    </body>
</html>
