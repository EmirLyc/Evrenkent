<?php

namespace App\Enums;

use App\Support\PlatformSettings;
use Illuminate\Support\Carbon;

/**
 * Premium abonelik planları — fiyatlar Süper Admin'in Premium Sistemi sayfasından
 * (PlatformSettings) okunur, burada sabit değil.
 */
enum SubscriptionPlan: string
{
    case Aylik = 'aylik';
    case Yillik = 'yillik';

    public function label(): string
    {
        return match ($this) {
            self::Aylik => 'Aylık',
            self::Yillik => 'Yıllık',
        };
    }

    public function price(): float
    {
        return match ($this) {
            self::Aylik => PlatformSettings::get('premium_monthly_price'),
            self::Yillik => PlatformSettings::get('premium_yearly_price'),
        };
    }

    /** "TL / ay" gibi fiyatın yanındaki periyot etiketi. */
    public function periodLabel(): string
    {
        return match ($this) {
            self::Aylik => 'ay',
            self::Yillik => 'yıl',
        };
    }

    public function endsAt(Carbon $startsAt): Carbon
    {
        return match ($this) {
            self::Aylik => $startsAt->copy()->addMonthNoOverflow(),
            self::Yillik => $startsAt->copy()->addYearNoOverflow(),
        };
    }

    /**
     * Yıllık planın, 12 aylık ödemeye göre yüzde kaç tasarruf ettirdiği (Abonelik
     * sayfasındaki "%X tasarruf" etiketi). Yıllık daha pahalıysa 0.
     */
    public static function yearlySavingsPercent(): int
    {
        $twelveMonths = self::Aylik->price() * 12;

        if ($twelveMonths <= 0) {
            return 0;
        }

        return max(0, (int) round((1 - self::Yillik->price() / $twelveMonths) * 100));
    }
}
