@extends('layouts.public')

@section('title', 'Dergiler')

@section('content')
    <h1 class="sr-only">Dergiler</h1>
    <x-content-type-switcher active="dergiler" />

    <h2 class="font-serif text-xl font-semibold text-slate-900 mb-5">Yeni Sayılar</h2>

    @if ($issues->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-newspaper class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Henüz yayınlanmış bir dergi sayısı yok.
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
            @foreach ($issues as $issue)
                <x-magazine-card :issue="$issue" />
            @endforeach
        </div>

        <div class="mt-8">
            {{ $issues->links() }}
        </div>
    @endif
@endsection
