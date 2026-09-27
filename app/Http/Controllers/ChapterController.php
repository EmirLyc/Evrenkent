<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Chapter;
use App\Rules\RichTextContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChapterController extends Controller
{
    public function index(Book $book): View
    {
        $this->authorize('update', $book);

        $chapters = $book->chapters;

        return view('panel.yayinlarim.kitap-bolumler', compact('book', 'chapters'));
    }

    public function create(Book $book): View
    {
        $this->authorize('update', $book);

        $nextOrder = $book->chapters()->max('order') + 1;

        return view('panel.yayinlarim.bolum-form', [
            'book' => $book,
            'chapter' => null,
            'nextOrder' => $nextOrder,
        ]);
    }

    public function store(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', new RichTextContent],
            'order' => [
                'required', 'integer', 'min:1',
                Rule::unique('chapters')->where('book_id', $book->id),
            ],
        ]);

        $book->chapters()->create($data);

        return redirect()->route('panel.yayinlarim.kitap.bolumler', $book)->with('status', 'Bölüm eklendi.');
    }

    public function edit(Book $book, Chapter $chapter): View
    {
        $this->authorize('update', $book);
        abort_unless($chapter->book_id === $book->id, 404);

        return view('panel.yayinlarim.bolum-form', [
            'book' => $book,
            'chapter' => $chapter,
            'nextOrder' => $chapter->order,
        ]);
    }

    public function update(Request $request, Book $book, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $book);
        abort_unless($chapter->book_id === $book->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', new RichTextContent],
            'order' => [
                'required', 'integer', 'min:1',
                Rule::unique('chapters')->where('book_id', $book->id)->ignore($chapter->id),
            ],
        ]);

        $chapter->update($data);

        return redirect()->route('panel.yayinlarim.kitap.bolumler', $book)->with('status', 'Bölüm güncellendi.');
    }

    public function destroy(Book $book, Chapter $chapter): RedirectResponse
    {
        $this->authorize('update', $book);
        abort_unless($chapter->book_id === $book->id, 404);

        $chapter->delete();

        return redirect()->route('panel.yayinlarim.kitap.bolumler', $book)->with('status', 'Bölüm silindi.');
    }
}
