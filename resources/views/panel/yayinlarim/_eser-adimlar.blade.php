{{-- Yeni Yayın adımları (mockup 1.1.1: 1 Temel Bilgiler ✓ · 2 İçerik · 3 Kapak ve Tanıtım · 4 Önizleme ve Gönder). --}}
<ol class="mt-3 space-y-1">
    @foreach (\App\Http\Controllers\WorkController::STEPS as $key => $label)
        @php $url = $stepUrl($key); $current = $step === $key; @endphp
        <li>
            <a @if ($url) href="{{ $url }}" @endif
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-[0.95rem] {{ $current ? 'bg-slate-100 font-semibold text-navy' : 'text-slate-700 hover:bg-slate-50' }} {{ $url ? '' : 'pointer-events-none opacity-50' }}"
               @if ($current) aria-current="step" @endif>
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-sm tabular-nums {{ $current ? 'border-navy bg-navy text-white' : 'border-slate-300 bg-white text-slate-600' }}">{{ $loop->iteration }}</span>
                <span class="min-w-0 flex-1">{{ $label }}</span>
                @if ($done[$key])
                    <x-heroicon-s-check-circle class="w-5 h-5 shrink-0 text-emerald-600" aria-label="Tamamlandı" />
                @endif
            </a>
        </li>
    @endforeach
</ol>
