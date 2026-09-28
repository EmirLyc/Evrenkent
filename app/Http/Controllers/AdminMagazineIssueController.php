<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Süper Admin'in Dergi Sayıları yönetimi (liste/oluştur/düzenle/sil). Faz 3 — bkz.
 * UI_RESTYLE_NOTES.md; AdminBookController'daki desenle birebir tutarlı.
 */
class AdminMagazineIssueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', MagazineIssue::class);

        $issues = MagazineIssue::query()
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.addcslashes($request->string('q'), '%_\\').'%'))
            ->when($request->filled('durum'), fn ($query) => $query->where('status', $request->string('durum')))
            ->with(['editor', 'magazine'])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('panel.admin.sayilar.index', [
            'issues' => $issues,
            'q' => $request->string('q')->toString(),
            'durum' => $request->string('durum')->toString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MagazineIssue::class);

        return view('panel.admin.sayilar.form', [
            'issue' => null,
            'magazines' => Magazine::with('editor')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MagazineIssue::class);

        $data = $this->withMagazineEditor($request->validate($this->validationRules(create: true)));

        if ($request->hasFile('cover_image')) {
            // x-magazine-cover bileşeni bu disk/dizinden okuyor. Disk adı config'ten (covers_disk).
            $data['cover_image'] = $request->file('cover_image')->store('covers/magazine-issues', config('filesystems.covers_disk'));
        }

        $data['status'] = $data['status'] ?? ContentStatus::Taslak->value;

        $issue = MagazineIssue::create($data);

        return redirect()->route('panel.adminpanel.sayilar.duzenle', $issue)->with('status', 'Sayı oluşturuldu.');
    }

    public function edit(MagazineIssue $magazineIssue): View
    {
        $this->authorize('update', $magazineIssue);

        return view('panel.admin.sayilar.form', [
            'issue' => $magazineIssue,
            'magazines' => Magazine::with('editor')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('update', $magazineIssue);

        $data = $this->withMagazineEditor($request->validate($this->validationRules(create: false)));

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('covers/magazine-issues', config('filesystems.covers_disk'));
        } else {
            unset($data['cover_image']);
        }

        // Durum sadece İçerik Onayları akışıyla (Faz 1) değişir.
        unset($data['status']);

        $magazineIssue->update($data);

        return redirect()->route('panel.adminpanel.sayilar.duzenle', $magazineIssue)->with('status', 'Sayı güncellendi.');
    }

    public function destroy(MagazineIssue $magazineIssue): RedirectResponse
    {
        $this->authorize('delete', $magazineIssue);

        if ($magazineIssue->cover_image) {
            Storage::disk(config('filesystems.covers_disk'))->delete($magazineIssue->cover_image);
        }

        $magazineIssue->delete();

        return redirect()->route('panel.adminpanel.sayilar.index')->with('status', 'Sayı silindi.');
    }

    /**
     * Sayının editörü, seçilen derginin editörü (Faz E) — ayrıca seçilmiyor. Editörü
     * atanmamış dergiye sayı açılamaz (sayının editor_id'si zorunlu, editör yetkileri ona bağlı).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withMagazineEditor(array $data): array
    {
        $magazine = Magazine::findOrFail($data['magazine_id']);

        if ($magazine->editor_id === null) {
            throw ValidationException::withMessages([
                'magazine_id' => "\"{$magazine->name}\" dergisine henüz editör atanmamış — önce Dergiler sayfasından editör atayın.",
            ]);
        }

        return array_merge($data, ['editor_id' => $magazine->editor_id]);
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function validationRules(bool $create): array
    {
        return [
            'magazine_id' => ['required', 'exists:magazines,id'],
            'title' => ['required', 'string', 'max:255'],
            'issue_number' => ['required', 'integer', 'min:1'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'editor_note' => ['nullable', 'string'],
            'status' => $create ? ['required', 'in:'.implode(',', array_column(ContentStatus::cases(), 'value'))] : ['sometimes'],
            'publish_date' => ['nullable', 'date'],
        ];
    }
}
