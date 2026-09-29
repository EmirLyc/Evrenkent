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

@section('content')
    @if ($chapters->isEmpty())
        <div class="reader-paper reader-muted mx-auto max-w-[46rem] rounded-sm px-5 py-16 text-center font-reading text-lg shadow-xl shadow-slate-900/10">
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
            :contents="$contents"
            :marks="$marks"
            :noteable-type="\App\Models\Book::class"
            :noteable-id="$book->id"
        >
            @foreach ($sections as $section)
                @php $c = $section['chapter']; @endphp
                <section class="rt-chapter rich-content rt-editor-surface" data-chapter="{{ $c->order }}" data-title="{{ $c->is_preface ? $book->title : $c->title }}" @if ($c->is_preface) data-preface @endif>
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

            {{-- Not / alıntı artık metinde seçilerek (Faz H3) — sayfanın altındaki elle not formu kalktı. --}}
            @if (auth()->check() && $readingListItem)
                <x-slot:after>
                    <div x-show="$store.pager.last" x-cloak class="mx-auto mt-6 w-full max-w-[46rem] px-1 text-center font-sans">
                        <form method="POST" action="{{ route('panel.okuma-listesi.tamamla', $readingListItem) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="reader-btn-primary px-4">
                                Tamamlandı Olarak İşaretle
                            </button>
                        </form>
                    </div>
                </x-slot:after>
            @endif
        </x-paged-reader>
    @endif
@endsection
