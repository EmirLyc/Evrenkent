@extends('layouts.admin-panel')

@section('title', 'Kullanıcılar')

@section('content')
    <div class="flex items-center justify-between gap-3 flex-wrap mb-6">
        <div>
            <h1 class="font-serif text-xl font-semibold text-slate-900">Kullanıcılar</h1>
            <p class="text-sm text-slate-500 mt-1">{{ $users->total() }} kullanıcı.</p>
        </div>
        <a href="{{ route('panel.adminpanel.kullanicilar.yeni') }}" class="btn-brand btn-sm">
            <x-heroicon-o-plus class="w-4 h-4" /> Yeni Kullanıcı
        </a>
    </div>

    <div class="flex flex-wrap gap-2 mb-5">
        <a href="{{ route('panel.adminpanel.kullanicilar.index') }}" class="{{ $rol === '' ? 'pill-active' : 'pill-idle' }}">Tümü</a>
        @foreach ($roles as $role)
            <a href="{{ route('panel.adminpanel.kullanicilar.index', ['rol' => $role->name]) }}" class="{{ $rol === $role->name ? 'pill-active' : 'pill-idle' }}">{{ $role->name }}</a>
        @endforeach
    </div>

    <form method="GET" class="flex flex-wrap gap-3 mb-5">
        @if ($rol)
            <input type="hidden" name="rol" value="{{ $rol }}">
        @endif
        <input type="text" name="q" value="{{ $q }}" placeholder="İsim veya e-posta ara…" class="w-full sm:w-64 rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        <button type="submit" class="btn-outline btn-sm">Filtrele</button>
        @if ($q)
            <a href="{{ route('panel.adminpanel.kullanicilar.index', ['rol' => $rol ?: null]) }}" class="text-sm text-slate-500 hover:text-slate-900 self-center transition-colors">Temizle</a>
        @endif
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-left text-xs text-slate-400 uppercase tracking-wide">
                        <th class="px-5 py-3 font-medium">Ad Soyad</th>
                        <th class="px-5 py-3 font-medium">E-posta</th>
                        <th class="px-5 py-3 font-medium">Rol</th>
                        <th class="px-5 py-3 font-medium">Premium</th>
                        <th class="px-5 py-3 font-medium text-right">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="px-5 py-3 text-slate-900 whitespace-nowrap">{{ $user->name }}</td>
                            <td class="px-5 py-3 text-slate-500 whitespace-nowrap">{{ $user->email }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @forelse ($user->roles as $role)
                                    <span class="pill-tag text-xs px-2 py-0.5">{{ $role->name }}</span>
                                @empty
                                    <span class="text-slate-400">—</span>
                                @endforelse
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($user->isPremium())
                                    <x-heroicon-o-check-circle class="w-4 h-4 text-emerald-600" />
                                @else
                                    <x-heroicon-o-x-mark class="w-4 h-4 text-slate-300" />
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('panel.adminpanel.kullanicilar.duzenle', $user) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-700 hover:text-brand-800 transition-colors">
                                        <x-heroicon-o-pencil class="w-4 h-4" /> Düzenle
                                    </a>
                                    @unless (auth()->user()->is($user))
                                        <form method="POST" action="{{ route('panel.adminpanel.kullanicilar.sil', $user) }}" data-turbo-confirm="&quot;{{ $user->name }}&quot; kalıcı olarak silinecek. Yazarsa/editörse kendi kitap/dergi sayısı/makaleleri de birlikte silinir. Emin misiniz?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 transition-colors">
                                                <x-heroicon-o-trash class="w-4 h-4" /> Sil
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">Kullanıcı bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5">
        {{ $users->links() }}
    </div>
@endsection
