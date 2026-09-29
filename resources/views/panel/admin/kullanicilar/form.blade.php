@extends('layouts.admin-panel')

@section('title', $user ? 'Kullanıcıyı Düzenle' : 'Yeni Kullanıcı')

@section('content')
    <div class="max-w-lg mx-auto">
        <h1 class="font-serif text-xl font-semibold text-slate-900 mb-5">{{ $user ? 'Kullanıcıyı Düzenle' : 'Yeni Kullanıcı' }}</h1>

        <form
            method="POST"
            action="{{ $user ? route('panel.adminpanel.kullanicilar.guncelle', $user) : route('panel.adminpanel.kullanicilar.store') }}"
            class="card p-6 space-y-5"
        >
            @csrf
            @if ($user)
                @method('PUT')
            @endif

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Ad Soyad</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user?->name) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">E-posta</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Şifre</label>
                <input id="password" name="password" type="password" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                <p class="text-xs text-slate-400 mt-1">{{ $user ? 'Değiştirmek istemiyorsanız boş bırakın.' : 'En az 8 karakter.' }}</p>
                @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-100 pt-5">
                <div class="text-sm font-medium text-slate-700 mb-2">Rol</div>
                <div class="flex flex-wrap gap-x-4 gap-y-2">
                    @foreach ($roles as $role)
                        <label class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->name }}"
                                {{ collect(old('roles', $user?->roles->pluck('name') ?? []))->contains($role->name) ? 'checked' : '' }}
                                class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                            >
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('roles') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
            </div>

            <div class="border-t border-slate-100 pt-5">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_premium" value="1" @checked(old('is_premium', $user?->is_premium)) class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    Premium Hesap
                </label>
                <div class="mt-3">
                    <label for="premium_until" class="block text-sm font-medium text-slate-700 mb-1">Premium Bitiş Tarihi</label>
                    <input id="premium_until" name="premium_until" type="datetime-local" value="{{ old('premium_until', $user?->premium_until?->format('Y-m-d\TH:i')) }}" class="w-full max-w-xs rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    @error('premium_until') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Hesaba özel çalışma alanı sınırları (ücretsiz hesap). Boş = Premium Sistemi'ndeki genel ayar. --}}
            <div class="border-t border-slate-100 pt-5">
                <div class="text-sm font-medium text-slate-700">Hesaba özel sınırlar</div>
                <p class="text-xs text-slate-400 mt-0.5 mb-3">Ücretsiz hesapta geçerli. Boş bıraktığınız sınır Premium Sistemi'ndeki genel ayarı kullanır; premium üyede sınır yoktur.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach (\App\Models\User::QUOTA_OVERRIDES as $key => $label)
                        @php $default = $key === 'defter_words' ? \App\Support\PlatformSettings::get('quota_defter_words') : \App\Support\PlatformSettings::noteQuota(\App\Enums\NoteType::from($key)); @endphp
                        <div>
                            <label for="quota_{{ $key }}" class="block text-sm text-slate-700 mb-1">{{ $label }}</label>
                            <input id="quota_{{ $key }}" name="quota[{{ $key }}]" type="number" min="1" inputmode="numeric"
                                   value="{{ old('quota.'.$key, $user?->quota_overrides[$key] ?? null) }}" placeholder="Genel: {{ number_format($default, 0, ',', '.') }}"
                                   class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                            @error('quota.'.$key) <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-4 pt-1">
                <button type="submit" class="btn-brand">{{ $user ? 'Değişiklikleri Kaydet' : 'Kullanıcıyı Oluştur' }}</button>
                <a href="{{ route('panel.adminpanel.kullanicilar.index') }}" class="text-sm text-slate-500 hover:text-slate-900 transition-colors">Vazgeç</a>
            </div>
        </form>
    </div>
@endsection
