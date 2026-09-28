<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\DictionaryEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Sözlükler (Faz G3, "Sözlüğe Dair" — köprü sistemi): kitaplarda geçen kavramlar sözlüğe bağlı,
 * okur tıklayınca maddeye gider.
 *  - index: /sozlukler — yayındaki sözlükler ve maddelerde arama,
 *  - entry: /sozlukler/{sözlük}/{madde} — madde sayfası,
 *  - search: editördeki "Sözlüğe Bağla" paneli (JSON).
 */
class DictionaryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $user = $request->user();

        // ?sozluk=slug: tek bir sözlüğün maddeleri (tanıtım sayfasındaki "Tüm maddeler").
        $dictionary = $request->filled('sozluk')
            ? Book::dictionaries()->where('slug', $request->query('sozluk'))->first()
            : null;
        if ($dictionary && ! ($dictionary->status === ContentStatus::Yayinda || $user?->id === $dictionary->author_id)) {
            $dictionary = null;
        }

        $entries = null;
        if ($q !== '' || $dictionary) {
            $query = DictionaryEntry::availableTo($user)->when($dictionary, fn (Builder $query) => $query->where('book_id', $dictionary->id));
            $entries = ($q !== '' ? $this->matching($query, $q) : $query->orderBy('term_search'))
                ->with('book.author')
                ->paginate($dictionary ? 60 : 20, pageName: 'madde')
                ->withQueryString();
        }

        $dictionaries = Book::dictionaries()->published()
            ->with('author')
            ->withCount('entries')
            ->latest('published_at')
            ->paginate(18)
            ->withQueryString();

        return view('dictionaries.index', compact('q', 'dictionary', 'entries', 'dictionaries'));
    }

    /**
     * Madde sayfası. Sözlüğü okuyabilen (ücretsiz ya da satın almış okur, yazarı) maddenin
     * tamamını görür; diğerleri kısa bir önizleme ve sözlüğün tanıtım sayfasına bağlantı —
     * köprüden gelen okur kavramın ne olduğunu görsün, sözlüğün ücretli içeriği açılmasın.
     */
    public function entry(Book $book, DictionaryEntry $entry): View
    {
        $user = auth()->user();
        $entry->setRelation('book', $book);

        abort_unless($entry->isAvailableTo($user), 404);

        $book->loadMissing('author');
        $neighbours = $book->entries()->select(['id', 'book_id', 'term', 'slug', 'position']);

        return view('dictionaries.entry', [
            'book' => $book,
            'entry' => $entry,
            'readable' => $book->isReadableBy($user),
            'previous' => (clone $neighbours)->where('position', '<', $entry->position)->reorder('position', 'desc')->first(),
            'next' => (clone $neighbours)->where('position', '>', $entry->position)->first(),
        ]);
    }

    /** "Sözlüğe Bağla": yayındaki sözlüklerin ve kullanıcının kendi sözlüklerinin maddeleri. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['entries' => []]);
        }

        $user = $request->user();
        $entries = $this->matching(DictionaryEntry::availableTo($user), $q)->with('book.author')->limit(20)->get();

        // Türkçe ekler: "egemenliğin" bulunamazsa kökün başıyla ("egemenli") bir daha dene.
        if ($entries->isEmpty() && ! str_contains($q, ' ') && mb_strlen($q) > 5) {
            $stem = mb_substr($q, 0, max(4, mb_strlen($q) - 3));
            $entries = $this->matching(DictionaryEntry::availableTo($user), $stem)->with('book.author')->limit(20)->get();
        }

        return response()->json([
            'entries' => $entries->map(fn (DictionaryEntry $entry) => [
                'id' => $entry->id,
                'term' => $entry->term,
                'dictionary' => $entry->book->title,
                'author' => $entry->book->author?->name,
                'published' => $entry->book->status === ContentStatus::Yayinda,
                'excerpt' => Str::limit((string) $entry->excerpt, 140),
                'url' => $entry->url(),
            ])->values(),
        ]);
    }

    /**
     * Kavram adında arama; tam eşleşme önce, sonra adın başı, sonra içinde geçen.
     *
     * @param  Builder<DictionaryEntry>  $query
     * @return Builder<DictionaryEntry>
     */
    private function matching(Builder $query, string $q): Builder
    {
        $needle = DictionaryEntry::searchable($q);
        $escaped = addcslashes($needle, '%_\\');

        return $query->where('term_search', 'like', '%'.$escaped.'%')
            ->orderByRaw('CASE WHEN term_search = ? THEN 0 WHEN term_search LIKE ? THEN 1 ELSE 2 END', [$needle, $escaped.'%'])
            ->orderBy('term_search');
    }
}
