@extends('layouts.panel')

{{--
    Notlarım (Faz H4, "Notlarım sayfası 1"): solda notların olduğu eserler, sağda seçilen eserin
    notları (Tüm Notlar / Bölümlere Göre / Tarihe Göre). Not okurken metinde seçilerek alınıyor
    (Faz H3) — buradaki elle ekleme formu kalktı. Dar ekranda önce eser listesi, eser seçilince
    notları ("← Kitaplar").
    Hiç eser yokken (not yok ya da aramaya uyan yok) sağ sütun boş kalıp sayfa sola dayalı
    görünüyordu — o durumda tek sütun, Alıntılarım'la aynı genişlik ve boş kutu.
--}}

@section('title', 'Notlarım')
@if ($works->isNotEmpty())
    @section('main_width', 'max-w-7xl')
    @section('main_padding', 'px-4 py-6 sm:px-6 sm:py-8')
@endif

@php
    $empty = $works->isEmpty();
    $params = fn (array $extra = []) => array_filter(array_merge(['q' => $query ?: null, 'tur' => $kind, 'sirala' => $sort !== 'son' ? $sort : null], $extra), fn ($value) => $value !== null && $value !== '');
@endphp

@section('content')
    <div class="grid gap-6 {{ $empty ? '' : 'lg:grid-cols-[21rem_minmax(0,1fr)] lg:gap-8' }}">
        {{-- Eserler --}}
        <section class="{{ $showNotes ? 'hidden lg:block' : '' }}" aria-label="Notların olduğu eserler">
            <h1 class="font-serif text-3xl font-semibold text-navy {{ $empty ? 'sm:text-4xl' : '' }}">Notlarım</h1>
            <p class="text-slate-600 {{ $empty ? 'mt-1.5' : 'mt-1 text-sm' }}">Okurken aldığın notlar ve onlara bağlı metinler.</p>

            <form method="GET" class="{{ $empty ? 'mt-6 flex flex-col gap-3 sm:flex-row sm:items-center' : 'mt-5 space-y-2.5' }}">
                <label class="relative block {{ $empty ? 'min-w-0 flex-1' : '' }}">
                    <span class="sr-only">Ara</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" name="q" value="{{ $query }}" placeholder="Notlarında veya kaynak metinlerde ara…" class="w-full rounded-lg border-slate-200 bg-white py-2 pl-9 text-sm focus:border-brand-400 focus:ring-brand-400">
                </label>
                <div class="grid grid-cols-2 gap-2.5 {{ $empty ? 'sm:w-80 sm:shrink-0' : '' }}">
                    <select name="tur" onchange="this.form.requestSubmit()" aria-label="Eser türü" class="rounded-lg border-slate-200 bg-white py-2 text-sm focus:border-brand-400 focus:ring-brand-400">
                        @foreach (['' => 'Tüm kitaplar', 'kitap' => 'Kitaplar', 'sozluk' => 'Sözlükler', 'makale' => 'Dergi yazıları'] as $value => $label)
                            <option value="{{ $value }}" @selected(($kind ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="sirala" onchange="this.form.requestSubmit()" aria-label="Sırala" class="rounded-lg border-slate-200 bg-white py-2 text-sm focus:border-brand-400 focus:ring-brand-400">
                        @foreach (['son' => 'Son eklenen', 'cok' => 'En çok not', 'ad' => 'Kitap adı'] as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            <div class="{{ $empty ? 'mt-4' : 'mt-3' }}"><x-note-quota-notice :type="\App\Enums\NoteType::Not" /></div>

            @if ($empty)
                <div class="mt-6 rounded-xl border border-dashed border-slate-300 px-6 py-14 text-center">
                    <x-heroicon-o-pencil-square class="mx-auto mb-2 h-8 w-8 text-brand-300" aria-hidden="true" />
                    @if ($query !== '' || $kind)
                        <p class="text-slate-600">Aramaya uyan not yok.</p>
                    @else
                        <p class="font-medium text-slate-700">Henüz not almadın.</p>
                        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Okurken bir cümleyi seçip <span class="font-medium">Not Al</span>'a bastığında burada, kitabına göre toplanır.</p>
                    @endif
                </div>
            @else
                <ul class="mt-4 space-y-2.5">
                    @foreach ($works as $item)
                        @php $active = $item->key === $selectedKey; @endphp
                        <li>
                            <a href="{{ route('panel.notlarim', $params(['eser' => $item->key])) }}"
                               class="flex items-center gap-3.5 rounded-lg border p-2.5 pr-3 transition-colors {{ $active ? 'border-brand-200 bg-brand-50 shadow-sm ring-1 ring-inset ring-brand-200' : 'border-slate-200 bg-white hover:border-slate-300' }}"
                               @if ($active) aria-current="true" @endif>
                                <x-book-cover :book="$item->work" class="h-[4.5rem] w-12 shrink-0 rounded-sm shadow-sm" icon-class="w-5 h-5" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-serif text-base font-semibold text-navy">{{ $item->work->title }}</p>
                                    <p class="truncate font-reading text-sm text-slate-600">{{ $item->work->author?->name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $item->count }} not</p>
                                </div>
                                <x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 {{ $active ? 'text-brand-600' : 'text-slate-400' }}" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Seçilen eserin notları --}}
        @unless ($empty)
        <section class="{{ $showNotes ? '' : 'hidden lg:block' }} min-w-0" aria-label="Notlar">
            @if ($selected)
                @php
                    $work = $selected->work;
                    $readUrl = $work instanceof \App\Models\Book ? route('kitaplar.oku', $work) : route('makaleler.show', $work);
                @endphp
                <a href="{{ route('panel.notlarim', $params()) }}" class="mb-4 inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 lg:hidden">
                    <x-heroicon-o-arrow-left class="h-4 w-4" /> Kitaplar
                </a>

                <div class="flex flex-wrap items-start gap-4 sm:gap-5">
                    <x-book-cover :book="$work" class="h-28 w-20 shrink-0 rounded shadow-md sm:h-32 sm:w-[5.5rem]" icon-class="w-7 h-7" />
                    <div class="min-w-0 flex-1">
                        <h2 class="font-serif text-2xl font-semibold text-navy sm:text-3xl">{{ $work->title }}</h2>
                        <p class="mt-0.5 font-reading text-lg text-slate-600">{{ $work->author?->name }}</p>
                    </div>
                    <a href="{{ $readUrl }}" class="btn-dark shrink-0 gap-2"><x-heroicon-o-book-open class="h-5 w-5" /> Kitabı Aç</a>
                </div>

                <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-slate-200 text-sm" aria-label="Görünüm">
                    @foreach (['tum' => 'Tüm Notlar ('.$selected->count.')', 'bolum' => 'Bölümlere Göre', 'tarih' => 'Tarihe Göre'] as $value => $label)
                        <a href="{{ route('panel.notlarim', $params(['eser' => $selectedKey, 'gorunum' => $value === 'tum' ? null : $value])) }}"
                           class="-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 font-medium {{ $view === $value ? 'border-brand-500 text-navy' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
                           @if ($view === $value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="mt-5 space-y-7">
                    @foreach ($groups as $heading => $notes)
                        <div>
                            @if ($heading !== '')
                                <h3 class="mb-3 font-serif text-lg font-semibold text-navy">{{ $heading }}</h3>
                            @endif
                            <ul class="space-y-4">
                                @foreach ($notes as $note)
                                    @php $pageUrl = \App\Http\Controllers\NoteController::readingUrl($note); @endphp
                                    <li x-data="{ edit: false, confirm: false }" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                                        <div class="flex items-start gap-3">
                                            <x-heroicon-s-document-text class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" />
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                                    <span class="text-sm text-slate-500">{{ $note->quote ? 'Kaynak Metin' : ($note->location ?: 'Not') }}</span>
                                                    <time class="text-xs tabular-nums text-slate-500" datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->translatedFormat('j M Y') }} &nbsp; {{ $note->created_at->format('H:i') }}</time>
                                                </div>
                                                @if ($note->quote)
                                                    <blockquote class="mt-2 flex items-end justify-between gap-4 rounded-r-md border-l-[3px] border-gold bg-brand-50/70 px-4 py-2.5 font-reading text-[1.05rem] leading-snug text-slate-800">
                                                        <span class="min-w-0 whitespace-pre-line">“{{ $note->quote }}”</span>
                                                        @if ($note->page)<span class="shrink-0 text-sm tabular-nums text-slate-500">s. {{ $note->page }}</span>@endif
                                                    </blockquote>
                                                @endif

                                                <p class="mt-3 text-xs font-medium text-slate-500">Notum</p>
                                                <p x-show="!edit" class="mt-0.5 whitespace-pre-line font-reading text-[1.05rem] leading-relaxed text-slate-800">{{ $note->content }}</p>
                                                <form x-show="edit" x-cloak method="POST" action="{{ route('panel.notlar.guncelle', $note) }}" class="mt-1.5 space-y-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <textarea name="content" rows="3" required class="w-full rounded-lg border-slate-300 font-reading text-base focus:border-brand-400 focus:ring-brand-400">{{ $note->content }}</textarea>
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button" class="btn-outline btn-sm" @click="edit = false">İptal</button>
                                                        <button type="submit" class="btn-dark btn-sm">Kaydet</button>
                                                    </div>
                                                </form>

                                                <div x-show="!edit" class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                                                    <button type="button" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-slate-950" @click="edit = true">
                                                        <x-heroicon-o-pencil class="h-4 w-4" /> Düzenle
                                                    </button>
                                                    <button type="button" x-show="!confirm" class="inline-flex items-center gap-1.5 text-slate-700 hover:text-red-600" @click="confirm = true">
                                                        <x-heroicon-o-trash class="h-4 w-4" /> Sil
                                                    </button>
                                                    <form x-show="confirm" x-cloak method="POST" action="{{ route('panel.notlar.sil', $note) }}" class="inline-flex items-center gap-2">
                                                        @csrf
                                                        @method('DELETE')
                                                        <span class="text-slate-500">Silinsin mi?</span>
                                                        <button type="submit" class="font-medium text-red-600 hover:underline">Sil</button>
                                                        <button type="button" class="text-slate-500 hover:underline" @click="confirm = false">Vazgeç</button>
                                                    </form>
                                                    @if ($pageUrl)
                                                        <a href="{{ $pageUrl }}" class="ml-auto inline-flex items-center gap-1.5 font-medium text-navy hover:text-brand-700">
                                                            {{ $note->anchor ? 'Sayfayı Aç' : 'Kitabı Aç' }} <x-heroicon-o-arrow-right class="h-4 w-4" />
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @elseif ($works->isNotEmpty())
                <p class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">Notlarını görmek için bir eser seç.</p>
            @endif
        </section>
        @endunless
    </div>
@endsection
