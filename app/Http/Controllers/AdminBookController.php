<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Süper Admin'in Kitaplar yönetimi (liste/oluştur/düzenle/sil). Durum sadece İçerik
 * Onayları akışıyla değişir.
 */
class AdminBookController extends Controller
{
    use Concerns\ResolvesBookPrice;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Book::class);

        $books = Book::query()
            ->when($request->filled('q'), fn ($query) => $query->where('title', 'like', '%'.addcslashes($request->string('q'), '%_\\').'%'))
            ->when($request->filled('durum'), fn ($query) => $query->where('status', $request->string('durum')))
            ->with('author')
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('panel.admin.kitaplar.index', [
            'books' => $books,
            'q' => $request->string('q')->toString(),
            'durum' => $request->string('durum')->toString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Book::class);

        return view('panel.admin.kitaplar.form', [
            'book' => null,
            'authors' => User::authors()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Book::class);

        $data = $this->bookData($request, $this->validationRules(create: true));

        if ($request->hasFile('cover_image')) {
            // x-book-cover bileşeni bu disk/dizinden okuyor. Disk adı sabit değil, config'ten
            // (bkz. config/filesystems.php -> covers_disk) — S3'e geçiş tek satırlık env değişikliği olsun diye.
            $data['cover_image'] = $request->file('cover_image')->store('covers/books', config('filesystems.covers_disk'));
        }

        $data['is_editors_pick'] = $request->boolean('is_editors_pick');
        $data['status'] = $data['status'] ?? ContentStatus::Taslak->value;

        $book = Book::create($data);
        $book->categories()->sync($request->input('categories', []));

        return redirect()->route('panel.adminpanel.kitaplar.duzenle', $book)->with('status', 'Kitap oluşturuldu.');
    }

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('categories');

        return view('panel.admin.kitaplar.form', [
            'book' => $book,
            'authors' => User::authors()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        $data = $this->bookData($request, $this->validationRules(create: false, book: $book));

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('covers/books', config('filesystems.covers_disk'));
        } else {
            unset($data['cover_image']);
        }

        $data['is_editors_pick'] = $request->boolean('is_editors_pick');
        // Durum sadece İçerik Onayları akışıyla (Faz 1) değişir — düzenleme formundan
        // gelen olası bir "status" değeri (yoksa da) burada bilerek yok sayılıyor.
        unset($data['status']);

        $book->update($data);
        $book->categories()->sync($request->input('categories', []));

        return redirect()->route('panel.adminpanel.kitaplar.duzenle', $book)->with('status', 'Kitap güncellendi.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        // Admin silmesi kalıcı (kapak ve belgeler model olaylarında temizleniyor); yazarın
        // silmesi çöp kutusuna gider (Faz G1).
        $book->forceDelete();

        return redirect()->route('panel.adminpanel.kitaplar.index')->with('status', 'Kitap silindi.');
    }

    /**
     * Doğrulama + fiyat kuralı (0 TL ancak "Ücretsiz" işaretiyle). is_free bir sütun değil,
     * sadece formdaki onay işareti — kayda girmesin diye çıkarılıyor.
     *
     * @param  array<string, array<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function bookData(Request $request, array $rules): array
    {
        $data = $this->resolveBookPrice($request, $request->validate($rules));
        unset($data['is_free']);

        // İndirim kaldırıldıysa (alan boşaltıldıysa) eski bitiş tarihi de kalmasın.
        if (empty($data['discount_price'])) {
            $data['discount_price'] = null;
            $data['discount_ends_at'] = null;
        }

        return $data;
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function validationRules(bool $create, ?Book $book = null): array
    {
        return [
            'author_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'alpha_dash',
                Rule::unique('books', 'slug')->ignore($book),
            ],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            // nullable: "Ücretsiz" işaretliyken fiyat alanı devre dışı, gönderilmiyor —
            // fiyatın dolu olması kuralı resolveBookPrice()'ta (0 ise ücretsiz işareti şart).
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'discount_ends_at' => ['nullable', 'date'],
            'is_free' => ['nullable', 'boolean'],
            'status' => $create ? ['required', 'in:'.implode(',', array_column(ContentStatus::cases(), 'value'))] : ['sometimes'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
            'published_at' => ['nullable', 'date'],
            'average_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'page_count' => ['nullable', 'integer', 'min:0'],
            // document_count / video_count bilerek yok: metinden otomatik (Book::refreshContentCounts).
            'map_count' => ['nullable', 'integer', 'min:0'],
            'author_note_count' => ['nullable', 'integer', 'min:0'],
            'source_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
