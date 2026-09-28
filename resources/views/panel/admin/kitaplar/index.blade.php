@extends('layouts.admin-panel')

@php $isDictionary = $tur === \App\Models\Book::KIND_SOZLUK; $noun = $isDictionary ? 'sözlük' : 'kitap'; @endphp

@section('title', $isDictionary ? 'Sözlükler' : 'Kitaplar')

@section('content')
    <div class="flex items-center justify-between gap-3 flex-wrap mb-6">
        <div>
            <h1 class="font-serif text-xl font-semibold text-slate-900">{{ $isDictionary ? 'Sözlükler' : 'Kitaplar' }}</h1>
            <p class="text-sm text-slate-500 mt-1">{{ $books->total() }} {{ $noun }}.</p>
        </div>
        <a href="{{ route('panel.adminpanel.kitaplar.yeni', $isDictionary ? ['tur' => 'sozluk'] : []) }}" class="btn-brand btn-sm">
            <x-heroicon-o-plus class="w-4 h-4" /> {{ $isDictionary ? 'Yeni Sözlük' : 'Yeni Kitap' }}
        </a>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 mb-5">
        @if ($isDictionary) <input type="hidden" name="tur" value="sozluk"> @endif
        <input type="text" name="q" value="{{ $q }}" placeholder="Başlıkta ara…" class="w-full sm:w-64 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        <select name="durum" class="rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            <option value="">Tüm Durumlar</option>
            @foreach (\App\Enums\ContentStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected($durum === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn-outline btn-sm">Filtrele</button>
        @if ($q || $durum)
            <a href="{{ route('panel.adminpanel.kitaplar.index', $isDictionary ? ['tur' => 'sozluk'] : []) }}" class="text-sm text-slate-500 hover:text-slate-900 self-center transition-colors">Temizle</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                        <th class="px-5 py-3 font-medium">Kapak</th>
                        <th class="px-5 py-3 font-medium">Başlık</th>
                        <th class="px-5 py-3 font-medium">Yazar</th>
                        @if ($isDictionary) <th class="px-5 py-3 font-medium">Madde</th> @endif
                        <th class="px-5 py-3 font-medium">Fiyat</th>
                        <th class="px-5 py-3 font-medium">Durum</th>
                        <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($books as $book)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-2.5">
                                <x-book-cover :book="$book" class="w-9 aspect-[3/4] rounded" />
                            </td>
                            <td class="px-5 py-2.5 max-w-xs">
                                <div class="text-slate-900 truncate">
                                    {{ $book->title }}
                                    @if ($book->is_editors_pick)
                                        <x-heroicon-o-sparkles class="w-3.5 h-3.5 inline text-amber-500" />
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $book->author->name }}</td>
                            @if ($isDictionary) <td class="px-5 py-2.5 text-slate-500 tabular-nums">{{ $book->entries_count }}</td> @endif
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ number_format((float) $book->price, 2, ',', '.') }} TL</td>
                            <td class="px-5 py-2.5 whitespace-nowrap"><x-status-badge :status="$book->status" /></td>
                            <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('kitaplar.show', $book) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                                        <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                                    </a>
                                    <a href="{{ route('panel.adminpanel.kitaplar.duzenle', $book) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                        <x-heroicon-o-pencil class="w-4 h-4" /> Düzenle
                                    </a>
                                    {{-- data-turbo-confirm, plain onsubmit="return confirm(...)" yerine kullanılıyor:
                                         Turbo Drive formu kendi submit akışına dahil ettiği için native onsubmit'in
                                         confirm() dönüş değeri Turbo'nun kendi işleyişiyle yarışıyor ve silme isteği
                                         hiç gönderilmeden sessizce düşebiliyordu — Turbo'nun kendi confirm mekanizması
                                         (data-turbo-confirm) bunu doğru şekilde submission akışının içine alıyor. --}}
                                    <form method="POST" action="{{ route('panel.adminpanel.kitaplar.sil', $book) }}" data-turbo-confirm="&quot;{{ $book->title }}&quot; kalıcı olarak silinecek. Emin misiniz?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                            <x-heroicon-o-trash class="w-4 h-4" /> Sil
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-8 text-center text-sm text-slate-400">{{ $isDictionary ? 'Sözlük' : 'Kitap' }} bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">
        {{ $books->links() }}
    </div>
@endsection
