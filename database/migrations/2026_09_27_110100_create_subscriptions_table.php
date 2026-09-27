<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Premium abonelik satın alımları (aylık/yıllık). Kullanıcının asıl premium durumu
     * users.is_premium + users.premium_until'de tutulmaya devam ediyor (Süper Admin elle de
     * verebiliyor); bu tablo her ödemenin geçmişi — Aboneliğim sayfası ve ileride gelir
     * istatistikleri için. Ödeme şimdilik mock (bkz. User::subscribe).
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->decimal('amount', 8, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('payment_status')->default('completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
