{{-- Adım 4 — Önizleme ve Gönder: eksik kontrolü, önizleme, onaya gönderme. --}}
@php
    $wordCount = $isBook
        ? $work->chapters->sum(fn ($c) => str_word_count(\App\Support\RichText::plainText($c->content), 0, 'çğıöşüÇĞİÖŞÜâîûÂÎÛ'))
        : str_word_count(\App\Support\RichText::plainText($work->content), 0, 'çğıöşüÇĞİÖŞÜâîûÂÎÛ');
    $checks = [
        ['Temel bilgiler', $done['bilgiler'], $isBook ? 'Başlık, sayfa oranı, kategoriler' : 'Başlık, dergi sayısı, sayfa oranı', 'bilgiler', true],
        ['İçerik', $done['icerik'], $isBook ? $work->chapters->where('is_preface', false)->count().' bölüm · '.number_format($wordCount, 0, ',', '.').' kelime' : number_format($wordCount, 0, ',', '.').' kelime', 'icerik', true],
        ['Kapak ve tanıtım', $done['kapak'], $done['kapak'] ? 'Kapak ve tanıtım metni hazır' : 'Önerilir: okur eseri tanıtım sayfasında kapak ve tanıtım metniyle görür', 'kapak', false],
    ];
    $canSubmit = auth()->user()->can('submit', $work);
    $missing = collect($checks)->filter(fn ($check) => $check[4] && ! $check[1]);
@endphp

<div class="grid grid-cols-1 xl:grid-cols-[1fr_22rem] gap-5 max-w-5xl">
    <section class="card p-4 sm:p-6">
        <h2 class="font-serif text-lg font-semibold text-slate-900 mb-4">Göndermeden önce</h2>
        <ul class="space-y-3">
            @foreach ($checks as [$label, $ok, $detail, $step, $required])
                <li class="flex items-start gap-3">
                    @if ($ok)
                        <x-heroicon-s-check-circle class="w-6 h-6 shrink-0 text-emerald-600" />
                    @else
                        <x-heroicon-o-exclamation-circle class="w-6 h-6 shrink-0 {{ $required ? 'text-red-500' : 'text-amber-500' }}" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-slate-900">{{ $label }} @unless ($required) <span class="text-xs font-normal text-slate-400">(isteğe bağlı)</span> @endunless</div>
                        <div class="text-sm text-slate-500">{{ $detail }}</div>
                    </div>
                    <a href="{{ $stepUrl($step) }}" class="text-sm text-brand-700 hover:text-brand-600 shrink-0">Düzenle</a>
                </li>
            @endforeach
        </ul>

        <div class="mt-6 rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            @if ($isBook)
                Kitap doğrudan <span class="font-medium text-slate-800">Süper Admin</span> onayına gider; satış fiyatını ve yayın tarihini Süper Admin belirler.
            @else
                Yazı önce <span class="font-medium text-slate-800">dergi editörüne</span> ({{ $work->magazineIssue?->magazine?->name }} · {{ $work->magazineIssue?->title }}), sonra Süper Admin onayına gider.
            @endif
            Gönderdikten sonra düzenleme kapanır; düzeltme istenirse eser Taslaklarım'a "Düzeltme İstendi" olarak döner.
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($canSubmit)
                <form method="POST" action="{{ $isBook ? route('panel.yayinlarim.kitap.gonder', $work) : route('panel.yayinlarim.makale.gonder', $work) }}">
                    @csrf
                    <button type="submit" class="btn-dark h-11 px-5" @disabled($missing->isNotEmpty())>
                        <x-heroicon-o-paper-airplane class="w-5 h-5" /> Yayına Gönder
                    </button>
                </form>
                @if ($missing->isNotEmpty())
                    <p class="text-sm text-red-600">Göndermek için: {{ $missing->pluck(0)->implode(', ') }}.</p>
                @endif
            @else
                <p class="text-sm text-slate-600">Bu eser şu anda <x-author-status-badge :item="$work" class="mx-1" /> durumunda.</p>
            @endif
        </div>
    </section>

    <aside class="card p-4 sm:p-5">
        <div class="flex gap-4">
            <div class="w-24 shrink-0 aspect-[3/4] overflow-hidden rounded-md">
                @if ($isBook)
                    <x-book-cover :book="$work" class="w-full h-full" />
                @elseif ($work->cover_image)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.covers_disk'))->url($work->cover_image) }}" alt="" class="w-full h-full object-cover">
                @elseif ($work->magazineIssue)
                    <x-magazine-cover :issue="$work->magazineIssue" class="w-full h-full" />
                @endif
            </div>
            <div class="min-w-0">
                <div class="text-[11px] font-semibold uppercase tracking-wider {{ $isBook ? 'text-brand-700' : 'text-sky-700' }}">{{ $isBook ? 'Kitap' : 'Dergi Yazısı' }}</div>
                <div class="font-serif text-lg font-semibold leading-snug text-slate-900 break-words">{{ $work->title }}</div>
                @if ($work->subtitle) <div class="text-sm text-slate-600">{{ $work->subtitle }}</div> @endif
                <div class="mt-1 text-xs text-slate-500">Sayfa oranı {{ \App\Http\Controllers\WorkController::RATIOS[$work->page_ratio][0] ?? $work->page_ratio }}</div>
            </div>
        </div>
        @if ($work->description)
            <p class="mt-4 text-sm text-slate-600 line-clamp-6 whitespace-pre-line">{{ $work->description }}</p>
        @endif
        <a href="{{ $isBook ? route('kitaplar.oku', $work) : route('makaleler.show', $work) }}" class="btn-outline w-full mt-4"><x-heroicon-o-eye class="w-4 h-4" /> Okur gibi önizle</a>
    </aside>
</div>
