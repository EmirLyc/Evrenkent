@props(['item'])

{{-- Yazarın gördüğü durum rozeti (Faz G1, "Yazarın Gözünden" 1.1) — İncelemede / Düzeltme İstendi / Kabul Edildi. --}}
@php
    [$label, $classes, $icon] = match ($item->authorStatusKey()) {
        'taslak' => ['Taslak', 'bg-amber-100 text-amber-800', 'document-text'],
        'incelemede' => ['İncelemede', 'bg-sky-50 text-sky-700', 'clock'],
        'duzeltme' => ['Düzeltme İstendi', 'bg-red-50 text-red-600', 'arrow-uturn-left'],
        'kabul' => ['Kabul Edildi', 'bg-emerald-50 text-emerald-700', 'check-circle'],
        'reddedildi' => ['Reddedildi', 'bg-red-100 text-red-800', 'no-symbol'],
        'yayinda' => ['Yayında', 'bg-slate-900 text-white', 'globe-alt'],
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium $classes"]) }}>
    @svg('heroicon-o-'.$icon, 'w-3.5 h-3.5 shrink-0')
    {{ $label }}
</span>
