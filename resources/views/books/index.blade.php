@extends('layouts.public')

@section('title', $category ? $category->name : 'Kitaplar')

@section('content')
    @if ($category)
        <div class="flex items-baseline gap-3 mb-5">
            <h1 class="font-serif text-xl font-semibold text-slate-900">{{ $category->name }}</h1>
            <a href="{{ route('kitaplar.index') }}" class="text-sm text-slate-400 hover:text-slate-600 transition-colors">Tüm kitaplar →</a>
        </div>
    @else
        {{-- Mockup 1'deki "Kitaplar seçili" görünüm: tip anahtarı + raf pilleri + rafın
             listesi. Anasayfa artık ayrı bir keşif sayfası (bkz. HomeController), bu görünüm
             Kitaplar'ın kendi ana sayfası oldu. --}}
        <h1 class="sr-only">Kitaplar</h1>
        <x-content-type-switcher active="kitaplar" />

        <div class="flex flex-wrap gap-2.5 mb-8">
            @foreach (\App\Enums\BookShelf::cases() as $tab)
                <a href="{{ route('kitaplar.index', ['raf' => $tab->value]) }}" class="{{ $shelf === $tab ? 'pill-active' : 'pill-idle' }}">
                    <x-dynamic-component :component="match ($tab) {
                        \App\Enums\BookShelf::YeniCikanlar => $shelf === $tab ? 'heroicon-s-star' : 'heroicon-o-star',
                        \App\Enums\BookShelf::CokSatanlar => 'heroicon-o-fire',
                        \App\Enums\BookShelf::EditorunSeckisi => 'heroicon-o-sparkles',
                        \App\Enums\BookShelf::Firsatlar => 'heroicon-o-tag',
                        \App\Enums\BookShelf::YakindaCikacaklar => 'heroicon-o-clock',
                    }" class="w-4 h-4" />
                    {{ $tab->label() }}
                </a>
            @endforeach
        </div>

        <h2 class="font-serif text-xl font-semibold text-slate-900 mb-5">{{ $shelf->label() }}</h2>
    @endif

    @if ($books->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-book-open class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            {{ $category ? 'Bu kategoride yayınlanmış bir kitap yok.' : $shelf->emptyMessage() }}
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
            @foreach ($books as $book)
                <x-book-card :book="$book" :show-scheduled-date="$shelf === \App\Enums\BookShelf::YakindaCikacaklar" />
            @endforeach
        </div>

        <div class="mt-8">
            {{ $books->links() }}
        </div>
    @endif
@endsection
