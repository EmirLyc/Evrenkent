<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    protected $fillable = [
        'author_id', 'magazine_issue_id', 'title', 'slug',
        'content', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Makalenin sayfasının URL'i — Book::url() ile aynı isim/imza, panel
     * listelerinde noteable/favoritable gibi polymorphic ilişkilerde tip
     * kontrolü yapmadan tek tip `$item->url()` çağrılabilsin diye.
     */
    public function url(): string
    {
        return route('makaleler.show', $this);
    }

    /**
     * Makale sayfasını bu kullanıcı görebilir mi — yayındaysa herkes, değilse
     * sadece yazarı ve Süper Admin (önizleme). Hem makale sayfası hem not ekleme
     * aynı kuralı kullansın diye tek yerde (bkz. NoteController::store).
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->status === ContentStatus::Yayinda
            || ($user && $user->id === $this->author_id)
            || ($user && $user->hasRole('super_admin'));
    }

    public function magazineIssue(): BelongsTo
    {
        return $this->belongsTo(MagazineIssue::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_article');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(ContentReview::class, 'reviewable');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Yayinda);
    }
}
