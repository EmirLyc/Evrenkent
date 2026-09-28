<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Support\RichText;
use App\Support\WorkOutline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sözlük maddesi (Faz G3, "Sözlüğe Dair"): sözlükte "Kavram" ile işaretlenen satır ve bir sonraki
 * kavrama (ya da başlığa / bölüm sonuna) kadar olan metin. Satırlar elle değil, sözlük her
 * kaydedildiğinde metinden üretiliyor — bkz. DictionaryDocument::sync.
 */
class DictionaryEntry extends Model
{
    protected $fillable = [
        'book_id', 'key', 'term', 'slug', 'term_search', 'position', 'chapter_order', 'content', 'excerpt',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'chapter_order' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Arama için küçük harf — Türkçe kurallarıyla (İ → i, I → ı). */
    public static function searchable(string $text): string
    {
        return mb_strtolower(str_replace(['İ', 'I'], ['i', 'ı'], trim($text)), 'UTF-8');
    }

    /**
     * Bağlanabilir / okunabilir maddeler: yayındaki sözlüklerinkiler; kullanıcı verilirse onun
     * kendi (henüz yayınlanmamış) sözlüklerininkiler de — yazar kendi sözlüğüne bağlantıyı
     * sözlük yayına girmeden kurabilsin.
     */
    public function scopeAvailableTo(Builder $query, ?User $user): Builder
    {
        return $query->whereHas('book', fn (Builder $book) => $book
            ->where('kind', Book::KIND_SOZLUK)
            ->where(fn (Builder $q) => $q
                ->where('status', ContentStatus::Yayinda)
                ->when($user, fn (Builder $q) => $q->orWhere('author_id', $user->id))));
    }

    public function isAvailableTo(?User $user): bool
    {
        return $this->book !== null
            && $this->book->isDictionary()
            && ($this->book->status === ContentStatus::Yayinda || ($user && $user->id === $this->book->author_id));
    }

    public function url(): string
    {
        return route('sozlukler.madde', [$this->book, $this]);
    }

    /** Maddenin sözlükteki yeri (okuma sayfası, kavram satırının çapası). */
    public function readUrl(): string
    {
        return route('kitaplar.oku', [$this->book, $this->chapter_order]).'#madde-'.$this->key;
    }

    /**
     * Madde sayfasındaki metin — sözlüğün bütünü içinde: kaynak numaraları eser genelindeki
     * sırayla, kaynakçası (başka bölümdeyse) oraya bağlı; belgeler ve görseller sözlüğün.
     */
    public function renderedContent(): string
    {
        $outline = WorkOutline::for($this->book);

        return RichText::render($this->content, 'dn-madde', $this->book->documents, [
            'numbering' => false,
            'anchor' => 'm',
            'sources' => $outline->sources,
            'bibliographyUrl' => (string) $outline->bibliographyUrl,
            'toc' => $outline->toc,
        ]);
    }
}
