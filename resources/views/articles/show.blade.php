@extends('layouts.public')

@section('title', $article->title)

@section('content')
    <div class="max-w-3xl mx-auto">
        <x-detail-header
            :title="$article->title"
            :byline="$article->author->name"
            :meta="[$article->magazineIssue?->title]"
        >
            <x-status-badge :status="$article->status" />
        </x-detail-header>

        {{-- Faz F1: temizlenmiş HTML (RichText), dipnotlar numaralı. --}}
        <div class="rich-content mt-9">{!! $article->renderedContent() !!}</div>

        @auth
            <div class="mt-10">
                <h2 class="font-serif text-lg font-semibold text-slate-900 mb-3">Not / Alıntı Ekle</h2>
                <x-quick-note-form
                    :noteable-type="\App\Models\Article::class"
                    :noteable-id="$article->id"
                />
            </div>
        @else
            <p class="text-sm text-slate-500 mt-8">
                Not veya alıntı eklemek için <a href="{{ route('login') }}" class="text-slate-900 underline">giriş yapın</a>.
            </p>
        @endauth
    </div>

    {{-- Faz F2: kar tanesi işaretli gömülü belgeleri site içinde açar. --}}
    <x-document-viewer />
@endsection
