<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = [
        'author_id', 'title', 'slug', 'description',
        'cover_image', 'price', 'discount_price', 'status',
        'is_editors_pick', 'published_at', 'scheduled_publish_at',
        'average_rating', 'review_count',
        'page_count', 'document_count', 'video_count',
        'map_count', 'author_note_count', 'source_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
            'scheduled_publish_at' => 'datetime',
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'is_editors_pick' => 'boolean',
            'average_rating' => 'decimal:2',
            'review_count' => 'integer',
            'page_count' => 'integer',
            'document_count' => 'integer',
            'video_count' => 'integer',
            'map_count' => 'integer',
            'author_note_count' => 'integer',
            'source_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Kitabın tanıtım sayfasının URL'i — Kitaplığım/Favorilerim/Okuma Listem/
     * Satın Aldıklarım/Notlarım gibi panellerde tek tip bir "içeriğe git" linki
     * kurabilmek için (Article::url() ile birlikte polymorphic kullanılabiliyor).
     */
    public function url(): string
    {
        return route('kitaplar.show', $this);
    }

    /**
     * Tanıtım sayfasını bu kullanıcı görebilir mi. "Onaylandı" + hedef tarihi olan
     * kitaplar bir teaser (Yakında Çıkacak) olarak herkese açık; tarihsiz "Onaylandı"
     * kitaplar hâlâ sadece yazarına görünür (henüz kamuya duyurulmaya hazır değil).
     * Hem tanıtım sayfası hem not ekleme aynı kuralı kullansın diye tek yerde
     * (bkz. NoteController::store).
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->status === ContentStatus::Yayinda
            || ($this->status === ContentStatus::Onaylandi && $this->scheduled_publish_at !== null)
            || ($user && $user->id === $this->author_id)
            || ($user && $user->hasRole('super_admin'));
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_book');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(ContentReview::class, 'reviewable');
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Yayinda);
    }

    /** En çok satın alınandan aza doğru sıralar (Çok Satanlar). */
    public function scopeBestsellers(Builder $query): Builder
    {
        return $query->withCount('purchases')->orderByDesc('purchases_count');
    }

    public function scopeEditorsPick(Builder $query): Builder
    {
        return $query->where('is_editors_pick', true);
    }

    /** Sadece indirimli fiyatı olan (Fırsatlar) kitaplar. */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereNotNull('discount_price');
    }

    /**
     * Onaylandı ama henüz yayınlanmamış, planlanan bir yayın tarihi olan kitaplar
     * (Yakında Çıkacaklar). Tarih geldiğinde books:publish-scheduled komutu bunları
     * otomatik "Yayında"ya çevirir.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Onaylandi)
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '>', now())
            ->orderBy('scheduled_publish_at');
    }

    /**
     * Kitap tanıtım sayfasındaki içerik istatistik şeridi — sadece dolu olan
     * alanlar döner (yazar panelinden hangisini girmişse sadece o gösterilir).
     *
     * @return array<int, array{count: int, icon: string, label: string}>
     */
    public function contentStats(): array
    {
        return collect([
            ['count' => $this->page_count, 'icon' => 'heroicon-o-document-text', 'label' => 'sayfa'],
            ['count' => $this->document_count, 'icon' => 'heroicon-o-paper-clip', 'label' => 'belge'],
            ['count' => $this->video_count, 'icon' => 'heroicon-o-play-circle', 'label' => 'video'],
            ['count' => $this->map_count, 'icon' => 'heroicon-o-map', 'label' => 'harita'],
            ['count' => $this->author_note_count, 'icon' => 'heroicon-o-pencil-square', 'label' => 'yazar notu'],
            ['count' => $this->source_count, 'icon' => 'heroicon-o-link', 'label' => 'kaynak'],
        ])
            ->filter(fn (array $stat) => ! empty($stat['count']))
            ->values()
            ->all();
    }
}
