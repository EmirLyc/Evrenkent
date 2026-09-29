<?php

namespace App\Models;

use App\Enums\NoteType;
use App\Support\NotebookHtml;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Defter kaydı, not, alıntı ya da fosfor. Okurken metinde seçilerek eklenenlerde (Faz H3) quote
 * seçilen metin, anchor metindeki yeri ({chapter, start, end, prefix, suffix}), page kaydedildiği
 * sayfa. Alıntı ve fosforda content de seçilen metin; notta okurun yazdığı.
 */
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /** Silinen defterin görselleri de gider (okurun başka bir defterine kopyaladıkları hariç). */
    protected static function booted(): void
    {
        static::deleted(function (Note $note) {
            if ($note->type === NoteType::Defter) {
                NotebookHtml::pruneImages($note->user_id, NotebookHtml::imagePaths($note->content));
            }
        });
    }

    protected $fillable = [
        'user_id', 'type', 'noteable_type', 'noteable_id', 'title', 'subtitle', 'content', 'quote', 'anchor', 'page', 'tags', 'info', 'location',
    ];

    protected function casts(): array
    {
        return [
            'type' => NoteType::class,
            'anchor' => 'array',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Okuma sayfasının işaret verisi (paged-reader.js). */
    public function toReadingMark(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'quote' => $this->quote ?? $this->content,
            'content' => $this->type === NoteType::Not ? $this->content : null,
            'anchor' => $this->anchor,
            'page' => $this->page,
        ];
    }
}
