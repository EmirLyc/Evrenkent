<?php

namespace App\Models;

use App\Models\Concerns\HasRichContent;
use App\Support\RichText;
use App\Support\WorkOutline;
use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory;

    use HasRichContent;

    protected $fillable = [
        'book_id', 'title', 'content', 'order', 'is_preface',
    ];

    protected function casts(): array
    {
        return ['is_preface' => 'boolean'];
    }

    /** Bölüm, kitabın bütünü içinde render edilir (numara, içindekiler, kaynaklar — Faz G2). */
    protected function renderContext(): array
    {
        return WorkOutline::for($this->book)->contextFor($this);
    }

    /**
     * Sayfalı okumada (Faz G4) bütün kitap tek sayfada: eser bağlamı bir kez hesaplanıp her
     * bölüme veriliyor (renderedContent her bölüm için WorkOutline'ı baştan kurardı).
     */
    public function renderedIn(WorkOutline $outline): string
    {
        return RichText::render($this->content, $this->footnotePrefix(), $this->contentDocuments(), $outline->contextFor($this));
    }

    /**
     * Toplu kayıtta (BookDocument::sync) bölüm başına yeniden sayım kapalı — sayım en sonda bir
     * kez yapılıyor. Açık kalsa her bölüm kaydı bütün kitabı yeniden okurdu (80 bölümde 80 kez).
     */
    private static bool $countsPaused = false;

    public static function withoutCountRefresh(callable $callback): mixed
    {
        $previous = self::$countsPaused;
        self::$countsPaused = true;

        try {
            return $callback();
        } finally {
            self::$countsPaused = $previous;
        }
    }

    /** Kitabın belge/video sayısı metinden hesaplanıyor (Book::refreshContentCounts). */
    protected static function booted(): void
    {
        $refresh = fn (Chapter $chapter) => self::$countsPaused ? null : $chapter->book?->refreshContentCounts();

        static::saved($refresh);
        static::deleted($refresh);
    }

    protected function footnotePrefix(): string
    {
        return 'dn-bolum-'.$this->order;
    }

    protected function contentDocuments(): Collection
    {
        return $this->book->documents;
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
