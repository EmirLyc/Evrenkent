@extends('layouts.panel')

@section('title', 'Satın Aldıklarım')

@section('content')
    <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">Satın Aldıklarım</h1>

    @if ($purchases->isEmpty())
        <div class="card p-12 text-center text-slate-400">
            <x-heroicon-o-shopping-bag class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Henüz bir satın alımınız yok.
        </div>
    @else
        <div class="card divide-y divide-slate-100">
            @foreach ($purchases as $purchase)
                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    @if ($purchase->book)
                        <a href="{{ $purchase->book->url() }}" class="group flex items-start gap-3 min-w-0">
                            <x-heroicon-o-book-open class="w-5 h-5 text-slate-300 mt-0.5 shrink-0" />
                            <div class="min-w-0">
                                <div class="font-medium text-slate-900 truncate group-hover:underline">{{ $purchase->book->title }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    {{ $purchase->book->author?->name }} · {{ $purchase->purchased_at->format('d.m.Y') }}
                                </div>
                            </div>
                        </a>
                    @else
                        <div class="flex items-start gap-3 min-w-0">
                            <x-heroicon-o-book-open class="w-5 h-5 text-slate-300 mt-0.5 shrink-0" />
                            <div class="min-w-0">
                                <div class="font-medium text-slate-900 truncate">Silinmiş kitap</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ $purchase->purchased_at->format('d.m.Y') }}</div>
                            </div>
                        </div>
                    @endif
                    <div class="text-sm text-slate-700 font-medium shrink-0">
                        {{ (float) $purchase->amount <= 0 ? 'Ücretsiz' : number_format($purchase->amount, 2, ',', '.').' TL' }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
