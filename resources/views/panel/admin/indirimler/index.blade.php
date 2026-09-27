@extends('layouts.admin-panel')

@section('title', 'İndirimler')

@section('content')
    <div class="mb-6">
        <h1 class="font-serif text-xl font-semibold text-slate-900">İndirimler</h1>
        <p class="text-sm text-slate-500 mt-1">Toplu kampanya uygulayın, indirimdeki kitapları yönetin. Tek bir kitabın indirimi kitap düzenleme formundan da değiştirilebilir.</p>
    </div>

    {{-- Toplu kampanya: hedef (tüm kitaplar / kategori / seçili kitaplar) + yüzde + isteğe
         bağlı bitiş. Sadece yayındaki ücretli kitaplara uygulanır; mevcut indirimlerin üzerine yazar. --}}
    <form method="POST" action="{{ route('panel.adminpanel.indirimler.toplu') }}" x-data="{ target: '{{ old('target', 'kategori') }}' }" class="card p-5 sm:p-6 mb-8 space-y-5">
        {{-- Başlık @csrf'den önce: gizli input ilk çocuk olursa space-y başlığa da üst boşluk verip kartın tepesini açıyordu. --}}
        <h2 class="font-serif text-base font-semibold text-slate-900">Toplu Kampanya Uygula</h2>
        @csrf

        <div>
            <div class="block text-sm font-medium text-slate-700 mb-2">Hedef</div>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach (['tumu' => 'Tüm yayındaki kitaplar', 'kategori' => 'Bir kategori', 'secili' => 'Seçili kitaplar'] as $value => $label)
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="target" value="{{ $value }}" x-model="target" class="text-slate-900 focus:ring-slate-500"> {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('target') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div x-show="target === 'kategori'" x-cloak>
            <label for="category_id" class="block text-sm font-medium text-slate-700 mb-1">Kategori</label>
            <select id="category_id" name="category_id" class="w-full sm:w-72 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">— Seçiniz —</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div x-show="target === 'secili'" x-cloak>
            <div class="block text-sm font-medium text-slate-700 mb-1">Kitaplar</div>
            @if ($eligibleBooks->isEmpty())
                <p class="text-sm text-slate-400">Yayında ücretli bir kitap yok.</p>
            @else
                <div class="max-h-64 overflow-y-auto rounded-md border border-slate-200 divide-y divide-slate-100">
                    @foreach ($eligibleBooks as $eligible)
                        <label class="flex items-center justify-between gap-3 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <span class="flex items-center gap-2 min-w-0">
                                <input type="checkbox" name="book_ids[]" value="{{ $eligible->id }}" @checked(in_array($eligible->id, old('book_ids', []))) class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span class="truncate">{{ $eligible->title }}</span>
                            </span>
                            <span class="text-slate-400 whitespace-nowrap">{{ number_format($eligible->price, 2, ',', '.') }} TL</span>
                        </label>
                    @endforeach
                </div>
            @endif
            @error('book_ids') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:max-w-lg">
            <div>
                <label for="percent" class="block text-sm font-medium text-slate-700 mb-1">İndirim Oranı (%)</label>
                <input id="percent" name="percent" type="number" min="1" max="90" value="{{ old('percent') }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('percent') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="ends_at" class="block text-sm font-medium text-slate-700 mb-1">Bitiş (opsiyonel)</label>
                <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at') }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('ends_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <p class="text-xs text-slate-400">Sadece yayındaki ücretli kitaplara uygulanır. Hedefteki kitapların mevcut indirimleri bu kampanyayla değiştirilir.</p>

        <button type="submit" class="btn-brand btn-sm">Kampanyayı Uygula</button>
    </form>

    <h2 class="font-serif text-base font-semibold text-slate-900 mb-3">İndirimdeki Kitaplar</h2>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                        <th class="px-5 py-3 font-medium">Başlık</th>
                        <th class="px-5 py-3 font-medium">Fiyat</th>
                        <th class="px-5 py-3 font-medium">İndirimli</th>
                        <th class="px-5 py-3 font-medium">Bitiş</th>
                        <th class="px-5 py-3 font-medium">Durum</th>
                        <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($discountedBooks as $book)
                        @php
                            $isActive = $book->activeDiscountPrice() !== null;
                            $percent = (float) $book->price > 0 ? round((1 - (float) $book->discount_price / (float) $book->price) * 100) : 0;
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-2.5 max-w-xs">
                                <div class="text-slate-900 truncate">{{ $book->title }}</div>
                                <div class="text-xs text-slate-400 truncate">{{ $book->author->name }}</div>
                            </td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ number_format((float) $book->price, 2, ',', '.') }} TL</td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                <span class="text-brand-700 font-medium">{{ number_format((float) $book->discount_price, 2, ',', '.') }} TL</span>
                                <span class="text-xs text-slate-400">%{{ $percent }}</span>
                            </td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $book->discount_ends_at?->format('d.m.Y H:i') ?? 'Süresiz' }}</td>
                            <td class="px-5 py-2.5 whitespace-nowrap">
                                @if ($isActive)
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200">Süresi doldu</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('panel.adminpanel.kitaplar.duzenle', $book) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                        <x-heroicon-o-pencil class="w-4 h-4" /> Düzenle
                                    </a>
                                    <form method="POST" action="{{ route('panel.adminpanel.indirimler.kaldir', $book) }}" data-turbo-confirm="&quot;{{ $book->title }}&quot; kitabının indirimi kaldırılacak. Emin misiniz?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                            <x-heroicon-o-x-mark class="w-4 h-4" /> Kaldır
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-slate-400">İndirimde bir kitap yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
