<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Faz E (2026-09-27 revizesi: "süper admin bir dergi için birini editör yapabilmeli",
     * "dergide yazarı süper admin yapacak"). Önceden ayrı bir "Dergi" varlığı yoktu, sadece
     * sayılar vardı ve her yazar açık olan her sayıya makale gönderebiliyordu.
     *
     *  - magazines: dergi (ad, açıklama, kapak) + tek editör (editor_id)
     *  - magazine_author: Süper Admin'in dergiye atadığı yazarlar (yazar sadece bunlara gönderir)
     *  - magazine_issues.magazine_id: sayının dergisi
     *
     * Mevcut veri: sayı başlıkları dergi adını içeriyor ("Bilim Tarihi Dergisi - Sayı 23") —
     * aynı adlı sayılar tek dergide toplanıyor, kalıba uymayanlar "Evrenkent Dergisi"ne gidiyor.
     * Derginin editörü sayıların editörü; o dergiye makale yazmış yazarlar dergiye atanıyor
     * ki bugün çalışan gönderim akışları bozulmasın.
     */
    public function up(): void
    {
        Schema::create('magazines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('magazine_author', function (Blueprint $table) {
            $table->foreignId('magazine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['magazine_id', 'user_id']);
        });

        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->foreignId('magazine_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $this->backfillExistingIssues();
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('magazine_id');
        });
        Schema::dropIfExists('magazine_author');
        Schema::dropIfExists('magazines');
    }

    private function backfillExistingIssues(): void
    {
        $issues = DB::table('magazine_issues')->orderBy('id')->get(['id', 'title', 'editor_id']);
        $magazineIds = [];

        foreach ($issues as $issue) {
            $name = preg_match('/^(.+?)\s*[-–—]\s*Sayı\s*\d+/u', $issue->title, $m) ? trim($m[1]) : 'Evrenkent Dergisi';

            if (! isset($magazineIds[$name])) {
                $slug = Str::slug($name);
                $magazineIds[$name] = DB::table('magazines')->insertGetId([
                    'name' => $name,
                    'slug' => DB::table('magazines')->where('slug', $slug)->exists() ? $slug.'-'.Str::random(4) : $slug,
                    'editor_id' => $issue->editor_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('magazine_issues')->where('id', $issue->id)->update(['magazine_id' => $magazineIds[$name]]);
        }

        // Dergiye daha önce makale göndermiş yazarlar o derginin yazarı olsun.
        $pairs = DB::table('articles')
            ->join('magazine_issues', 'magazine_issues.id', '=', 'articles.magazine_issue_id')
            ->whereNotNull('magazine_issues.magazine_id')
            ->select('magazine_issues.magazine_id', 'articles.author_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            DB::table('magazine_author')->insertOrIgnore([
                'magazine_id' => $pair->magazine_id,
                'user_id' => $pair->author_id,
            ]);
        }
    }
};
