<?php

namespace App\Models\Concerns;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\ContentMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Yazarın eserleri (kitap, makale) için Taslaklarım'ın ortak davranışı (Faz G1):
 * çöp kutusu (soft delete), esere bağlı yazışma ve yazarın gördüğü durum adı / tarihi.
 */
trait IsPublication
{
    use SoftDeletes;

    public function messages(): MorphMany
    {
        return $this->morphMany(ContentMessage::class, 'messageable')->oldest();
    }

    /**
     * Yazışmaya katılabilecekler: yazar ve Süper Admin'ler; makalede sayının editörü de.
     *
     * @return Collection<int, User>
     */
    public function conversationParticipants()
    {
        $editorId = $this instanceof Article ? $this->magazineIssue?->editor_id : null;

        return User::role('super_admin')->get()
            ->push($this->author)
            ->when($editorId, fn ($users) => $users->push(User::find($editorId)))
            ->filter()
            ->unique('id')
            ->values();
    }

    /** Taslaklarım sekmesi / rozeti — yazarın gördüğü ad (mockup "Yazarın Gözünden" 1.1). */
    public function authorStatusKey(): string
    {
        return match ($this->status) {
            ContentStatus::Taslak => 'taslak',
            ContentStatus::Gonderildi, ContentStatus::Incelemede => 'incelemede',
            ContentStatus::RevizyonIstendi => 'duzeltme',
            ContentStatus::Onaylandi => 'kabul',
            ContentStatus::Yayinda => 'yayinda',
            ContentStatus::Reddedildi => 'reddedildi',
        };
    }

    /** Durumun başladığı an (kart altındaki tarih): gönderim / kabul / ret kaydı, yoksa son düzenleme. */
    public function authorStatusDate(): ?Carbon
    {
        $action = match ($this->status) {
            ContentStatus::Gonderildi, ContentStatus::Incelemede => 'gonderildi',
            ContentStatus::Onaylandi => 'onaylandi',
            ContentStatus::Reddedildi => 'reddedildi',
            ContentStatus::Yayinda => 'yayinda',
            default => null,
        };

        $review = $action ? $this->reviews->where('action', $action)->sortByDesc('id')->first() : null;

        return $review?->created_at ?? ($this->status === ContentStatus::Yayinda ? $this->published_at : null) ?? $this->updated_at;
    }
}
