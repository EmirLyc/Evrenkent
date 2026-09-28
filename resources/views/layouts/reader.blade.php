{{--
    Okuma modu (Faz F4, mockup 3 "Okuma modundayken sayfanın görünümü"). Uygulama kabuğu
    (header arama/sepet, sidebar) yok — dikkat metinde. Masaüstünde koyu zemin üzerinde
    ortalı kâğıt sayfa, mobilde tam genişlik kâğıt.

    Section'lar: title, reader_back_url, reader_back_label, reader_drawer (isteğe bağlı —
    "Bölümler" çekmecesi), reader_paged (Faz G4: içerik x-paged-reader — sayfalar kendi kâğıdında,
    başlıkta yakınlaştırma), content. Sayfasız içerikte klavye ←/→ sayfadaki
    [data-reader-prev] / [data-reader-next] bağlantılarına gider.

    Okurun yazı boyutu ayarı Faz G4'te kalktı (belge: punto değişirse dizgi bozulur); sayfa
    büyütülüyor.
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
    <body class="font-sans antialiased bg-parchment-deep text-slate-800">
        <div x-data="reader" @keydown.window="keyNav($event)" @scroll.window.throttle.50ms="trackProgress()" @pager-navigated.window="drawer = false" class="min-h-screen flex flex-col">
            <header class="sticky top-0 z-30 bg-parchment/95 backdrop-blur border-b border-gold/20">
                <div class="mx-auto flex h-14 max-w-4xl items-center gap-2 px-3 sm:px-6">
                    <a href="@yield('reader_back_url', route('home'))" class="inline-flex min-w-0 items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-slate-600 hover:bg-white/60 hover:text-slate-900">
                        <x-heroicon-o-arrow-left class="w-4 h-4 shrink-0" />
                        <span class="truncate">@yield('reader_back_label', 'Geri')</span>
                    </a>

                    <div class="ml-auto flex shrink-0 items-center gap-1">
                        {{-- Sayfayı büyüt / küçült (sayfalı okuma; bu cihazda hatırlanır). --}}
                        <div x-show="$store.pager.active" x-cloak class="flex items-center rounded-md border border-gold/30 bg-white/50" role="group" aria-label="Sayfa büyüklüğü">
                            <button type="button" class="grid h-9 w-9 place-items-center text-slate-600 hover:text-slate-900 disabled:opacity-30" @click="$dispatch('pager-zoom', -1)" :disabled="!$store.pager.canZoomOut" aria-label="Sayfayı küçült"><x-heroicon-o-magnifying-glass-minus class="w-5 h-5" /></button>
                            <span class="w-11 text-center text-xs tabular-nums text-slate-600" x-text="$store.pager.percent + '%'"></span>
                            <button type="button" class="grid h-9 w-9 place-items-center text-slate-600 hover:text-slate-900 disabled:opacity-30" @click="$dispatch('pager-zoom', 1)" :disabled="!$store.pager.canZoomIn" aria-label="Sayfayı büyüt"><x-heroicon-o-magnifying-glass-plus class="w-5 h-5" /></button>
                        </div>
                        @hasSection('reader_drawer')
                            <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-md px-2.5 text-sm text-slate-600 hover:bg-white/60 hover:text-slate-900" @click="drawer = true" aria-label="Bölümler">
                                <x-heroicon-o-list-bullet class="w-5 h-5" />
                                <span class="hidden sm:inline">Bölümler</span>
                            </button>
                        @endif
                    </div>
                </div>
                {{-- Okuma ilerlemesi --}}
                <div class="h-0.5 bg-gold/70 origin-left transition-transform duration-100" :style="`transform: scaleX(${$store.pager.active ? $store.pager.progress : progress})`" aria-hidden="true"></div>
            </header>

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

            @hasSection('reader_drawer')
                <div x-show="drawer" x-cloak class="fixed inset-0 z-40" @keydown.escape.window="drawer = false">
                    <div class="absolute inset-0 bg-slate-900/40" x-show="drawer" x-transition.opacity @click="drawer = false"></div>
                    <nav class="absolute inset-y-0 right-0 flex w-80 max-w-[85vw] flex-col bg-parchment shadow-2xl" x-show="drawer"
                         x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="translate-x-full"
                         aria-label="Bölümler">
                        <div class="flex items-center justify-between border-b border-gold/20 px-4 py-3">
                            <span class="font-reading text-lg font-semibold text-navy">Bölümler</span>
                            <button type="button" class="rich-editor-btn" @click="drawer = false" aria-label="Kapat"><x-heroicon-o-x-mark class="w-5 h-5" /></button>
                        </div>
                        <div class="flex-1 overflow-y-auto overscroll-contain p-2">
                            @yield('reader_drawer')
                        </div>
                    </nav>
                </div>
            @endif
        </div>

        <x-document-viewer />
    </body>
</html>
