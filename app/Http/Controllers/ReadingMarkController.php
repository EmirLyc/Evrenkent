<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Models\Article;
use App\Models\Book;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Okurken metinde seçilip eklenen işaretler (Faz H3, "Okurun Gözünden" — Okuma moduna dair):
 * Alıntıla (Alıntılarım'a gider), Not Al (seçilen cümle + okurun notu) ve Fosforla (sadece
 * metinde). Okuma sayfası (paged-reader.js) JSON ile çağırıyor.
 *
 * Kurallar: sadece okuyabildiği esere; alıntı ve notta ücretsiz hesabın kotası (fosforda yok);
 * "okur aynı kelimeyi iki defa alıntılayamaz" — aynı bölümde çakışan ikinci alıntı reddedilir.
 */
class ReadingMarkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn (NoteType $type) => $type->value, NoteType::readingMarks()))],
            'noteable_type' => ['required', Rule::in([Book::class, Article::class])],
            'noteable_id' => ['required', 'integer'],
            'quote' => ['required', 'string', 'max:5000'],
            'content' => ['nullable', 'required_if:type,not', 'string', 'max:5000'],
            'anchor' => ['required', 'array'],
            'anchor.chapter' => ['required', 'integer', 'min:1'],
            'anchor.start' => ['required', 'integer', 'min:0'],
            'anchor.end' => ['required', 'integer', 'gt:anchor.start'],
            'anchor.prefix' => ['nullable', 'string', 'max:64'],
            'anchor.suffix' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'string', 'max:16'],
            'location' => ['nullable', 'string', 'max:255'],
        ], [
            'content.required_if' => 'Notunuzu yazın.',
        ]);

        $user = $request->user();
        $type = NoteType::from($data['type']);
        $work = $data['noteable_type']::find($data['noteable_id']);
        $readable = $work instanceof Book ? $work->isReadableBy($user) : $work?->isVisibleTo($user);

        abort_unless($readable, 403);

        if (! $user->canCreateNote($type)) {
            return response()->json([
                'message' => "Ücretsiz hesapta {$type->label()} alanında en fazla {$user->noteQuota($type)} kayıt tutabilirsiniz. Sınırsız kullanım için Premium'a geçebilirsiniz.",
                'premium' => route('abonelik'),
            ], 422);
        }

        $anchor = [
            'chapter' => (int) $data['anchor']['chapter'],
            'start' => (int) $data['anchor']['start'],
            'end' => (int) $data['anchor']['end'],
            'prefix' => (string) ($data['anchor']['prefix'] ?? ''),
            'suffix' => (string) ($data['anchor']['suffix'] ?? ''),
        ];

        if ($type === NoteType::Alinti && $this->overlapsQuote($user->notes()->getQuery(), $work, $anchor)) {
            return response()->json(['message' => 'Bu kısmı zaten alıntıladınız.'], 422);
        }

        $note = $user->notes()->create([
            'type' => $type,
            'noteable_type' => $work::class,
            'noteable_id' => $work->id,
            'quote' => $data['quote'],
            'content' => $type === NoteType::Not ? $data['content'] : $data['quote'],
            'anchor' => $anchor,
            'page' => $data['page'] ?? null,
            'location' => $data['location'] ?? null,
        ]);

        return response()->json($note->toReadingMark(), 201);
    }

    /** Notun metni ("Düzenle"). */
    public function update(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        abort_unless($note->type === NoteType::Not, 422);

        $data = $request->validate(['content' => ['required', 'string', 'max:5000']], ['content.required' => 'Notunuzu yazın.']);
        $note->update($data);

        return response()->json($note->toReadingMark());
    }

    public function destroy(Note $note): Response
    {
        $this->authorize('delete', $note);
        $note->delete();

        return response()->noContent();
    }

    /** Aynı eserin aynı bölümünde, okurun başka bir alıntısıyla kesişiyor mu. */
    private function overlapsQuote($notes, Book|Article $work, array $anchor): bool
    {
        return $notes->where('type', NoteType::Alinti)
            ->where('noteable_type', $work::class)
            ->where('noteable_id', $work->id)
            ->whereNotNull('anchor')
            ->get()
            ->contains(fn (Note $quote) => ($quote->anchor['chapter'] ?? null) === $anchor['chapter']
                && $quote->anchor['start'] < $anchor['end']
                && $anchor['start'] < $quote->anchor['end']);
    }
}
