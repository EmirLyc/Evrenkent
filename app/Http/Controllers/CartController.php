<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        $items = auth()->user()->cartItems()->with('book.author')->latest()->get();

        return view('panel.sepetim.index', compact('items'));
    }

    /**
     * Sepete sadece yayındaki kitaplar eklenebilir — zaten satın alınmış bir kitabı
     * tekrar sepete eklemeye gerek yok. Route parametresi {book}, ayrı bir Policy
     * gerekmiyor: sepet her zaman auth()->user()->cartItems() üzerinden scope'lanıyor
     * (favoriler.kitap.toggle / okuma-listesi.kitap.ekle ile aynı desen).
     *
     * Kitap tanıtım sayfasındaki "Sepete Ekle" butonu (x-add-to-cart-button) bu uca
     * fetch ile (Accept: application/json) istek atıyor — sayfa yenilenmeden header'daki
     * sepet rozeti ve "sepete eklendi" toast'ı güncellenebilsin diye. Normal form
     * gönderimi (JS kapalıysa) hâlâ çalışır, sadece redirect döner.
     */
    public function store(Request $request, Book $book): RedirectResponse|JsonResponse
    {
        abort_unless($book->status === ContentStatus::Yayinda, 404);

        $user = auth()->user();

        if ($user->hasPurchased($book)) {
            if ($request->wantsJson()) {
                return response()->json(['added' => false, 'reason' => 'already_purchased'], 200);
            }

            return back()->with('status', 'Bu kitabı zaten satın aldınız.');
        }

        // Ücretsiz eserin sepete girmesine gerek yok: doğrudan kitaplığa eklenir.
        if ($book->isFreeFor($user)) {
            $user->purchase($book);

            if ($request->wantsJson()) {
                return response()->json(['added' => false, 'reason' => 'claimed'], 200);
            }

            return back()->with('status', "\"{$book->title}\" kitaplığınıza eklendi.");
        }

        $user->cartItems()->firstOrCreate(['book_id' => $book->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'added' => true,
                'cartCount' => $user->cartItems()->count(),
                // "Sepete Git" toast linki sepet sayfasına gitmeli, kitabın kendi
                // sayfasına değil (kullanıcı zaten o sayfadaydı).
                'cartUrl' => route('panel.sepetim'),
                'book' => [
                    'title' => $book->title,
                ],
            ]);
        }

        return back()->with('status', 'Sepete eklendi.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        auth()->user()->cartItems()->where('book_id', $book->id)->delete();

        return back()->with('status', 'Sepetten çıkarıldı.');
    }

    /**
     * Gerçek ödeme entegrasyonu (Stripe/iyzico) gelecek bir faz — PurchaseController
     * ile aynı mock mantığını kullanan User::purchase() üzerinden sepetteki her
     * (hâlâ yayında olan) kitap için anında/mock tamamlanmış satın alma kaydı
     * oluşturur, ardından sepeti boşaltır.
     */
    public function checkout(): RedirectResponse
    {
        $user = auth()->user();
        $items = $user->cartItems()->with('book')->get();

        foreach ($items as $item) {
            if ($item->book && $item->book->status === ContentStatus::Yayinda) {
                $user->purchase($item->book);
            }
        }

        $user->cartItems()->delete();

        return redirect()->route('panel.satin-aldiklarim')->with('status', 'Satın alma tamamlandı.');
    }
}
