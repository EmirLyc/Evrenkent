@extends('layouts.reader')

{{--
    Okuma modu — sayfalı (Faz G4; mockup 3: kâğıt sayfa, altta sayfa numarası). Bütün kitap tek
    akışta, yazarın sayfa oranında sayfalara bölünüyor (x-paged-reader); her bölüm yeni sayfada
    süslü bir açılışla başlıyor, sayfa numaraları kitap boyunca sürüyor. /oku/{bölüm} o bölümün
    açılış sayfasını açıyor; okur sayfa çevirdikçe adres ve okuma listesindeki konum güncelleniyor.
--}}

@section('title', $chapter ? $chapter->title.' — '.$book->title : $book->title)
@section('reader_back_url', route('kitaplar.show', $book))
@section('reader_back_label', $book->title)
@section('reader_paged', '1')

@if ($chapters->isNotEmpty())
    @section('reader_drawer')
        <div class="space-y-0.5">
            @foreach ($sections as $section)
                @php $c = $section['chapter']; @endphp
                <a
                    href="{{ route('kitaplar.oku', [$book, $c->order]) }}"
                    class="flex gap-2 rounded-md px-3 py-2 font-reading text-[1.05rem] leading-snug"
                    :class="$store.pager.chapter === {{ $c->order }} ? 'bg-navy text-white' : 'text-slate-700 hover:bg-white/70'"
                    :aria-current="$store.pager.chapter === {{ $c->order }} ? 'page' : null"
                >
                    @if ($section['number'])
                        <span class="shrink-0 tabular-nums opacity-70">{{ $section['number'] }}</span>
                    @endif
                    <span class="min-w-0 break-words">{{ $c->title }}</span>
                </a>
            @endforeach
        </div>
    @endsection
@endif

@section('content')
    @if ($chapters->isEmpty())
        <div class="reader-paper mx-auto max-w-[46rem] rounded-sm px-5 py-16 text-center font-reading text-lg text-slate-500 shadow-xl shadow-slate-900/10">
            <x-reader-ornament class="mb-6" />
            Bu kitap için henüz bölüm eklenmedi.
        </div>
    @else
        @unless ($book->status === \App\Enums\ContentStatus::Yayinda)
            {{-- Yazarın önizlemesi: kitap henüz yayında değil. --}}
            <div class="mb-4 flex justify-center font-sans"><x-status-badge :status="$book->status" /></div>
        @endunless

        <x-paged-reader
            :ratio="$book->page_ratio"
            :storage-key="'kitap-'.$book->id"
            :read-base="route('kitaplar.oku', $book)"
            :position-url="auth()->check() ? route('kitaplar.konum', $book) : null"
            :initial-chapter="$chapter?->order"
        >
            @foreach ($sections as $section)
                @php $c = $section['chapter']; @endphp
                <section class="rt-chapter rich-content rt-editor-surface" data-chapter="{{ $c->order }}" data-title="{{ $c->is_preface ? $book->title : $c->title }}">
                    @unless ($c->is_preface)
                        <header class="rt-opener">
                            <x-reader-ornament />
                            @if ($section['number'])
                                <div class="rt-opener-number">{{ $section['number'] }}</div>
                            @endif
                            <div class="rt-opener-title" role="heading" aria-level="1">{{ $c->title }}</div>
                            <x-reader-ornament />
                        </header>
                    @endunless
                    {!! $section['html'] !!}
                </section>
            @endforeach

            <x-slot:after>
                <div class="mx-auto mt-6 w-full max-w-[46rem] space-y-6 px-1 font-sans">
                    @if (auth()->check() && $readingListItem)
                        <div x-show="$store.pager.last" x-cloak class="text-center">
                            <form method="POST" action="{{ route('panel.okuma-listesi.tamamla', $readingListItem) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="rounded-lg bg-navy px-4 py-2 text-sm text-white hover:bg-navy/90">
                                    Tamamlandı Olarak İşaretle
                                </button>
                            </form>
                        </div>
                    @endif

                    @auth
                        {{-- Metnin akışını bölmesin diye kapalı; doğrulama hatasıyla dönülürse açık gelir. --}}
                        <section x-data="{ open: @js($errors->has('content') || $errors->has('noteable_id')) }">
                            <button type="button" class="inline-flex items-center gap-2 text-sm font-medium text-navy hover:text-brand-700" @click="open = !open" :aria-expanded="open">
                                <x-heroicon-o-pencil-square class="w-4 h-4" /> Not / Alıntı Ekle
                            </button>
                            <div x-show="open" x-cloak class="mt-3">
                                <x-quick-note-form
                                    :noteable-type="\App\Models\Book::class"
                                    :noteable-id="$book->id"
                                    location-bind="$store.pager.location"
                                />
                            </div>
                        </section>
                    @endauth
                </div>
            </x-slot:after>
        </x-paged-reader>
    @endif
@endsection
