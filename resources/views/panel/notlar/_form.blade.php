<form method="POST" action="{{ route('panel.notlar.ekle') }}" class="card p-6 space-y-5 max-w-2xl mx-auto mb-8">
    <x-note-quota-notice :type="$type" />
    @csrf
    <input type="hidden" name="type" value="{{ $type->value }}">

    @if ($type !== \App\Enums\NoteType::Defter)
        <p class="text-sm text-slate-500">
            Bir kitap sayfasından "{{ $type->label() }} Ekle" ile ekleme yapabilirsiniz. Aşağıdaki alanları elle doldurup da kaydedebilirsiniz.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="noteable_type" class="block text-sm font-medium text-slate-700 mb-1">İçerik Türü</label>
                <select id="noteable_type" name="noteable_type" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                    <option value="App\Models\Book" {{ old('noteable_type') === 'App\Models\Book' ? 'selected' : '' }}>Kitap</option>
                </select>
                @error('noteable_type') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="noteable_id" class="block text-sm font-medium text-slate-700 mb-1">Kitap ID</label>
                <input id="noteable_id" name="noteable_id" type="number" value="{{ old('noteable_id') }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
                @error('noteable_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    @endif

    <div>
        <label for="title" class="block text-sm font-medium text-slate-700 mb-1">Başlık (opsiyonel)</label>
        <input id="title" name="title" type="text" value="{{ old('title') }}" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
        @error('title') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    @if ($type === \App\Enums\NoteType::Alinti)
        <div>
            <label for="location" class="block text-sm font-medium text-slate-700 mb-1">Sayfa / Bölüm</label>
            <input id="location" name="location" type="text" value="{{ old('location') }}" placeholder="ör. Sayfa 42" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">
            @error('location') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    @endif

    <div>
        <label for="content" class="block text-sm font-medium text-slate-700 mb-1">İçerik</label>
        <textarea id="content" name="content" rows="4" class="w-full rounded-md border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500">{{ old('content') }}</textarea>
        @error('content') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <button type="submit" class="btn-dark">
        Kaydet
    </button>
</form>
