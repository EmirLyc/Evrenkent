@extends('layouts.panel')

@section('title', 'Makaleyi Düzenle')

@section('content')
    <div class="flex items-center justify-between gap-3 flex-wrap mb-5 max-w-3xl mx-auto">
        <h1 class="font-serif text-xl font-semibold text-slate-900">Makaleyi Düzenle</h1>
        {{-- Faz F2: metne gömülü belgeler. --}}
        <a href="{{ route('panel.yayinlarim.makale.belgeler', $article) }}" class="text-sm px-4 py-2 border border-slate-300 bg-white text-slate-700 rounded-lg hover:bg-slate-50 transition-colors inline-flex items-center gap-1.5">
            <x-snowflake-icon class="w-4 h-4" /> Belgeler ({{ $article->documents->count() }})
        </a>
    </div>

    <form method="POST" action="{{ route('panel.yayinlarim.makale.guncelle', $article) }}" class="bg-white border border-slate-200 rounded-lg p-4 sm:p-6 space-y-5 max-w-3xl mx-auto">
        @csrf
        @method('PUT')

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title', $article->title) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <x-rich-editor
                name="body"
                :value="old('body', $article->content)"
                :import-url="route('panel.yayinlarim.word-aktar', ['makale' => $article->id])"
                title-input="title"
                :documents="$article->documents"
                :documents-url="route('panel.yayinlarim.makale.belgeler', $article)"
            />
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="magazine_issue_id" class="block text-sm font-medium text-slate-700 mb-1">Dergi Sayısı</label>
            <select id="magazine_issue_id" name="magazine_issue_id" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">— Seçiniz —</option>
                {{-- Faz E: sadece yazar/editör olarak atandığınız dergilerin açık sayıları (+ makalenin mevcut sayısı), dergiye göre gruplu. --}}
                @foreach ($magazineIssues->groupBy(fn ($issue) => $issue->magazine?->name ?? 'Diğer') as $magazineName => $issuesOfMagazine)
                    <optgroup label="{{ $magazineName }}">
                        @foreach ($issuesOfMagazine as $issue)
                            <option value="{{ $issue->id }}" @selected(old('magazine_issue_id', $article->magazine_issue_id) == $issue->id)>{{ $issue->title }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">Yalnızca Süper Admin'in sizi yazar ya da editör olarak atadığı dergilerin sayıları listelenir.</p>
            @error('magazine_issue_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
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
