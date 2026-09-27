@extends('layouts.admin-panel')

@section('title', 'Onayla')

@section('content')
    <div class="max-w-lg mx-auto">
        <h1 class="font-serif text-xl font-semibold text-slate-900 mb-1">Onayla</h1>
        <p class="text-sm text-slate-500 mb-5">"{{ $title }}" onaylanacak.</p>

        <form method="POST" action="{{ $submitRoute }}" class="card p-6 space-y-5">
            @csrf

            {{-- Kitap fiyatı yazardan alınmıyor, burada zorunlu. "Ücretsiz" işaretlenirse fiyat
                 alanı devre dışı kalır (bkz. Concerns\ResolvesBookPrice). --}}
            @if ($showPrice ?? false)
                <div x-data="{ free: {{ old('is_free') ? 'true' : 'false' }} }">
                    <label for="price" class="block text-sm font-medium text-slate-700 mb-1">Satış Fiyatı (TL)</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $price) }}" :disabled="free" class="w-full max-w-xs rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500 disabled:bg-slate-100 disabled:text-slate-400">
                    @if ($discountPrice !== null)
                        <p class="text-xs text-slate-400 mt-1">Mevcut kampanya fiyatı: {{ number_format($discountPrice, 2, ',', '.') }} TL — satış fiyatı bundan yüksek olmalı.</p>
                    @endif
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700 mt-3">
                        <input type="checkbox" name="is_free" value="1" x-model="free" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                        Bu kitap ücretsiz
                    </label>
                    @error('price') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($showScheduledPublishAt ?? false)
                <div>
                    <label for="scheduled_publish_at" class="block text-sm font-medium text-slate-700 mb-1">Planlanan Yayın Tarihi (opsiyonel)</label>
                    <p class="text-xs text-slate-400 mb-2">Doluysa "Yakında Çıkacaklar" rafında teaser olarak görünür ve tarihi gelince otomatik yayınlanır. Boşsa kitap "Yayınla" aksiyonuyla elle yayınlanana kadar sadece "Onaylandı" durumunda kalır.</p>
                    <input id="scheduled_publish_at" name="scheduled_publish_at" type="datetime-local" value="{{ old('scheduled_publish_at', $scheduledPublishAt?->format('Y-m-d\TH:i')) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('scheduled_publish_at') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex items-center gap-4 pt-1">
                <button type="submit" class="btn-brand">Onayla</button>
                <a href="{{ $backRoute }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">Vazgeç</a>
            </div>
        </form>
    </div>
@endsection
