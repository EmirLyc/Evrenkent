<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Magazine;
use App\Models\MagazineIssue;
use App\Models\User;
use App\Rules\RichTextContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Süper Admin'in Makaleler yönetimi (2026-09-28) — Filament kaldırılmadan önce makalelerin
 * admin tarafında düzenlenebildiği tek yer Filament'teki ArticleResource'tu. Kitaplar/Dergi
 * Sayıları sayfalarıyla aynı desen; içerik yazar panelindeki zengin editörle düzenleniyor
 * (Filament'in Trix editörü dipnotları siliyordu). Durum sadece İçerik Onayları akışıyla değişir.
 */
class AdminArticleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Article::class);

        $articles = Article::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.addcslashes($request->string('q'), '%_\\').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhereHas('author', fn ($a) => $a->where('name', 'like', $term)));
            })
            ->when($request->filled('durum'), fn ($query) => $query->where('status', $request->string('durum')))
            ->when($request->filled('dergi'), fn ($query) => $query->whereHas('magazineIssue', fn ($q) => $q->where('magazine_id', $request->integer('dergi'))))
            ->with(['author', 'magazineIssue.magazine'])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('panel.admin.makaleler.index', [
            'articles' => $articles,
            'magazines' => Magazine::orderBy('name')->get(),
            'q' => $request->string('q')->toString(),
            'durum' => $request->string('durum')->toString(),
            'dergi' => $request->string('dergi')->toString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Article::class);

        return view('panel.admin.makaleler.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Article::class);

        $data = $request->validate($this->rules(null), $this->messages());
        $status = ContentStatus::from($data['status']);
        $issue = MagazineIssue::findOrFail($data['magazine_issue_id']);

        // Faz D kuralı 3: sayısı yayında olmayan makale tek başına yayında olamaz.
        if ($status === ContentStatus::Yayinda && $issue->status !== ContentStatus::Yayinda) {
            throw ValidationException::withMessages([
                'status' => 'Sayısı yayında olmayan bir makale "Yayında" olarak oluşturulamaz — sayıyla birlikte yayına girer.',
            ]);
        }

        $article = Article::create([
            'author_id' => $data['author_id'],
            'magazine_issue_id' => $issue->id,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['body'],
            'status' => $status,
            'published_at' => $data['published_at'] ?? ($status === ContentStatus::Yayinda ? now() : null),
        ]);
        $article->categories()->sync($data['categories'] ?? []);

        return redirect()->route('panel.adminpanel.makaleler.duzenle', $article)->with('status', 'Makale oluşturuldu.');
    }

    public function edit(Article $article): View
    {
        $this->authorize('update', $article);

        $article->load(['categories', 'documents', 'magazineIssue.magazine', 'author']);

        return view('panel.admin.makaleler.form', $this->formData($article));
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $this->authorize('update', $article);

        $data = $request->validate($this->rules($article), $this->messages());

        // Durum sadece İçerik Onayları akışıyla değişir — formdan gelen olası bir değer yok sayılıyor.
        $article->update([
            'author_id' => $data['author_id'],
            'magazine_issue_id' => $data['magazine_issue_id'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'content' => $data['body'],
            'published_at' => $data['published_at'] ?? $article->published_at,
        ]);
        $article->categories()->sync($data['categories'] ?? []);

        return redirect()->route('panel.adminpanel.makaleler.duzenle', $article)->with('status', 'Makale güncellendi.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $this->authorize('delete', $article);

        // Belgeleri (ve dosyaları) HasDocuments siliyor.
        $article->delete();

        return redirect()->route('panel.adminpanel.makaleler.index')->with('status', 'Makale silindi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Article $article): array
    {
        return [
            'article' => $article,
            // Yazarlık yetkisi olanlar + (rolü sonradan değişmiş olsa bile) makalenin mevcut yazarı.
            'authors' => User::authors()->orderBy('name')->get()
                ->when($article && ! $article->author->canAuthor(), fn ($authors) => $authors->push($article->author)),
            'magazineIssues' => MagazineIssue::with('magazine')->orderBy('title')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function rules(?Article $article): array
    {
        return [
            'author_id' => ['required', 'exists:users,id'],
            'magazine_issue_id' => ['required', 'exists:magazine_issues,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('articles', 'slug')->ignore($article)],
            'body' => ['required', 'string', new RichTextContent],
            'status' => $article ? ['prohibited'] : ['required', Rule::enum(ContentStatus::class)],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'status.prohibited' => 'Durum sadece İçerik Onayları\'ndaki aksiyonlarla değişir.',
            'magazine_issue_id.required' => 'Makalenin yayınlanacağı dergi sayısını seçin.',
        ];
    }
}
