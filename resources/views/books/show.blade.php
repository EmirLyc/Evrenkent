@extends('layouts.public')

@section('title', $book->title)

@section('content')
    @php
        $isAuthor = auth()->check() && auth()->id() === $book->author_id;
        $locked = $book->price > 0 && ! ($isAuthor || $hasPurchased);

        // Tür • Yıl • Yayın adı — hepsi tek yayınevi olduğu için "Evrenkent Yayınları"
        // sabit bir metin (ayrı bir sütun gerektirmiyor), diğer ikisi boşsa satıra girmiyor.
        $metaParts = array_filter([
            $book->isDictionary() ? 'Sözlük' : null,
            $book->categories->first()?->name,
            $book->published_at?->format('Y'),
            'Evrenkent Yayınları',
        ]);

        // Bölüm sayısı gerçek bir okuma-modu verisi — yazarın panelden girdiği diğer
        // istatistiklerle (sayfa/belge/video vb.) aynı şeritte, en başta gösteriliyor.
        $stats = collect($chapterCount > 0 ? [['count' => $chapterCount, 'icon' => 'heroicon-o-book-open', 'label' => $chapterCount === 1 ? 'bölüm' : 'bölüm']] : [])
            ->concat($book->contentStats());
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr_320px] gap-8">
        <x-book-cover :book="$book" class="aspect-[3/4] rounded-lg border border-slate-200 shadow-sm" icon-class="w-10 h-10" />

        <div class="min-w-0">
            <x-detail-header
                :title="$book->title"
                :byline="$book->author->name"
                :meta="$metaParts"
                :rating-average="$book->average_rating"
                :rating-count="$book->review_count"
                :stats="$stats->all()"
            >
                {{-- Herkese açık sayfada kitap zaten yayında olduğu için "Yayında" etiketi
                     gereksiz — sadece yazar kendi taslağını/incelemedeki halini önizlerken
                     durumu görmesi anlamlı olduğu için o durumlarda gösteriliyor. --}}
                @if ($book->status !== \App\Enums\ContentStatus::Yayinda)
                    <x-status-badge :status="$book->status" />
                @endif
                @foreach ($book->categories as $category)
                    <a href="{{ route('kitaplar.index', ['kategori' => $category->slug]) }}" class="pill-tag !py-1 !px-3 !text-xs hover:border-brand-300 hover:text-brand-700 transition-colors">
                        {{ $category->name }}
                    </a>
                @endforeach
            </x-detail-header>

            @if ($book->description)
                <div class="mt-9">
                    <h2 class="font-serif text-base font-semibold text-slate-900 mb-2">{{ $book->isDictionary() ? 'Sözlük' : 'Kitap' }} Hakkında</h2>
                    <p class="text-slate-600 whitespace-pre-line leading-relaxed">{{ $book->description }}</p>
                </div>
            @endif

            {{-- Sözlük (Faz G3): maddeler — her biri madde sayfasına gider (önizleme herkese açık). --}}
            @if ($book->isDictionary() && ($entryCount = $book->entries()->count()) > 0)
                <div class="mt-9">
                    <div class="flex items-baseline justify-between gap-3 mb-3">
                        <h2 class="font-serif text-base font-semibold text-slate-900">Maddeler <span class="font-sans text-sm font-normal text-slate-400 tabular-nums">({{ $entryCount }})</span></h2>
                        <a href="{{ route('sozlukler.index', ['sozluk' => $book->slug]) }}" class="text-sm font-medium text-brand-700 hover:text-brand-600">Tüm maddeler →</a>
                    </div>
                    <ul class="columns-2 sm:columns-3 gap-6 text-[0.95rem]">
                        @foreach ($book->entries()->reorder('term_search')->limit(30)->get(['id', 'book_id', 'term', 'slug']) as $entry)
                            <li class="break-inside-avoid py-0.5"><a href="{{ route('sozlukler.madde', [$book, $entry]) }}" class="font-reading text-navy hover:underline">{{ $entry->term }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="card p-6 h-fit lg:sticky lg:top-24">
            @if ($isUpcoming)
                {{-- Sadece tanıtım — henüz satın alma/okuma yok, kitap planlanan tarihte
                     otomatik yayına açılacak (bkz. books:publish-scheduled komutu). --}}
                <div class="flex items-center gap-2 text-brand-700 text-sm font-medium">
                    <x-heroicon-o-clock class="w-4 h-4" /> Yakında Çıkacak
                </div>
                <div class="text-lg font-serif font-semibold text-slate-900 mt-2">
                    {{ $book->scheduled_publish_at->format('d.m.Y') }} tarihinde yayınlanacak
                </div>
                <x-countdown :at="$book->scheduled_publish_at" class="block text-2xl font-serif font-semibold text-brand-700 mt-1" />
                <p class="text-sm text-slate-500 mt-2">
                    Bu {{ $book->isDictionary() ? 'sözlük' : 'kitap' }} şu an tanıtım aşamasında — satın alma ve okuma, yayın tarihinde açılacak.
                </p>
            @elseif ($hasPurchased)
                {{-- Satın alındı hâli (mockup: 6-)Satın Alındıktan Sonra Kitap Tanıtım Sayfası.png) —
                     fiyat artık anlamsız, yerine satın alma tarihi gösteriliyor. --}}
                <div class="flex items-center gap-3">
                    <x-heroicon-o-check-circle class="w-9 h-9 text-emerald-700 shrink-0" />
                    <div class="text-lg font-serif font-semibold text-slate-900">Satın alındı</div>
                </div>
                <p class="text-sm text-slate-500 mt-3">
                    Bu {{ $book->isDictionary() ? 'sözlüğü' : 'kitabı' }} {{ $purchase->purchased_at->translatedFormat('j F Y') }} tarihinde satın aldınız.
                </p>
            @else
                {{-- Fiyat Book::priceFor()'dan — sepet ve satın alma da aynı metodu kullanıyor.
                     Geçerli bir kampanya varsa eski fiyat üstü çizili, süreliyse bitiş tarihiyle. --}}
                @php
                    $finalPrice = $book->priceFor(auth()->user());
                    $isPremiumPrice = $book->isPremiumPriceFor(auth()->user());
                    // Abone olmayana (ziyaretçi dahil) premium fiyatı şu anki fiyatından ucuzsa teşvik.
                    $showPremiumTeaser = ! auth()->user()?->isPremium() && (float) $book->premiumPrice() < (float) $finalPrice;
                @endphp
                @if ((float) $finalPrice < (float) $book->price)
                    <div class="text-sm text-slate-400 line-through">{{ number_format($book->price, 2, ',', '.') }} TL</div>
                    <div class="text-2xl font-serif font-semibold text-brand-700">{{ number_format($finalPrice, 2, ',', '.') }} TL</div>
                    @if ($isPremiumPrice)
                        <div class="inline-flex items-center gap-1 text-xs font-medium text-amber-700 mt-1">
                            <x-heroicon-s-sparkles class="w-3.5 h-3.5" /> Premium fiyatınız
                        </div>
                    @elseif ($book->discount_ends_at)
                        <div class="text-xs text-brand-700 mt-1">Kampanya {{ $book->discount_ends_at->translatedFormat('j F Y') }} tarihine kadar</div>
                    @endif
                @else
                    <div class="text-2xl font-serif font-semibold text-slate-900">{{ number_format($book->price, 2, ',', '.') }} TL</div>
                @endif
                <div class="text-xs text-slate-400 mt-1">KDV dahil</div>
                @if ($showPremiumTeaser)
                    <a href="{{ route('abonelik') }}" class="mt-3 flex items-center gap-2 rounded-lg bg-amber-50 ring-1 ring-inset ring-amber-200 px-3 py-2 text-xs text-amber-800 hover:bg-amber-100 transition-colors">
                        <x-heroicon-s-sparkles class="w-4 h-4 shrink-0" />
                        <span>Premium üyelere <strong>{{ number_format($book->premiumPrice(), 2, ',', '.') }} TL</strong> →</span>
                    </a>
                @endif
            @endif

            @unless ($isUpcoming)
                <div class="flex flex-col gap-3 mt-5">
                    @auth
                        @if ($hasPurchased)
                            @if ($book->status === \App\Enums\ContentStatus::Yayinda)
                                <a href="{{ route('kitaplar.oku', $book) }}" class="btn-success w-full">
                                    <x-heroicon-o-book-open class="w-4 h-4" /> Şimdi Oku
                                </a>
                            @endif
                            <a href="{{ route('panel.index') }}" class="btn-outline w-full">
                                <x-heroicon-o-building-library class="w-4 h-4" /> Kütüphanemde Görüntüle
                            </a>
                        @elseif ($book->status === \App\Enums\ContentStatus::Yayinda)
                            <form method="POST" action="{{ route('panel.satin-al', $book) }}">
                                @csrf
                                <button type="submit" class="btn-brand w-full">
                                    <x-heroicon-o-shopping-bag class="w-4 h-4" /> Satın Al
                                </button>
                            </form>
                            <x-add-to-cart-button :book="$book" :in-cart="$hasInCart" />

                            {{-- Ücretsiz kitap veya yazarın kendi kitabı: satın almadan okunabiliyor. --}}
                            @if (! $locked)
                                <a href="{{ route('kitaplar.oku', $book) }}" class="btn-dark w-full">
                                    <x-heroicon-o-book-open class="w-4 h-4" /> Oku
                                </a>
                            @endif
                        @endif

                        @if ($readingListItem && $readingListItem->status === \App\Enums\ReadingStatus::Tamamlandi)
                            <span class="btn-outline w-full cursor-default text-slate-400">
                                <x-heroicon-o-check class="w-4 h-4" /> Okundu
                            </span>
                        @elseif ($readingListItem)
                            <span class="btn-outline w-full cursor-default text-slate-400">
                                <x-heroicon-o-bookmark class="w-4 h-4" /> Okuma Listesinde
                            </span>
                        @else
                            <form method="POST" action="{{ route('panel.okuma-listesi.kitap.ekle', $book) }}">
                                @csrf
                                <button type="submit" class="btn-outline w-full">
                                    <x-heroicon-o-bookmark class="w-4 h-4" /> Okuma Listeme Ekle
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('panel.favoriler.kitap.toggle', $book) }}">
                            @csrf
                            <button type="submit" class="btn-outline w-full">
                                <x-heroicon-o-heart class="w-4 h-4 {{ $hasFavorited ? 'text-brand-600' : '' }}" />
                                {{ $hasFavorited ? 'Favorilerde' : 'Favorilere Ekle' }}
                            </button>
                        </form>

                        @if ($hasPurchased || $book->status === \App\Enums\ContentStatus::Yayinda)
                            <p class="text-xs text-slate-400 mt-1 text-center">
                                Not/alıntı eklemek için <a href="{{ route('panel.notlarim') }}" class="underline hover:text-slate-600">Notlarım</a>'ı kullanabilirsiniz.
                            </p>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn-brand w-full">Giriş Yap ve Satın Al</a>
                        <p class="text-xs text-slate-400 text-center">
                            Favorilemek veya okuma listenize eklemek için de giriş yapmanız gerekiyor.
                        </p>
                    @endauth

                    @if ($book->status === \App\Enums\ContentStatus::Yayinda)
                        <div class="flex items-center gap-2 rounded-lg bg-emerald-50 ring-1 ring-inset ring-emerald-200 text-emerald-800 text-xs px-3 py-2.5 mt-1">
                            <x-heroicon-o-shield-check class="w-4 h-4 shrink-0" />
                            Güvenli ödeme · Anında erişim · Tüm cihazlarda oku
                        </div>
                    @endif
                </div>
            @endunless
        </div>
    </div>

    @if ($relatedBooks->isNotEmpty())
        <div class="mt-14">
            <div class="flex items-baseline justify-between mb-5">
                <h2 class="font-serif text-xl font-semibold text-slate-900">Bu Eserler de Dikkatini Çekebilir</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($relatedBooks as $related)
                    <a href="{{ route('kitaplar.show', $related) }}" class="group flex gap-3">
                        <x-book-cover :book="$related" class="w-20 aspect-[3/4] rounded-md shrink-0 transition-opacity group-hover:opacity-80" />
                        <div class="min-w-0 flex flex-col justify-center">
                            <div class="text-xs text-brand-600 font-medium uppercase tracking-wide">{{ $related->author->name }}</div>
                            <div class="font-medium text-slate-900 text-sm truncate mt-0.5 group-hover:underline">{{ $related->title }}</div>
                            <div class="text-sm mt-1">
                                <x-book-price :book="$related" />
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
@endsection
