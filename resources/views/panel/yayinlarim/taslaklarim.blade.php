@extends('layouts.panel')

@section('title', 'Taslaklarım')

@section('content')
    {{-- Faz G1 ("Yazarın Gözünden" 1.1): yeni yayın oluşturmanın esas yeri burası. --}}
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div class="min-w-0">
            <h1 class="font-serif text-3xl font-semibold text-slate-900">Taslaklarım</h1>
            <p class="text-sm text-slate-600 mt-1"><span class="font-medium text-slate-800">Yayınlanmamış çalışmalarınızı</span> burada yönetebilir, durumlarını takip edebilirsiniz.</p>
        </div>
        @include('panel.yayinlarim._yeni-yayin-modal')
    </div>


    {{-- Sekmeler ve arama çok genişte tek satır, daha darda iki satır (arama ezilmesin). --}}
    <div class="flex flex-col 2xl:flex-row 2xl:items-center gap-3 mb-5">
        {{-- Durum sekmeleri — mobilde yatay kayar. --}}
        <nav class="flex gap-1 overflow-x-auto scrollbar-none -mx-4 px-4 sm:mx-0 sm:px-0 shrink-0" aria-label="Durum">
            @foreach (\App\Http\Controllers\PublicationController::TABS as $key => $label)
                <a href="{{ route('panel.yayinlarim.taslaklarim', array_filter(['durum' => $key === 'tumu' ? null : $key, 'q' => $q, 'sirala' => $sort === 'guncel' ? null : $sort, 'gorunum' => $view === 'izgara' ? null : $view])) }}"
                   class="inline-flex items-center gap-2 whitespace-nowrap px-3 py-2 text-sm rounded-t-md border-b-2 transition-colors {{ $tab === $key ? 'border-brand-500 bg-brand-50 text-slate-900 font-medium' : 'border-transparent text-slate-600 hover:text-slate-900' }}"
                   @if ($tab === $key) aria-current="page" @endif>
                    {{ $label }}
                    <span class="min-w-[1.4rem] h-5 px-1.5 rounded-full text-xs leading-5 text-center {{ $tab === $key ? 'bg-brand-200 text-brand-900' : 'bg-slate-100 text-slate-600' }}">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- sm ve üstünde tek satır: sekmelerin yanında yer darsa arama kutusu daralır,
             görünüm düğmeleri alt satıra düşmez. --}}
        <form method="GET" class="flex flex-wrap sm:flex-nowrap items-center gap-2 2xl:ml-auto min-w-0">
            @if ($tab !== 'tumu') <input type="hidden" name="durum" value="{{ $tab }}"> @endif
            @if ($view !== 'izgara') <input type="hidden" name="gorunum" value="{{ $view }}"> @endif
            <label class="relative w-full sm:w-72 min-w-0 sm:min-w-[10rem]">
                <span class="sr-only">Ara</span>
                <x-heroicon-o-magnifying-glass class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input type="search" name="q" value="{{ $q }}" placeholder="Başlık, konu veya tür ara…" class="w-full pl-9 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            </label>
            <label class="sr-only" for="sirala">Sıralama</label>
            <select id="sirala" name="sirala" onchange="this.form.requestSubmit()" class="shrink-0 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @foreach (\App\Http\Controllers\PublicationController::SORTS as $key => $label)
                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @include('panel.yayinlarim._gorunum-gecisi')
        </form>
    </div>

    @if ($items->isEmpty())
        <div class="card p-12 text-center text-slate-500">
            <x-heroicon-o-document-text class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            {{ $q !== '' ? 'Aramanızla eşleşen bir çalışma yok.' : ($tab === 'tumu' ? 'Henüz bir taslağınız yok. "Yeni Yayın Oluştur" ile başlayabilirsiniz.' : 'Bu sekmede bir çalışma yok.') }}
        </div>
    @else
        <div class="{{ $view === 'liste' ? 'space-y-3' : 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4' }}">
            @foreach ($items as $item)
                <x-publication-card :item="$item" :view="$view" />
            @endforeach
        </div>
    @endif

    <div class="flex items-center justify-between gap-3 flex-wrap mt-6">
        {{-- flex + items-center: inline-flex link satır içinde ikonun tabanına oturup metinden yukarı kayıyordu. --}}
        <p class="flex items-center gap-1.5 text-sm text-slate-500">
            <span>Toplam {{ $items->total() }} yayın</span>
            @if ($trashCount)
                <span aria-hidden="true">·</span>
                <a href="{{ route('panel.yayinlarim.cop-kutusu') }}" class="inline-flex items-center gap-1 text-slate-600 hover:text-slate-900 underline-offset-2 hover:underline"><x-heroicon-o-trash class="w-4 h-4" /> Çöp Kutusu ({{ $trashCount }})</a>
            @endif
        </p>
        {{ $items->onEachSide(1)->links('panel.yayinlarim._sayfalama') }}
    </div>
@endsection
