@extends('layouts.public')

@section('title', $magazine->name)

@section('content')
    <div class="mb-8">
        <a href="{{ route('dergiler.index') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">&larr; Dergiler</a>
        <h1 class="font-serif text-3xl sm:text-4xl font-semibold text-slate-900 mt-3">{{ $magazine->name }}</h1>
        @if ($magazine->editor)
            <div class="text-brand-600 mt-2">Editör: {{ $magazine->editor->name }}</div>
        @endif
        @if ($magazine->description)
            <p class="text-slate-600 mt-4 max-w-2xl whitespace-pre-line leading-relaxed">{{ $magazine->description }}</p>
        @endif
    </div>

    @if ($upcomingIssues->isNotEmpty())
        <x-home-shelf title="Yakında Çıkacak Sayılar">
            @foreach ($upcomingIssues as $upcomingIssue)
                <x-magazine-card :issue="$upcomingIssue" show-scheduled-date class="w-36 shrink-0 snap-start sm:w-auto" />
            @endforeach
        </x-home-shelf>
    @endif

    <h2 class="font-serif text-xl font-semibold text-slate-900 mb-5">Sayılar</h2>
    @if ($issues->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-newspaper class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Bu derginin henüz yayınlanmış bir sayısı yok.
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
            @foreach ($issues as $issue)
                <x-magazine-card :issue="$issue" />
            @endforeach
        </div>
    @endif
@endsection
