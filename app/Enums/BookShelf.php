<?php

namespace App\Enums;

use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;

/**
 * Anasayfa/katalog sayfasındaki "pil" filtreleri — hangi kitap rafının
 * gösterileceğini belirler. Tek yerden yönetilir, hem HomeController hem
 * BookCatalogController aynı enum'u kullanır.
 */
enum BookShelf: string
{
    case YeniCikanlar = 'yeni';
    case CokSatanlar = 'cok-satanlar';
    case EditorunSeckisi = 'editorun-seckisi';
    case Firsatlar = 'firsatlar';
    case YakindaCikacaklar = 'yakinda';

    public function label(): string
    {
        return match ($this) {
            self::YeniCikanlar => 'Yeni Çıkanlar',
            self::CokSatanlar => 'Çok Satanlar',
            self::EditorunSeckisi => 'Editörün Seçkisi',
            self::Firsatlar => 'Fırsatlar',
            self::YakindaCikacaklar => 'Yakında Çıkacaklar',
        };
    }

    /** Boş durumda gösterilecek mesaj. */
    public function emptyMessage(): string
    {
        return match ($this) {
            self::YeniCikanlar => 'Henüz yayınlanmış bir kitap yok.',
            self::CokSatanlar => 'Henüz satın alınmış bir kitap yok.',
            self::EditorunSeckisi => 'Editörün seçkisine henüz bir kitap eklenmedi.',
            self::Firsatlar => 'Şu an indirimde bir kitap yok.',
            self::YakindaCikacaklar => 'Yakında çıkacak bir kitap yok.',
        };
    }

    /** İlgili rafın kitap sorgusu — sıralama/filtre bu raftan gelir. Sözlükler rafta yok (/sozlukler). */
    public function query(): Builder
    {
        if ($this === self::YakindaCikacaklar) {
            return Book::books()->upcoming()->with('author');
        }

        $query = Book::books()->published()->with('author');

        return match ($this) {
            self::YeniCikanlar => $query->latest('published_at'),
            self::CokSatanlar => $query->bestsellers(),
            self::EditorunSeckisi => $query->editorsPick()->latest('published_at'),
            self::Firsatlar => $query->onSale()->latest('published_at'),
        };
    }
}
