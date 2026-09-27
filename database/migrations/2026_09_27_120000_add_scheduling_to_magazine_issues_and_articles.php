<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Faz D (2026-09-27 revizesi): Süper Admin dergi sayısını ve — sayı yayındayken sonradan
     * onaylanan — makaleyi de ileri bir tarihe zamanlayabiliyor (önceden sadece kitaplarda
     * vardı). Zamanı gelince content:publish-scheduled yayınlıyor.
     *
     * content_reviews.reviewer_id boş olabilir hâle geliyor: zamanlanmış yayını bir kullanıcı
     * değil zamanlayıcı yapıyor, geçmişte "Sistem" olarak görünüyor (SuperAdminController'ın
     * canlı akışı reviewer yoksa zaten "Sistem" yazıyordu).
     */
    public function up(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->timestamp('scheduled_publish_at')->nullable()->after('publish_date');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->timestamp('scheduled_publish_at')->nullable()->after('published_at');
        });

        Schema::table('content_reviews', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->dropColumn('scheduled_publish_at');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('scheduled_publish_at');
        });

        Schema::table('content_reviews', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable(false)->change();
        });
    }
};
