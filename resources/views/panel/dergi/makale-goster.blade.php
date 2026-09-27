@extends('layouts.panel')

@section('title', $article->title)

@section('content')
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('panel.dergi.makale-havuzu') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">
            &larr; Makale Havuzuna dön
        </a>

        <div class="mt-4">
            <x-detail-header
                :title="$article->title"
                :byline="$article->author->name"
                :meta="[$article->magazineIssue?->title]"
            >
                <x-status-badge :status="$article->status" />
                @foreach ($article->categories as $category)
                    <span class="pill-tag !py-1 !px-3 !text-xs">{{ $category->name }}</span>
                @endforeach
            </x-detail-header>
        </div>

        {{-- Faz F1: temizlenmiş HTML (RichText), dipnotlar numaralı. --}}
        <div class="rich-content mt-9">{!! $article->renderedContent() !!}</div>

        @can('review', $article)
            <div class="card p-6 mt-10">
                <h2 class="font-medium text-slate-900 mb-1">İncelemeyi tamamla</h2>
                <p class="text-sm text-slate-500 mb-4">Makale incelendi olarak işaretlenip Süper Admin onayına gönderilecek.</p>
                <form method="POST" action="{{ route('panel.dergi.makale-havuzu.incele', $article) }}">
                    @csrf
                    <button type="submit" class="btn-dark">
                        <x-heroicon-o-check-circle class="w-4 h-4" /> İncele ve Onaya Gönder
                    </button>
                </form>
            </div>
        @endcan
    </div>

    {{-- Faz F2: kar tanesi işaretli gömülü belgeleri site içinde açar. --}}
    <x-document-viewer />
@endsection
