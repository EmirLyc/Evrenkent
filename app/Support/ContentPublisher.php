<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Book;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Notifications\ContentApproved;
use App\Notifications\ContentPublished;
use Illuminate\Support\Carbon;

/**
 * Yayınlama ve zamanlamanın tek adresi (Faz D, 2026-09-27). Kendi onay ekranımız
 * (ContentApprovalController) ve zamanlayıcı (content:publish-scheduled) buradan geçer — önceden aynı "durum güncelle + geçmiş
 * kaydı + bildirim" üçlüsü her yerde ayrı ayrı yazılıydı, sayı→makale zincirlemesi gibi
 * yeni kurallar üç yere birden eklenmek zorunda kalırdı.
 *
 * Sayı/makale kuralları (toplantı kararı):
 *  1. Sayı yayınlanınca (veya zamanı gelince) içindeki onaylı makaleler de aynı anda yayına girer.
 *  2. Sayı en az bir onaylı makale olmadan yayınlanamaz/zamanlanamaz (MagazineIssuePolicy).
 *  3. Makale, sayısı yayında değilse tek başına yayınlanamaz (ArticlePolicy::publish).
 *  4. Sayı yayındayken sonradan onaylanan makale tek başına yayınlanabilir/zamanlanabilir.
 *
 * $by boşsa işlemi zamanlayıcı yapıyor demektir; geçmiş kaydı "Sistem" olarak düşer.
 */
class ContentPublisher
{
    public static function publishBook(Book $book, ?User $by = null, ?Carbon $at = null): void
    {
        $book->update(['status' => ContentStatus::Yayinda, 'published_at' => $at ?? now()]);
        self::record($book, 'yayinda', $by);
        $book->author->notify(new ContentPublished($book));
    }

    public static function publishIssue(MagazineIssue $issue, ?User $by = null, ?Carbon $at = null): void
    {
        $at ??= now();

        $issue->update([
            'status' => ContentStatus::Yayinda,
            'publish_date' => $issue->publish_date ?? $at->toDateString(),
        ]);
        self::record($issue, 'yayinda', $by);
        $issue->editor->notify(new ContentPublished($issue));

        // Kural 1: sayıyla birlikte onaylı makaleleri de yayına al.
        foreach ($issue->articles()->where('status', ContentStatus::Onaylandi)->get() as $article) {
            self::publishArticle($article, $by, $at);
        }
    }

    public static function publishArticle(Article $article, ?User $by = null, ?Carbon $at = null): void
    {
        $article->update(['status' => ContentStatus::Yayinda, 'published_at' => $at ?? now()]);
        self::record($article, 'yayinda', $by);
        $article->author->notify(new ContentPublished($article));
    }

    /**
     * Onaylayıp ileri bir tarihe zamanlar — içerik "Yakında Çıkacaklar"da görünür, tarihi
     * gelince publishDue() yayınlar.
     */
    public static function schedule(Book|MagazineIssue|Article $record, Carbon $at, ?User $by = null): void
    {
        $record->update(['status' => ContentStatus::Onaylandi, 'scheduled_publish_at' => $at]);
        self::record($record, 'onaylandi', $by, 'Yayın tarihi: '.$at->format('d.m.Y H:i'));
        self::owner($record)->notify(new ContentApproved($record));
    }

    /**
     * Zamanı gelmiş her şeyi yayınlar (zamanlayıcı her dakika çağırır). Sıra önemli:
     * önce sayılar (makalelerini de zincirleme yayınlar), sonra tek başına zamanlanmış
     * makaleler — onlar da sadece sayıları yayındaysa (kural 3).
     *
     * @return array{books: int, issues: int, articles: int}
     */
    public static function publishDue(): array
    {
        $counts = ['books' => 0, 'issues' => 0, 'articles' => 0];

        foreach (self::dueQuery(Book::query())->with('author')->get() as $book) {
            self::publishBook($book, null, $book->scheduled_publish_at);
            $counts['books']++;
        }

        foreach (self::dueQuery(MagazineIssue::query())->with('editor')->get() as $issue) {
            // Zamanlandıktan sonra makaleleri revizyona düşmüş olabilir — boş sayı yayına çıkmasın.
            if (! $issue->hasApprovedArticles()) {
                continue;
            }

            self::publishIssue($issue, null, $issue->scheduled_publish_at);
            $counts['issues']++;
        }

        $articles = self::dueQuery(Article::query())
            ->whereHas('magazineIssue', fn ($q) => $q->where('status', ContentStatus::Yayinda))
            ->with('author')
            ->get();

        foreach ($articles as $article) {
            self::publishArticle($article, null, $article->scheduled_publish_at);
            $counts['articles']++;
        }

        return $counts;
    }

    private static function dueQuery($query)
    {
        return $query->where('status', ContentStatus::Onaylandi)
            ->whereNotNull('scheduled_publish_at')
            ->where('scheduled_publish_at', '<=', now());
    }

    private static function owner(Book|MagazineIssue|Article $record): User
    {
        return $record instanceof MagazineIssue ? $record->editor : $record->author;
    }

    private static function record(Book|MagazineIssue|Article $record, string $action, ?User $by, ?string $note = null): void
    {
        $record->reviews()->create([
            'reviewer_id' => $by?->id,
            'action' => $action,
            'note' => $note,
        ]);
    }
}
