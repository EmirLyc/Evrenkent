{{--
    Dergi sayısı kartı (kapak + sayı no + başlık) — anasayfa, /dergiler kataloğu ve arama
    sonuçları aynı kartı kullanır. Makale sayısı sadece sorguda withCount ile yüklenmişse
    gösterilir (articles_count). Zamanlanmış sayıda (Yakında Çıkacaklar) yayın tarihi + geri sayım.
--}}
@props(['issue', 'showScheduledDate' => false])

<a href="{{ route('dergiler.show', $issue) }}" {{ $attributes->merge(['class' => 'group block card-hover overflow-hidden']) }}>
    <x-magazine-cover :issue="$issue" class="aspect-[3/4]" />
    <div class="p-3">
        <div class="text-xs text-brand-600 font-medium uppercase tracking-wide">Sayı {{ $issue->issue_number }}</div>
        <div class="font-medium text-slate-900 text-sm truncate mt-0.5">{{ $issue->title }}</div>
        @if ($showScheduledDate && $issue->scheduled_publish_at)
            <div class="text-sm mt-1">
                <span class="text-brand-700 font-medium whitespace-nowrap">{{ $issue->scheduled_publish_at->format('d.m.Y') }}</span>
                <x-countdown :at="$issue->scheduled_publish_at" class="block text-xs text-slate-500 mt-0.5" />
            </div>
        @elseif (isset($issue->articles_count))
            <div class="text-sm text-slate-500 mt-1">{{ $issue->articles_count }} makale</div>
        @endif
    </div>
</a>
