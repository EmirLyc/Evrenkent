@extends('layouts.panel')

@section('title', 'Yeni Taslak Oluştur')

@section('content')
    <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">Yeni Taslak Oluştur</h1>

    {{-- Tür, doğrulama hatasından dönüşte korunuyor (önceden hep "Kitap"a dönüyordu). --}}
    <form method="POST" action="{{ route('panel.yayinlarim.taslaklarim.store') }}" x-data="{ type: @js(old('type', 'kitap')) }" class="bg-white border border-slate-200 rounded-lg p-4 sm:p-6 space-y-5 max-w-3xl mx-auto">
        @csrf

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Tür</label>
            <div class="flex gap-5">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="type" value="kitap" x-model="type" class="text-slate-900 focus:ring-slate-500"> Kitap
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="type" value="makale" x-model="type" class="text-slate-900 focus:ring-slate-500"> Makale
                </label>
            </div>
            @error('type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title') }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Fiyat yazardan alınmıyor (2026-09-27 revizesi) — Süper Admin onay aşamasında belirliyor. --}}
        <p x-show="type === 'kitap'" class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-md px-3 py-2">
            Kitabın satış fiyatı, onay aşamasında Süper Admin tarafından belirlenir.
        </p>

        {{-- Makale hangi dergi sayısı için yazılıyorsa o seçilmeli — bu seçim
             olmadan makale hiçbir Dergi Editörü'nün Makale Havuzu'nda görünmüyor. --}}
        <div x-show="type === 'makale'">
            <label for="magazine_issue_id" class="block text-sm font-medium text-slate-700 mb-1">Dergi Sayısı</label>
            {{-- Faz E: sadece Süper Admin'in sizi atadığı dergilerin açık sayıları, dergiye göre gruplu. --}}
            @if ($magazineIssues->isEmpty())
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                    Makale gönderebileceğiniz açık bir dergi sayısı yok. Makaleler yalnızca Süper Admin'in sizi yazar olarak atadığı dergilere gönderilebilir; atandığınız dergide editör yeni bir sayı açınca burada görünür.
                </p>
            @else
                <select id="magazine_issue_id" name="magazine_issue_id" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="">— Seçiniz —</option>
                    @foreach ($magazineIssues->groupBy(fn ($issue) => $issue->magazine?->name ?? 'Diğer') as $magazineName => $issuesOfMagazine)
                        <optgroup label="{{ $magazineName }}">
                            @foreach ($issuesOfMagazine as $issue)
                                <option value="{{ $issue->id }}" @selected(old('magazine_issue_id') == $issue->id)>{{ $issue->title }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            @endif
            @error('magazine_issue_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Kitapta düz metin açıklama (bölümler ayrıca eklenir), makalede zengin metin içerik.
             İkisi de "body" adıyla gidiyor; x-if sadece seçili türün alanını DOM'da tutuyor. --}}
        <div>
            <template x-if="type === 'kitap'">
                <div>
                    <label for="body" class="block text-sm font-medium text-slate-700 mb-1">Açıklama</label>
                    <textarea id="body" name="body" rows="8" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('type', 'kitap') === 'kitap' ? old('body') : '' }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">Kitabın metnini taslağı oluşturduktan sonra "Bölümler" sayfasından ekleyebilir ya da Word dosyasından aktarabilirsiniz.</p>
                </div>
            </template>
            <template x-if="type === 'makale'">
                <div>
                    <x-rich-editor
                        name="body"
                        :value="old('type') === 'makale' ? old('body') : ''"
                        :import-url="route('panel.yayinlarim.word-aktar')"
                        title-input="title"
                    />
                </div>
            </template>
            @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4 pt-1">
            <button type="submit" class="px-4 py-2.5 bg-slate-900 text-white text-sm font-medium rounded-lg hover:bg-slate-800 transition-colors">
                Taslak Olarak Kaydet
            </button>
            <a href="{{ route('panel.yayinlarim.taslaklarim') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">
                Vazgeç
            </a>
        </div>
    </form>
@endsection
