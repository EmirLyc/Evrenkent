@extends('layouts.panel')

@section('title', 'Kitaplığım')

{{--
    Kitaplığım (mockup "4.1-) Kitaplığım 2 (liste şeklinde)"): satın alınan / kitaplığa eklenen,
    favorilenen ve okuma listesindeki eserler. Liste (varsayılan) ve ızgara görünümü, sıralama;
    her eserde okuma durumu, duruma göre düğme ve "⋮" menüsü. Veri: PanelController::index.
--}}
@section('content')
    <div class="flex items-end justify-between gap-4 flex-wrap mb-6">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <span class="w-1 h-8 rounded-full bg-brand-500 shrink-0" aria-hidden="true"></span>
                <h1 class="font-serif text-3xl sm:text-4xl font-semibold text-slate-900">Kitaplığım</h1>
            </div>
            <p class="text-sm text-slate-600 mt-2">Toplam {{ $items->count() }} kitap</p>
        </div>

        @if ($items->isNotEmpty())
            <form method="GET" class="flex items-center gap-2 flex-wrap">
                @if ($view === 'izgara') <input type="hidden" name="gorunum" value="izgara"> @endif

                <div class="flex rounded-md border border-slate-300 bg-white overflow-hidden" role="group" aria-label="Görünüm">
                    @foreach (['izgara' => ['Izgara', 'squares-2x2'], 'liste' => ['Liste', 'bars-3']] as $key => [$label, $icon])
                        <a href="{{ request()->fullUrlWithQuery(['gorunum' => $key === 'liste' ? null : $key]) }}"
                           class="flex items-center gap-2 px-3 sm:px-4 h-10 text-sm {{ $key === 'liste' ? 'border-l border-slate-300' : '' }} {{ $view === $key ? 'bg-brand-50 text-brand-700 font-medium' : 'text-slate-600 hover:bg-slate-50' }}"
                           @if ($view === $key) aria-current="true" @endif>
                            @svg('heroicon-o-'.$icon, 'w-5 h-5')
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach
                </div>

                <label class="sr-only" for="sirala">Sıralama</label>
                <select id="sirala" name="sirala" onchange="this.form.requestSubmit()" class="h-10 rounded-md border-slate-300 text-sm text-slate-700 focus:border-slate-500 focus:ring-slate-500">
                    @foreach (\App\Http\Controllers\PanelController::LIBRARY_SORTS as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>Sıralama: {{ $label }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if ($items->isEmpty())
        <div class="card p-12 text-center text-slate-500">
            <x-heroicon-o-book-open class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Henüz kitaplığınıza eklenmiş bir eser yok.
            <div class="mt-4">
                <a href="{{ route('kitaplar.index') }}" class="btn-outline btn-sm">Kitaplara göz at</a>
            </div>
        </div>
    @elseif ($view === 'izgara')
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($items as $item)
                <article class="card flex flex-col">
                    <div class="relative">
                        <a href="{{ $item->book->url() }}" class="block group">
                            <x-book-cover :book="$item->book" class="aspect-[2/3] rounded-t-lg transition-opacity group-hover:opacity-90" icon-class="w-8 h-8" />
                        </a>
                        <div class="absolute top-2 right-2">
                            @include('panel.kitapligim._menu', ['buttonClass' => 'bg-white/95 shadow-sm hover:bg-white'])
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col p-3 sm:p-4 min-w-0">
                        @if ($item->book->categories->isNotEmpty())
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-brand-600 truncate">{{ $item->book->categories->first()->name }}</div>
                        @endif
                        <a href="{{ $item->book->url() }}" class="font-serif text-base sm:text-lg font-semibold text-slate-900 leading-snug mt-1 line-clamp-2 hover:underline">{{ $item->book->title }}</a>
                        <div class="text-sm text-slate-600 truncate mt-0.5">{{ $item->book->author?->name }}</div>
                        <div class="mt-3">
                            @include('panel.kitapligim._durum', ['compact' => true])
                        </div>
                        <div class="mt-auto pt-4">
                            <a href="{{ $item->actionUrl }}" class="btn-soft btn-sm w-full whitespace-normal text-center leading-tight">{{ $item->actionLabel }}</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="card divide-y divide-slate-100">
            @foreach ($items as $item)
                <article class="flex gap-4 px-4 py-4 sm:px-5">
                    <a href="{{ $item->book->url() }}" class="group shrink-0">
                        <x-book-cover :book="$item->book" class="w-16 h-24 sm:w-20 sm:h-28 rounded-md transition-opacity group-hover:opacity-90" icon-class="w-5 h-5" />
                    </a>

                    {{-- Mobilde bilgi, durum ve düğme alt alta; md ve üstünde mockup'taki üç sütun. --}}
                    <div class="flex-1 min-w-0 flex flex-col gap-3 md:grid md:grid-cols-[minmax(0,1fr)_12rem_11rem] md:items-center md:gap-6">
                        <div class="min-w-0">
                            @if ($item->book->categories->isNotEmpty())
                                <div class="text-xs font-semibold uppercase tracking-wider text-brand-600 truncate">{{ $item->book->categories->first()->name }}</div>
                            @endif
                            <a href="{{ $item->book->url() }}" class="block font-serif text-lg sm:text-xl font-semibold text-slate-900 leading-snug mt-1 break-words hover:underline">{{ $item->book->title }}</a>
                            <div class="text-sm text-slate-600 mt-1 truncate">{{ $item->book->author?->name }}</div>
                        </div>

                        @include('panel.kitapligim._durum')

                        <a href="{{ $item->actionUrl }}" class="btn-soft self-start md:self-auto md:w-full whitespace-nowrap">{{ $item->actionLabel }}</a>
                    </div>

                    <div class="shrink-0 self-start md:self-center">
                        @include('panel.kitapligim._menu')
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
