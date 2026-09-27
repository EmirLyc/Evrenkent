@extends('layouts.public')

@section('title', $q !== '' ? "\"{$q}\" için arama sonuçları" : 'Arama')

@section('content')
    {{-- Masaüstünde ayrı bir arama kutusu yok — header'daki (sticky, her sayfada görünür) arama
       kutusu zaten mevcut sorguyu dolu gösteriyor, tekrarlamak gereksiz. Ama header'daki o kutu
       mobilde gizli (sadece ikon var) — bu yüzden mobilde burada gerçek bir input kutusu şart,
       yoksa mobilde arama yapmanın hiçbir yolu kalmaz. --}}
    <form method="GET" action="{{ route('arama') }}" class="relative mb-8 sm:hidden">
        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input type="text" name="q" value="{{ $q }}" placeholder="Kitap, yazar veya konu ara…" class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2.5 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:border-brand-300">
    </form>

    @if ($q !== '')
        <h1 class="font-serif text-xl font-semibold text-slate-900 mb-8">"{{ $q }}" için arama sonuçları</h1>
    @endif

    @if ($q === '')
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-magnifying-glass class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Aramak için yukarıya bir şeyler yazın.
        </div>
    @elseif ($books->isEmpty() && $issues->isEmpty() && $articles->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-magnifying-glass class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            "{{ $q }}" için bir sonuç bulunamadı.
        </div>
    @else
        @if ($books->isNotEmpty())
            <div class="mb-12">
                <h2 class="font-serif text-xl font-semibold text-slate-900 mb-5">Kitaplar</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
                    @foreach ($books as $book)
                        <x-book-card :book="$book" />
                    @endforeach
                </div>
            </div>
        @endif

        @if ($issues->isNotEmpty())
            <div class="mb-12">
                <h2 class="font-serif text-xl font-semibold text-slate-900 mb-5">Dergiler</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
                    @foreach ($issues as $issue)
                        <x-magazine-card :issue="$issue" />
                    @endforeach
                </div>
            </div>
        @endif

        @if ($articles->isNotEmpty())
            <div class="mb-12">
                <h2 class="font-serif text-xl font-semibold text-slate-900 mb-5">Makaleler</h2>
                <div class="card divide-y divide-slate-100">
                    @foreach ($articles as $article)
                        <a href="{{ route('makaleler.show', $article) }}" class="block px-5 py-4 hover:bg-slate-50 transition-colors">
                            <div class="font-medium text-slate-900">{{ $article->title }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">
                                {{ $article->author->name }}
                                @if ($article->magazineIssue)
                                    · {{ $article->magazineIssue->title }}
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
@endsection
