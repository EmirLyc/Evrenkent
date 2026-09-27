{{--
    Anasayfa rafı: başlık + "Tümünü Gör" + kart satırı. Mobilde kartlar yatay kaydırılan tek
    bir satırda (snap ile) duruyor — anasayfada birden fazla raf alt alta olduğu için, grid
    olsaydı her raf 3 satıra yayılıp sayfayı çok uzatırdı. Kaydırma alanı main'in yatay
    dolgusuna (px-6) kadar taşıyor (-mx-6 px-6), kartlar ekran kenarından kayarak giriyor.
    sm ve üstünde katalog sayfalarıyla aynı grid.

    Kartlar mobil genişliklerini kendileri almalı: class="w-36 shrink-0 snap-start sm:w-auto".
--}}
@props(['title', 'href' => null])

<section class="mb-12">
    <div class="flex items-baseline justify-between gap-3 mb-4">
        <h2 class="font-serif text-xl font-semibold text-slate-900">{{ $title }}</h2>
        @if ($href)
            <a href="{{ $href }}" class="shrink-0 text-sm font-medium text-brand-600 hover:text-brand-700">
                Tümünü Gör →
            </a>
        @endif
    </div>

    {{-- scroll-px-6: snap hizası dolgunun içinden başlasın — yoksa ilk kart "snap" olunca
         sol kenardaki 24px'lik boşluk kaybolup kart ekran kenarına yapışır. --}}
    <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory scroll-px-6 scrollbar-none -mx-6 px-6 pb-1 sm:mx-0 sm:px-0 sm:pb-0 sm:scroll-px-0 sm:grid sm:grid-cols-3 lg:grid-cols-6 sm:gap-5 sm:overflow-visible">
        {{ $slot }}
    </div>
</section>
