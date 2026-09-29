@extends('layouts.reader')

{{-- Okuma modu — makaleler de kitaplar gibi sayfalı (Faz G4): yazarın sayfa oranında (dergide çoğunlukla 21 × 27,5). --}}

@section('title', $article->title)
@section('reader_back_url', $article->magazineIssue ? route('dergiler.show', $article->magazineIssue) : route('dergiler.index'))
@section('reader_back_label', $article->magazineIssue?->title ?? 'Dergiler')
@section('reader_paged', '1')

@section('content')
    @unless ($article->status === \App\Enums\ContentStatus::Yayinda)
        {{-- Yazar / Süper Admin önizlemesi: henüz yayında değil. --}}
        <div class="mb-4 flex justify-center font-sans"><x-status-badge :status="$article->status" /></div>
    @endunless

    <x-paged-reader :ratio="$article->page_ratio" :storage-key="'makale-'.$article->id" :contents="$contents">
        <section class="rt-chapter rich-content rt-editor-surface" data-chapter="1" data-title="{{ $article->title }}">
            <header class="rt-opener">
                <x-reader-ornament />
                @if ($article->magazineIssue)
                    <p class="rt-opener-kicker">{{ $article->magazineIssue->magazine?->name ?? 'Dergi' }} · Sayı {{ $article->magazineIssue->issue_number }}</p>
                @endif
                <div class="rt-opener-title" role="heading" aria-level="1">{{ $article->title }}</div>
                <p class="rt-opener-byline">{{ $article->author->name }}</p>
                <x-reader-ornament />
            </header>
            {!! $article->renderedContent() !!}
        </section>

        <x-slot:after>
            <div class="mx-auto mt-6 w-full max-w-[46rem] px-1 font-sans">
                @auth
                    <section x-data="{ open: @js($errors->has('content') || $errors->has('noteable_id')) }">
                        <button type="button" class="reader-heading inline-flex items-center gap-2 text-sm font-medium hover:opacity-75" @click="open = !open" :aria-expanded="open">
                            <x-heroicon-o-pencil-square class="w-4 h-4" /> Not / Alıntı Ekle
                        </button>
                        <div x-show="open" x-cloak class="mt-3">
                            <x-quick-note-form
                                :noteable-type="\App\Models\Article::class"
                                :noteable-id="$article->id"
                                location-bind="$store.pager.location"
                            />
                        </div>
                    </section>
                @else
                    <p class="reader-muted text-center text-sm">
                        Not veya alıntı eklemek için <a href="{{ route('login') }}" class="reader-heading underline">giriş yapın</a>.
                    </p>
                @endauth
            </div>
        </x-slot:after>
    </x-paged-reader>
@endsection
