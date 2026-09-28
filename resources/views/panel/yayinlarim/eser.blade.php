@extends('layouts.panel')

{{--
    Yeni Yayın sayfası (Faz G2, "Yazarın Gözünden" 1.1.1–1.1.6): solda 4 adım, üstte eser adı +
    kayıt durumu + Önizle / Yayına Gönder / "…", ortada adımın içeriği.
--}}

@php
    $isBook = $type === 'kitap';
    $steps = \App\Http\Controllers\WorkController::STEPS;
    $done = $done ?? ['bilgiler' => false, 'icerik' => false, 'kapak' => false, 'gonder' => false];
    $stepUrl = fn (string $key) => $work ? app(\App\Http\Controllers\WorkController::class)->stepUrl($work, $key) : null;
    $kindLabel = $isBook ? 'Kitap' : 'Dergi Yazısı';
    $isRevision = $work?->status === \App\Enums\ContentStatus::RevizyonIstendi;
@endphp

@section('title', $work ? $work->title : 'Yeni Yayın')
@section('main_width', 'max-w-[92rem]')
@section('main_padding', 'px-3 sm:px-6 py-6 sm:py-8')

@section('sidebar')
    <div class="flex h-full flex-col px-4">
        <a href="{{ route('panel.yayinlarim.taslaklarim') }}" class="inline-flex items-center gap-2 px-2 py-1.5 text-[0.95rem] text-navy hover:text-brand-700">
            <x-heroicon-o-arrow-left class="w-5 h-5" /> Yayınlarıma Dön
        </a>

        <div class="mt-4 flex items-center gap-3 rounded-lg bg-brand-50 px-3 py-2.5 font-semibold text-navy">
            <x-heroicon-o-document-text class="w-5 h-5" /> {{ $isRevision ? 'Düzeltme' : 'Yeni Yayın' }}
        </div>

        @include('panel.yayinlarim._eser-adimlar')

        <div class="mt-auto pb-4 pt-10 px-2">
            <p class="font-reading text-lg italic leading-snug text-slate-700">“İyi bir kitap,<br>yeni bir evrendir.”</p>
            <p class="mt-3 font-serif text-sm font-semibold tracking-[0.3em] text-slate-700">EVRENKENT</p>
        </div>
    </div>
@endsection

@section('content')
    {{-- Başlık satırı --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <h1 class="font-serif text-2xl sm:text-3xl font-semibold text-navy">{{ $isRevision ? 'Düzeltme' : 'Yeni Yayın' }}</h1>
                @if ($work)
                    <a href="{{ $stepUrl('bilgiler') }}" class="text-navy hover:text-brand-700" title="Temel bilgileri düzenle" aria-label="Temel bilgileri düzenle"><x-heroicon-s-pencil class="w-5 h-5" /></a>
                @endif
            </div>
            <p class="font-reading text-[1.05rem] text-slate-600 truncate">
                {{ $work?->title ?? 'Adsız eser' }} <span class="mx-1.5 text-slate-400">·</span> {{ $kindLabel }} Taslağı
            </p>
        </div>

        @if ($work)
            <div class="flex flex-wrap items-center gap-2 sm:gap-3" x-data="{ label: @js($work->updated_at->translatedFormat('j F Y, H:i')), more: false }" @work-saved.window="label = $event.detail.label">
                <span class="hidden md:inline text-sm text-slate-500">Taslak kaydedildi <span class="mx-1">·</span> <span x-text="label"></span></span>
                <a href="{{ $isBook ? route('kitaplar.oku', $work) : route('makaleler.show', $work) }}" class="btn-outline h-11 px-4"><x-heroicon-o-eye class="w-5 h-5" /> Önizle</a>
                <a href="{{ $stepUrl('gonder') }}" class="btn-dark h-11 px-4"><x-heroicon-o-paper-airplane class="w-5 h-5" /> Yayına Gönder</a>
                <div class="relative" @click.outside="more = false">
                    <button type="button" class="btn-outline h-11 w-11 !px-0" @click="more = !more" aria-label="Diğer işlemler" :aria-expanded="more"><x-heroicon-o-ellipsis-horizontal class="w-5 h-5" /></button>
                    <div x-show="more" x-cloak x-transition.origin.top.right class="absolute right-0 top-full mt-1 z-30 w-60 card py-1 text-sm">
                        @if ($step === 'icerik')
                            <button type="button" class="w-full flex items-center gap-2 px-3 py-2 text-left text-slate-700 hover:bg-slate-50" @click="more = false; document.querySelector('[data-work-import]')?.click()">
                                <x-heroicon-o-document-arrow-up class="w-4 h-4" /> Word / EPUB'dan içe aktar
                            </button>
                        @endif
                        <a href="{{ $isBook ? route('panel.yayinlarim.kitap.belgeler', $work) : route('panel.yayinlarim.makale.belgeler', $work) }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-snowflake-icon class="w-4 h-4" /> Belgeler</a>
                        <a href="{{ $isBook ? route('panel.mesajlar.kitap', $work) : route('panel.mesajlar.makale', $work) }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-heroicon-o-chat-bubble-oval-left class="w-4 h-4" /> Mesajlar</a>
                        <a href="{{ $isBook ? route('panel.yayinlarim.kitap.detay', $work) : route('panel.yayinlarim.makale.detay', $work) }}" class="flex items-center gap-2 px-3 py-2 text-slate-700 hover:bg-slate-50"><x-heroicon-o-information-circle class="w-4 h-4" /> Detaylar ve süreç</a>
                        @can('delete', $work)
                            <form method="POST" action="{{ $isBook ? route('panel.yayinlarim.kitap.sil', $work) : route('panel.yayinlarim.makale.sil', $work) }}" data-turbo-confirm="&quot;{{ $work->title }}&quot; çöp kutusuna taşınacak. Oradan geri alabilirsiniz.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full flex items-center gap-2 px-3 py-2 text-left text-red-600 hover:bg-red-50 border-t border-slate-100"><x-heroicon-o-trash class="w-4 h-4" /> Çöp kutusuna taşı</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Dar ekranda adımlar (masaüstünde sol sütunda). --}}
    <nav class="lg:hidden -mx-3 px-3 mb-4 flex gap-2 overflow-x-auto scrollbar-none" aria-label="Adımlar">
        @foreach ($steps as $key => $label)
            @php $url = $stepUrl($key); $current = $step === $key; @endphp
            <a @if ($url) href="{{ $url }}" @endif class="shrink-0 inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm {{ $current ? 'border-navy bg-navy text-white' : 'border-slate-200 bg-white text-slate-700' }} {{ $url ? '' : 'opacity-50 pointer-events-none' }}">
                <span class="tabular-nums">{{ $loop->iteration }}</span> {{ $label }}
                @if ($done[$key] && ! $current) <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-600" /> @endif
            </a>
        @endforeach
    </nav>

    @if ($isRevision && $work->reviews()->where('action', 'revizyon_istendi')->latest('id')->value('note'))
        <div class="mb-4 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
            <span class="font-medium">Düzeltme notu:</span> {{ $work->reviews()->where('action', 'revizyon_istendi')->latest('id')->value('note') }}
            <a href="{{ $isBook ? route('panel.mesajlar.kitap', $work) : route('panel.mesajlar.makale', $work) }}" class="ml-2 underline">Mesajlar</a>
        </div>
    @endif

    @include('panel.yayinlarim._adim-'.$step)
@endsection
