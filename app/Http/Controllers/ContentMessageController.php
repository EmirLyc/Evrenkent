<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Book;
use App\Notifications\ContentMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Taslaklarım kartlarındaki "Sohbet" / "Mesajlar" (Faz G1, "Yazarın Gözünden" 1.1): yazar ile
 * eseri inceleyenler (Süper Admin; makalede sayının dergi editörü) arasında esere bağlı
 * yazışma. Sayfada inceleme kararları (gönderildi, revizyon notu, ret gerekçesi…) de mesajlarla
 * aynı zaman çizelgesinde görünür — yazar "neden geri döndü"yü burada okur.
 */
class ContentMessageController extends Controller
{
    public function showBook(Book $book): View
    {
        return $this->show($book);
    }

    public function showArticle(Article $article): View
    {
        return $this->show($article->load('magazineIssue.magazine'));
    }

    public function storeBook(Request $request, Book $book): RedirectResponse
    {
        return $this->store($request, $book, route('panel.mesajlar.kitap', $book));
    }

    public function storeArticle(Request $request, Article $article): RedirectResponse
    {
        return $this->store($request, $article, route('panel.mesajlar.makale', $article));
    }

    private function show(Book|Article $item): View
    {
        $this->authorize('converse', $item);

        $item->load(['messages.user', 'reviews.reviewer']);

        // Mesajlar ve inceleme kayıtları tek zaman çizelgesinde.
        $timeline = $item->messages->map(fn ($message) => ['type' => 'message', 'at' => $message->created_at, 'model' => $message])
            ->concat($item->reviews->map(fn ($review) => ['type' => 'review', 'at' => $review->created_at, 'model' => $review]))
            ->sortBy(fn ($entry) => [$entry['at']->timestamp, $entry['type'] === 'review' ? 0 : 1])
            ->values();

        return view('panel.mesajlar', [
            'item' => $item,
            'timeline' => $timeline,
            'postUrl' => $item instanceof Book ? route('panel.mesajlar.kitap.gonder', $item) : route('panel.mesajlar.makale.gonder', $item),
            'backUrl' => $this->backUrl($item),
        ]);
    }

    private function store(Request $request, Book|Article $item, string $threadUrl): RedirectResponse
    {
        $this->authorize('converse', $item);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Boş mesaj gönderilemez.']);

        $message = $item->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        $item->conversationParticipants()
            ->reject(fn ($user) => $user->is($request->user()))
            ->each(fn ($user) => $user->notify(new ContentMessageReceived($message, $threadUrl)));

        return redirect()->to($threadUrl.'#son')->with('status', 'Mesaj gönderildi.');
    }

    /** Geri bağlantısı: yazara Taslaklarım, Süper Admin'e Onaylar, editöre Makale Havuzu. */
    private function backUrl(Book|Article $item): string
    {
        $user = auth()->user();

        return match (true) {
            $user->id === $item->author_id => route('panel.yayinlarim.taslaklarim'),
            $user->hasRole('super_admin') => route('panel.adminpanel.onaylar.index', ['tur' => $item instanceof Book ? 'kitaplar' : 'makaleler']),
            default => route('panel.dergi.makale-havuzu'),
        };
    }
}
