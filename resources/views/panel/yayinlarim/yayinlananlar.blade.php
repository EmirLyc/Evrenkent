@extends('layouts.panel')

@section('title', 'Yayınlananlar')

@section('content')
    <div class="mb-6">
        <h1 class="font-serif text-3xl font-semibold text-slate-900">Yayınlananlar</h1>
        <p class="text-sm text-slate-600 mt-1">Yayımdaki eserleriniz.</p>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-5">
        @if ($view !== 'izgara') <input type="hidden" name="gorunum" value="{{ $view }}"> @endif
        <label class="relative w-full sm:w-72 min-w-0">
            <span class="sr-only">Ara</span>
            <x-heroicon-o-magnifying-glass class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input type="search" name="q" value="{{ $q }}" placeholder="Başlık, konu veya tür ara…" class="w-full pl-9 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        </label>
        <label class="sr-only" for="sirala">Sıralama</label>
        <select id="sirala" name="sirala" onchange="this.form.requestSubmit()" class="rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @foreach (\App\Http\Controllers\PublicationController::SORTS as $key => $label)
                <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @include('panel.yayinlarim._gorunum-gecisi')
    </form>

    @if ($items->isEmpty())
        <div class="card p-12 text-center text-slate-500">
            <x-heroicon-o-book-open class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            {{ $q !== '' ? 'Aramanızla eşleşen bir eser yok.' : 'Henüz yayında bir eseriniz yok.' }}
        </div>
    @else
        <div class="{{ $view === 'liste' ? 'space-y-3' : 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4' }}">
            @foreach ($items as $item)
                <x-publication-card :item="$item" :view="$view" />
            @endforeach
        </div>
    @endif

    <div class="flex items-center justify-between gap-3 flex-wrap mt-6">
        <p class="text-sm text-slate-500">Toplam {{ $items->total() }} yayın</p>
        {{ $items->onEachSide(1)->links('panel.yayinlarim._sayfalama') }}
    </div>
@endsection
