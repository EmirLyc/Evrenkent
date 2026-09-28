@extends('layouts.admin-panel')

@section('title', $article ? 'Makaleyi Düzenle' : 'Yeni Makale')

@section('content')
    @php $inputClass = 'w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500'; @endphp

    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
            <h1 class="font-serif text-xl font-semibold text-slate-900">{{ $article ? 'Makaleyi Düzenle' : 'Yeni Makale' }}</h1>
            @if ($article)
                <div class="flex items-center gap-3">
                    <x-status-badge :status="$article->status" />
                    <a href="{{ route('makaleler.show', $article) }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 transition-colors">
                        <x-heroicon-o-eye class="w-4 h-4" /> Görüntüle
                    </a>
                </div>
            @endif
        </div>

        <form
            method="POST"
            action="{{ $article ? route('panel.adminpanel.makaleler.guncelle', $article) : route('panel.adminpanel.makaleler.store') }}"
            class="card p-4 sm:p-6 space-y-5"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="author_id" class="block text-sm font-medium text-slate-700 mb-1">Yazar</label>
                    <select id="author_id" name="author_id" class="{{ $inputClass }}">
                        @foreach ($authors as $author)
                            <option value="{{ $author->id }}" @selected(old('author_id', $article?->author_id) == $author->id)>{{ $author->name }}</option>
                        @endforeach
                    </select>
                    @error('author_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="magazine_issue_id" class="block text-sm font-medium text-slate-700 mb-1">Dergi Sayısı</label>
                    <select id="magazine_issue_id" name="magazine_issue_id" class="{{ $inputClass }}">
                        <option value="">— Seçiniz —</option>
                        @foreach ($magazineIssues->groupBy(fn ($issue) => $issue->magazine?->name ?? 'Diğer') as $magazineName => $issuesOfMagazine)
                            <optgroup label="{{ $magazineName }}">
                                @foreach ($issuesOfMagazine as $issue)
                                    <option value="{{ $issue->id }}" @selected(old('magazine_issue_id', $article?->magazine_issue_id) == $issue->id)>{{ $issue->title }} ({{ $issue->status->label() }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('magazine_issue_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Slug, başlık yazılırken otomatik doluyor (yeni makalede, elle değiştirilmediyse). --}}
            <div x-data="{ title: @js(old('title', $article?->title ?? '')), slug: @js(old('slug', $article?->slug ?? '')), touched: {{ $article ? 'true' : 'false' }} }" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
                    <input id="title" name="title" type="text" x-model="title" @input="if (! touched) slug = title.toLocaleLowerCase('tr').replace(/ı/g, 'i').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ö/g, 'o').replace(/ç/g, 'c').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')" class="{{ $inputClass }}">
                    @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700 mb-1">Adres (slug)</label>
                    <input id="slug" name="slug" type="text" x-model="slug" @input="touched = true" class="{{ $inputClass }}">
                    <p class="text-xs text-slate-400 mt-1">Makale sayfasının URL'inde kullanılır, benzersiz olmalı.</p>
                    @error('slug') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                {{-- Yazar panelindeki editörün aynısı; makalenin yüklenmiş belgeleri kar tanesiyle eklenebilir. --}}
                <x-rich-editor
                    name="body"
                    :value="old('body', $article?->content)"
                    :documents="$article?->documents"
                />
                @if ($article)
                    <p class="text-xs text-slate-400 mt-1">Belgeler ({{ $article->documents->count() }}) yazarın Belgeler sayfasından yüklenir.</p>
                @endif
                @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @if ($article)
                <div>
                    <div class="block text-sm font-medium text-slate-700 mb-1">Durum</div>
                    <p class="text-xs text-slate-400">Sadece <a href="{{ route('panel.adminpanel.onaylar.index', ['tur' => 'makaleler']) }}" class="underline">İçerik Onayları</a>'ndaki Onayla/Reddet/Yayınla aksiyonlarıyla değişir.</p>
                </div>
            @else
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700 mb-1">Durum</label>
                    <select id="status" name="status" class="{{ $inputClass }} sm:max-w-xs">
                        @foreach (\App\Enums\ContentStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', 'taslak') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-400 mt-1">Oluşturduktan sonra durum sadece İçerik Onayları'ndaki aksiyonlarla değişir. Sayısı yayında değilse "Yayında" seçilemez.</p>
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
                                @checked(collect(old('categories', $article?->categories->pluck('id') ?? []))->contains($category->id))
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
                <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', $article?->published_at?->format('Y-m-d\TH:i')) }}" class="{{ $inputClass }} sm:max-w-xs">
                @error('published_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-4 pt-1">
                <button type="submit" class="btn-brand">{{ $article ? 'Değişiklikleri Kaydet' : 'Makaleyi Oluştur' }}</button>
                <a href="{{ route('panel.adminpanel.makaleler.index') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">Vazgeç</a>
            </div>

            {{-- @csrf sonda: ilk çocuk olunca kartın tepesinde fazladan boşluk bırakıyordu (bkz. madde 49). --}}
            @csrf
            @if ($article)
                @method('PUT')
            @endif
        </form>
    </div>
@endsection
