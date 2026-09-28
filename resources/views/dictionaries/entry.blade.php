@extends('layouts.reader')

{{--
    Sözlük maddesi (Faz G3, "Sözlüğe Dair"): okurken tıklanan kavram buraya gelir. Sözlüğü
    okuyabilen maddenin tamamını görür; diğerleri kısa önizleme + sözlüğün tanıtım sayfası.
--}}

@section('title', $entry->term.' — '.$book->title)
@section('reader_back_url', route('kitaplar.show', $book))
@section('reader_back_label', $book->title)

@section('content')
    <header class="mb-10 sm:mb-12">
        <x-reader-ornament class="mb-8" />
        <p class="text-center font-reading text-sm uppercase tracking-[0.2em] text-gold">Sözlük · {{ $book->title }}</p>
        <h1 class="reader-title mt-3">{{ $entry->term }}</h1>
        <p class="reader-byline mt-3">{{ $book->author->name }}</p>
        @unless ($book->status === \App\Enums\ContentStatus::Yayinda)
            {{-- Yazarın önizlemesi: sözlük henüz yayında değil. --}}
            <div class="mt-4 flex justify-center font-sans"><x-status-badge :status="$book->status" /></div>
        @endunless
        <x-reader-ornament class="mt-6" />
    </header>

    @if ($readable)
        @if (\App\Support\RichText::hasText($entry->content))
            <div class="rich-content">{!! $entry->renderedContent() !!}</div>
        @else
            <p class="text-center font-reading text-lg text-slate-500">Bu maddenin tanımı henüz yazılmamış.</p>
        @endif
    @else
        <div class="relative">
            <div class="rich-content"><p>{{ $entry->excerpt }}</p></div>
            <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-parchment to-transparent" aria-hidden="true"></div>
        </div>
        <div class="mt-8 rounded-lg border border-gold/40 bg-white/50 p-5 text-center font-sans">
            <p class="text-slate-700">Maddenin tamamı <span class="font-semibold">{{ $book->title }}</span> sözlüğünde.</p>
            <a href="{{ route('kitaplar.show', $book) }}" class="btn-dark mt-3">Sözlüğü İncele</a>
        </div>
    @endif

    <footer class="mt-14 font-sans">
        <x-reader-ornament />
        @if ($readable)
            <p class="mt-4 text-center">
                <a href="{{ $entry->readUrl() }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-navy hover:text-brand-700">
                    <x-heroicon-o-book-open class="w-4 h-4" /> Sözlükte, yerinde oku
                </a>
            </p>
        @endif

        <nav class="mt-8 flex items-center justify-between gap-3" aria-label="Maddeler arası geçiş">
            @if ($previous)
                <a href="{{ route('sozlukler.madde', [$book, $previous]) }}" class="inline-flex min-w-0 items-center gap-1.5 rounded-lg border border-gold/40 bg-white/40 px-3.5 py-2 text-sm text-slate-700 hover:bg-white/80">
                    &larr; <span class="truncate">{{ $previous->term }}</span>
                </a>
            @else
                <span></span>
            @endif
            @if ($next)
                <a href="{{ route('sozlukler.madde', [$book, $next]) }}" class="inline-flex min-w-0 items-center gap-1.5 rounded-lg bg-navy px-3.5 py-2 text-sm text-white hover:bg-navy/90">
                    <span class="truncate">{{ $next->term }}</span> &rarr;
                </a>
            @endif
        </nav>
    </footer>
@endsection
