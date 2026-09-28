@extends('layouts.admin-panel')

@section('title', $book ? 'Kitabı Düzenle' : 'Yeni Kitap')

@section('content')
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <h1 class="font-serif text-xl font-semibold text-slate-900">{{ $book ? 'Kitabı Düzenle' : 'Yeni Kitap' }}</h1>
            @if ($book)
                <x-status-badge :status="$book->status" />
            @endif
        </div>

        <form
            method="POST"
            action="{{ $book ? route('panel.adminpanel.kitaplar.guncelle', $book) : route('panel.adminpanel.kitaplar.store') }}"
            enctype="multipart/form-data"
            class="card p-6 space-y-5"
        >
            @csrf
            @if ($book)
                @method('PUT')
            @endif

            {{-- Faz G3: sözlük bir kitap türü (katalogda /sozlukler, editörde "Kavram"). --}}
            <div>
                <label for="kind" class="block text-sm font-medium text-slate-700 mb-1">Tür</label>
                <select id="kind" name="kind" class="w-full sm:w-60 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="{{ \App\Models\Book::KIND_KITAP }}" @selected(old('kind', $book?->kind ?? request('tur')) !== \App\Models\Book::KIND_SOZLUK)>Kitap</option>
                    <option value="{{ \App\Models\Book::KIND_SOZLUK }}" @selected(old('kind', $book?->kind ?? request('tur')) === \App\Models\Book::KIND_SOZLUK)>Sözlük</option>
                </select>
                @error('kind') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="author_id" class="block text-sm font-medium text-slate-700 mb-1">Yazar</label>
                <select id="author_id" name="author_id" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach ($authors as $author)
                        <option value="{{ $author->id }}" @selected(old('author_id', $book?->author_id) == $author->id)>{{ $author->name }}</option>
                    @endforeach
                </select>
                @error('author_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
                <input id="title" name="title" type="text" value="{{ old('title', $book?->title) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="slug" class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                <input id="slug" name="slug" type="text" value="{{ old('slug', $book?->slug) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                <p class="text-xs text-slate-400 mt-1">Kitap sayfasının URL'inde kullanılır, benzersiz olmalı.</p>
                @error('slug') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Açıklama</label>
                <textarea id="description" name="description" rows="6" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('description', $book?->description) }}</textarea>
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cover_image" class="block text-sm font-medium text-slate-700 mb-1">Kapak Görseli</label>
                @if ($book?->cover_image)
                    <x-book-cover :book="$book" class="w-24 aspect-[3/4] rounded-md mb-2" />
                @endif
                <input id="cover_image" name="cover_image" type="file" accept="image/*" class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:text-sm file:text-slate-700 hover:file:bg-slate-50">
                <p class="text-xs text-slate-400 mt-1">{{ $book ? 'Değiştirmek istemiyorsanız boş bırakın.' : 'Opsiyonel, en fazla 5MB.' }}</p>
                @error('cover_image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Fiyat 0 ise "Ücretsiz" açıkça işaretlenmeli (bkz. Concerns\ResolvesBookPrice). Var olan
                 0 TL'lik bir kitap düzenlenirken kutu işaretli gelir. Ücretsizken indirim alanları kapanır. --}}
            @php $isFree = (bool) old('is_free', $book && (float) $book->price === 0.0); @endphp
            <div x-data="{ free: {{ $isFree ? 'true' : 'false' }} }" class="space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="price" class="block text-sm font-medium text-slate-700 mb-1">Fiyat (TL)</label>
                        <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $book && (float) $book->price > 0 ? $book->price : null) }}" :disabled="free" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-400">
                        @error('price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="discount_price" class="block text-sm font-medium text-slate-700 mb-1">İndirimli Fiyat</label>
                        <input id="discount_price" name="discount_price" type="number" step="0.01" min="0" value="{{ old('discount_price', $book?->discount_price) }}" :disabled="free" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-400">
                        <p class="text-xs text-slate-400 mt-1">Doluysa "Fırsatlar" rafında gösterilir.</p>
                        @error('discount_price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="discount_ends_at" class="block text-sm font-medium text-slate-700 mb-1">İndirim Bitişi</label>
                        <input id="discount_ends_at" name="discount_ends_at" type="datetime-local" value="{{ old('discount_ends_at', $book?->discount_ends_at?->format('Y-m-d\TH:i')) }}" :disabled="free" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-400">
                        <p class="text-xs text-slate-400 mt-1">Boşsa süresiz.</p>
                        @error('discount_ends_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_free" value="1" x-model="free" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    Bu kitap ücretsiz
                </label>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_editors_pick" value="1" @checked(old('is_editors_pick', $book?->is_editors_pick)) class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                Editörün Seçkisi
            </label>

            @if ($book)
                <div>
                    <div class="block text-sm font-medium text-slate-700 mb-1">Durum</div>
                    <p class="text-xs text-slate-400">Sadece <a href="{{ route('panel.adminpanel.onaylar.index', ['tur' => 'kitaplar']) }}" class="underline">İçerik Onayları</a>'ndaki Onayla/Reddet/Yayınla aksiyonlarıyla değişir.</p>
                </div>
            @else
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-1">Durum</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach (\App\Enums\ContentStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', 'taslak') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Oluşturduktan sonra durum sadece İçerik Onayları'ndaki aksiyonlarla değişir.</p>
                    @error('status') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="border-t border-slate-100 pt-5">
                <div class="text-sm font-medium text-slate-700 mb-1">Kategoriler</div>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach ($categories as $category)
                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="categories[]"
                                value="{{ $category->id }}"
                                {{ collect(old('categories', $book?->categories->pluck('id') ?? []))->contains($category->id) ? 'checked' : '' }}
                                class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                            >
                            {{ $category->name }}
                        </label>
                    @endforeach
                </div>
                @error('categories') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-100 pt-5">
                <label for="published_at" class="block text-sm font-medium text-slate-700 mb-1">Yayın Tarihi</label>
                <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $book?->published_at?->format('Y-m-d\TH:i')) }}" class="w-full max-w-xs rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('published_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-100 pt-5">
                <div class="text-sm font-medium text-slate-700 mb-1">Değerlendirme</div>
                <p class="text-xs text-slate-400 mb-3">Gerçek bir yorum/puanlama sistemi kurulana kadar özet değerler burada elle girilir.</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="average_rating" class="block text-xs text-slate-500 mb-1">Ortalama Puan</label>
                        <input id="average_rating" name="average_rating" type="number" step="0.1" min="0" max="5" value="{{ old('average_rating', $book?->average_rating) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('average_rating') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="review_count" class="block text-xs text-slate-500 mb-1">Değerlendirme Sayısı</label>
                        <input id="review_count" name="review_count" type="number" min="0" value="{{ old('review_count', $book?->review_count) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        @error('review_count') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-5">
                <div class="text-sm font-medium text-slate-700 mb-1">İçerik İstatistikleri</div>
                <p class="text-xs text-slate-400 mb-3">Satın alma sayfasında sadece doldurulan alanlar gösterilir.</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    @foreach ([
                        'page_count' => 'Sayfa',
                        'map_count' => 'Harita',
                        'author_note_count' => 'Yazar Notu',
                        'source_count' => 'Kaynak',
                    ] as $field => $label)
                        <div>
                            <label for="{{ $field }}" class="block text-xs text-slate-500 mb-1">{{ $label }}</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="number" min="0" value="{{ old($field, $book?->$field) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error($field) <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-slate-500 mt-3">Belge ve video sayısı metinden otomatik hesaplanır{{ $book && ($book->document_count || $book->video_count) ? ': '.collect([$book->document_count ? $book->document_count.' belge' : null, $book->video_count ? $book->video_count.' video' : null])->filter()->implode(', ') : '' }}.</p>
            </div>

            <div class="flex items-center gap-4 pt-1">
                <button type="submit" class="btn-brand">{{ $book ? 'Değişiklikleri Kaydet' : 'Kitabı Oluştur' }}</button>
                <a href="{{ route('panel.adminpanel.kitaplar.index') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">Vazgeç</a>
            </div>
        </form>
    </div>
@endsection
