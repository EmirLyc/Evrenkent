<?php

use App\Models\Book;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Belge ve video sayıları artık bölüm metninden hesaplanıyor (Book::refreshContentCounts);
     * elle girilmiş eski değerler gerçek içerikle değiştiriliyor. Şema değişmiyor.
     */
    public function up(): void
    {
        // withoutGlobalScopes: Book sonradan soft delete kullanıyor (Faz G1), bu migration
        // çalışırken deleted_at sütunu henüz yok.
        Book::withoutGlobalScopes()->each(fn (Book $book) => $book->refreshContentCounts());
    }

    public function down(): void {}
};
