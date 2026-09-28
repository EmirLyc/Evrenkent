@props(['item', 'view' => 'izgara'])

{{--
    Taslaklarım / Yayınlananlar kartı (Faz G1, "Yazarın Gözünden" 1.1): görsel, tür etiketi, başlık +
    alt satır, yazarın gördüğü durum, duruma göre tarih ve iki düğme, "…" menüsü. Izgara ve liste
    görünümü aynı bileşen — liste satırında görsel küçülüp yana geçer.
--}}
@php
    $isBook = $item instanceof \App\Models\Book;
    $key = $item->authorStatusKey();

    [$kindLabel, $kindClass] = $isBook ? ['Kitap', 'text-brand-700'] : ['Dergi Yazısı', 'text-sky-700'];
    $subtitle = $isBook
        ? ($item->latestChapter ? 'Bölüm '.$item->latestChapter->order.' – '.$item->latestChapter->title : null)
        : ($item->magazineIssue?->magazine ? '('.$item->magazineIssue->magazine->name.')' : null);

    $date = $item->authorStatusDate();
    $dateText = $date ? ($date->isToday() ? 'Bugün, '.$date->format('H:i') : ($date->isYesterday() ? 'Dün, '.$date->format('H:i') : $date->translatedFormat('j F Y'))) : null;
    $dateLabel = match ($key) {
        'taslak' => 'Son düzenleme',
        'incelemede' => 'Gönderim tarihi',
        'duzeltme' => 'Son güncelleme',
        'kabul' => 'Kabul tarihi',
        'reddedildi' => 'Ret tarihi',
        'yayinda' => 'Yayın tarihi',
    };

    $routes = [
        'edit' => $isBook ? route('panel.yayinlarim.kitap.duzenle', $item) : route('panel.yayinlarim.makale.duzenle', $item),
        'preview' => $isBook ? route('kitaplar.oku', $item) : route('makaleler.show', $item),
        'detail' => $isBook ? route('panel.yayinlarim.kitap.detay', $item) : route('panel.yayinlarim.makale.detay', $item),
        'messages' => $isBook ? route('panel.mesajlar.kitap', $item) : route('panel.mesajlar.makale', $item),
        'public' => $isBook ? route('kitaplar.show', $item) : route('makaleler.show', $item),
        'submit' => $isBook ? route('panel.yayinlarim.kitap.gonder', $item) : route('panel.yayinlarim.makale.gonder', $item),
        'delete' => $isBook ? route('panel.yayinlarim.kitap.sil', $item) : route('panel.yayinlarim.makale.sil', $item),
        'documents' => $isBook ? route('panel.yayinlarim.kitap.belgeler', $item) : route('panel.yayinlarim.makale.belgeler', $item),
    ];

    // Mockup'taki düğme çiftleri: [etiket, ikon, adres, birincil mi]
    $buttons = match ($key) {
        'taslak' => [['Düzenle', 'pencil', $routes['edit'], true], ['Önizle', 'eye', $routes['preview'], false]],
        'incelemede' => [['Gönderimi Gör', 'document-text', $routes['detail'], false], ['Sohbet', 'chat-bubble-oval-left', $routes['messages'], false]],
        'duzeltme' => [['Düzenlemeye Devam Et', 'pencil', $routes['edit'], false], ['Mesajlar', 'chat-bubble-oval-left', $routes['messages'], false]],
        'kabul' => [['Detayları Gör', 'information-circle', $routes['detail'], false], ['Yayın Sürecini Takip Et', 'chevron-right', $routes['detail'].'#surec', false]],
        'reddedildi' => [['Gönderimi Gör', 'document-text', $routes['detail'], false], ['Mesajlar', 'chat-bubble-oval-left', $routes['messages'], false]],
        'yayinda' => [['Görüntüle', 'eye', $routes['public'], false], ['Detayları Gör', 'information-circle', $routes['detail'], false]],
    };

    $list = $view === 'liste';
@endphp

<article class="card flex {{ $list ? 'flex-row items-stretch' : 'flex-col' }}">
    {{-- Görsel kırpılan kutuda, "…" menüsü onun dışında (yoksa açılan menü de kırpılıyordu). --}}
    <div class="relative shrink-0 {{ $list ? 'w-24 sm:w-40' : 'm-2.5 mb-0 aspect-[16/7]' }}">
        <div class="w-full h-full overflow-hidden {{ $list ? 'rounded-l-lg' : 'rounded-md' }}">
        @if ($isBook)
            <x-book-cover :book="$item" class="w-full h-full" icon-class="w-8 h-8" />
        @elseif ($item->cover_image)
            {{-- Faz G2: dergi yazısının kendi kapağı ("Kapak ve Tanıtım" adımı). --}}
            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.covers_disk'))->url($item->cover_image) }}" alt="{{ $item->title }}" class="w-full h-full object-cover">
        @elseif ($item->magazineIssue)
            <x-magazine-cover :issue="$item->magazineIssue" class="w-full h-full" />
        @else
            <div class="w-full h-full bg-gradient-to-br from-sky-700 to-slate-900 flex items-center justify-center">
                <x-heroicon-o-newspaper class="w-8 h-8 text-white/50" />
            </div>
        @endif
        </div>

        {{-- "…" menüsü --}}
        <div class="absolute top-2 right-2" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open" class="flex items-center justify-center w-8 h-8 rounded-md bg-white/95 text-slate-700 shadow-sm hover:bg-white" aria-label="Diğer işlemler" :aria-expanded="open">
                <x-heroicon-o-ellipsis-horizontal class="w-5 h-5" />
            </button>
            <div x-show="open" x-cloak x-transition.origin.top.right class="absolute right-0 mt-1 w-52 card py-1 z-20 text-sm">
                @can('submit', $item)
                    <form method="POST" action="{{ $routes['submit'] }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50">
                            <x-heroicon-o-paper-airplane class="w-4 h-4" /> Onaya Gönder
                        </button>
                    </form>
                @endcan
                @can('update', $item)
                    <a href="{{ $routes['edit'] }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-heroicon-o-pencil class="w-4 h-4" /> Düzenle</a>
                    <a href="{{ $routes['documents'] }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-snowflake-icon class="w-4 h-4" /> Belgeler</a>
                @endcan
                <a href="{{ $routes['preview'] }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-heroicon-o-eye class="w-4 h-4" /> Önizle</a>
                <a href="{{ $routes['detail'] }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-heroicon-o-information-circle class="w-4 h-4" /> Detaylar ve süreç</a>
                <a href="{{ $routes['messages'] }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-heroicon-o-chat-bubble-oval-left class="w-4 h-4" /> Mesajlar</a>
                @can('delete', $item)
                    <form method="POST" action="{{ $routes['delete'] }}" data-turbo-confirm="&quot;{{ $item->title }}&quot; çöp kutusuna taşınacak. Oradan geri alabilirsiniz.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-red-600 hover:bg-red-50 border-t border-slate-100">
                            <x-heroicon-o-trash class="w-4 h-4" /> Çöp kutusuna taşı
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <div class="flex-1 min-w-0 flex flex-col p-3.5 sm:p-4">
        <div class="text-[11px] font-semibold uppercase tracking-wider {{ $kindClass }}">{{ $kindLabel }}</div>
        <h3 class="font-serif text-lg font-semibold text-slate-900 leading-snug mt-1 break-words">{{ $item->title }}</h3>
        @if ($subtitle)
            <p class="text-sm text-slate-600 truncate">{{ $subtitle }}</p>
        @endif

        <div class="mt-3">
            <x-author-status-badge :item="$item" />
        </div>
        @if ($dateText)
            <p class="text-sm text-slate-500 mt-2">{{ $dateLabel }}: {{ $dateText }}</p>
        @endif

        <div class="grid grid-cols-2 gap-2 mt-auto pt-4">
            @foreach ($buttons as [$label, $icon, $href, $primary])
                {{-- Uzun etiketler ("Düzenlemeye Devam Et") dar kartta kırpılmasın, iki satıra insin. --}}
                <a href="{{ $href }}" class="{{ $primary ? 'btn-dark' : 'btn-outline' }} btn-sm justify-center min-w-0 whitespace-normal text-center leading-tight">
                    @svg('heroicon-o-'.$icon, 'w-4 h-4 shrink-0')
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
</article>
