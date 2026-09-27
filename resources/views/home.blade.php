@extends('layouts.public')

@section('title', 'Ana Sayfa')

@section('content')
    {{-- Karşılama bandı — mockup 1'in altındaki "Okumanın yeni bir evreni var" bandının
         anasayfanın başına alınmış hâli. Görsel yerine hafif bir marka gradyanı + dekoratif
         ikon (mockup'taki illüstrasyon için elimizde bir görsel yok). --}}
    <section class="relative overflow-hidden rounded-xl border border-brand-100 bg-gradient-to-br from-brand-50 via-amber-50 to-orange-100 px-6 py-8 sm:px-10 sm:py-12 mb-8">
        <h1 class="relative font-serif text-2xl sm:text-4xl font-semibold leading-tight text-slate-900 max-w-md">
            Okumanın yeni bir evreni var.
        </h1>
        <p class="relative mt-3 text-slate-600 max-w-md">
            Kitaplara, dergilere ve sözlüklere tek bir yerden ulaşın.
        </p>
        <a href="{{ route('kitaplar.index') }}" class="relative btn-brand mt-6">Keşfet</a>
        <x-heroicon-o-book-open class="hidden sm:block absolute -right-8 -bottom-10 w-64 h-64 text-brand-200/70" />
    </section>

    <x-content-type-switcher />

    @php $cardClass = 'w-36 shrink-0 snap-start sm:w-auto'; @endphp

    @if ($newBooks->isNotEmpty())
        <x-home-shelf title="Yeni Çıkanlar" :href="route('kitaplar.index', ['raf' => \App\Enums\BookShelf::YeniCikanlar->value])">
            @foreach ($newBooks as $book)
                <x-book-card :book="$book" :class="$cardClass" />
            @endforeach
        </x-home-shelf>
    @endif

    @if ($upcomingBooks->isNotEmpty())
        <x-home-shelf title="Yakında Çıkacaklar" :href="route('kitaplar.index', ['raf' => \App\Enums\BookShelf::YakindaCikacaklar->value])">
            @foreach ($upcomingBooks as $book)
                <x-book-card :book="$book" show-scheduled-date :class="$cardClass" />
            @endforeach
        </x-home-shelf>
    @endif

    @if ($editorsPicks->isNotEmpty())
        <x-home-shelf title="Editörün Seçkisi" :href="route('kitaplar.index', ['raf' => \App\Enums\BookShelf::EditorunSeckisi->value])">
            @foreach ($editorsPicks as $book)
                <x-book-card :book="$book" :class="$cardClass" />
            @endforeach
        </x-home-shelf>
    @endif

    @if ($newIssues->isNotEmpty())
        <x-home-shelf title="Yeni Dergi Sayıları" :href="route('dergiler.index')">
            @foreach ($newIssues as $issue)
                <x-magazine-card :issue="$issue" :class="$cardClass" />
            @endforeach
        </x-home-shelf>
    @endif

    @if ($categories->isNotEmpty())
        <section class="mb-12">
            <h2 class="font-serif text-xl font-semibold text-slate-900 mb-4">Kategoriler</h2>
            <div class="flex flex-wrap gap-2.5">
                @foreach ($categories as $category)
                    <a href="{{ route('kitaplar.index', ['kategori' => $category->slug]) }}" class="pill-tag hover:border-brand-300 hover:text-brand-700">
                        {{ $category->name }}
                        <span class="text-slate-400 font-normal">{{ $category->books_count }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($newBooks->isEmpty() && $upcomingBooks->isEmpty() && $newIssues->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-book-open class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Henüz yayınlanmış bir içerik yok.
        </div>
    @endif
@endsection
