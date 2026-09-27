@extends('layouts.admin-panel')

@section('title', $magazine ? 'Dergiyi Düzenle' : 'Yeni Dergi')

@section('content')
    @php $inputClass = 'w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500'; @endphp

    <div class="max-w-2xl mx-auto">
        <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">{{ $magazine ? 'Dergiyi Düzenle' : 'Yeni Dergi' }}</h1>

        <form method="POST" action="{{ $magazine ? route('panel.adminpanel.dergiler.guncelle', $magazine) : route('panel.adminpanel.dergiler.store') }}" enctype="multipart/form-data" class="card p-6 space-y-5">
            {{-- Slug, ad yazılırken otomatik doluyor (elle değiştirilmediyse). --}}
            <div x-data="{ name: @js(old('name', $magazine?->name ?? '')), slug: @js(old('slug', $magazine?->slug ?? '')), touched: {{ $magazine ? 'true' : 'false' }} }" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Dergi Adı</label>
                    <input id="name" name="name" type="text" x-model="name" @input="if (! touched) slug = name.toLocaleLowerCase('tr').replace(/ı/g, 'i').replace(/ğ/g, 'g').replace(/ü/g, 'u').replace(/ş/g, 's').replace(/ö/g, 'o').replace(/ç/g, 'c').replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')" class="{{ $inputClass }}">
                    @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="slug" class="block text-sm font-medium text-slate-700 mb-1">Adres (slug)</label>
                    <input id="slug" name="slug" type="text" x-model="slug" @input="touched = true" class="{{ $inputClass }}">
                    @error('slug') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-slate-700 mb-1">Açıklama</label>
                <textarea id="description" name="description" rows="4" class="{{ $inputClass }}">{{ old('description', $magazine?->description) }}</textarea>
                @error('description') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cover_image" class="block text-sm font-medium text-slate-700 mb-1">Kapak / Logo</label>
                <input id="cover_image" name="cover_image" type="file" accept="image/*" class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:text-sm file:text-slate-700 hover:file:bg-slate-50">
                <p class="text-xs text-slate-400 mt-1">{{ $magazine?->cover_image ? 'Yüklü bir görsel var — değiştirmek istemiyorsanız boş bırakın.' : 'Opsiyonel, en fazla 5MB.' }}</p>
                @error('cover_image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-100 pt-5">
                <label for="editor_id" class="block text-sm font-medium text-slate-700 mb-1">Editör</label>
                <select id="editor_id" name="editor_id" class="{{ $inputClass }} sm:max-w-xs">
                    <option value="">— Atanmamış —</option>
                    @foreach ($editors as $editor)
                        <option value="{{ $editor->id }}" @selected(old('editor_id', $magazine?->editor_id) == $editor->id)>{{ $editor->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">Editör, derginin sayılarını hazırlar ve gelen makaleleri inceler. Değiştirirseniz derginin sayıları yeni editöre devredilir. Listede "Dergi Editörü" rolündeki kullanıcılar var.</p>
                @error('editor_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-100 pt-5">
                <div class="text-sm font-medium text-slate-700 mb-1">Yazarlar</div>
                <p class="text-xs text-slate-400 mb-3">Sadece işaretli yazarlar bu dergiye makale gönderebilir. Listede "Yazar" rolündeki kullanıcılar var.</p>
                @php $selectedAuthors = collect(old('author_ids', $magazine?->authors->pluck('id') ?? []))->map(fn ($id) => (int) $id); @endphp
                @if ($authors->isEmpty())
                    <p class="text-sm text-slate-400">Henüz "Yazar" rolünde kullanıcı yok.</p>
                @else
                    <div class="max-h-64 overflow-y-auto rounded-md border border-slate-200 divide-y divide-slate-100">
                        @foreach ($authors as $author)
                            <label class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <input type="checkbox" name="author_ids[]" value="{{ $author->id }}" @checked($selectedAuthors->contains($author->id)) class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span class="truncate">{{ $author->name }}</span>
                                <span class="text-xs text-slate-400 truncate">{{ $author->email }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('author_ids.*') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @csrf
            @if ($magazine)
                @method('PUT')
            @endif
            <div class="flex items-center gap-4 pt-1">
                <button type="submit" class="btn-brand">{{ $magazine ? 'Değişiklikleri Kaydet' : 'Dergiyi Oluştur' }}</button>
                <a href="{{ route('panel.adminpanel.dergiler.index') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">Vazgeç</a>
            </div>
        </form>
    </div>
@endsection
