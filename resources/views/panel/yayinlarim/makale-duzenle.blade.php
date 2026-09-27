@extends('layouts.panel')

@section('title', 'Makaleyi Düzenle')

@section('content')
    <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">Makaleyi Düzenle</h1>

    <form method="POST" action="{{ route('panel.yayinlarim.makale.guncelle', $article) }}" class="bg-white border border-slate-200 rounded-lg p-6 space-y-5 max-w-2xl mx-auto">
        @csrf
        @method('PUT')

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title', $article->title) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="body" class="block text-sm font-medium text-slate-700 mb-1">İçerik</label>
            <textarea id="body" name="body" rows="8" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('body', $article->content) }}</textarea>
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="magazine_issue_id" class="block text-sm font-medium text-slate-700 mb-1">Dergi Sayısı</label>
            <select id="magazine_issue_id" name="magazine_issue_id" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                <option value="">— Seçiniz —</option>
                {{-- Faz E: sadece atandığınız dergilerin açık sayıları (+ makalenin mevcut sayısı), dergiye göre gruplu. --}}
                @foreach ($magazineIssues->groupBy(fn ($issue) => $issue->magazine?->name ?? 'Diğer') as $magazineName => $issuesOfMagazine)
                    <optgroup label="{{ $magazineName }}">
                        @foreach ($issuesOfMagazine as $issue)
                            <option value="{{ $issue->id }}" @selected(old('magazine_issue_id', $article->magazine_issue_id) == $issue->id)>{{ $issue->title }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">Yalnızca Süper Admin'in sizi yazar olarak atadığı dergilerin sayıları listelenir.</p>
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
