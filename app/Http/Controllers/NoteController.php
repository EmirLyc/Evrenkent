<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Models\Article;
use App\Models\Book;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NoteController extends Controller
{
    /**
     * Notlarım (Faz H4, "Notlarım sayfası 1"): solda notların olduğu eserler (arama, tür,
     * sıralama), sağda seçilen eserin notları — Tüm Notlar / Bölümlere Göre / Tarihe Göre. Her
     * not kaynak metniyle (seçilen cümle, sayfa) ve "Sayfayı Aç" bağlantısıyla.
     */
    public function notlarim(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $kind = $request->query('tur');
        $notes = $this->readingNotes($request, NoteType::Not)
            ->filter(fn (Note $note) => $this->matchesKind($note->noteable, $kind))
            ->filter(fn (Note $note) => $this->matches([$note->content, $note->quote, $note->noteable->title], $query));

        $works = $this->groupByWork($notes, $request->query('sirala', 'son'));
        $selectedKey = $works->has($request->query('eser')) ? $request->query('eser') : $works->keys()->first();
        $selected = $selectedKey ? $works[$selectedKey] : null;
        $view = in_array($request->query('gorunum'), ['bolum', 'tarih'], true) ? $request->query('gorunum') : 'tum';

        $groups = collect();
        if ($selected) {
            $items = $selected->notes->sortByDesc('created_at')->values();
            $groups = match ($view) {
                'bolum' => $this->groupByChapter($selected->work, $items),
                'tarih' => $items->groupBy(fn (Note $note) => $note->created_at->translatedFormat('j F Y')),
                default => collect(['' => $items]),
            };
        }

        return view('panel.notlar.notlarim', [
            'works' => $works,
            'selected' => $selected,
            'selectedKey' => $selectedKey,
            'groups' => $groups,
            'view' => $view,
            'query' => $query,
            'kind' => $kind,
            'sort' => $request->query('sirala', 'son'),
            'showNotes' => $request->filled('eser'),
        ]);
    }

    /**
     * Alıntılarım (Faz H4, "Alıntılarım sayfası 1"): alıntı yapılan eserlerin listesi — kaç
     * alıntı, son alıntı tarihi; arama eserde ve alıntılarda. Eser seçilince Benim Seçkim.
     */
    public function alintilarim(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $quotes = $this->readingNotes($request, NoteType::Alinti);
        $works = $this->groupByWork($quotes, $request->query('sirala', 'son'))
            ->filter(fn (object $item) => $this->matches([$item->work->title, $item->work->author?->name, ...$item->notes->pluck('content')], $query));

        return view('panel.notlar.alintilarim', [
            'works' => $works,
            'query' => $query,
            'sort' => $request->query('sirala', 'son'),
        ]);
    }

    /**
     * Benim Seçkim ("Alıntılarım sayfası 2"): bir eserden yapılan alıntılar, eserdeki sırasıyla;
     * kenar çubuğu olmayan sade okuma görünümü, salt okunur. Her alıntının yanında sayfası ve
     * kitabı o yerde açan ok.
     */
    public function seckim(Request $request, string $tur, int $id): View
    {
        $work = match ($tur) {
            'kitap' => Book::find($id),
            'makale' => Article::find($id),
            default => null,
        };
        abort_unless($work, 404);

        $quotes = $request->user()->notes()
            ->where('type', NoteType::Alinti)
            ->where('noteable_type', $work::class)
            ->where('noteable_id', $work->id)
            ->get()
            ->sortBy(fn (Note $note) => $note->anchor
                ? sprintf('0-%06d-%09d', $note->anchor['chapter'] ?? 0, $note->anchor['start'] ?? 0)
                : '1-'.$note->created_at->format('YmdHis'))
            ->values();

        // Alıntısı olmayan esere seçkim yok (başkasının taslağını adresle açmak da böylece kapalı).
        abort_if($quotes->isEmpty(), 404);

        return view('panel.notlar.seckim', ['work' => $work->loadMissing('author'), 'quotes' => $quotes]);
    }

    /** Okumada metnin yerini açan adres (işaretin çapası); konumsuz notta eserin başı. */
    public static function readingUrl(Note $note): ?string
    {
        $work = $note->noteable;
        if (! $work) {
            return null;
        }

        $anchor = $note->anchor ? '#isaret-'.$note->id : '';

        return $work instanceof Book
            ? route('kitaplar.oku', $note->anchor ? [$work, $note->anchor['chapter']] : [$work]).$anchor
            : route('makaleler.show', $work).$anchor;
    }

    /**
     * Esere bağlı not / alıntı (form ile). Okuma sayfası artık metinde seçerek ekliyor
     * (ReadingMarkController, Faz H3); defter kendi sayfasında (NotebookController, Faz H5).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:not,alinti'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'noteable_type' => ['required', 'in:App\\Models\\Book,App\\Models\\Article'],
            'noteable_id' => ['required', 'integer'],
        ]);

        $type = NoteType::from($data['type']);

        // Not, kullanıcının zaten görebildiği bir içeriğe bağlanabilir — aksi halde istek elle
        // düzenlenerek başka bir yazarın taslağına not eklenip Notlarım listesinde o taslağın
        // başlığı görülebiliyordu.
        $noteable = $data['noteable_type']::find($data['noteable_id']);

        if (! $noteable || ! $noteable->isVisibleTo($request->user())) {
            throw ValidationException::withMessages([
                'noteable_id' => 'Not eklemek istediğiniz içerik bulunamadı.',
            ]);
        }

        // Çalışma alanı kotası (2026-09-27 kararı): ücretsiz hesapta her alan (Defter/Not/
        // Alıntı) ayrı sınırlı, premiumda sınırsız. Sadece yeni ekleme engellenir — mevcut
        // kayıtlar okunur/düzenlenir/silinir, abonelik bitince de silinmez.
        if (! $request->user()->canCreateNote($type)) {
            return back()->withInput()->withErrors([
                'quota' => "Ücretsiz hesapta {$type->label()} alanında en fazla {$request->user()->noteQuota($type)} kayıt tutabilirsiniz. Sınırsız kullanım için Premium'a geçebilirsiniz.",
            ]);
        }

        auth()->user()->notes()->create([
            'type' => $type,
            'noteable_type' => $data['noteable_type'] ?? null,
            'noteable_id' => $data['noteable_id'] ?? null,
            'title' => $data['title'] ?? null,
            'content' => $data['content'],
            'location' => $data['location'] ?? null,
        ]);

        return redirect()->route($this->listRouteFor($type))->with('status', 'Kaydedildi.');
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $note->update($data);

        // Notlarım'da seçili eser / sekme kaybolmasın: geldiği sayfaya dön.
        return redirect()->back(302, [], route($this->listRouteFor($note->type)))->with('status', 'Güncellendi.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $type = $note->type;
        $note->delete();

        return redirect()->back(302, [], route($this->listRouteFor($type)))->with('status', 'Silindi.');
    }

    private function listRouteFor(NoteType $type): string
    {
        return match ($type) {
            NoteType::Defter => 'panel.defterim',
            NoteType::Not => 'panel.notlarim',
            NoteType::Alinti, NoteType::Fosfor => 'panel.alintilarim',
        };
    }

    /**
     * Okurun bir türdeki, bir esere bağlı kayıtları (eser silinmiş ya da artık görünmüyorsa —
     * başkasının taslağına dönmüş — listeye girmez).
     *
     * @return Collection<int, Note>
     */
    private function readingNotes(Request $request, NoteType $type): Collection
    {
        return $request->user()->notes()
            ->where('type', $type)
            ->whereNotNull('noteable_type')
            ->with('noteable.author')
            ->latest()
            ->get()
            ->filter(fn (Note $note) => $note->noteable && $note->noteable->isVisibleTo($request->user()))
            ->values();
    }

    /**
     * Kayıtları esere göre topla: [anahtar => {work, key, notes, count, last}], sıralı.
     *
     * @param  Collection<int, Note>  $notes
     */
    private function groupByWork(Collection $notes, ?string $sort): Collection
    {
        $works = $notes
            ->groupBy(fn (Note $note) => ($note->noteable instanceof Book ? 'kitap' : 'makale').'-'.$note->noteable_id)
            ->map(fn (Collection $items, string $key) => (object) [
                'key' => $key,
                'work' => $items->first()->noteable,
                'notes' => $items,
                'count' => $items->count(),
                'last' => $items->max('created_at'),
            ]);

        return match ($sort) {
            'ad' => $works->sortBy(fn (object $item) => Str::lower($item->work->title)),
            'cok' => $works->sortByDesc('count'),
            default => $works->sortByDesc('last'),
        };
    }

    /**
     * Bölümlere Göre: bölüm sırasıyla, bölüm adıyla; konumsuz (elle eklenmiş eski) notlar sonda.
     *
     * @param  Collection<int, Note>  $notes
     */
    private function groupByChapter(Book|Article $work, Collection $notes): Collection
    {
        $titles = $work instanceof Book ? $work->chapters()->pluck('title', 'order') : collect([1 => $work->title]);

        return $notes
            ->sortBy(fn (Note $note) => $note->anchor ? sprintf('%06d-%09d', $note->anchor['chapter'], $note->anchor['start']) : 'z')
            ->groupBy(fn (Note $note) => $note->anchor ? ($titles[$note->anchor['chapter']] ?? 'Bölüm '.$note->anchor['chapter']) : 'Konumu olmayan notlar');
    }

    private function matchesKind(Book|Article $work, ?string $kind): bool
    {
        return match ($kind) {
            'kitap' => $work instanceof Book && ! $work->isDictionary(),
            'sozluk' => $work instanceof Book && $work->isDictionary(),
            'makale' => $work instanceof Article,
            default => true,
        };
    }

    /** Türkçe büyük/küçük harf duyarsız arama (boş sorgu her şeyi geçirir). */
    private function matches(array $haystacks, string $query): bool
    {
        if ($query === '') {
            return true;
        }

        $needle = Str::lower(str_replace(['I', 'İ'], ['ı', 'i'], $query));

        return collect($haystacks)->filter()->contains(fn ($text) => Str::contains(Str::lower(str_replace(['I', 'İ'], ['ı', 'i'], (string) $text)), $needle));
    }
}
