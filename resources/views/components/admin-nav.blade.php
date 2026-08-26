{{--
    Süper Admin sidebar'ı — mockup'ın (dosyalar/2.4-)...png) sol menü gruplamasıyla
    birebir. Her kalem iki türden biri:
    1) Gerçek ve Filament'te zaten çalışan bir liste/form → doğrudan o Filament
       sayfasına link (Kitaplar, Dergiler, Kategoriler, Kullanıcılar...).
    2) Hiç altyapısı olmayan bir özellik (Sözlükler, Abonelikler, Gelir Merkezi,
       Premium Sistemi, Sistem Ayarları vb.) → tek bir generic "Yakında" sayfasına
       link (panel.adminpanel.placeholder), sahte veri/işlevsellik üretilmiyor.
--}}
@php
    $pendingTotal = \App\Models\Book::whereIn('status', [\App\Enums\ContentStatus::Gonderildi, \App\Enums\ContentStatus::Incelemede])->count()
        + \App\Models\Article::whereIn('status', [\App\Enums\ContentStatus::Gonderildi, \App\Enums\ContentStatus::Incelemede])->count()
        + \App\Models\MagazineIssue::whereIn('status', [\App\Enums\ContentStatus::Gonderildi, \App\Enums\ContentStatus::Incelemede])->count();

    $groups = [
        'Yayın Yönetimi' => [
            ['label' => 'Kitaplar', 'icon' => 'book-open', 'href' => route('panel.adminpanel.kitaplar.index')],
            ['label' => 'Dergiler', 'icon' => 'newspaper', 'href' => route('panel.adminpanel.dergiler.index')],
            ['label' => 'Sözlükler', 'icon' => 'language', 'href' => route('panel.adminpanel.placeholder', 'sozlukler')],
            ['label' => 'Tüm Yayınlar', 'icon' => 'rectangle-stack', 'href' => route('panel.adminpanel.placeholder', 'tum-yayinlar')],
            ['label' => 'Onay Bekleyenler', 'icon' => 'clock', 'href' => route('panel.adminpanel.onaylar.index'), 'badge' => $pendingTotal],
        ],
        'Kullanıcı Yönetimi' => [
            ['label' => 'Kullanıcılar', 'icon' => 'users', 'href' => route('panel.adminpanel.kullanicilar.index')],
            ['label' => 'Yazarlar', 'icon' => 'pencil-square', 'href' => route('panel.adminpanel.kullanicilar.index', ['rol' => 'yazar'])],
            ['label' => 'Dergi Editörleri', 'icon' => 'identification', 'href' => route('panel.adminpanel.kullanicilar.index', ['rol' => 'dergi_editoru'])],
            ['label' => 'Roller ve Yetkiler', 'icon' => 'shield-check', 'href' => route('panel.adminpanel.kullanicilar.roller')],
        ],
        'İstatistikler' => [
            ['label' => 'Satışlar', 'icon' => 'chart-bar', 'href' => route('panel.adminpanel.placeholder', 'istatistik-satislar')],
            ['label' => 'Abonelikler', 'icon' => 'arrow-path', 'href' => route('panel.adminpanel.placeholder', 'istatistik-abonelikler')],
            ['label' => 'Kitaplar', 'icon' => 'book-open', 'href' => route('panel.adminpanel.placeholder', 'istatistik-kitaplar')],
            ['label' => 'Dergiler', 'icon' => 'newspaper', 'href' => route('panel.adminpanel.placeholder', 'istatistik-dergiler')],
            ['label' => 'Sözlükler', 'icon' => 'language', 'href' => route('panel.adminpanel.placeholder', 'istatistik-sozlukler')],
            ['label' => 'Yazarlar', 'icon' => 'pencil-square', 'href' => route('panel.adminpanel.placeholder', 'istatistik-yazarlar')],
        ],
        'Gelir Merkezi' => [
            ['label' => 'Platform Geliri', 'icon' => 'banknotes', 'href' => route('panel.adminpanel.placeholder', 'platform-geliri')],
            ['label' => 'Yazar Hakedişleri', 'icon' => 'gift', 'href' => route('panel.adminpanel.placeholder', 'yazar-hakedisleri')],
            ['label' => 'Ödemeler', 'icon' => 'credit-card', 'href' => route('panel.adminpanel.placeholder', 'odemeler')],
            ['label' => 'Faturalar', 'icon' => 'document-text', 'href' => route('panel.adminpanel.placeholder', 'faturalar')],
        ],
        'Platform' => [
            ['label' => 'Ana Sayfa Yönetimi', 'icon' => 'home-modern', 'href' => route('panel.adminpanel.placeholder', 'ana-sayfa-yonetimi')],
            ['label' => 'Kategoriler', 'icon' => 'tag', 'href' => route('panel.adminpanel.kategoriler.index')],
            ['label' => 'Premium Sistemi', 'icon' => 'sparkles', 'href' => route('panel.adminpanel.placeholder', 'premium-sistemi')],
            ['label' => 'İndirimler', 'icon' => 'receipt-percent', 'href' => route('panel.adminpanel.placeholder', 'indirimler')],
            ['label' => 'Bildirimler', 'icon' => 'bell', 'href' => route('panel.adminpanel.placeholder', 'bildirimler-sistemi')],
            ['label' => 'Sistem Ayarları', 'icon' => 'cog-6-tooth', 'href' => route('panel.adminpanel.placeholder', 'sistem-ayarlari')],
        ],
        'Denetim Merkezi' => [
            ['label' => 'İşlem Geçmişi', 'icon' => 'clipboard-document-list', 'href' => route('panel.adminpanel.placeholder', 'islem-gecmisi')],
            ['label' => 'Sistem Günlükleri', 'icon' => 'document-magnifying-glass', 'href' => route('panel.adminpanel.placeholder', 'sistem-gunlukleri')],
        ],
    ];

    // Mockup'ta (dosyalar/2.4-)...png) aktif kalem hem açık (beyaz) zeminli hem de solda
    // ince turuncu bir vurgu çizgisiyle işaretleniyor, çizgi de sidebar'ın gerçek (padding'siz)
    // kenarına yapışık duruyor — bkz. layouts/admin-panel.blade.php'deki py-5 sarmalayıcı ve
    // panel-nav.blade.php'deki aynı desen. border-l-4 aktif olmayan satırlarda da (border-transparent)
    // yer kapladığı için hiza kaymıyor, mr-5 sağ kenarda pay bırakıyor.
    $navLinkBase = 'flex items-center justify-between gap-2 pl-3 pr-3 py-1.5 mr-5 rounded-r-lg text-sm border-l-4 transition-colors';
    $navLinkActive = 'border-brand-500 bg-white text-slate-900 font-medium';
    $navLinkInactive = 'border-transparent text-slate-300 hover:bg-slate-800';
    $groupHeading = 'text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2.5 pl-4';
@endphp

<div class="mb-7">
    <div class="{{ $groupHeading }}">Gösterge Paneli</div>
    <div class="space-y-0.5">
        <a href="{{ route('panel.adminpanel.index') }}" class="{{ $navLinkBase }} {{ request()->routeIs('panel.adminpanel.index') ? $navLinkActive : $navLinkInactive }}">
            <span class="flex items-center gap-2.5">
                <x-heroicon-o-home class="w-4 h-4 shrink-0" />
                Ana Sayfa
            </span>
        </a>
    </div>
</div>

@foreach ($groups as $group => $links)
    <div class="mb-7">
        <div class="{{ $groupHeading }}">{{ $group }}</div>
        <div class="space-y-0.5">
            @foreach ($links as $link)
                {{-- Filament, Turbo'nun yönettiği sayfalarla aynı SPA akışına dahil değil (kendi
                     Livewire/Alpine JS'i ayrı <head> varlıklarına bağımlı) — Turbo bu linki bir
                     body-only swap ile "ziyaret" etmeye çalışırsa Filament'in JS'i bozuk bir
                     ortamda çalışır (çift Alpine instance'ı, tanımsız $store.sidebar vb.).
                     data-turbo="false" tam sayfa yüklemeye zorlayıp bunu önlüyor. --}}
                {{-- request()->routeIs() değil fullUrlIs() kullanılıyor: aynı route birden fazla
                     linkte farklı query string ile tekrar ediyor (ör. Kullanıcılar/Yazarlar/Dergi
                     Editörleri hepsi panel.adminpanel.kullanicilar.index, sadece ?rol= değişiyor) —
                     routeIs() bunların hepsini aynı anda aktif işaretler, fullUrlIs() tam URL'yi
                     karşılaştırdığı için doğru olanı seçer. --}}
                <a href="{{ $link['href'] }}" @if (! empty($link['external'])) data-turbo="false" @endif class="{{ $navLinkBase }} {{ request()->fullUrlIs($link['href']) ? $navLinkActive : $navLinkInactive }}">
                    <span class="flex items-center gap-2.5 min-w-0">
                        @svg('heroicon-o-' . $link['icon'], 'w-4 h-4 shrink-0')
                        <span class="truncate">{{ $link['label'] }}</span>
                    </span>
                    @if (! empty($link['badge']))
                        <span class="min-w-[1.25rem] h-5 px-1 rounded-full bg-brand-500 text-white text-[11px] leading-5 text-center shrink-0">{{ $link['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
@endforeach

<div class="pt-3 mt-1 mr-5 border-t border-slate-800">
    <a href="{{ url('/admin') }}" data-turbo="false" class="flex items-center gap-2 pl-4 pr-3 py-1.5 rounded-lg text-sm text-slate-400 hover:bg-slate-800 transition-colors">
        <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
        Filament Yönetim Paneli
    </a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full flex items-center gap-2 pl-4 pr-3 py-1.5 rounded-lg text-sm text-red-400 hover:bg-red-950/50 transition-colors">
            <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
            Çıkış Yap
        </button>
    </form>
</div>
