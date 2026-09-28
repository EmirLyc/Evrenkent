@extends('layouts.panel')

@section('title', $item->title)

@section('content')
    @php
        $isBook = $item instanceof \App\Models\Book;
        $status = $item->status;
        $S = \App\Enums\ContentStatus::class;
        $reviews = $item->reviews->sortBy('id')->values();
        $has = fn (string $action) => $reviews->contains('action', $action);
        $decision = $reviews->whereIn('action', ['onaylandi', 'revizyon_istendi', 'reddedildi'])->last();
        $issueLive = ! $isBook && $item->magazineIssue?->status === $S::Yayinda;

        // Yayın süreci ("Yayın Sürecini Takip Et"): tamamlanan / sıradaki adımlar.
        $steps = [
            ['Taslak oluşturuldu', $item->created_at, true, null],
            ['Onaya gönderildi', $reviews->where('action', 'gonderildi')->last()?->created_at, $has('gonderildi'), null],
        ];
        if (! $isBook) {
            $steps[] = ['Dergi editörü incelemesi', $reviews->where('action', 'incelemede')->last()?->created_at, $has('incelemede') || in_array($status, [$S::Onaylandi, $S::Yayinda], true), null];
        }
        $steps[] = [
            $decision?->step()['label'] ?? 'Süper Admin kararı',
            $decision?->created_at,
            $decision !== null,
            $decision?->action === 'revizyon_istendi' || $decision?->action === 'reddedildi' ? $decision->action : null,
        ];
        $publishDetail = match (true) {
            $status === $S::Yayinda => null,
            $status === $S::Onaylandi && $item->scheduled_publish_at !== null => 'scheduled',
            $status === $S::Onaylandi && ! $isBook && ! $issueLive => 'with-issue',
            $status === $S::Onaylandi => 'waiting',
            default => null,
        };
        $steps[] = ['Yayın', $item->published_at, $status === $S::Yayinda, $publishDetail];

        $editUrl = $isBook ? route('panel.yayinlarim.kitap.duzenle', $item) : route('panel.yayinlarim.makale.duzenle', $item);
    @endphp

    <a href="{{ $status === $S::Yayinda ? route('panel.yayinlarim.yayinlananlar') : route('panel.yayinlarim.taslaklarim') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 mb-4">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> {{ $status === $S::Yayinda ? 'Yayınlananlar' : 'Taslaklarım' }}
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
        <section class="card p-4 sm:p-6 lg:col-span-2">
            <div class="flex gap-4 sm:gap-5">
                <div class="w-20 sm:w-28 shrink-0 aspect-[3/4] rounded-md overflow-hidden">
                    @if ($isBook)
                        <x-book-cover :book="$item" class="w-full h-full" />
                    @elseif ($item->magazineIssue)
                        <x-magazine-cover :issue="$item->magazineIssue" class="w-full h-full" />
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold uppercase tracking-wider {{ $isBook ? 'text-brand-700' : 'text-sky-700' }}">{{ $isBook ? 'Kitap' : 'Dergi Yazısı' }}</div>
                    <h1 class="font-serif text-2xl font-semibold text-slate-900 leading-snug break-words">{{ $item->title }}</h1>
                    @if (! $isBook && $item->magazineIssue)
                        <p class="text-sm text-slate-600">{{ $item->magazineIssue->magazine?->name }} · {{ $item->magazineIssue->title }}</p>
                    @endif
                    <x-author-status-badge :item="$item" class="mt-3" />
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 mt-6 text-sm">
                <div><dt class="text-slate-500">Oluşturulma</dt><dd class="text-slate-900">{{ $item->created_at->translatedFormat('j F Y H:i') }}</dd></div>
                <div><dt class="text-slate-500">Son düzenleme</dt><dd class="text-slate-900">{{ $item->updated_at->translatedFormat('j F Y H:i') }}</dd></div>
                @if ($item->categories->isNotEmpty())
                    <div><dt class="text-slate-500">Kategoriler</dt><dd class="text-slate-900">{{ $item->categories->pluck('name')->join(', ') }}</dd></div>
                @endif
                @if ($isBook && $item->latestChapter)
                    <div><dt class="text-slate-500">Son çalışılan bölüm</dt><dd class="text-slate-900">Bölüm {{ $item->latestChapter->order }} – {{ $item->latestChapter->title }}</dd></div>
                @endif
            </dl>

            @if ($decision?->note && in_array($status, [$S::RevizyonIstendi, $S::Reddedildi], true))
                <div class="mt-6 rounded-md border px-4 py-3 text-sm {{ $status === $S::Reddedildi ? 'border-red-200 bg-red-50 text-red-800' : 'border-orange-200 bg-orange-50 text-orange-900' }}">
                    <div class="font-medium">{{ $status === $S::Reddedildi ? 'Ret gerekçesi' : 'Düzeltme notu' }}</div>
                    <p class="mt-1 whitespace-pre-line">{{ $decision->note }}</p>
                </div>
            @endif

            <div class="flex flex-wrap gap-2 mt-6">
                @can('update', $item)
                    <a href="{{ $editUrl }}" class="btn-dark btn-sm"><x-heroicon-o-pencil class="w-4 h-4" /> {{ $status === $S::RevizyonIstendi ? 'Düzenlemeye Devam Et' : 'Düzenle' }}</a>
                @endcan
                @can('submit', $item)
                    <form method="POST" action="{{ $isBook ? route('panel.yayinlarim.kitap.gonder', $item) : route('panel.yayinlarim.makale.gonder', $item) }}">
                        @csrf
                        <button type="submit" class="btn-brand btn-sm"><x-heroicon-o-paper-airplane class="w-4 h-4" /> Onaya Gönder</button>
                    </form>
                @endcan
                <a href="{{ $isBook ? ($status === $S::Yayinda ? route('kitaplar.show', $item) : route('kitaplar.oku', $item)) : route('makaleler.show', $item) }}" class="btn-outline btn-sm"><x-heroicon-o-eye class="w-4 h-4" /> {{ $status === $S::Yayinda ? 'Görüntüle' : 'Önizle' }}</a>
                <a href="{{ $isBook ? route('panel.mesajlar.kitap', $item) : route('panel.mesajlar.makale', $item) }}" class="btn-outline btn-sm"><x-heroicon-o-chat-bubble-oval-left class="w-4 h-4" /> Mesajlar @if ($item->messages()->count()) ({{ $item->messages()->count() }}) @endif</a>
            </div>
        </section>

        <section id="surec" class="card p-4 sm:p-6 scroll-mt-24">
            <h2 class="font-serif text-lg font-semibold text-slate-900 mb-4">Yayın Süreci</h2>
            <ol class="relative space-y-5">
                @foreach ($steps as $index => [$label, $at, $done, $detail])
                    @php
                        $bad = in_array($detail, ['revizyon_istendi', 'reddedildi'], true);
                        $current = ! $done && ($index === 0 || $steps[$index - 1][2]);
                    @endphp
                    <li class="flex gap-3">
                        <span class="relative flex flex-col items-center">
                            <span class="flex items-center justify-center w-7 h-7 rounded-full shrink-0 {{ $bad ? 'bg-orange-100 text-orange-700' : ($done ? 'bg-emerald-100 text-emerald-700' : ($current ? 'bg-brand-100 text-brand-700 ring-2 ring-brand-300' : 'bg-slate-100 text-slate-400')) }}">
                                @if ($bad) <x-heroicon-o-arrow-uturn-left class="w-4 h-4" />
                                @elseif ($done) <x-heroicon-s-check class="w-4 h-4" />
                                @else <span class="text-xs font-medium">{{ $index + 1 }}</span>
                                @endif
                            </span>
                            @if (! $loop->last)
                                <span class="w-px flex-1 bg-slate-200 mt-1 min-h-[1rem]"></span>
                            @endif
                        </span>
                        <div class="min-w-0 pb-1">
                            <div class="text-sm font-medium {{ $done || $bad || $current ? 'text-slate-900' : 'text-slate-400' }}">{{ $label }}</div>
                            @if ($at)
                                <div class="text-xs text-slate-500">{{ $at->translatedFormat('j F Y H:i') }}</div>
                            @endif
                            @if ($detail === 'scheduled')
                                <div class="text-xs text-slate-600 mt-0.5">{{ $item->scheduled_publish_at->translatedFormat('j F Y H:i') }} · <x-countdown :at="$item->scheduled_publish_at" class="text-brand-700 font-medium" /></div>
                            @elseif ($detail === 'with-issue')
                                <div class="text-xs text-slate-600 mt-0.5">Sayı ("{{ $item->magazineIssue?->title }}") yayınlandığında birlikte yayına girecek.</div>
                            @elseif ($detail === 'waiting')
                                <div class="text-xs text-slate-600 mt-0.5">Süper Admin yayın tarihini belirleyecek.</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($reviews->isNotEmpty())
                <details class="mt-6 border-t border-slate-100 pt-4">
                    <summary class="text-sm font-medium text-slate-700 cursor-pointer">Tüm geçmiş ({{ $reviews->count() }})</summary>
                    <ul class="mt-3 space-y-3">
                        @foreach ($reviews->reverse() as $review)
                            <li class="text-sm">
                                <div class="text-slate-900">{{ $review->step()['label'] }} <span class="text-slate-400">· {{ $review->created_at->translatedFormat('j F Y H:i') }}</span></div>
                                @if ($review->note)
                                    <p class="text-slate-600 whitespace-pre-line">{{ $review->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>
    </div>
@endsection
