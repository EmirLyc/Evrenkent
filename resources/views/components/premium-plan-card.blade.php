{{--
    Premium plan kartı (mockup 7'nin sağındaki "Premium Hesap" kartı) — hem herkese açık
    Abonelik sayfasında hem Aboneliğim'de kullanılır. Aylık/Yıllık seçimi, fiyatlar Süper
    Admin'in Premium Sistemi ayarlarından (SubscriptionPlan::price).

    Ziyaretçide buton satın alma formu değil Aboneliğim'e giden bir link: POST isteği girişten
    sonra "intended" olarak hatırlanmadığı için ziyaretçi girişten sonra Aboneliğim'e (GET) döner
    ve aynı kartla orada satın alır.

    Mockup'taki "Güvenli ödeme / İptal & iade / 256 bit SSL" ibareleri bilerek yok — ödeme
    şimdilik mock, gerçek olmayan bir güvence gösterilmiyor.
--}}
@php
    $user = auth()->user();
    $isLifetime = $user?->isPremium() && $user->premium_until === null;
    $savings = \App\Enums\SubscriptionPlan::yearlySavingsPercent();
    $formatPrice = fn (float $price) => $price == floor($price) ? number_format($price, 0, ',', '.') : number_format($price, 2, ',', '.');
@endphp

<div {{ $attributes->merge(['class' => 'card p-6 sm:p-7']) }} x-data="{ plan: '{{ old('plan', 'aylik') }}' }">
    <div class="flex justify-center">
        <span class="flex items-center justify-center w-12 h-12 rounded-full bg-amber-50 ring-1 ring-amber-200">
            <x-heroicon-o-sparkles class="w-6 h-6 text-amber-600" />
        </span>
    </div>
    <h2 class="font-serif text-xl font-semibold text-slate-900 text-center mt-3">Premium Hesap</h2>

    @if ($isLifetime)
        <p class="text-sm text-slate-500 text-center mt-4">Süresiz premium üyesiniz. Tüm premium avantajları hesabınızda aktif.</p>
    @else
        <div class="grid grid-cols-2 gap-1 rounded-lg bg-slate-100 p-1 mt-5 text-sm">
            @foreach (\App\Enums\SubscriptionPlan::cases() as $plan)
                <button type="button" @click="plan = '{{ $plan->value }}'"
                    :class="plan === '{{ $plan->value }}' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-md px-3 py-1.5 font-medium transition-colors">
                    {{ $plan->label() }}
                    @if ($plan === \App\Enums\SubscriptionPlan::Yillik && $savings > 0)
                        <span class="text-[11px] font-semibold text-emerald-600">%{{ $savings }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @foreach (\App\Enums\SubscriptionPlan::cases() as $plan)
            <div x-show="plan === '{{ $plan->value }}'" @if ($plan !== \App\Enums\SubscriptionPlan::Aylik) x-cloak @endif class="text-center mt-5">
                <span class="font-serif text-5xl font-semibold text-slate-900">{{ $formatPrice($plan->price()) }}</span>
                <span class="text-slate-600">TL / {{ $plan->periodLabel() }}</span>
                @if ($plan === \App\Enums\SubscriptionPlan::Yillik && $savings > 0)
                    <div class="text-xs text-emerald-600 mt-1">Aylık plana göre %{{ $savings }} tasarruf</div>
                @endif
            </div>
        @endforeach

        @if ($user?->isPremium())
            <p class="text-xs text-slate-500 text-center mt-3">
                Üyeliğiniz {{ $user->premium_until->translatedFormat('j F Y') }} tarihine kadar aktif. Uzatırsanız yeni süre bu tarihin üzerine eklenir.
            </p>
        @endif

        <ul class="space-y-2 text-sm text-slate-600 mt-5">
            @foreach (['Anında erişim', 'Tüm cihazlarda kullanım', 'Otomatik yenileme yok'] as $item)
                <li class="flex items-center gap-2">
                    <x-heroicon-o-check-badge class="w-4 h-4 text-brand-500 shrink-0" /> {{ $item }}
                </li>
            @endforeach
        </ul>

        @auth
            <form method="POST" action="{{ route('panel.abonelik.satin-al') }}" class="mt-6">
                @csrf
                <input type="hidden" name="plan" :value="plan" value="{{ old('plan', 'aylik') }}">
                <button type="submit" class="btn-brand w-full">
                    {{ $user->isPremium() ? 'Süreyi Uzat' : "Premium'a Geç" }}
                    <x-heroicon-o-chevron-right class="w-4 h-4" />
                </button>
                @error('plan') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
            </form>
        @else
            <a href="{{ route('panel.aboneligim') }}" class="btn-brand w-full mt-6">
                Giriş Yap ve Premium'a Geç
                <x-heroicon-o-chevron-right class="w-4 h-4" />
            </a>
        @endauth
    @endif
</div>
