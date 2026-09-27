@extends('layouts.panel')

@section('title', 'Kitabı Düzenle')

@section('content')
    <div class="flex items-center justify-between gap-3 mb-5 max-w-2xl mx-auto flex-wrap">
        <h1 class="font-serif text-xl font-semibold text-slate-900">Kitabı Düzenle</h1>
        <a href="{{ route('panel.yayinlarim.kitap.bolumler', $book) }}" class="text-sm px-3.5 py-1.5 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
            Bölümler
        </a>
    </div>

    <form method="POST" action="{{ route('panel.yayinlarim.kitap.guncelle', $book) }}" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5 max-w-2xl mx-auto">
        @csrf
        @method('PUT')

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title', $book->title) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Fiyat yazardan alınmıyor (2026-09-27 revizesi) — Süper Admin onay aşamasında belirliyor. --}}
        <p class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-md px-3 py-2">
            Kitabın satış fiyatı, onay aşamasında Süper Admin tarafından belirlenir.
        </p>

        <div>
            <label for="body" class="block text-sm font-medium text-slate-700 mb-1">Açıklama</label>
            <textarea id="body" name="body" rows="8" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('body', $book->description) }}</textarea>
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="border-t border-slate-100 pt-5">
            <div class="text-sm font-medium text-slate-700 mb-1">Kategoriler / Etiketler</div>
            <p class="text-xs text-slate-400 mb-3">Kitap sayfasında tıklanabilir etiket olarak görünür, okur aynı etiketteki diğer kitapları keşfedebilir.</p>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                @foreach ($categories as $category)
                    <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                        <input
                            type="checkbox"
                            name="categories[]"
                            value="{{ $category->id }}"
                            {{ collect(old('categories', $book->categories->pluck('id')))->contains($category->id) ? 'checked' : '' }}
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                        >
                        {{ $category->name }}
                    </label>
                @endforeach
            </div>
            @error('categories') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="border-t border-slate-100 pt-5">
            <label for="scheduled_publish_at" class="block text-sm font-medium text-slate-700 mb-1">Hedef Yayın Tarihi (opsiyonel)</label>
            <p class="text-xs text-slate-400 mb-2">Kesin bir taahhüt değil — kitap onaya gönderildiğinde Süper Admin bu tarihi görüp değiştirebilir/kesinleştirebilir. Dolduysa "Yakında Çıkacaklar" rafında görünür.</p>
            <input id="scheduled_publish_at" name="scheduled_publish_at" type="datetime-local" value="{{ old('scheduled_publish_at', $book->scheduled_publish_at?->format('Y-m-d\TH:i')) }}" class="w-full max-w-xs rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('scheduled_publish_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="border-t border-slate-100 pt-5">
            <div class="text-sm font-medium text-slate-700 mb-1">İçerik İstatistikleri</div>
            <p class="text-xs text-slate-400 mb-3">Satın alma sayfasında sadece doldurduğunuz alanlar gösterilir, boş bıraktıklarınız hiç görünmez.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                @foreach ([
                    'page_count' => 'Sayfa',
                    'document_count' => 'Belge',
                    'video_count' => 'Video',
                    'map_count' => 'Harita',
                    'author_note_count' => 'Yazar Notu',
                    'source_count' => 'Kaynak',
                ] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="block text-xs text-slate-500 mb-1">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" value="{{ old($field, $book->$field) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @error($field) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-4 pt-1">
            <button type="submit" class="px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 transition-colors">
                Kaydet
            </button>
            <a href="{{ route('panel.yayinlarim.taslaklarim') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">
                Vazgeç
            </a>
        </div>
    </form>
@endsection
