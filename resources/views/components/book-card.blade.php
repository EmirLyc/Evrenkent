{{--
    Kitap kartı (kapak + yazar + başlık + fiyat) — anasayfa rafları, /kitaplar kataloğu ve
    arama sonuçları aynı kartı kullanır. Fiyat x-book-price'tan gelir (geçerli indirim varsa
    eski fiyat üstü çizili). "Yakında Çıkacaklar" rafında fiyat yerine planlanan yayın tarihi.
--}}
@props(['book', 'showScheduledDate' => false])

<a href="{{ route('kitaplar.show', $book) }}" {{ $attributes->merge(['class' => 'group block card-hover overflow-hidden']) }}>
    <x-book-cover :book="$book" class="aspect-[3/4]" />
    <div class="p-3">
        <div class="text-xs text-brand-600 font-medium uppercase tracking-wide truncate">{{ $book->author->name }}</div>
        <div class="font-medium text-slate-900 text-sm truncate mt-0.5">{{ $book->title }}</div>
        <div class="text-sm mt-1">
            @if ($showScheduledDate && $book->scheduled_publish_at)
                <span class="text-brand-700 font-medium whitespace-nowrap">{{ $book->scheduled_publish_at->format('d.m.Y') }}</span>
                <x-countdown :at="$book->scheduled_publish_at" class="block text-xs text-slate-500 mt-0.5" />
            @else
                <x-book-price :book="$book" />
            @endif
        </div>
    </div>
</a>
