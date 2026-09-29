<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\Chapter;
use App\Support\RichText;
use App\Support\WorkOutline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BookController extends Controller
{
    public function show(Book $book): View
    {
        $user = auth()->user();

        abort_unless($book->isVisibleTo($user), 404);

        $book->load(['author', 'categories']);

        $isUpcoming = $book->status === ContentStatus::Onaylandi && $book->scheduled_publish_at !== null;
        $hasFavorited = $user?->hasFavorited($book) ?? false;
        $readingListItem = $user?->readingListItemFor($book);
        // Satın alma kaydının kendisi (sadece var/yok değil) — satın alındı kartında
        // "Bu kitabı X tarihinde satın aldınız" yazabilmek için tarihi gerekiyor.
        $purchase = $user?->purchases()->where('book_id', $book->id)->first();
        $hasPurchased = $purchase !== null;
        $hasInCart = $user?->hasInCart($book) ?? false;
        $chapterCount = $book->chapters()->count();

        // Öneri şeridi: önce aynı kategorideki başka yayınlanmış kitaplar; yeterli sayıda yoksa
        // aynı yazarın diğer kitaplarıyla tamamlanır (hiçbiri yoksa şerit hiç gösterilmez, uydurma yok).
        $relatedBooks = collect();

        if ($book->categories->isNotEmpty()) {
            $relatedBooks = Book::published()
                ->where('kind', $book->kind)
                ->where('id', '!=', $book->id)
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $book->categories->pluck('id')))
                ->with('author')
                ->latest('published_at')
                ->take(6)
                ->get();
        }

        if ($relatedBooks->count() < 6) {
            $relatedBooks = $relatedBooks->concat(
                Book::published()
                    ->where('kind', $book->kind)
                    ->where('id', '!=', $book->id)
                    ->where('author_id', $book->author_id)
                    ->whereNotIn('id', $relatedBooks->pluck('id'))
                    ->with('author')
                    ->latest('published_at')
                    ->take(6 - $relatedBooks->count())
                    ->get()
            );
        }

        return view('books.show', compact('book', 'isUpcoming', 'hasFavorited', 'readingListItem', 'purchase', 'hasPurchased', 'hasInCart', 'chapterCount', 'relatedBooks'));
    }

    public function read(Book $book, ?int $chapterNumber = null): View|RedirectResponse
    {
        $user = auth()->user();
        $isAuthor = $user && $user->id === $book->author_id;

        abort_unless($book->status === ContentStatus::Yayinda || $isAuthor, 404);

        if (! $book->isReadableBy($user)) {
            return redirect()->route('kitaplar.show', $book)
                ->with('status', 'Bu kitabı okumak için satın almanız gerekiyor.');
        }

        $chapters = $book->chapters;
        $readingListItem = $user?->readingListItemFor($book);

        // Kaldığı bölüm sonradan silinmiş olabilir (editör kitabı her kayıtta bölümlere yeniden
        // ayırıyor, fazla bölümler gidiyor) — o zaman kitabın başından açılır, 404 değil.
        if ($chapterNumber === null) {
            $saved = $readingListItem?->last_chapter_number;
            $chapterNumber = $saved !== null && $chapters->contains('order', $saved)
                ? $saved
                : $chapters->first()?->order;
        }

        $chapter = $chapterNumber ? $chapters->firstWhere('order', $chapterNumber) : null;

        if ($chapterNumber !== null && ! $chapter) {
            abort(404);
        }

        // Yazarın kendi taslağını önizlemesi okuma listesine / Kitaplığım'a girmesin.
        if ($user && $chapter && $book->status === ContentStatus::Yayinda) {
            $readingListItem = $user->readingListItems()->firstOrCreate([
                'readable_type' => Book::class,
                'readable_id' => $book->id,
            ], [
                'status' => ReadingStatus::Listede,
            ]);
            $readingListItem->update(['last_chapter_number' => $chapter->order]);
        }

        // Faz G4 — sayfalı okuma: bütün kitap tek akışta, yazarın sayfa oranında sayfalara
        // bölünüyor (tarayıcıda); sayfa numaraları kitap boyunca sürüyor, istenen bölüm açılış
        // sayfası. Bölüm başlığı numarası editördeki gibi (Başlık 1 → "I.").
        $book->load('documents');
        $chapters->each->setRelation('book', $book);
        $outline = WorkOutline::for($book);
        $sections = $chapters->map(fn (Chapter $item) => [
            'chapter' => $item,
            'number' => ! $item->is_preface && $book->heading_numbering ? RichText::roman($outline->chapterNumber($item)).'.' : null,
            'html' => $item->renderedIn($outline),
        ]);

        $contents = $outline->contents;
        // Faz H3: okurun bu kitaptaki alıntı / not / fosforları metinde gösterilsin.
        $marks = $user?->readingMarksFor($book);

        return view('books.read', compact('book', 'chapters', 'chapter', 'sections', 'readingListItem', 'contents', 'marks'));
    }

    /**
     * Sayfalı okumada okur sayfa çevirdikçe okuma listesindeki konum güncelleniyor: "kaldığın
     * yerden devam et" bölüm bazında, Kitaplığım'daki "%45 okundu" sayfa bazında (sayfa /
     * toplam sayfa — kitap her cihazda aynı sayfalara bölünüyor).
     */
    public function position(Request $request, Book $book): Response
    {
        $user = $request->user();
        abort_unless($book->isReadableBy($user), 403);

        $data = $request->validate([
            'bolum' => ['required', 'integer'],
            'sayfa' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'toplam' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        abort_unless($book->chapters()->where('order', $data['bolum'])->exists(), 422);

        // Yazarın kendi taslağını önizlemesi kaydedilmez (bkz. read()).
        if ($book->status !== ContentStatus::Yayinda) {
            return response()->noContent();
        }

        $user->readingListItems()->firstOrCreate(
            ['readable_type' => Book::class, 'readable_id' => $book->id],
            ['status' => ReadingStatus::Listede],
        )->recordPosition($data['bolum'], $data['sayfa'] ?? null, $data['toplam'] ?? null);

        return response()->noContent();
    }
}
