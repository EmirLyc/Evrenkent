@extends('layouts.reader')

{{--
    Benim Seçkim (Faz H4, "Alıntılarıma Dair" 2–3 + "Alıntılarım sayfası 2"): bir eserden yapılan
    alıntılar, eserdeki sırasıyla. Kenar çubuğu olmayan sade okuma görünümü, salt okunur — Alıntıla /
    Not Al / Fosforla ve içindekiler yok; arama (🔍, alıntılarda) ve Aa (tema) var. Her alıntının
    yanında sayfası ve eseri o yerde açan ok.
--}}

@section('title', 'Benim Seçkim — '.$work->title)
@section('reader_back_url', route('panel.alintilarim'))
@section('reader_back_label', 'Alıntılarıma Dön')
@section('reader_searchable', '1')

@section('content')
    <div x-data="{
            q: '', open: false, texts: @js($quotes->pluck('content')->values()),
            get needle() { return this.q.trim().toLocaleLowerCase('tr') },
            shows(text) { return !this.needle || text.toLocaleLowerCase('tr').includes(this.needle) },
            get hits() { return this.texts.filter((text) => this.shows(text)).length },
         }"
         @reader-search.window="open = !open; if (open) $nextTick(() => $refs.q.focus())">
        <div x-show="open" x-cloak class="mb-8 font-sans">
            <label class="relative block">
                <span class="sr-only">Seçkimde ara</span>
                <x-heroicon-o-magnifying-glass class="reader-muted pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                <input x-ref="q" x-model="q" type="search" placeholder="Seçkimde ara…" class="reader-input h-11 w-full pl-9" @keydown.escape="q = ''; open = false">
            </label>
        </div>

        <header class="text-center">
            <x-book-cover :book="$work" class="mx-auto h-40 w-28 rounded shadow-lg shadow-slate-900/20" icon-class="w-8 h-8" />
            <h1 class="reader-heading mt-6 font-serif text-3xl font-bold uppercase tracking-wide sm:text-4xl">{{ $work->title }}</h1>
            <p class="reader-muted mt-1 font-reading text-xl">{{ $work->author?->name }}</p>
            <x-reader-ornament class="mt-4" />
            <p class="mt-2 font-reading text-sm tracking-[0.35em] text-gold">BENİM SEÇKİM</p>
        </header>

        <ol class="mx-auto mt-10 max-w-2xl space-y-8">
            @foreach ($quotes as $quote)
                @php $url = \App\Http\Controllers\NoteController::readingUrl($quote); @endphp
                <li class="flex items-end gap-5" x-show="shows(texts[{{ $loop->index }}])">
                    <blockquote class="min-w-0 flex-1 whitespace-pre-line font-reading text-[1.2rem] leading-relaxed">{{ $quote->content }}</blockquote>
                    @if ($url)
                        <a href="{{ $url }}" class="reader-muted inline-flex shrink-0 items-center gap-2 pb-1 font-reading text-base hover:opacity-75" aria-label="{{ $quote->page ? 'Sayfa '.$quote->page.'’da aç' : 'Kitapta aç' }}">
                            @if ($quote->page)<span class="tabular-nums">s. {{ $quote->page }}</span>@elseif ($quote->location)<span>{{ $quote->location }}</span>@endif
                            <x-heroicon-o-arrow-right class="h-5 w-5 text-brand-500" />
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
        <p x-show="needle && hits === 0" x-cloak class="reader-muted mt-6 text-center font-sans text-sm">Aramaya uyan alıntı yok.</p>

        <footer class="mt-14 text-center">
            <x-reader-ornament />
            <p class="reader-heading mt-2 font-reading text-sm tracking-[0.35em]">EVRENKENT</p>
            <p class="reader-muted font-reading text-sm italic">Okumanın yeni bir evreni var.</p>
        </footer>
    </div>
@endsection
