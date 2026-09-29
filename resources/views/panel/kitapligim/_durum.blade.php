{{-- Okuma durumu (mockup 4.1'in orta sütunu): "%45 okundu" + çubuk ya da "✓ Okundu" + tarih. --}}
<div class="min-w-0">
    <div class="flex items-center gap-2 text-sm font-medium text-slate-800">
        @svg('heroicon-o-'.$item->status['icon'], 'w-5 h-5 shrink-0 text-slate-700')
        <span class="truncate">{{ $item->status['text'] }}</span>
    </div>
    @if ($item->status['percent'] !== null)
        <div class="mt-2 h-1.5 rounded-full bg-slate-200 overflow-hidden {{ $compact ?? false ? 'w-full' : 'w-full md:w-40' }}"
             role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $item->status['percent'] }}" aria-label="Okuma ilerlemesi">
            <div class="h-full rounded-full bg-brand-500" style="width: {{ $item->status['percent'] }}%"></div>
        </div>
    @elseif ($item->status['sub'])
        <div class="text-sm text-slate-500 mt-1 {{ $compact ?? false ? '' : 'md:pl-7' }}">{{ $item->status['sub'] }}</div>
    @endif
</div>
