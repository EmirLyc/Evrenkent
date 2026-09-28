@extends('layouts.panel')

@section('title', 'Çöp Kutusu')

@section('content')
    <a href="{{ route('panel.yayinlarim.taslaklarim') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 mb-4">
        <x-heroicon-o-arrow-left class="w-4 h-4" /> Taslaklarım
    </a>

    <div class="mb-6">
        <h1 class="font-serif text-3xl font-semibold text-slate-900">Çöp Kutusu</h1>
        {{-- "Yazarın Gözünden": silme iki kademeli — yanlışlıkla silinen eser kaybolmasın. --}}
        <p class="text-sm text-slate-600 mt-1">Sildiğiniz taslaklar burada saklanır. Geri alabilir ya da kalıcı olarak silebilirsiniz.</p>
    </div>


    @if ($items->isEmpty())
        <div class="card p-12 text-center text-slate-500">
            <x-heroicon-o-trash class="w-8 h-8 mx-auto mb-3 text-slate-300" />
            Çöp kutusu boş.
        </div>
    @else
        <div class="card divide-y divide-slate-100">
            @foreach ($items as $item)
                @php $isBook = $item instanceof \App\Models\Book; @endphp
                <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-4 flex-wrap">
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wider {{ $isBook ? 'text-brand-700' : 'text-sky-700' }}">{{ $isBook ? 'Kitap' : 'Dergi Yazısı' }}</div>
                        <div class="font-serif text-lg font-semibold text-slate-900 break-words">{{ $item->title }}</div>
                        <div class="text-sm text-slate-500">Silinme: {{ $item->deleted_at->translatedFormat('j F Y H:i') }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <form method="POST" action="{{ $isBook ? route('panel.yayinlarim.kitap.geri-al', $item) : route('panel.yayinlarim.makale.geri-al', $item) }}">
                            @csrf
                            <button type="submit" class="btn-outline btn-sm"><x-heroicon-o-arrow-uturn-left class="w-4 h-4" /> Geri Al</button>
                        </form>
                        <form method="POST" action="{{ $isBook ? route('panel.yayinlarim.kitap.kalici-sil', $item) : route('panel.yayinlarim.makale.kalici-sil', $item) }}" data-turbo-confirm="&quot;{{ $item->title }}&quot; ve belgeleri kalıcı olarak silinecek. Bu işlem geri alınamaz.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-sm btn text-red-600 hover:bg-red-50"><x-heroicon-o-trash class="w-4 h-4" /> Kalıcı Sil</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
