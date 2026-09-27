@extends('layouts.panel')

@section('title', 'Belgeler')

@section('content')
    {{-- Faz F2: metne gömülü belgeler (mockup 3 / 3.1) — kitabın ya da makalenin belge listesi. --}}
    <div class="mb-5">
        <h1 class="font-serif text-xl font-semibold text-slate-900 break-words">{{ $owner->title }} — Belgeler</h1>
        <a href="{{ $backUrl }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">&larr; {{ $backLabel }}</a>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
        <div class="space-y-3 order-2 lg:order-1">
            @forelse ($documents as $document)
                <div x-data="{ editing: @js($errors->any() && old('document_id') == $document->id) }" class="card p-4">
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-navy">
                            <x-snowflake-icon class="w-5 h-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-slate-900 break-words">{{ $document->title }}</div>
                            <div class="text-sm text-slate-500 mt-0.5 flex flex-wrap gap-x-2">
                                <span>{{ $document->isPdf() ? 'PDF' : 'Görsel' }}</span>
                                @if ($document->date_label) <span>· {{ $document->date_label }}</span> @endif
                                @if ($document->page_count) <span>· {{ $document->page_count }} sayfa</span> @endif
                                <span>· {{ \Illuminate\Support\Number::fileSize($document->size, 1) }}</span>
                            </div>
                            <div class="text-xs mt-1 {{ $document->usage_count ? 'text-emerald-700' : 'text-slate-400' }}">
                                @if ($document->usage_count)
                                    Metinde kullanılıyor{{ $owner instanceof \App\Models\Book ? ' ('.$document->usage_count.' bölüm)' : '' }}
                                @else
                                    Henüz metne eklenmedi
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-3">
                        <a href="{{ $document->viewUrl() }}" data-turbo="false" data-document-viewer="{{ $document->isPdf() ? 'pdf' : 'image' }}" data-document-title="{{ $document->caption() }}" class="btn-outline btn-sm">Görüntüle</a>
                        <button type="button" class="btn-outline btn-sm" @click="editing = !editing">Düzenle</button>
                        <form method="POST" action="{{ route('panel.yayinlarim.belgeler.sil', $document) }}"
                              data-turbo-confirm="{{ $document->usage_count ? 'Bu belge metinde kullanılıyor; silinirse okur sayfasındaki kar tanesi işaretleri kaybolur. Silmek istediğinize emin misiniz?' : 'Bu belgeyi silmek istediğinize emin misiniz?' }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-sm btn text-red-600 hover:bg-red-50">Sil</button>
                        </form>
                    </div>

                    <form x-show="editing" x-cloak method="POST" action="{{ route('panel.yayinlarim.belgeler.guncelle', $document) }}" class="mt-4 pt-4 border-t border-slate-100 grid gap-3 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="document_id" value="{{ $document->id }}">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="title-{{ $document->id }}">Belge adı</label>
                            <input id="title-{{ $document->id }}" name="title" type="text" required value="{{ old('document_id') == $document->id ? old('title') : $document->title }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="date-{{ $document->id }}">Tarih</label>
                            <input id="date-{{ $document->id }}" name="date_label" type="text" value="{{ old('document_id') == $document->id ? old('date_label') : $document->date_label }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1" for="pages-{{ $document->id }}">Sayfa sayısı</label>
                            <input id="pages-{{ $document->id }}" name="page_count" type="number" min="1" value="{{ old('document_id') == $document->id ? old('page_count') : $document->page_count }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                        </div>
                        @if (old('document_id') == $document->id)
                            @foreach ($errors->all() as $error) <p class="sm:col-span-2 text-sm text-red-600">{{ $error }}</p> @endforeach
                        @endif
                        <div class="sm:col-span-2 flex gap-2">
                            <button type="submit" class="btn-dark btn-sm">Kaydet</button>
                            <button type="button" class="btn-ghost btn-sm" @click="editing = false">Vazgeç</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="card p-10 text-center text-slate-400">
                    <x-snowflake-icon class="w-8 h-8 mx-auto mb-3 text-slate-300" />
                    Henüz belge yüklenmedi. Arşiv belgeleri, mektuplar, haritalar gibi PDF ya da görselleri buradan yükleyip metne kar tanesi işaretiyle ekleyebilirsiniz.
                </div>
            @endforelse
        </div>

        {{-- Yükleme formu: mobilde listenin üstünde, masaüstünde sağda. --}}
        <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="card p-4 sm:p-5 space-y-4 order-1 lg:order-2 lg:sticky lg:top-24">
            @csrf
            <h2 class="font-medium text-slate-900">Belge yükle</h2>
            @php($isNew = ! old('document_id'))
            <div>
                <label for="new-title" class="block text-sm font-medium text-slate-700 mb-1">Belge adı</label>
                <input id="new-title" name="title" type="text" required value="{{ $isNew ? old('title') : '' }}" placeholder="Ör. Paris Sefareti'nden Gönderilen Yazı" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @if ($isNew) @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="new-date" class="block text-sm font-medium text-slate-700 mb-1">Tarih</label>
                    <input id="new-date" name="date_label" type="text" value="{{ $isNew ? old('date_label') : '' }}" placeholder="3 Haziran 1873" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
                <div>
                    <label for="new-pages" class="block text-sm font-medium text-slate-700 mb-1">Sayfa</label>
                    <input id="new-pages" name="page_count" type="number" min="1" value="{{ $isNew ? old('page_count') : '' }}" placeholder="Otomatik" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
            </div>
            @if ($isNew) @error('page_count') <p class="text-sm text-red-600 -mt-2">{{ $message }}</p> @enderror @endif
            <div>
                <label for="new-file" class="block text-sm font-medium text-slate-700 mb-1">Dosya (PDF, JPG, PNG — en fazla 20 MB)</label>
                <input id="new-file" name="file" type="file" required accept="application/pdf,image/jpeg,image/png" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200">
                @if ($isNew) @error('file') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror @endif
            </div>
            <p class="text-xs text-slate-500">Ad, tarih ve sayfa sayısı okurun kar tanesinin üstüne geldiğinde görünür. Okurlar belgeyi site içinde görüntüler, indiremez.</p>
            <button type="submit" class="btn-dark w-full">Yükle</button>
        </form>
    </div>

    <x-document-viewer />
@endsection
