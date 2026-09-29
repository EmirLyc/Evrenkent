<?php

namespace App\Support;

use App\Enums\NoteType;
use App\Models\Setting;

/**
 * Süper Admin'in "Premium Sistemi" sayfasından değiştirdiği ayarlar (2026-09-27 toplantı
 * kararları: plan fiyatları, premium indirim oranı ve çalışma alanı kotaları panelden
 * ayarlanabilir olsun). DB'de satır yoksa buradaki varsayılan geçerli.
 *
 * Aynı istek içinde tekrar tekrar sorgu atılmasın diye değerler bir kere okunup
 * saklanıyor; set() önbelleği temizliyor.
 */
class PlatformSettings
{
    /** @var array<string, int|float> */
    public const DEFAULTS = [
        'premium_monthly_price' => 99.0,
        'premium_yearly_price' => 990.0,
        'premium_discount_percent' => 30,
        // "Bazı Prensipler" (Okurun Gözünden): ücretsiz hesapta 1 defter, defter uzunluğu örn. 1.000 kelime.
        'quota_defter' => 1,
        'quota_defter_words' => 1000,
        'quota_not' => 10,
        'quota_alinti' => 10,
    ];

    /** @var array<string, string|null>|null */
    private static ?array $loaded = null;

    public static function get(string $key): int|float
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            throw new \InvalidArgumentException("Bilinmeyen platform ayarı: {$key}");
        }

        self::$loaded ??= Setting::pluck('value', 'key')->all();

        $raw = self::$loaded[$key] ?? null;
        $default = self::DEFAULTS[$key];

        if ($raw === null) {
            return $default;
        }

        return is_float($default) ? (float) $raw : (int) $raw;
    }

    /**
     * @param  array<string, int|float|string>  $values
     */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
        }

        self::$loaded = null;
    }

    /** Ücretsiz hesabın ilgili çalışma alanındaki kayıt sınırı. */
    public static function noteQuota(NoteType $type): int
    {
        return (int) self::get('quota_'.$type->value);
    }

    /** Testler arası (ve set() dışı DB değişikliklerinde) önbelleği sıfırlar. */
    public static function flush(): void
    {
        self::$loaded = null;
    }
}
