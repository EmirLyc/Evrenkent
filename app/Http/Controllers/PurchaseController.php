<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(): View
    {
        $purchases = auth()->user()->purchases()
            ->with('book.author')
            ->latest('purchased_at')
            ->get();

        return view('panel.satin-aldiklarim.index', compact('purchases'));
    }

    /**
     * Gerçek ödeme entegrasyonu (Stripe/iyzico) gelecek bir faz —
     * User::purchase() ödeme sorulmadan anında/mock tamamlanmış bir satın alma kaydı
     * oluşturur (aynı mantık sepet ödemesinde de kullanılıyor, bkz. CartController).
     */
    public function store(Book $book): RedirectResponse
    {
        abort_unless($book->status === ContentStatus::Yayinda, 404);

        $purchase = auth()->user()->purchase($book);

        // Ücretsiz eser: 0 TL'lik kayıt — fiyatı sonradan artsa da kullanıcının kitaplığında kalır.
        return back()->with('status', (float) $purchase->amount <= 0
            ? "\"{$book->title}\" kitaplığınıza eklendi. Fiyatı ileride değişse de sizin kalacak."
            : 'Satın alma tamamlandı.');
    }
}
