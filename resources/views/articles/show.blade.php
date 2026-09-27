@extends('layouts.reader')

{{-- Okuma modu (Faz F4, mockup 3) — makaleler de kitap bölümleriyle aynı kâğıt sayfada okunuyor. --}}

@section('title', $article->title)
@section('reader_back_url', $article->magazineIssue ? route('dergiler.show', $article->magazineIssue) : route('dergiler.index'))
@section('reader_back_label', $article->magazineIssue?->title ?? 'Dergiler')

@section('content')
    <header class="mb-10 sm:mb-14">
        <x-reader-ornament class="mb-8" />
        @if ($article->magazineIssue)
            <p class="text-center font-reading text-sm uppercase tracking-[0.2em] text-gold">
                {{ $article->magazineIssue->magazine?->name ?? 'Dergi' }} · Sayı {{ $article->magazineIssue->issue_number }}
            </p>
        @endif
        <h1 class="reader-title mt-3">{{ $article->title }}</h1>
        <p class="reader-byline mt-3">{{ $article->author->name }}</p>
        @unless ($article->status === \App\Enums\ContentStatus::Yayinda)
            {{-- Yazar / Süper Admin önizlemesi: henüz yayında değil. --}}
            <div class="mt-4 flex justify-center font-sans"><x-status-badge :status="$article->status" /></div>
        @endunless
        <x-reader-ornament class="mt-6" />
    </header>

    {{-- Faz F1–F3: temizlenmiş HTML (RichText) — dipnotlar, kar tanesi belgeler, videolar. --}}
    <div class="rich-content">{!! $article->renderedContent() !!}</div>

    <footer class="mt-14">
        <x-reader-ornament />
    </footer>

    @auth
        <section class="mt-10 font-sans" x-data="{ open: @js($errors->has('content') || $errors->has('noteable_id')) }">
            <button type="button" class="inline-flex items-center gap-2 text-sm font-medium text-navy hover:text-brand-700" @click="open = !open" :aria-expanded="open">
                <x-heroicon-o-pencil-square class="w-4 h-4" /> Not / Alıntı Ekle
            </button>
            <div x-show="open" x-cloak class="mt-3">
                <x-quick-note-form
                    :noteable-type="\App\Models\Article::class"
                    :noteable-id="$article->id"
                />
            </div>
        </section>
    @else
        <p class="mt-10 text-center font-sans text-sm text-slate-500">
            Not veya alıntı eklemek için <a href="{{ route('login') }}" class="text-navy underline">giriş yapın</a>.
        </p>
    @endauth
@endsection
