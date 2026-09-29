@extends('layouts.panel')

{{--
    Alıntılarım (Faz H4, "Alıntılarıma Dair" + "Alıntılarım sayfası 1"): alıntı yapılan eserlerin
    listesi; bir esere tıklanınca Benim Seçkim (sade okuma görünümü). Alıntı okurken metinde
    seçilerek ekleniyor (Faz H3) — buradaki elle ekleme formu kalktı.
--}}

@section('title', 'Alıntılarım')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-serif text-3xl font-semibold text-navy sm:text-4xl">Alıntılarım</h1>
            <p class="mt-1.5 text-slate-600">Okuduklarından seçtiklerin, senin düşünce evrenin.</p>
        </div>
        <figure class="max-w-xs text-right font-reading italic text-slate-500 sm:pt-2">
            <blockquote class="leading-snug">“İyi bir alıntı, insanın kendi kendine söyleyemediği şeyi ona yeniden söyler.”</blockquote>
            <figcaption class="mt-1 text-sm not-italic text-slate-400">— Goethe</figcaption>
        </figure>
    </div>

    <form method="GET" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <label class="relative min-w-0 flex-1">
            <span class="sr-only">Ara</span>
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input type="search" name="q" value="{{ $query }}" placeholder="Kitaplarda ve alıntılarında ara…" class="w-full rounded-lg border-slate-200 bg-white py-2.5 pl-11 text-sm focus:border-brand-400 focus:ring-brand-400">
        </label>
        <label class="flex items-center gap-3 text-sm text-slate-600">
            <span class="shrink-0">Sırala:</span>
            <select name="sirala" onchange="this.form.requestSubmit()" class="rounded-lg border-slate-200 bg-white py-2.5 pr-9 text-sm font-medium text-slate-800 focus:border-brand-400 focus:ring-brand-400">
                @foreach (['son' => 'Son alıntı yapılan', 'cok' => 'En çok alıntı', 'ad' => 'Kitap adı'] as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </form>

    <div class="mt-4"><x-note-quota-notice :type="\App\Enums\NoteType::Alinti" /></div>

    @if ($works->isEmpty())
        <div class="mt-6 rounded-xl border border-dashed border-slate-300 px-6 py-14 text-center">
            <p class="font-serif text-4xl text-brand-300" aria-hidden="true">“</p>
            @if ($query !== '')
                <p class="text-slate-600">"{{ $query }}" için alıntı bulunamadı.</p>
            @else
                <p class="font-medium text-slate-700">Henüz bir alıntın yok.</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">Okurken bir cümleyi seçip <span class="font-medium">Alıntıla</span>'ya bastığında burada, kitabına göre toplanır.</p>
            @endif
        </div>
    @else
        <ul class="mt-4 divide-y divide-slate-200 border-y border-slate-200">
            @foreach ($works as $item)
                @php [$tur, $id] = explode('-', $item->key); @endphp
                <li>
                    <a href="{{ route('panel.alintilarim.seckim', [$tur, $id]) }}" class="group flex items-center gap-4 px-1 py-3.5 transition-colors hover:bg-brand-50/50 sm:gap-5 sm:px-3">
                        <x-book-cover :book="$item->work" class="h-20 w-14 shrink-0 rounded shadow-sm sm:h-24 sm:w-[4.5rem]" icon-class="w-6 h-6" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-serif text-lg font-semibold text-navy sm:text-xl">{{ $item->work->title }}</p>
                            <p class="truncate font-reading text-slate-600">{{ $item->work->author?->name }}</p>
                            <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
                                <span class="font-serif text-xl leading-none" aria-hidden="true">“</span> {{ $item->count }} alıntı
                            </p>
                        </div>
                        <div class="hidden shrink-0 text-right text-sm text-slate-500 sm:block">
                            <p class="text-xs">Son alıntı</p>
                            <p>{{ $item->last->translatedFormat('j F Y') }}</p>
                        </div>
                        <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-slate-700" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-10 text-center">
        <x-reader-ornament />
        <p class="mt-2 font-reading text-sm tracking-[0.35em] text-navy">EVRENKENT</p>
        <p class="font-reading text-sm italic text-slate-500">Okumanın yeni bir evreni var.</p>
    </div>
@endsection
