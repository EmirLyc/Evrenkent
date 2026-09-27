@extends('layouts.panel')

@section('title', $magazineIssue ? 'Sayıyı Düzenle' : 'Yeni Sayı Oluştur')

@section('content')
    <div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-5 flex-wrap">
        <h1 class="font-serif text-xl font-semibold text-slate-900">
            {{ $magazineIssue ? 'Sayıyı Düzenle' : 'Yeni Sayı Oluştur' }}
        </h1>
        @if ($magazineIssue)
            <x-status-badge :status="$magazineIssue->status" />
        @endif
    </div>

    <form
        method="POST"
        action="{{ $magazineIssue ? route('panel.dergi.sayilarim.guncelle', $magazineIssue) : route('panel.dergi.sayilarim.store') }}"
        enctype="multipart/form-data"
        class="card p-6 space-y-5"
    >
        @csrf
        @if ($magazineIssue)
            @method('PUT')
        @endif

        {{-- Faz E: sayı bir dergiye bağlı. Oluştururken editörü olduğunuz dergilerden biri
             seçilir; sonradan değiştirilemez (Süper Admin sayı yönetiminden değiştirebilir). --}}
        <div>
            <div class="block text-sm font-medium text-slate-700 mb-1">Dergi</div>
            @if ($magazineIssue)
                <p class="text-sm text-slate-900">{{ $magazineIssue->magazine?->name ?? '—' }}</p>
            @elseif ($magazines->isEmpty())
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                    Size henüz bir dergi atanmadı. Sayı açabilmek için Süper Admin'in sizi bir derginin editörü olarak ataması gerekiyor.
                </p>
            @else
                <select id="magazine_id" name="magazine_id" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @if ($magazines->count() > 1)
                        <option value="">— Seçiniz —</option>
                    @endif
                    @foreach ($magazines as $magazine)
                        <option value="{{ $magazine->id }}" @selected(old('magazine_id') == $magazine->id)>{{ $magazine->name }}</option>
                    @endforeach
                </select>
            @endif
            @error('magazine_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık</label>
            <input id="title" name="title" type="text" value="{{ old('title', $magazineIssue?->title) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="issue_number" class="block text-sm font-medium text-slate-700 mb-1">Sayı No</label>
            <input id="issue_number" name="issue_number" type="number" min="1" value="{{ old('issue_number', $magazineIssue?->issue_number) }}" class="w-full max-w-xs rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('issue_number') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="cover_image" class="block text-sm font-medium text-slate-700 mb-1">Kapak Görseli</label>
            @if ($magazineIssue?->cover_image)
                <x-magazine-cover :issue="$magazineIssue" class="w-24 aspect-[3/4] rounded-md mb-2" />
            @endif
            <input id="cover_image" name="cover_image" type="file" accept="image/*" class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border file:border-slate-300 file:bg-white file:text-sm file:text-slate-700 hover:file:bg-slate-50">
            <p class="text-xs text-slate-400 mt-1">{{ $magazineIssue ? 'Değiştirmek istemiyorsanız boş bırakın.' : 'Opsiyonel, en fazla 5MB.' }}</p>
            @error('cover_image') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="editor_note" class="block text-sm font-medium text-slate-700 mb-1">Editör Yazısı</label>
            <textarea id="editor_note" name="editor_note" rows="6" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('editor_note', $magazineIssue?->editor_note) }}</textarea>
            @error('editor_note') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="publish_date" class="block text-sm font-medium text-slate-700 mb-1">Yayın Tarihi (opsiyonel)</label>
            <input id="publish_date" name="publish_date" type="date" value="{{ old('publish_date', $magazineIssue?->publish_date?->format('Y-m-d')) }}" class="w-full max-w-xs rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('publish_date') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4 pt-1">
            <button type="submit" class="btn-brand" @disabled(! $magazineIssue && $magazines->isEmpty())>
                {{ $magazineIssue ? 'Değişiklikleri Kaydet' : 'Sayıyı Oluştur' }}
            </button>
            <a href="{{ route('panel.dergi.sayilarim') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">
                Vazgeç
            </a>
        </div>
    </form>

    @if ($magazineIssue)
        @can('submit', $magazineIssue)
            <div class="card p-6 mt-6">
                <h2 class="font-medium text-slate-900 mb-1">Sayıyı onaya gönder</h2>
                <p class="text-sm text-slate-500 mb-4">Bu sayı Süper Admin onayına gönderilecek, siz onaylanana/reddedilene kadar düzenleyemeyeceksiniz.</p>
                <form method="POST" action="{{ route('panel.dergi.sayilarim.gonder', $magazineIssue) }}">
                    @csrf
                    <button type="submit" class="btn-dark">
                        <x-heroicon-o-paper-airplane class="w-4 h-4" /> Yayına Gönder
                    </button>
                </form>
            </div>
        @endcan
    @endif
    </div>
@endsection
