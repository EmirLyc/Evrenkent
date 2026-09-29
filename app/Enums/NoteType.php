<?php

namespace App\Enums;

enum NoteType: string
{
    case Defter = 'defter';
    case Not = 'not';
    case Alinti = 'alinti';
    // Faz H3: okurken fosforlanan yer — sadece metinde görünür, kotası yok.
    case Fosfor = 'fosfor';

    public function label(): string
    {
        return match ($this) {
            self::Defter => 'Defter',
            self::Not => 'Not',
            self::Alinti => 'Alıntı',
            self::Fosfor => 'Fosfor',
        };
    }

    /** Çalışma alanı kotası olan türler (fosforun sınırı yok — "Okurun Gözünden" kararı 5). */
    public static function quotaTypes(): array
    {
        return [self::Defter, self::Not, self::Alinti];
    }

    /** Okurken metinde seçilerek eklenen türler (Alıntıla / Not Al / Fosforla). */
    public static function readingMarks(): array
    {
        return [self::Alinti, self::Not, self::Fosfor];
    }
}
