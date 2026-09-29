{{-- Satırın "⋮" menüsü (mockup 4.1): eserle ilgili diğer işlemler. --}}
@php
    $book = $item->book;
    $reading = $item->readingItem;
@endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-label="Diğer işlemler"
            class="flex items-center justify-center w-9 h-9 rounded-md text-slate-600 hover:bg-slate-100 hover:text-slate-900 {{ $buttonClass ?? '' }}">
        <x-heroicon-o-ellipsis-vertical class="w-5 h-5" />
    </button>
    <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 top-full mt-1 w-60 card py-1 z-20 text-sm">
        <a href="{{ route('kitaplar.show', $book) }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50">
            <x-heroicon-o-information-circle class="w-4 h-4" /> Kitabı incele
        </a>

        @if ($reading?->status === \App\Enums\ReadingStatus::Listede)
            <form method="POST" action="{{ route('panel.okuma-listesi.tamamla', $reading) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
                    <x-heroicon-o-check-circle class="w-4 h-4" /> Okundu olarak işaretle
                </button>
            </form>
        @elseif ($reading)
            <form method="POST" action="{{ route('panel.okuma-listesi.listeye-al', $reading) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
                    <x-heroicon-o-arrow-path class="w-4 h-4" /> Yeniden okumaya başla
                </button>
            </form>
        @elseif ($book->status === \App\Enums\ContentStatus::Yayinda)
            <form method="POST" action="{{ route('panel.okuma-listesi.kitap.ekle', $book) }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
                    <x-heroicon-o-queue-list class="w-4 h-4" /> Okuma listeme ekle
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('panel.favoriler.kitap.toggle', $book) }}">
            @csrf
            <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
                @if ($item->favorited)
                    <x-heroicon-s-heart class="w-4 h-4 text-brand-600" /> Favorilerden çıkar
                @else
                    <x-heroicon-o-heart class="w-4 h-4" /> Favorilere ekle
                @endif
            </button>
        </form>

        @if ($reading)
            <form method="POST" action="{{ route('panel.okuma-listesi.sil', $reading) }}"
                  data-turbo-confirm="&quot;{{ $book->title }}&quot; okuma listenizden çıkarılacak; okuma ilerlemesi de silinir.">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-red-600 hover:bg-red-50 border-t border-slate-100">
                    <x-heroicon-o-x-mark class="w-4 h-4" /> Okuma listemden çıkar
                </button>
            </form>
        @endif
    </div>
</div>
