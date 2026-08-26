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
            ['label' => 'Kitaplar', 'href' => route('panel.adminpanel.kitaplar.index')],
            ['label' => 'Dergiler', 'href' => route('panel.adminpanel.dergiler.index')],
            ['label' => 'Sözlükler', 'href' => route('panel.adminpanel.placeholder', 'sozlukler')],
            ['label' => 'Tüm Yayınlar', 'href' => route('panel.adminpanel.placeholder', 'tum-yayinlar')],
            ['label' => 'Onay Bekleyenler', 'href' => route('panel.adminpanel.onaylar.index'), 'badge' => $pendingTotal],
        ],
        'Kullanıcı Yönetimi' => [
            ['label' => 'Kullanıcılar', 'href' => route('panel.adminpanel.kullanicilar.index')],
            ['label' => 'Yazarlar', 'href' => route('panel.adminpanel.kullanicilar.index', ['rol' => 'yazar'])],
            ['label' => 'Dergi Editörleri', 'href' => route('panel.adminpanel.kullanicilar.index', ['rol' => 'dergi_editoru'])],
            ['label' => 'Roller ve Yetkiler', 'href' => route('panel.adminpanel.kullanicilar.roller')],
        ],
        'İstatistikler' => [
            ['label' => 'Satışlar', 'href' => route('panel.adminpanel.placeholder', 'istatistik-satislar')],
            ['label' => 'Abonelikler', 'href' => route('panel.adminpanel.placeholder', 'istatistik-abonelikler')],
            ['label' => 'Kitaplar', 'href' => route('panel.adminpanel.placeholder', 'istatistik-kitaplar')],
            ['label' => 'Dergiler', 'href' => route('panel.adminpanel.placeholder', 'istatistik-dergiler')],
            ['label' => 'Sözlükler', 'href' => route('panel.adminpanel.placeholder', 'istatistik-sozlukler')],
            ['label' => 'Yazarlar', 'href' => route('panel.adminpanel.placeholder', 'istatistik-yazarlar')],
        ],
        'Gelir Merkezi' => [
            ['label' => 'Platform Geliri', 'href' => route('panel.adminpanel.placeholder', 'platform-geliri')],
            ['label' => 'Yazar Hakedişleri', 'href' => route('panel.adminpanel.placeholder', 'yazar-hakedisleri')],
            ['label' => 'Ödemeler', 'href' => route('panel.adminpanel.placeholder', 'odemeler')],
            ['label' => 'Faturalar', 'href' => route('panel.adminpanel.placeholder', 'faturalar')],
        ],
        'Platform' => [
            ['label' => 'Ana Sayfa Yönetimi', 'href' => route('panel.adminpanel.placeholder', 'ana-sayfa-yonetimi')],
            ['label' => 'Kategoriler', 'href' => route('panel.adminpanel.kategoriler.index')],
            ['label' => 'Premium Sistemi', 'href' => route('panel.adminpanel.placeholder', 'premium-sistemi')],
            ['label' => 'İndirimler', 'href' => route('panel.adminpanel.placeholder', 'indirimler')],
            ['label' => 'Bildirimler', 'href' => route('panel.adminpanel.placeholder', 'bildirimler-sistemi')],
            ['label' => 'Sistem Ayarları', 'href' => route('panel.adminpanel.placeholder', 'sistem-ayarlari')],
        ],
        'Denetim Merkezi' => [
            ['label' => 'İşlem Geçmişi', 'href' => route('panel.adminpanel.placeholder', 'islem-gecmisi')],
            ['label' => 'Sistem Günlükleri', 'href' => route('panel.adminpanel.placeholder', 'sistem-gunlukleri')],
        ],
    ];
@endphp

<div class="mb-7">
    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2.5">Gösterge Paneli</div>
    <div class="space-y-0.5">
        <a href="{{ route('panel.adminpanel.index') }}" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm {{ request()->routeIs('panel.adminpanel.index') ? 'bg-white text-slate-900 font-medium' : 'text-slate-300 hover:bg-slate-800' }}">
            <x-heroicon-o-home class="w-4 h-4" />
            Ana Sayfa
        </a>
    </div>
</div>

@foreach ($groups as $group => $links)
    <div class="mb-7">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2.5">{{ $group }}</div>
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
                <a href="{{ $link['href'] }}" @if (! empty($link['external'])) data-turbo="false" @endif class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-lg text-sm transition-colors {{ request()->fullUrlIs($link['href']) ? 'bg-white text-slate-900 font-medium' : 'text-slate-300 hover:bg-slate-800' }}">
                    {{ $link['label'] }}
                    @if (! empty($link['badge']))
                        <span class="min-w-[1.25rem] h-5 px-1 rounded-full bg-brand-500 text-white text-[11px] leading-5 text-center">{{ $link['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
@endforeach

<div class="pt-3 mt-1 border-t border-slate-800">
    <a href="{{ url('/admin') }}" data-turbo="false" class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-slate-400 hover:bg-slate-800 transition-colors">
        <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
        Filament Yönetim Paneli
    </a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="w-full flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm text-red-400 hover:bg-red-950/50 transition-colors">
            <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
            Çıkış Yap
        </button>
    </form>
</div>
