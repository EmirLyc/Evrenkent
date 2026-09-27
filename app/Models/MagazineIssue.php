<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\MagazineIssueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MagazineIssue extends Model
{
    /** @use HasFactory<MagazineIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'magazine_id', 'editor_id', 'title', 'issue_number', 'cover_image', 'editor_note', 'status', 'publish_date',
        'scheduled_publish_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'publish_date' => 'date',
            'scheduled_publish_at' => 'datetime',
        ];
    }

    /**
     * Sayıda en az bir onaylı makale var mı — sayı bu olmadan yayınlanamaz ve
     * zamanlanamaz (2026-09-27 kararı; önceden boş bir sayı yayına çıkabiliyordu).
     * MagazineIssuePolicy::approve/publish bu koşulu kullanıyor.
     */
    public function hasApprovedArticles(): bool
    {
        return $this->articles()->whereIn('status', [ContentStatus::Onaylandi, ContentStatus::Yayinda])->exists();
    }

    /** Onaylanıp ileri bir tarihe zamanlanmış sayılar (Yakında Çıkacaklar). */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Onaylandi)
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '>', now())
            ->orderBy('scheduled_publish_at');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function magazine(): BelongsTo
    {
        return $this->belongsTo(Magazine::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(ContentReview::class, 'reviewable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Yayinda);
    }
}
