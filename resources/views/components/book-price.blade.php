{{--
    Kartlardaki satır içi fiyat: geçerli bir kampanya indirimi varsa eski fiyat üstü çizili +
    indirimli fiyat, yoksa düz fiyat. Fiyat Book::priceFor()'dan gelir (sepet ve satın alma da
    aynı metodu kullanıyor — görünen fiyat = ödenen fiyat).

    whitespace-nowrap: dar kartlarda (mobil raf, 6 sütunlu grid) satır kırılması "99,00 / TL"
    gibi fiyatın ortasından değil, eski ve yeni fiyatın arasından olsun.
--}}
@props(['book'])

@php $finalPrice = $book->priceFor(auth()->user()); @endphp

@if ((float) $finalPrice < (float) $book->price)
    <span class="text-slate-400 line-through mr-1.5 whitespace-nowrap">{{ number_format($book->price, 2, ',', '.') }} TL</span>
    <span class="text-brand-700 font-medium whitespace-nowrap">{{ number_format($finalPrice, 2, ',', '.') }} TL</span>
    {{-- Fiyat premium indiriminden geliyorsa (kampanyadan değil) üye neden farklı gördüğünü bilsin. --}}
    @if ($book->isPremiumPriceFor(auth()->user()))
        <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 whitespace-nowrap">
            <x-heroicon-s-sparkles class="w-3 h-3" /> Premium
        </span>
    @endif
@else
    <span class="text-slate-500 whitespace-nowrap">{{ number_format($book->price, 2, ',', '.') }} TL</span>
@endif
