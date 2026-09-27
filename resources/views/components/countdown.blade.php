{{--
    Yayın geri sayımı ("3 gün 4 saat kaldı") — Yakında Çıkacaklar kartları ve tanıtım sayfaları
    (2026-09-27 revizesi: "yakında çıkacaklarda süresi görünmeli"). İlk metin sunucuda üretilir
    (JS'siz ve testlerde de doğru), tarayıcıda Alpine'in countdown bileşeni (resources/js/app.js)
    her saniye günceller. Süre dolunca zamanlayıcı en geç bir dakika içinde yayınlıyor.
--}}
@props(['at'])

@php
    // Carbon 3'te diffInSeconds ondalıklı döner — intdiv'e vermeden önce tam sayıya çevrilmeli.
    $seconds = max(0, (int) floor(now()->diffInSeconds($at, false)));
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $label = match (true) {
        $seconds <= 0 => 'Yayına giriyor',
        $days > 0 => "{$days} gün {$hours} saat kaldı",
        $hours > 0 => "{$hours} saat {$minutes} dk kaldı",
        default => max(1, $minutes).' dk kaldı',
    };
@endphp

<span x-data="countdown('{{ $at->toIso8601String() }}')" x-text="label" {{ $attributes->merge(['class' => 'whitespace-nowrap']) }}>{{ $label }}</span>
