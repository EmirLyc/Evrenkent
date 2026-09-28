@php
    $isBook = $item instanceof \App\Models\Book;
    $me = auth()->user();
    // Süper Admin kendi panel düzeninde görür; yazar ve dergi editörü yazar panelinde.
    $layout = $me->hasRole('super_admin') && $me->id !== $item->author_id ? 'layouts.admin-panel' : 'layouts.panel';
@endphp

@extends($layout)

@section('title', 'Mesajlar — '.$item->title)

@section('content')
    <div class="max-w-3xl mx-auto">
        <a href="{{ $backUrl }}" class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 mb-4">
            <x-heroicon-o-arrow-left class="w-4 h-4" /> Geri
        </a>

        <div class="flex items-start justify-between gap-3 flex-wrap mb-5">
            <div class="min-w-0">
                <div class="text-[11px] font-semibold uppercase tracking-wider {{ $item->kindColor() }}">{{ $item->kindLabel() }} · Mesajlar</div>
                <h1 class="font-serif text-2xl font-semibold text-slate-900 break-words">{{ $item->title }}</h1>
                <p class="text-sm text-slate-500">
                    Yazar: {{ $item->author->name }}
                    @if (! $isBook && $item->magazineIssue)
                        · {{ $item->magazineIssue->magazine?->name }} / {{ $item->magazineIssue->title }}
                    @endif
                </p>
            </div>
            <x-author-status-badge :item="$item" />
        </div>


        <div class="card p-4 sm:p-5">
            @if ($timeline->isEmpty())
                <p class="text-sm text-slate-500 text-center py-6">Henüz mesaj yok. Eserle ilgili sorularınızı buradan iletebilirsiniz.</p>
            @else
                <ol class="space-y-4">
                    @foreach ($timeline as $entry)
                        @if ($entry['type'] === 'review')
                            @php $step = $entry['model']->step(); @endphp
                            {{-- İnceleme kaydı (gönderildi, düzeltme notu, ret gerekçesi…) --}}
                            <li class="flex justify-center">
                                <div class="max-w-md w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-center">
                                    <div class="text-xs font-medium text-slate-700 inline-flex items-center gap-1.5">
                                        @svg('heroicon-o-'.$step['icon'], 'w-3.5 h-3.5')
                                        {{ $step['label'] }}
                                        <span class="font-normal text-slate-400">· {{ $entry['model']->reviewer?->name ?? 'Sistem' }} · {{ $entry['at']->translatedFormat('j F Y H:i') }}</span>
                                    </div>
                                    @if ($entry['model']->note && in_array($entry['model']->action, ['revizyon_istendi', 'reddedildi'], true))
                                        <p class="text-sm text-slate-700 mt-1 whitespace-pre-line text-left">{{ $entry['model']->note }}</p>
                                    @endif
                                </div>
                            </li>
                        @else
                            @php $msg = $entry['model']; $mine = $msg->user_id === $me->id; @endphp
                            <li class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[85%] sm:max-w-[75%]">
                                    <div class="text-xs text-slate-500 mb-1 {{ $mine ? 'text-right' : '' }}">
                                        {{ $mine ? 'Siz' : $msg->user->name }}
                                        @unless ($mine)
                                            <span class="text-slate-400">· {{ $msg->user->id === $item->author_id ? 'Yazar' : ($msg->user->hasRole('super_admin') ? 'Süper Admin' : 'Dergi Editörü') }}</span>
                                        @endunless
                                        · {{ $msg->created_at->translatedFormat('j F H:i') }}
                                    </div>
                                    <div class="rounded-lg px-3.5 py-2.5 text-sm whitespace-pre-line break-words {{ $mine ? 'bg-navy text-white rounded-br-sm' : 'bg-white border border-slate-200 text-slate-800 rounded-bl-sm' }}">{{ $msg->body }}</div>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ol>
            @endif

            <form id="son" method="POST" action="{{ $postUrl }}" class="mt-5 border-t border-slate-100 pt-4 scroll-mt-24">
                <label for="body" class="sr-only">Mesajınız</label>
                <textarea id="body" name="body" rows="3" required maxlength="5000" placeholder="Mesajınızı yazın…" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('body') }}</textarea>
                @error('body') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                <div class="flex items-center justify-between gap-3 mt-2 flex-wrap">
                    <p class="text-xs text-slate-400">
                        {{ $isBook ? 'Yazar ve Süper Admin görür.' : 'Yazar, dergi editörü ve Süper Admin görür.' }}
                    </p>
                    <button type="submit" class="btn-dark btn-sm"><x-heroicon-o-paper-airplane class="w-4 h-4" /> Gönder</button>
                </div>
                @csrf
            </form>
        </div>
    </div>
@endsection
