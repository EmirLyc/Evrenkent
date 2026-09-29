@extends('layouts.admin-panel')

@section('title', 'Premium Sistemi')

@section('content')
    <div class="mb-6">
        <h1 class="font-serif text-xl font-semibold text-slate-900">Premium Sistemi</h1>
        <p class="text-sm text-slate-500 mt-1">Plan fiyatları, premium indirim oranı ve ücretsiz hesabın çalışma alanı sınırları. Değişiklikler hemen Abonelik sayfasına ve fiyatlara yansır.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="card p-5">
            <div class="text-xs text-slate-400">Aktif Premium Üye</div>
            <div class="text-2xl font-serif font-semibold text-slate-900 mt-1">{{ $activePremiumCount }}</div>
        </div>
        <div class="card p-5">
            <div class="text-xs text-slate-400">Son 30 Gün Abonelik</div>
            <div class="text-2xl font-serif font-semibold text-slate-900 mt-1">{{ $last30Count }}</div>
        </div>
        <div class="card p-5">
            <div class="text-xs text-slate-400">Son 30 Gün Abonelik Geliri</div>
            <div class="text-2xl font-serif font-semibold text-slate-900 mt-1">{{ number_format((float) $last30Revenue, 2, ',', '.') }} TL</div>
        </div>
    </div>

    @php
        $inputClass = 'w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500';
        $field = fn (string $key) => old($key, $settings[$key]);
    @endphp

    <form method="POST" action="{{ route('panel.adminpanel.premium.guncelle') }}" class="card p-5 sm:p-6 space-y-6 mb-8">
        <h2 class="font-serif text-base font-semibold text-slate-900">Ayarlar</h2>
        @csrf
        @method('PUT')

        <div>
            <div class="text-sm font-medium text-slate-700 mb-2">Plan Fiyatları (TL)</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:max-w-lg">
                <div>
                    <label for="premium_monthly_price" class="block text-xs text-slate-500 mb-1">Aylık</label>
                    <input id="premium_monthly_price" name="premium_monthly_price" type="number" step="0.01" min="0" value="{{ $field('premium_monthly_price') }}" class="{{ $inputClass }}">
                    @error('premium_monthly_price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="premium_yearly_price" class="block text-xs text-slate-500 mb-1">Yıllık</label>
                    <input id="premium_yearly_price" name="premium_yearly_price" type="number" step="0.01" min="0" value="{{ $field('premium_yearly_price') }}" class="{{ $inputClass }}">
                    @error('premium_yearly_price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-2">Yıllık fiyat 12 aylık ödemeden düşükse Abonelik sayfasında "%X tasarruf" etiketi otomatik gösterilir.</p>
        </div>

        <div class="border-t border-slate-100 pt-5">
            <label for="premium_discount_percent" class="block text-sm font-medium text-slate-700 mb-1">Premium İndirim Oranı (%)</label>
            <input id="premium_discount_percent" name="premium_discount_percent" type="number" min="0" max="90" value="{{ $field('premium_discount_percent') }}" class="{{ $inputClass }} sm:max-w-[10rem]">
            <p class="text-xs text-slate-400 mt-1">Premium üyeye tüm kitaplarda uygulanır. Kampanyadaki kitapta kampanya ile toplanmaz, hangisi avantajlıysa o geçerli olur.</p>
            @error('premium_discount_percent') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="border-t border-slate-100 pt-5">
            <div class="text-sm font-medium text-slate-700 mb-1">Ücretsiz Hesap — Çalışma Alanı Sınırları</div>
            <p class="text-xs text-slate-400 mb-3">Her alan ayrı sayılır. Premium üyede sınır yoktur. Favoriler her zaman sınırsızdır; fosforun sınırı yoktur.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:max-w-2xl">
                @foreach (['quota_defter' => ['Defterim (defter sayısı)', 1, 1000], 'quota_defter_words' => ['Defter uzunluğu (kelime)', 100, 1000000], 'quota_not' => ['Notlarım', 1, 1000], 'quota_alinti' => ['Alıntılarım', 1, 1000]] as $key => [$label, $min, $max])
                    <div>
                        <label for="{{ $key }}" class="block text-xs text-slate-500 mb-1">{{ $label }}</label>
                        <input id="{{ $key }}" name="{{ $key }}" type="number" min="{{ $min }}" max="{{ $max }}" value="{{ $field($key) }}" class="{{ $inputClass }}">
                        @error($key) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn-brand btn-sm">Kaydet</button>
    </form>

    <h2 class="font-serif text-base font-semibold text-slate-900 mb-3">Son Abonelikler</h2>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                        <th class="px-5 py-3 font-medium">Tarih</th>
                        <th class="px-5 py-3 font-medium">Kullanıcı</th>
                        <th class="px-5 py-3 font-medium">Plan</th>
                        <th class="px-5 py-3 font-medium">Bitiş</th>
                        <th class="px-5 py-3 font-medium text-right">Tutar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentSubscriptions as $subscription)
                        <tr>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $subscription->created_at->format('d.m.Y H:i') }}</td>
                            <td class="px-5 py-2.5 text-slate-900 whitespace-nowrap">{{ $subscription->user?->name ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $subscription->plan->label() }}</td>
                            <td class="px-5 py-2.5 text-slate-500 whitespace-nowrap">{{ $subscription->ends_at->format('d.m.Y') }}</td>
                            <td class="px-5 py-2.5 text-slate-900 text-right whitespace-nowrap">{{ number_format($subscription->amount, 2, ',', '.') }} TL</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">Henüz abonelik yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
