<?php

namespace App\Http\Controllers;

use App\Enums\NoteType;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function defterim(): View
    {
        return $this->listView(NoteType::Defter, 'panel.notlar.defterim');
    }

    public function notlarim(): View
    {
        return $this->listView(NoteType::Not, 'panel.notlar.notlarim');
    }

    public function alintilarim(): View
    {
        return $this->listView(NoteType::Alinti, 'panel.notlar.alintilarim');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:defter,not,alinti'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'noteable_type' => ['nullable', 'in:App\\Models\\Book,App\\Models\\Article'],
            'noteable_id' => ['nullable', 'integer'],
        ]);

        $type = NoteType::from($data['type']);

        if ($type === NoteType::Defter) {
            $data['noteable_type'] = null;
            $data['noteable_id'] = null;
        } else {
            $request->validate([
                'noteable_type' => ['required', 'in:App\\Models\\Book,App\\Models\\Article'],
                'noteable_id' => ['required', 'integer'],
            ]);

            // Not, kullanıcının zaten görebildiği bir içeriğe bağlanabilir — aksi halde
            // istek elle düzenlenerek başka bir yazarın taslağına not eklenip Notlarım
            // listesinde o taslağın başlığı görülebiliyordu.
            $noteable = $data['noteable_type']::find($data['noteable_id']);

            if (! $noteable || ! $noteable->isVisibleTo($request->user())) {
                throw ValidationException::withMessages([
                    'noteable_id' => 'Not eklemek istediğiniz içerik bulunamadı.',
                ]);
            }
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

        return redirect()->route($this->listRouteFor($note->type))->with('status', 'Güncellendi.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $type = $note->type;
        $note->delete();

        return redirect()->route($this->listRouteFor($type))->with('status', 'Silindi.');
    }

    private function listRouteFor(NoteType $type): string
    {
        return match ($type) {
            NoteType::Defter => 'panel.defterim',
            NoteType::Not => 'panel.notlarim',
            NoteType::Alinti => 'panel.alintilarim',
        };
    }

    private function listView(NoteType $type, string $view): View
    {
        $notes = auth()->user()->notes()
            ->with('noteable')
            ->where('type', $type)
            ->latest()
            ->get();

        return view($view, compact('notes'));
    }
}
