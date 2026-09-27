{{--
    Çalışma alanı kotası göstergesi — ücretsiz hesapta "3 / 10 kayıt", kota dolunca Premium'a
    yönlendiren uyarı; kota aşımıyla reddedilen bir kaydın hata mesajını da gösterir.
    Premium üyede hiçbir şey göstermez (sınırsız).
--}}
@props(['type'])

@php
    $quota = auth()->user()->noteQuota($type);
    $used = $quota === null ? null : auth()->user()->notes()->where('type', $type)->count();
@endphp

@if ($errors->has('quota'))
    <div class="flex items-start gap-2 rounded-lg bg-amber-50 ring-1 ring-inset ring-amber-200 px-3 py-2.5 text-sm text-amber-800">
        <x-heroicon-o-exclamation-triangle class="w-4 h-4 mt-0.5 shrink-0" />
        <span>{{ $errors->first('quota') }} <a href="{{ route('abonelik') }}" class="font-medium underline">Premium'a göz at</a></span>
    </div>
@elseif ($quota !== null)
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs {{ $used >= $quota ? 'text-amber-700' : 'text-slate-400' }}">
        <span>{{ $type->label() }}: {{ $used }} / {{ $quota }} kayıt (ücretsiz hesap)</span>
        @if ($used >= $quota)
            <a href="{{ route('abonelik') }}" class="font-medium underline">Sınır doldu — Premium ile sınırsız</a>
        @endif
    </div>
@endif
