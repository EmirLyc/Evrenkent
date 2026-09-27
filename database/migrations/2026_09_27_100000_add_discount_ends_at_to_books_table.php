<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kampanya indiriminin (discount_price) isteğe bağlı bitiş tarihi — Süper Admin'in
     * İndirimler sayfasından verdiği süreli kampanyalar için. Boşsa indirim süresiz;
     * tarih geçmişse indirim artık uygulanmaz (bkz. Book::activeDiscountPrice).
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->timestamp('discount_ends_at')->nullable()->after('discount_price');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('discount_ends_at');
        });
    }
};
