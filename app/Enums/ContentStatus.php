<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Taslak = 'taslak';
    case Gonderildi = 'gonderildi';
    case Incelemede = 'incelemede';
    case RevizyonIstendi = 'revizyon_istendi';
    case Onaylandi = 'onaylandi';
    case Yayinda = 'yayinda';
    case Reddedildi = 'reddedildi';

    public function label(): string
    {
        return match ($this) {
            self::Taslak => 'Taslak',
            self::Gonderildi => 'Gönderildi',
            self::Incelemede => 'İncelemede',
            self::RevizyonIstendi => 'Revizyon İstendi',
            self::Onaylandi => 'Onaylandı',
            self::Yayinda => 'Yayında',
            self::Reddedildi => 'Reddedildi',
        };
    }

    /**
     * Panel/public tarafta durum rozetleri için tutarlı renk sınıfları.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Taslak => 'bg-slate-100 text-slate-600 ring-slate-300',
            self::Gonderildi, self::Incelemede => 'bg-sky-50 text-sky-700 ring-sky-200',
            self::RevizyonIstendi => 'bg-orange-50 text-orange-800 ring-orange-200',
            self::Onaylandi => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::Yayinda => 'bg-slate-900 text-white ring-slate-900',
            self::Reddedildi => 'bg-red-50 text-red-700 ring-red-200',
        };
    }
}
