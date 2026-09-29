<?php

namespace App\Http\Controllers;

use App\Enums\ReadingStatus;
use App\Models\Book;
use App\Models\ReadingListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReadingListController extends Controller
{
    public function okumaListem(): View
    {
        return $this->listView(ReadingStatus::Listede, 'panel.okuma-listesi.okuma-listem');
    }

    public function okuduklarim(): View
    {
        return $this->listView(ReadingStatus::Tamamlandi, 'panel.okuma-listesi.okuduklarim');
    }

    public function addBook(Book $book): RedirectResponse
    {
        $user = auth()->user();

        $user->readingListItems()->firstOrCreate([
            'readable_type' => Book::class,
            'readable_id' => $book->id,
        ], [
            'status' => ReadingStatus::Listede,
        ]);

        return back()->with('status', 'Okuma listesine eklendi.');
    }

    public function complete(ReadingListItem $readingListItem): RedirectResponse
    {
        $this->authorize('update', $readingListItem);

        $readingListItem->update([
            'status' => ReadingStatus::Tamamlandi,
            'completed_at' => now(),
            'progress' => 100,
        ]);

        return back()->with('status', 'Tamamlandı olarak işaretlendi.');
    }

    public function reopen(ReadingListItem $readingListItem): RedirectResponse
    {
        $this->authorize('update', $readingListItem);

        // Yeniden okumaya başlıyor: ilerleme baştan sayılır.
        $readingListItem->update([
            'status' => ReadingStatus::Listede,
            'completed_at' => null,
            'progress' => null,
        ]);

        return back()->with('status', 'Okuma listesine geri alındı.');
    }

    public function destroy(ReadingListItem $readingListItem): RedirectResponse
    {
        $this->authorize('delete', $readingListItem);

        $readingListItem->delete();

        return back()->with('status', 'Listeden kaldırıldı.');
    }

    private function listView(ReadingStatus $status, string $view): View
    {
        $items = auth()->user()->readingListItems()
            ->with('readable')
            ->where('status', $status)
            ->latest()
            ->get();

        return view($view, compact('items'));
    }
}
