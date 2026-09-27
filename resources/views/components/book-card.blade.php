{{--
    Kitap kartı (kapak + yazar + başlık + fiyat) — anasayfa rafları, /kitaplar kataloğu ve
    arama sonuçları aynı kartı kullanır. İndirimli fiyat varsa eski fiyat üstü çizili
    gösterilir (satın alma da indirimli fiyatı ücretlendiriyor, bkz. User::purchase).
    "Yakında Çıkacaklar" rafında fiyat yerine planlanan yayın tarihi gösterilir.
--}}
@props(['book', 'showScheduledDate' => false])

<a href="{{ route('kitaplar.show', $book) }}" {{ $attributes->merge(['class' => 'group block card-hover overflow-hidden']) }}>
    <x-book-cover :book="$book" class="aspect-[3/4]" />
    <div class="p-3">
        <div class="text-xs text-brand-600 font-medium uppercase tracking-wide truncate">{{ $book->author->name }}</div>
        <div class="font-medium text-slate-900 text-sm truncate mt-0.5">{{ $book->title }}</div>
        <div class="text-sm mt-1">
            {{-- whitespace-nowrap: dar kartlarda (mobil raf, 6 sütunlu grid) satır kırılması
                 "99,00 / TL" gibi fiyatın ortasından değil, eski ve yeni fiyatın arasından olsun. --}}
            @if ($showScheduledDate && $book->scheduled_publish_at)
                <span class="text-brand-700 font-medium whitespace-nowrap">{{ $book->scheduled_publish_at->format('d.m.Y') }}</span>
            @elseif ($book->discount_price !== null)
                <span class="text-slate-400 line-through mr-1.5 whitespace-nowrap">{{ number_format($book->price, 2, ',', '.') }} TL</span>
                <span class="text-brand-700 font-medium whitespace-nowrap">{{ number_format($book->discount_price, 2, ',', '.') }} TL</span>
            @else
                <span class="text-slate-500 whitespace-nowrap">{{ number_format($book->price, 2, ',', '.') }} TL</span>
            @endif
        </div>
    </div>
</a>
