<?php

namespace App\Http\Controllers;

use App\Models\Magazine;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Süper Admin'in Dergiler yönetimi (Faz E, 2026-09-27 revizesi): dergi tanımı + editör
 * ataması ("süper admin bir dergi için birini editör yapabilmeli") + yazar ataması
 * ("dergide yazarı süper admin yapacak" — yazar sadece atandığı dergilere makale gönderir).
 */
class AdminMagazineController extends Controller
{
    public function index(): View
    {
        return view('panel.admin.dergiler.index', [
            'magazines' => Magazine::with('editor')->withCount(['issues', 'authors'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('panel.admin.dergiler.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(null));

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('covers/magazines', config('filesystems.covers_disk'));
        }

        $magazine = Magazine::create($data);
        $magazine->authors()->sync($request->input('author_ids', []));

        return redirect()->route('panel.adminpanel.dergiler.duzenle', $magazine)->with('status', 'Dergi oluşturuldu.');
    }

    public function edit(Magazine $magazine): View
    {
        return view('panel.admin.dergiler.form', $this->formData($magazine->load('authors')));
    }

    public function update(Request $request, Magazine $magazine): RedirectResponse
    {
        $data = $request->validate($this->rules($magazine));

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('covers/magazines', config('filesystems.covers_disk'));
        } else {
            unset($data['cover_image']);
        }

        $editorChanged = array_key_exists('editor_id', $data) && (int) $data['editor_id'] !== (int) $magazine->editor_id;

        $magazine->update($data);
        $magazine->authors()->sync($request->input('author_ids', []));

        // Editör yetkileri (policy'ler, Dergi Yönetimi paneli, bildirimler) sayı düzeyinde
        // editor_id'ye bağlı — derginin editörü değişince sayıları da yeni editöre geçer.
        if ($editorChanged && $magazine->editor_id !== null) {
            $magazine->issues()->update(['editor_id' => $magazine->editor_id]);
        }

        return redirect()->route('panel.adminpanel.dergiler.duzenle', $magazine)->with('status', $editorChanged && $magazine->editor_id
            ? 'Dergi güncellendi — derginin sayıları yeni editöre devredildi.'
            : 'Dergi güncellendi.');
    }

    public function destroy(Magazine $magazine): RedirectResponse
    {
        // Sayıları olan dergi silinemez (sayılar yetim kalırdı; DB'de de restrictOnDelete).
        if ($magazine->issues()->exists()) {
            return redirect()->route('panel.adminpanel.dergiler.index')
                ->with('status', "\"{$magazine->name}\" silinemedi: önce derginin sayılarını silin.");
        }

        if ($magazine->cover_image) {
            Storage::disk(config('filesystems.covers_disk'))->delete($magazine->cover_image);
        }

        $magazine->delete();

        return redirect()->route('panel.adminpanel.dergiler.index')->with('status', 'Dergi silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Magazine $magazine): array
    {
        return [
            'magazine' => $magazine,
            'editors' => User::role('dergi_editoru')->orderBy('name')->get(),
            'authors' => User::role('yazar')->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function rules(?Magazine $magazine): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('magazines', 'slug')->ignore($magazine)],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            // Editör sadece dergi editörü, yazarlar sadece yazar rolündeki kullanıcılardan seçilebilir.
            'editor_id' => ['nullable', Rule::in(User::role('dergi_editoru')->pluck('id'))],
            'author_ids' => ['nullable', 'array'],
            'author_ids.*' => [Rule::in(User::role('yazar')->pluck('id'))],
        ];
    }
}
