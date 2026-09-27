@extends('layouts.admin-panel')

@section('title', 'Revizyon / Reddet')

@section('content')
    {{-- 2026-09-27, karar A: "Revizyon iste" ile "Kalıcı olarak reddet" ayrı (ContentReviewer). --}}
    <div class="max-w-lg mx-auto">
        <h1 class="font-serif text-xl font-semibold text-slate-900 mb-1">Revizyon iste ya da reddet</h1>
        <p class="text-sm text-slate-500 mb-5 break-words">"{{ $title }}" için kararınızı ve gerekçenizi yazın. Not, içerik sahibine bildirim olarak gider.</p>

        <form method="POST" action="{{ $submitRoute }}" class="card p-6 space-y-5" x-data="{ decision: @js(old('decision', 'revizyon')) }">
            <fieldset class="space-y-2">
                <legend class="block text-sm font-medium text-slate-700 mb-1">Karar</legend>
                <label class="flex items-start gap-3 rounded-lg border p-3 cursor-pointer" :class="decision === 'revizyon' ? 'border-orange-300 bg-orange-50' : 'border-slate-200'">
                    <input type="radio" name="decision" value="revizyon" x-model="decision" class="mt-0.5 text-orange-600 focus:ring-orange-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Revizyon iste</span>
                        <span class="block text-xs text-slate-500 mt-0.5">Sahibine geri döner; düzeltip tekrar gönderebilir.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-lg border p-3 cursor-pointer" :class="decision === 'ret' ? 'border-red-300 bg-red-50' : 'border-slate-200'">
                    <input type="radio" name="decision" value="ret" x-model="decision" class="mt-0.5 text-red-600 focus:ring-red-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Kalıcı olarak reddet</span>
                        <span class="block text-xs text-slate-500 mt-0.5">İçerik kapanır; sahibi görebilir ve silebilir ama düzenleyip tekrar gönderemez. Reddedilenler sayfasından geri açabilirsiniz.</span>
                    </span>
                </label>
                @error('decision') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            @if (! empty($rejectNote))
                <p x-show="decision === 'ret'" x-cloak class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">{{ $rejectNote }}</p>
            @endif

            <div>
                <label for="note" class="block text-sm font-medium text-slate-700 mb-1" x-text="decision === 'ret' ? 'Ret gerekçesi' : 'Revizyon notu'">Revizyon notu</label>
                <textarea id="note" name="note" rows="4" required class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('note') }}</textarea>
                @error('note') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-4 pt-1">
                <button type="submit" class="btn text-white shadow-sm" :class="decision === 'ret' ? 'bg-red-600 hover:bg-red-700 shadow-red-600/20' : 'bg-orange-600 hover:bg-orange-700 shadow-orange-600/20'" x-text="decision === 'ret' ? 'Kalıcı Olarak Reddet' : 'Revizyon İste'">Revizyon İste</button>
                <a href="{{ $backRoute }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">Vazgeç</a>
            </div>
            {{-- Sonda: gizli alan ilk çocuk olursa space-y kartın tepesine boşluk ekliyordu. --}}
            @csrf
        </form>
    </div>
@endsection
