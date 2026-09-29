<?php

namespace App\Models;

use App\Enums\ReadingStatus;
use Database\Factories\ReadingListItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReadingListItem extends Model
{
    /** @use HasFactory<ReadingListItemFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'readable_type', 'readable_id', 'status', 'completed_at', 'last_chapter_number', 'progress',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReadingStatus::class,
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    /**
     * Sayfalı okumada okurun bulunduğu yer ("%45 okundu"). Yüzde okurun ulaştığı en ileri yer —
     * İçindekiler'den başa dönüp bir şeye bakmak ilerlemeyi geri almaz. Okunmuş eserde değişmez.
     */
    public function recordPosition(int $chapter, ?int $page = null, ?int $total = null): void
    {
        $attributes = ['last_chapter_number' => $chapter];

        if ($page !== null && $total && $this->status !== ReadingStatus::Tamamlandi) {
            $percent = (int) min(100, max(0, round(($page + 1) / $total * 100)));
            $attributes['progress'] = max($percent, (int) $this->progress);
        }

        $this->update($attributes);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function readable(): MorphTo
    {
        return $this->morphTo();
    }
}
