@extends('layouts.panel')

@section('title', 'Bölümler')

@section('content')
    <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
        <div class="min-w-0">
            <h1 class="font-serif text-xl font-semibold text-slate-900 break-words">{{ $book->title }} — Bölümler</h1>
            <a href="{{ route('panel.yayinlarim.kitap.duzenle', $book) }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">
                &larr; Kitaba dön
            </a>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" x-data @click="$dispatch('toggle-word-import')" class="text-sm px-4 py-2 border border-slate-300 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors inline-flex items-center gap-1.5">
                <x-heroicon-o-document-arrow-up class="w-4 h-4" /> Word'den Bölüm Aktar
            </button>
            <a href="{{ route('panel.yayinlarim.kitap.bolumler.yeni', $book) }}" class="text-sm px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors">
                Yeni Bölüm
            </a>
        </div>
    </div>

    {{-- Faz F1: tüm kitabı tek Word dosyasından bölümlere ayırarak ekleme. --}}
    <div
        x-data="{ open: @js($errors->has('file')) }"
        @toggle-word-import.window="open = !open"
        x-show="open"
        x-cloak
        class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5 mb-5"
    >
        <h2 class="font-medium text-slate-900">Word dosyasından bölüm aktar</h2>
        <p class="text-sm text-slate-500 mt-1">
            Dosyadaki her <strong class="font-medium text-slate-700">Başlık 1</strong> yeni bir bölüm başlatır ve bölümün adı olur; başlıktan önceki metin "Giriş" bölümü olarak eklenir.
            Kalın, eğik, alt başlıklar, listeler ve dipnotlar korunur. Bölümler mevcut bölümlerin sonuna eklenir, sonra tek tek düzenleyebilirsiniz.
        </p>
        <form method="POST" action="{{ route('panel.yayinlarim.kitap.bolumler.word-aktar', $book) }}" enctype="multipart/form-data" class="mt-3 flex flex-col sm:flex-row sm:items-center gap-3">
            @csrf
            <input type="file" name="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
            <button type="submit" class="text-sm px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors shrink-0">
                Bölümleri Oluştur
            </button>
        </form>
        @error('file') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
    </div>

    @if ($chapters->isEmpty())
        <div class="bg-white border border-slate-200 rounded-lg p-12 text-center text-slate-400">
            <x-heroicon-o-book-open class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Bu kitap için henüz bölüm eklenmedi.
        </div>
    @else
        <div class="bg-white border border-slate-200 rounded-lg divide-y divide-slate-100">
            @foreach ($chapters as $chapter)
                <div class="flex items-center justify-between gap-3 px-5 py-4 flex-wrap sm:flex-nowrap">
                    <div class="min-w-0">
                        <span class="text-xs uppercase text-orange-700 font-medium tracking-wide">Bölüm {{ $chapter->order }}</span>
                        <div class="font-medium text-slate-900 truncate">{{ $chapter->title }}</div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        <a href="{{ route('panel.yayinlarim.kitap.bolumler.duzenle', [$book, $chapter]) }}" class="text-sm px-3.5 py-1.5 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                            Düzenle
                        </a>
                        {{-- data-turbo-confirm: onsubmit="return confirm(...)" Turbo'nun kendi submit
                             akışıyla yarışıp isteği sessizce düşürebiliyordu (bkz. UI_RESTYLE_NOTES.md) —
                             Turbo'nun kendi confirm mekanizması kullanılıyor. --}}
                        <form method="POST" action="{{ route('panel.yayinlarim.kitap.bolumler.sil', [$book, $chapter]) }}" data-turbo-confirm="Bu bölümü silmek istediğinize emin misiniz?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm px-3.5 py-1.5 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                Sil
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
