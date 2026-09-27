<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\MagazineIssue;
use App\Models\User;

class MagazineIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'dergi_editoru']);
    }

    public function view(User $user, MagazineIssue $magazineIssue): bool
    {
        return $user->hasRole('super_admin') || $magazineIssue->editor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'dergi_editoru']);
    }

    public function update(User $user, MagazineIssue $magazineIssue): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $magazineIssue->editor_id === $user->id
            && in_array($magazineIssue->status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true);
    }

    public function delete(User $user, MagazineIssue $magazineIssue): bool
    {
        return $user->hasRole('super_admin')
            // Kalıcı reddedilen (2026-09-27) sayısını da silebilir; içindeki makaleler sayısız kalır (nullOnDelete).
            || ($magazineIssue->editor_id === $user->id && in_array($magazineIssue->status, [ContentStatus::Taslak, ContentStatus::Reddedildi], true));
    }

    public function restore(User $user, MagazineIssue $magazineIssue): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDelete(User $user, MagazineIssue $magazineIssue): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Dergi Editörü, hazırladığı sayıyı Süper Admin onayına gönderir ("Yayına Gönder").
     */
    public function submit(User $user, MagazineIssue $magazineIssue): bool
    {
        return $magazineIssue->editor_id === $user->id
            && in_array($magazineIssue->status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true);
    }

    /**
     * Onaylama/reddetme/zamanlama sadece Süper Admin yetkisindedir. Onay artık "şimdi yayınla"
     * ya da "ileri tarihte yayınla" demek — bu yüzden sayı, içinde en az bir onaylı makale
     * olmadan onaylanamaz (2026-09-27 kararı: boş sayı yayınlanamaz/zamanlanamaz).
     * Hem kendi panelimiz hem Filament bu policy'den geçtiği için kural ikisinde de geçerli.
     */
    public function approve(User $user, MagazineIssue $magazineIssue): bool
    {
        return $user->hasRole('super_admin')
            && $magazineIssue->status === ContentStatus::Gonderildi
            && $magazineIssue->hasApprovedArticles();
    }

    public function reject(User $user, MagazineIssue $magazineIssue): bool
    {
        return $user->hasRole('super_admin') && $magazineIssue->status === ContentStatus::Gonderildi;
    }

    public function publish(User $user, MagazineIssue $magazineIssue): bool
    {
        return $user->hasRole('super_admin')
            && $magazineIssue->status === ContentStatus::Onaylandi
            && $magazineIssue->hasApprovedArticles();
    }
}
