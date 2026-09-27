@extends('layouts.panel')

@section('title', 'Sepetim')

@section('content')
    <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">Sepetim</h1>

    @if ($items->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-shopping-cart class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Sepetiniz boş.
            <div class="mt-3">
                <a href="{{ route('kitaplar.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Kitaplara göz atın →</a>
            </div>
        </div>
    @else
        {{-- Book::priceFor — ödemede (User::purchase) alınacak tutarla birebir aynı. --}}
        @php $total = $items->sum(fn ($item) => (float) $item->book->priceFor(auth()->user())); @endphp

        <div class="card divide-y divide-slate-100">
            @foreach ($items as $item)
                <div class="flex items-center justify-between gap-4 px-5 py-4">
                    <a href="{{ route('kitaplar.show', $item->book) }}" class="flex items-start gap-3 min-w-0">
                        <x-heroicon-o-book-open class="w-5 h-5 text-slate-300 mt-0.5 shrink-0" />
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900 truncate">{{ $item->book->title }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $item->book->author->name }}</div>
                        </div>
                    </a>
                    <div class="flex items-center gap-4 shrink-0">
                        <div class="text-sm text-slate-700 font-medium">
                            {{ number_format($item->book->priceFor(auth()->user()), 2, ',', '.') }} TL
                        </div>
                        <form method="POST" action="{{ route('panel.sepet.kitap.sil', $item->book) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-outline btn-sm">Kaldır</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card p-5 mt-5 flex items-center justify-between">
            <div>
                <div class="text-xs text-slate-400">Toplam</div>
                <div class="text-xl font-serif font-semibold text-slate-900">{{ number_format($total, 2, ',', '.') }} TL</div>
            </div>
            <form method="POST" action="{{ route('panel.sepet.checkout') }}">
                @csrf
                <button type="submit" class="btn-brand">
                    <x-heroicon-o-credit-card class="w-4 h-4" /> Ödemeyi Tamamla
                </button>
            </form>
        </div>
    @endif
@endsection
