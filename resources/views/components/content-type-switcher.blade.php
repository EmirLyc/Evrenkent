{{--
    Kitaplar / Dergiler / Sözlükler kutuları (mockup: 1-)Evrenkent Anasayfa.png). Anasayfada
    hiçbiri seçili değildir, her biri kendi sayfasına götüren bir giriş noktasıdır; /kitaplar,
    /dergiler ve /sozlukler'de (Faz G3) ilgili kutu seçili (turuncu çerçeve) görünür.

    Mobilde üç kutu alt alta dizilip içeriği aşağı itmesin diye yan yana, dikey ve kompakt
    duruyor; sm ve üstünde mockup'taki yatay (ikon solda) düzene geçiyor.
--}}
@props(['active' => null])

@php
    $boxes = [
        'kitaplar' => [
            'label' => 'Kitaplar',
            'count' => \App\Models\Book::books()->published()->count().' eser',
            'icon' => 'book-open',
            'href' => route('kitaplar.index'),
        ],
        'dergiler' => [
            'label' => 'Dergiler',
            'count' => \App\Models\MagazineIssue::published()->count().' sayı',
            'icon' => 'newspaper',
            'href' => route('dergiler.index'),
        ],
        'sozlukler' => [
            'label' => 'Sözlükler',
            'count' => \App\Models\Book::dictionaries()->published()->count().' sözlük',
            'icon' => 'language',
            'href' => route('sozlukler.index'),
        ],
    ];
    $boxBase = 'flex flex-col sm:flex-row items-center gap-1.5 sm:gap-4 rounded-lg border bg-white p-3 sm:p-4 text-center sm:text-left transition-colors';
@endphp

<div class="grid grid-cols-3 gap-2 sm:gap-4 mb-8 sm:mb-10">
    @foreach ($boxes as $key => $box)
        <a href="{{ $box['href'] }}" class="{{ $boxBase }} {{ $active === $key ? 'border-brand-300' : 'border-slate-200 hover:border-slate-300' }}">
            @svg('heroicon-o-' . $box['icon'], 'w-6 h-6 sm:w-7 sm:h-7 shrink-0 ' . ($active === null || $active === $key ? 'text-brand-500' : 'text-slate-400'))
            <div class="min-w-0">
                <div class="font-medium text-slate-900 text-sm sm:text-base">{{ $box['label'] }}</div>
                <div class="text-xs sm:text-sm text-slate-500">{{ $box['count'] }}</div>
            </div>
        </a>
    @endforeach
</div>
