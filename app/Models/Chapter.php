<?php

namespace App\Models;

use App\Models\Concerns\HasRichContent;
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
        'book_id', 'title', 'content', 'order',
    ];

    /** Kitabın belge/video sayısı metinden hesaplanıyor (Book::refreshContentCounts). */
    protected static function booted(): void
    {
        static::saved(fn (Chapter $chapter) => $chapter->book?->refreshContentCounts());
        static::deleted(fn (Chapter $chapter) => $chapter->book?->refreshContentCounts());
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
