@extends('layouts.public')

{{-- Sözlükler (Faz G3, "Sözlüğe Dair"): yayındaki sözlükler + maddelerde arama. --}}

@section('title', $q !== '' ? "\"{$q}\" — Sözlükler" : 'Sözlükler')

@section('content')
    <h1 class="sr-only">Sözlükler</h1>
    <x-content-type-switcher active="sozlukler" />

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between mb-8">
        <div class="min-w-0">
            <h2 class="font-serif text-xl font-semibold text-slate-900">Sözlükler</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">Kitaplarda geçen kavramlar sözlüklere bağlı — okurken altı noktalı bir kelimeye tıkladığınızda sözlükteki maddesine gelirsiniz.</p>
        </div>
        <form method="GET" action="{{ route('sozlukler.index') }}" class="relative w-full sm:w-80 shrink-0" role="search">
            @if ($dictionary)
                <input type="hidden" name="sozluk" value="{{ $dictionary->slug }}">
            @endif
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ $dictionary ? 'Bu sözlükte kavram ara' : 'Kavram ara (ör. egemenlik)' }}" aria-label="Sözlüklerde kavram ara" class="w-full rounded-lg border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-700 placeholder:text-slate-400 focus:border-brand-300 focus:outline-none focus:ring-0">
        </form>
    </div>

    @if ($entries !== null)
        <section class="mb-12" aria-labelledby="madde-sonuclari">
            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 mb-4">
                <h2 id="madde-sonuclari" class="font-serif text-lg font-semibold text-slate-900">
                    @if ($dictionary)
                        <a href="{{ route('kitaplar.show', $dictionary) }}" class="hover:underline">{{ $dictionary->title }}</a> — {{ $q !== '' ? "\"{$q}\" için maddeler" : 'Maddeler' }}
                    @else
                        "{{ $q }}" için maddeler
                    @endif
                </h2>
                <span class="text-sm text-slate-400 tabular-nums">{{ $entries->total() }} madde</span>
                <a href="{{ route('sozlukler.index') }}" class="text-sm text-slate-400 hover:text-slate-600">Temizle</a>
            </div>

            @if ($entries->isEmpty())
                <div class="card p-10 text-center text-slate-400">
                    <x-heroicon-o-language class="w-8 h-8 mx-auto mb-3 text-slate-300" />
                    {{ $dictionary && $q === '' ? 'Bu sözlükte henüz madde yok.' : 'Bu adla bir madde bulunamadı.' }}
                </div>
            @else
                <div class="card divide-y divide-slate-100">
                    @foreach ($entries as $entry)
                        <a href="{{ $entry->url() }}" class="block px-4 py-4 sm:px-5 hover:bg-slate-50 transition-colors">
                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                <span class="font-reading text-lg font-semibold text-navy">{{ $entry->term }}</span>
                                <span class="text-xs text-slate-500">{{ $entry->book->title }} · {{ $entry->book->author->name }}</span>
                            </div>
                            @if ($entry->excerpt)
                                <p class="mt-1 text-sm text-slate-600 line-clamp-2">{{ $entry->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="mt-6">{{ $entries->links() }}</div>
            @endif
        </section>
    @endif

    <section aria-labelledby="sozluk-listesi">
        <h2 id="sozluk-listesi" class="font-serif text-lg font-semibold text-slate-900 mb-5">Yayındaki Sözlükler</h2>

        @if ($dictionaries->isEmpty())
            <div class="card p-12 text-center text-slate-400">
                <x-heroicon-o-language class="w-8 h-8 mx-auto mb-3 text-slate-300" />
                Henüz yayınlanmış bir sözlük yok.
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
                @foreach ($dictionaries as $dictionary)
                    <div>
                        <x-book-card :book="$dictionary" />
                        <p class="mt-1.5 px-1 text-xs text-slate-500 tabular-nums">{{ $dictionary->entries_count }} madde</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-8">{{ $dictionaries->links() }}</div>
        @endif
    </section>
@endsection
