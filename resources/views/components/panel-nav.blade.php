{{--
    Panel menüsü — hem panel sayfalarının sol sütununda (layouts/panel.blade.php)
    hem de public sayfalardaki hamburger drawer'ında (layouts/public.blade.php)
    kullanılır. Tek yerden değişir, ikisi de aynı kalır.
--}}
@php
    $navGroups = [
        'Kişisel Kütüphanem' => [
            'panel.index' => ['Kitaplığım', route('panel.index')],
            'panel.favorilerim' => ['Favorilerim', route('panel.favorilerim')],
            'panel.okuma-listem' => ['Okuma Listem', route('panel.okuma-listem')],
            'panel.okuduklarim' => ['Okuduklarım', route('panel.okuduklarim')],
        ],
        'Çalışma Alanım' => [
            'panel.defterim' => ['Defterim', route('panel.defterim')],
            'panel.notlarim' => ['Notlarım', route('panel.notlarim')],
            'panel.alintilarim' => ['Alıntılarım', route('panel.alintilarim')],
        ],
        'Alışveriş ve Abonelik' => [
            'panel.sepetim' => ['Sepetim', route('panel.sepetim')],
            'panel.satin-aldiklarim' => ['Satın Aldıklarım', route('panel.satin-aldiklarim')],
            'panel.aboneligim' => ['Aboneliğim', route('panel.aboneligim')],
        ],
        'Hesap ve Destek' => [
            'profile.edit' => ['Ayarlar', route('profile.edit')],
            'panel.yardim' => ['Yardım Merkezi', route('panel.yardim')],
            'panel.iletisim' => ['İletişim', route('panel.iletisim')],
        ],
    ];
@endphp

@php
    // Aktif öğenin sol vurgu çizgisi sidebar'ın gerçek (padding'siz) kenarına yapışsın diye
    // dıştaki sarmalayıcı artık yatayda boşluksuz (bkz. layouts/public.blade.php) — boşluk
    // ve sağ kenar payı (mr-5) her linkin kendi içinde. border-l-4 aktif olmayan öğelerde de
    // (border-transparent) yer kapladığı için satırlar arası hiza kaymıyor.
    $navLinkBase = 'flex items-center pl-4 pr-3 py-1.5 mr-5 rounded-r-lg text-sm border-l-4 transition-colors';
    $navLinkInactive = 'border-transparent text-slate-600 hover:bg-slate-100';
    // Tüm gruplarda (Yayın Yönetimi / Dergi Yönetimi / diğerleri) aktif öğe aynı stil:
    // düşük opaklıklı turuncu zemin + turuncu metin + sol turuncu çizgi. Ayrı bir "koyu"
    // aktif stil yok, panel genelinde tek bir vurgu rengi var.
    $navLinkActive = 'border-brand-500 bg-brand-50 text-brand-800 font-medium';
    $groupHeading = 'text-xs font-semibold uppercase tracking-wider mb-2.5 pl-4';
@endphp

@if (auth()->user()->hasRole('yazar'))
    <div class="mb-7">
        <div class="{{ $groupHeading }} text-brand-700">Yayın Yönetimi</div>
        <div class="space-y-0.5">
            @foreach ([
                'panel.yayinlarim.index' => ['Yayınlarım', route('panel.yayinlarim.index')],
                'panel.yayinlarim.taslaklarim' => ['Taslaklarım', route('panel.yayinlarim.taslaklarim')],
                'panel.yayinlarim.gonderilenler' => ['Gönderilenler', route('panel.yayinlarim.gonderilenler')],
                'panel.yayinlarim.geri-donenler' => ['Geri Dönenler', route('panel.yayinlarim.geri-donenler')],
                'panel.yayinlarim.yayinlananlar' => ['Yayınlananlar', route('panel.yayinlarim.yayinlananlar')],
                'panel.yayinlarim.istatistiklerim' => ['İstatistiklerim', route('panel.yayinlarim.istatistiklerim')],
            ] as $routeName => [$label, $href])
                <a href="{{ $href }}" class="{{ $navLinkBase }} {{ request()->routeIs($routeName) ? $navLinkActive : $navLinkInactive }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
@endif

@if (auth()->user()->hasRole('dergi_editoru'))
    <div class="mb-7">
        <div class="{{ $groupHeading }} text-brand-700">Dergi Yönetimi</div>
        <div class="space-y-0.5">
            @foreach ([
                'panel.dergi.index' => ['Ana Sayfa', route('panel.dergi.index')],
                'panel.dergi.sayilarim' => ['Sayılarım', route('panel.dergi.sayilarim')],
                'panel.dergi.makale-havuzu' => ['Makale Havuzu', route('panel.dergi.makale-havuzu')],
                'panel.dergi.yayin-takvimi' => ['Yayın Takvimi', route('panel.dergi.yayin-takvimi')],
            ] as $routeName => [$label, $href])
                <a href="{{ $href }}" class="{{ $navLinkBase }} {{ request()->routeIs($routeName) ? $navLinkActive : $navLinkInactive }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
@endif

@foreach ($navGroups as $group => $links)
    <div class="mb-7">
        <div class="{{ $groupHeading }} text-slate-400">{{ $group }}</div>
        <div class="space-y-0.5">
            @foreach ($links as $routeName => [$label, $href])
                <a href="{{ $href }}" class="{{ $navLinkBase }} {{ request()->routeIs($routeName) ? $navLinkActive : $navLinkInactive }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
@endforeach

{{-- Çıkış Yap — bilerek diğer linklerden ayrı ve kırmızı: hem header'ı
     kalabalıklaştırmasın (mobilde daha da sıkışıktı) hem de yanlışlıkla
     tıklanmasın diye görsel olarak ayrışıyor. --}}
<div class="pt-3 mt-1 mr-5 border-t border-slate-200">
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full flex items-center gap-2 pl-4 pr-3 py-1.5 rounded-lg text-sm text-red-600 hover:bg-red-50 transition-colors">
            <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
            Çıkış Yap
        </button>
    </form>
</div>
