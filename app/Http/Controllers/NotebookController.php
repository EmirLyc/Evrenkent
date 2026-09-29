<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Models\Note;
use App\Support\NotebookHtml;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Defterim (Faz H5, "Okurun Gözünden" — Defterim 1; "Bazı Prensipler": "Defterim: kitaplardan
 * bağımsız olarak ben ne düşünüyorum?"). Solda defterler (Bugün / Son 7 Gün / aylar), sağda
 * zengin metin editörü; otomatik kayıt. Defter notes tablosunda type = defter.
 *
 * Sınırlar (ücretsiz hesap): 1 defter, defter başına ~1.000 kelime — ikisi de Süper Admin'in
 * Premium Sistemi ayarı; premiumda yok. Sınır dolunca sadece yeni defter / fazla metin engellenir,
 * var olanlar okunur ve düzenlenir.
 */
class NotebookController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $latest = $this->notebooks($request)->first();

        return $latest
            ? redirect()->route('panel.defterim.goster', array_filter([$latest, 'sirala' => $request->query('sirala')]))
            : view('panel.defterim.show', $this->shared($request) + ['notebook' => null]);
    }

    public function show(Request $request, Note $note): View
    {
        $this->authorize('update', $note);
        abort_unless($note->type === NoteType::Defter, 404);

        return view('panel.defterim.show', $this->shared($request) + ['notebook' => $note]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->canCreateNote(NoteType::Defter)) {
            return back()->with('quota', "Ücretsiz hesapta en fazla {$user->noteQuota(NoteType::Defter)} defter tutabilirsiniz. Sınırsız defter için Premium'a geçebilirsiniz.");
        }

        $note = $user->notes()->create(['type' => NoteType::Defter, 'title' => 'Yeni Defter', 'content' => '']);

        return redirect()->route('panel.defterim.goster', $note);
    }

    /** Otomatik kayıt (editör JSON ile). */
    public function update(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        abort_unless($note->type === NoteType::Defter, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'content' => ['nullable', 'string', 'max:500000'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
            'info' => ['nullable', 'string', 'max:1000'],
        ], ['title.required' => 'Deftere bir ad verin.']);

        $content = NotebookHtml::clean($data['content'] ?? '');
        $words = NotebookHtml::wordCount($content);
        $limit = $request->user()->notebookWordLimit();

        // Sınırı aşan metin kaydedilmez; sınırın altına inene kadar önceki kayıt geçerli.
        if ($limit !== null && $words > $limit && $words > NotebookHtml::wordCount($note->content)) {
            return response()->json([
                'message' => 'Ücretsiz hesapta bir defter en fazla '.number_format($limit, 0, ',', '.').' kelime olabilir. Sınırsız yazmak için Premium\'a geçebilirsiniz.',
                'premium' => route('abonelik'),
                'words' => $words,
            ], 422);
        }

        $note->update([
            'title' => trim($data['title']),
            'subtitle' => filled($data['subtitle'] ?? null) ? trim($data['subtitle']) : null,
            'content' => $content,
            'tags' => collect($data['tags'] ?? [])->map(fn ($tag) => trim(ltrim($tag, '#')))->filter()->unique()->values()->all() ?: null,
            'info' => filled($data['info'] ?? null) ? trim($data['info']) : null,
        ]);

        return response()->json(['saved_at' => $note->updated_at->format('H:i'), 'words' => $words]);
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);
        abort_unless($note->type === NoteType::Defter, 404);
        $note->delete();

        return redirect()->route('panel.defterim')->with('status', '"'.$note->title.'" silindi.');
    }

    /** Araç çubuğundaki görsel: okurun kendi yüklediği resim (herkese açık değil — adres tahmin edilemez). */
    public function uploadImage(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120']], [
            'image.image' => 'Sadece görsel yükleyebilirsiniz.',
            'image.max' => 'Görsel en fazla 5 MB olabilir.',
        ]);

        $disk = Storage::disk(config('filesystems.covers_disk'));
        $path = $request->file('image')->storeAs('defterler/'.$request->user()->id, Str::random(40).'.'.$request->file('image')->extension(), config('filesystems.covers_disk'));

        return response()->json(['url' => $disk->url($path)]);
    }

    /** "Alıntı ekle" / "Not ekle": okurun alıntıları ya da notları (arama ile). */
    public function sources(Request $request): JsonResponse
    {
        $type = $request->query('tur') === 'not' ? NoteType::Not : NoteType::Alinti;
        $query = Str::lower(trim((string) $request->query('q', '')));

        $items = $request->user()->notes()
            ->where('type', $type)
            ->whereNotNull('noteable_type')
            ->with('noteable')
            ->latest()
            ->limit(200)
            ->get()
            ->filter(fn (Note $note) => $note->noteable)
            ->filter(fn (Note $note) => $query === '' || Str::contains(Str::lower($note->content.' '.$note->quote.' '.$note->noteable->title), $query))
            ->take(50)
            ->map(fn (Note $note) => [
                'id' => $note->id,
                'quote' => $note->quote ?? ($type === NoteType::Alinti ? $note->content : null),
                'note' => $type === NoteType::Not ? $note->content : null,
                'work' => $note->noteable->title,
                'page' => $note->page,
                'url' => NoteController::readingUrl($note),
            ])
            ->values();

        return response()->json($items);
    }

    /** Sol sütun: defterler, sırayla ve tarih gruplarıyla. */
    private function shared(Request $request): array
    {
        $user = $request->user();
        $sort = in_array($request->query('sirala'), ['olusturma', 'ad'], true) ? $request->query('sirala') : 'son';
        $notebooks = $this->notebooks($request, $sort);

        return [
            'groups' => $sort === 'ad' ? collect(['Tümü' => $notebooks]) : $this->groupByDate($notebooks, $sort === 'olusturma' ? 'created_at' : 'updated_at'),
            'sort' => $sort,
            'quota' => $user->noteQuota(NoteType::Defter),
            'count' => $notebooks->count(),
            'wordLimit' => $user->notebookWordLimit(),
        ];
    }

    private function notebooks(Request $request, string $sort = 'son'): Collection
    {
        $query = $request->user()->notes()->where('type', NoteType::Defter);

        return match ($sort) {
            'ad' => $query->orderBy('title')->get(),
            'olusturma' => $query->latest()->get(),
            default => $query->latest('updated_at')->latest('id')->get(),
        };
    }

    /** Bugün / Son 7 Gün / Ağustos / Temmuz 2025… (mockup'taki gruplar). */
    private function groupByDate(Collection $notebooks, string $column): Collection
    {
        return $notebooks->groupBy(function (Note $note) use ($column) {
            $date = $note->{$column};

            return match (true) {
                $date->isToday() => 'Bugün',
                $date->greaterThanOrEqualTo(now()->subDays(7)->startOfDay()) => 'Son 7 Gün',
                $date->year === now()->year => $date->translatedFormat('F'),
                default => $date->translatedFormat('F Y'),
            };
        });
    }
}
