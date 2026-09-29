<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Book;
use App\Models\Chapter;

/**
 * Bir eserin bütünü üzerinden hesaplananlar (Faz G2, "Yazarın Gözünden" 1.1.1): editörde kitap
 * tek belge, okumada bölüm bölüm. Bölüm sayfası render edilirken eserin geneline dair şunlar
 * gerekiyor —
 *  - içindekiler: Başlık 1 = bölüm, Başlık 2–4 bölüm içi (numaralı, bağlantılı),
 *  - kaynaklar: "1 Kaynak No." numaraları bölümler arasında süren tek sıra,
 *  - kaynakça hangi bölümde (atıf bağlantısı oraya gitsin),
 *  - bölüm numarası (başlıksız giriş bölümü numaralamaya katılmaz).
 */
class WorkOutline
{
    /** @var list<array{level: int, number: string, text: string, url: string}> */
    public array $toc = [];

    /**
     * Okuma sayfasının İçindekiler çekmecesi (Faz H1, "Okuma modu 9"): bölümler (giriş dahil) ve
     * altlarında başlıklar / kavramlar. Sayfa numaralarını tarayıcı sayfaları dizince yazıyor.
     *
     * @var list<array{level: int, number: string, text: string, url: string, items: list<array<string, mixed>>}>
     */
    public array $contents = [];

    /** @var list<string> */
    public array $sources = [];

    public ?string $bibliographyUrl = null;

    /** @var array<int, int> bölüm id → bölüm numarası (giriş bölümü: 0) */
    private array $chapterNumbers = [];

    private function __construct(private Book|Article $work) {}

    public static function for(Book|Article $work): self
    {
        $outline = new self($work);
        $work instanceof Book ? $outline->buildForBook($work) : $outline->buildForArticle($work);

        return $outline;
    }

    private function buildForBook(Book $book): void
    {
        $number = 0;

        foreach ($book->chapters as $chapter) {
            $url = route('kitaplar.oku', [$book, $chapter->order]);
            $this->chapterNumbers[$chapter->id] = $chapter->is_preface ? 0 : ++$number;
            $counters = [1 => $this->chapterNumbers[$chapter->id], 2 => 0, 3 => 0, 4 => 0];

            $group = ['level' => 1, 'number' => '', 'text' => $chapter->title, 'url' => $url, 'items' => []];
            if (! $chapter->is_preface) {
                $group['number'] = $book->heading_numbering ? RichText::headingNumber($counters, 1) : '';
                $this->toc[] = array_diff_key($group, ['items' => true]);
            }

            // Başlıklar ve (sözlükte) kavramlar metindeki sırayla. Kavram numarasız, bulunduğu
            // başlığın bir altında listelenir (Faz G3).
            $index = 0;
            $lastLevel = 1;
            foreach (RichText::outline($chapter->content) as $item) {
                if ($item['type'] === 'concept') {
                    if ($item['text'] !== '' && DictionaryDocument::isValidKey($item['key'])) {
                        $this->toc[] = $group['items'][] = ['level' => min(4, $lastLevel + 1), 'number' => '', 'text' => $item['text'], 'url' => $url.'#madde-'.$item['key'], 'concept' => true];
                    }

                    continue;
                }

                $level = $lastLevel = $item['level'];
                $counters[$level]++;
                for ($deeper = $level + 1; $deeper <= 4; $deeper++) {
                    $counters[$deeper] = 0;
                }
                $this->toc[] = $group['items'][] = [
                    'level' => $level,
                    'number' => $book->heading_numbering ? RichText::headingNumber($counters, $level) : '',
                    'text' => $item['text'],
                    'url' => $url.'#'.$this->anchor($chapter).'-'.(++$index),
                ];
            }

            $this->contents[] = $group;

            $this->sources = array_values(array_unique([...$this->sources, ...RichText::citations($chapter->content)]));

            if ($this->bibliographyUrl === null && str_contains((string) $chapter->content, 'data-bibliography')) {
                $this->bibliographyUrl = $url;
            }
        }
    }

    private function buildForArticle(Article $article): void
    {
        $this->sources = RichText::citations($article->content);
        $this->bibliographyUrl = '';

        // Makale tek sayfa: Başlık 1 grup açar, öncesindeki başlıklar kendi başına satır.
        $counters = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        $index = 0;
        $group = null;
        foreach (RichText::outline($article->content) as $item) {
            if ($item['type'] !== 'heading') {
                continue;
            }

            $level = $item['level'];
            $counters[$level]++;
            for ($deeper = $level + 1; $deeper <= 4; $deeper++) {
                $counters[$deeper] = 0;
            }
            $entry = [
                'level' => $level,
                'number' => $article->heading_numbering ? RichText::headingNumber($counters, $level) : '',
                'text' => $item['text'],
                'url' => '#b-'.(++$index),
            ];

            if ($level > 1 && $group !== null && $group['level'] === 1) {
                $group['items'][] = $entry;
            } else {
                $group !== null && $this->contents[] = $group;
                $group = $entry + ['items' => []];
            }
        }
        $group !== null && $this->contents[] = $group;
    }

    public function chapterNumber(Chapter $chapter): int
    {
        return $this->chapterNumbers[$chapter->id] ?? 0;
    }

    private function anchor(Chapter $chapter): string
    {
        return 'b'.$chapter->order;
    }

    /**
     * RichText::render bağlamı — bölüm sayfası için.
     *
     * @return array<string, mixed>
     */
    public function contextFor(Chapter $chapter): array
    {
        $currentUrl = route('kitaplar.oku', [$this->work, $chapter->order]);

        return [
            'numbering' => (bool) $this->work->heading_numbering,
            'counters' => [1 => $this->chapterNumber($chapter)],
            'anchor' => $this->anchor($chapter),
            'sources' => $this->sources,
            // Kaynakça bu sayfadaysa sadece çapa; başka bölümdeyse o bölümün adresi.
            'bibliographyUrl' => $this->bibliographyUrl === null || $this->bibliographyUrl === $currentUrl ? '' : $this->bibliographyUrl,
            'toc' => $this->toc,
        ];
    }

    /** @return array<string, mixed> */
    public function articleContext(): array
    {
        return [
            'numbering' => (bool) $this->work->heading_numbering,
            'anchor' => 'b',
            'sources' => $this->sources,
            'bibliographyUrl' => '',
        ];
    }
}
