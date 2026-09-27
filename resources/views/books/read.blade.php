@extends('layouts.public')

@section('title', $book->title)

@section('content')
    <div class="mb-6">
        <a href="{{ route('kitaplar.show', $book) }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">
            &larr; {{ $book->title }}
        </a>
    </div>

    @if ($chapters->isEmpty())
        <div class="bg-white border border-slate-200 rounded-lg p-12 text-center text-slate-400">
            <x-heroicon-o-book-open class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Bu kitap için henüz bölüm eklenmedi.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-[220px_1fr] gap-8">
            <aside class="sm:sticky sm:top-24 self-start">
                <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2.5">Bölümler</div>
                <div class="space-y-0.5">
                    @foreach ($chapters as $c)
                        <a
                            href="{{ route('kitaplar.oku', [$book, $c->order]) }}"
                            class="block px-3 py-1.5 rounded-lg text-sm {{ $chapter && $chapter->id === $c->id ? 'bg-slate-900 text-white font-medium' : 'text-slate-600 hover:bg-slate-100' }}"
                        >
                            {{ $c->order }}. {{ $c->title }}
                        </a>
                    @endforeach
                </div>
            </aside>

            <div>
                @if ($chapter)
                    <span class="text-xs uppercase text-orange-700 font-medium tracking-wide">Bölüm {{ $chapter->order }}</span>
                    <h1 class="font-serif text-2xl font-semibold text-slate-900 mt-1 mb-6">{{ $chapter->title }}</h1>

                    {{-- Faz F1: temizlenmiş HTML (RichText), dipnotlar numaralı. --}}
                    <div class="rich-content">{!! $chapter->renderedContent() !!}</div>

                    <div class="flex items-center justify-between mt-10 pt-6 border-t border-slate-200">
                        @if ($prevChapter)
                            <a href="{{ route('kitaplar.oku', [$book, $prevChapter->order]) }}" class="text-sm px-3.5 py-1.5 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition-colors">
                                &larr; Önceki Bölüm
                            </a>
                        @else
                            <span></span>
                        @endif

                        @if ($nextChapter)
                            <a href="{{ route('kitaplar.oku', [$book, $nextChapter->order]) }}" class="text-sm px-3.5 py-1.5 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors">
                                Sonraki Bölüm &rarr;
                            </a>
                        @elseif (auth()->check() && $readingListItem)
                            <form method="POST" action="{{ route('panel.okuma-listesi.tamamla', $readingListItem) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-sm px-3.5 py-1.5 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors">
                                    Tamamlandı Olarak İşaretle
                                </button>
                            </form>
                        @endif
                    </div>

                    @auth
                        <div class="mt-10">
                            <h2 class="font-serif text-lg font-semibold text-slate-900 mb-3">Not / Alıntı Ekle</h2>
                            <x-quick-note-form
                                :noteable-type="\App\Models\Book::class"
                                :noteable-id="$book->id"
                                :default-location="'Bölüm '.$chapter->order.': '.$chapter->title"
                            />
                        </div>
                    @endauth
                @endif
            </div>
        </div>
    @endif

    {{-- Faz F2: kar tanesi işaretli gömülü belgeleri site içinde açar. --}}
    <x-document-viewer />
@endsection
