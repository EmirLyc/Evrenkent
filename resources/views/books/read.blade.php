@extends('layouts.reader')

{{-- Okuma modu (Faz F4, mockup 3) — kâğıt sayfa, süslemeler, klasik gövde metni. --}}

@section('title', $chapter ? $chapter->title.' — '.$book->title : $book->title)
@section('reader_back_url', route('kitaplar.show', $book))
@section('reader_back_label', $book->title)

@if ($chapters->isNotEmpty())
    @section('reader_drawer')
        <div class="space-y-0.5">
            @foreach ($chapters as $c)
                <a
                    href="{{ route('kitaplar.oku', [$book, $c->order]) }}"
                    @class([
                        'flex gap-2 rounded-md px-3 py-2 font-reading text-[1.05rem] leading-snug',
                        'bg-navy text-white' => $chapter && $chapter->id === $c->id,
                        'text-slate-700 hover:bg-white/70' => ! ($chapter && $chapter->id === $c->id),
                    ])
                    @if ($chapter && $chapter->id === $c->id) aria-current="page" @endif
                >
                    <span class="shrink-0 tabular-nums opacity-70">{{ $c->order }}.</span>
                    <span class="min-w-0 break-words">{{ $c->title }}</span>
                </a>
            @endforeach
        </div>
    @endsection
@endif

@section('content')
    @if ($chapters->isEmpty())
        <div class="py-16 text-center font-reading text-lg text-slate-500">
            <x-reader-ornament class="mb-6" />
            Bu kitap için henüz bölüm eklenmedi.
        </div>
    @elseif ($chapter)
        <header class="mb-10 sm:mb-14">
            <x-reader-ornament class="mb-8" />
            <div class="reader-kicker">{{ $chapter->order }}.</div>
            <h1 class="reader-title mt-2">{{ $chapter->title }}</h1>
            <x-reader-ornament class="mt-6" />
        </header>

        {{-- Faz F1–F3: temizlenmiş HTML (RichText) — dipnotlar, kar tanesi belgeler, videolar. --}}
        <div class="rich-content">{!! $chapter->renderedContent() !!}</div>

        {{-- Sayfa sonu: mockup'taki sayfa numarası yerine bölüm konumu (web metni sayfalara bölünmüyor). --}}
        <footer class="mt-14">
            <x-reader-ornament />
            <p class="mt-3 text-center font-reading text-base text-slate-500 tabular-nums">Bölüm {{ $chapter->order }} / {{ $chapters->count() }}</p>

            <nav class="mt-8 flex items-center justify-between gap-3 font-sans" aria-label="Bölümler arası geçiş">
                @if ($prevChapter)
                    <a href="{{ route('kitaplar.oku', [$book, $prevChapter->order]) }}" data-reader-prev class="inline-flex items-center gap-1.5 rounded-lg border border-gold/40 bg-white/40 px-3.5 py-2 text-sm text-slate-700 hover:bg-white/80">
                        &larr; <span class="hidden sm:inline">Önceki Bölüm</span><span class="sm:hidden">Önceki</span>
                    </a>
                @else
                    <span></span>
                @endif

                @if ($nextChapter)
                    <a href="{{ route('kitaplar.oku', [$book, $nextChapter->order]) }}" data-reader-next class="inline-flex items-center gap-1.5 rounded-lg bg-navy px-3.5 py-2 text-sm text-white hover:bg-navy/90">
                        <span class="hidden sm:inline">Sonraki Bölüm</span><span class="sm:hidden">Sonraki</span> &rarr;
                    </a>
                @elseif (auth()->check() && $readingListItem)
                    <form method="POST" action="{{ route('panel.okuma-listesi.tamamla', $readingListItem) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="rounded-lg bg-navy px-3.5 py-2 text-sm text-white hover:bg-navy/90">
                            Tamamlandı Olarak İşaretle
                        </button>
                    </form>
                @endif
            </nav>
        </footer>

        @auth
            {{-- Metnin akışını bölmesin diye kapalı; doğrulama hatasıyla dönülürse açık gelir. --}}
            <section class="mt-12 font-sans" x-data="{ open: @js($errors->has('content') || $errors->has('noteable_id')) }">
                <button type="button" class="inline-flex items-center gap-2 text-sm font-medium text-navy hover:text-brand-700" @click="open = !open" :aria-expanded="open">
                    <x-heroicon-o-pencil-square class="w-4 h-4" /> Not / Alıntı Ekle
                </button>
                <div x-show="open" x-cloak class="mt-3">
                    <x-quick-note-form
                        :noteable-type="\App\Models\Book::class"
                        :noteable-id="$book->id"
                        :default-location="'Bölüm '.$chapter->order.': '.$chapter->title"
                    />
                </div>
            </section>
        @endauth
    @endif
@endsection
