<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_premium', 'premium_until'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_premium' => 'boolean',
            'premium_until' => 'datetime',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    public function editedMagazineIssues(): HasMany
    {
        return $this->hasMany(MagazineIssue::class, 'editor_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function readingListItems(): HasMany
    {
        return $this->hasMany(ReadingListItem::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function hasFavorited(Model $favoritable): bool
    {
        return $this->favorites()
            ->where('favoritable_type', $favoritable::class)
            ->where('favoritable_id', $favoritable->getKey())
            ->exists();
    }

    public function hasPurchased(Book $book): bool
    {
        return $this->purchases()->where('book_id', $book->id)->exists();
    }

    public function hasInCart(Book $book): bool
    {
        return $this->cartItems()->where('book_id', $book->id)->exists();
    }

    /**
     * Gerçek ödeme entegrasyonu (Stripe/iyzico) gelecek bir faz — bu metod ödeme
     * sorulmadan anında/mock tamamlanmış bir satın alma kaydı oluşturur. Hem tekil
     * "Satın Al" (PurchaseController) hem sepet ödemesi (CartController) bunu kullanır.
     * Tutar Book::priceFor()'dan gelir (geçerli kampanya indirimi varsa o).
     */
    public function purchase(Book $book): Purchase
    {
        return $this->purchases()->firstOrCreate([
            'book_id' => $book->id,
        ], [
            'amount' => $book->priceFor($this),
            'purchased_at' => now(),
            'payment_status' => 'completed',
        ]);
    }

    public function readingListItemFor(Model $readable): ?ReadingListItem
    {
        return $this->readingListItems()
            ->where('readable_type', $readable::class)
            ->where('readable_id', $readable->getKey())
            ->first();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['super_admin', 'dergi_editoru']);
    }

    /**
     * Girişten sonra role göre yönlendirilecek yol.
     * Süper Admin -> kendi dashboard'u, Dergi Editörü -> kendi dashboard'u
     * (Filament hâlâ /admin'den tam erişilebilir ama zorunlu ilk durak değil),
     * Yazar -> Yayınlarım, Okur -> Anasayfa (panele değil — sidebar zaten açık
     * geliyor, "Kitaplığım" bir tık uzakta, ayrıca kullanıcı doğrudan panele
     * düşürülmek istemedi).
     */
    public function redirectPath(): string
    {
        if ($this->hasRole('super_admin')) {
            return '/panel/admin-panel';
        }

        if ($this->hasRole('dergi_editoru')) {
            return '/panel/dergi';
        }

        if ($this->hasRole('yazar')) {
            return '/panel/yayinlarim';
        }

        return '/';
    }
}
