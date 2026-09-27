@extends('layouts.panel')

@section('title', $chapter ? 'Bölümü Düzenle' : 'Yeni Bölüm')

@section('content')
    <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">{{ $chapter ? 'Bölümü Düzenle' : 'Yeni Bölüm' }}</h1>

    <form
        method="POST"
        action="{{ $chapter ? route('panel.yayinlarim.kitap.bolumler.guncelle', [$book, $chapter]) : route('panel.yayinlarim.kitap.bolumler.store', $book) }}"
        class="bg-white border border-slate-200 rounded-lg p-4 sm:p-6 space-y-5 max-w-3xl mx-auto"
    >
        @csrf
        @if ($chapter)
            @method('PUT')
        @endif

        <div>
            <label for="order" class="block text-sm font-medium text-slate-700 mb-1">Sıra No</label>
            <input id="order" name="order" type="number" min="1" value="{{ old('order', $nextOrder) }}" class="w-32 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('order') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Bölüm Başlığı</label>
            <input id="title" name="title" type="text" value="{{ old('title', $chapter?->title) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-rich-editor
                name="content"
                :value="old('content', $chapter?->content)"
                :import-url="route('panel.yayinlarim.word-aktar')"
                title-input="title"
                :documents="$book->documents"
                :documents-url="route('panel.yayinlarim.kitap.belgeler', $book)"
            />
            <p class="text-xs text-slate-400 mt-1">Tüm kitabı tek dosyada yüklemek için bölümler sayfasındaki "Word'den Bölüm Aktar"ı kullanın.</p>
            @error('content') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4 pt-1">
            <button type="submit" class="px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 transition-colors">
                Kaydet
            </button>
            <a href="{{ route('panel.yayinlarim.kitap.bolumler', $book) }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">
                Vazgeç
            </a>
        </div>
    </form>
@endsection
