<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    /**
     * Dergi makalesi akışı: Yazar -> Dergi Editörü (inceler, sayıya ekler) -> Süper Admin (onaylar/yayınlar).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'dergi_editoru', 'yazar']);
    }

    public function view(User $user, Article $article): bool
    {
        if ($user->hasAnyRole(['super_admin', 'dergi_editoru'])) {
            return true;
        }

        return $article->author_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'yazar']);
    }

    public function update(User $user, Article $article): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($user->hasRole('dergi_editoru') && $article->status === ContentStatus::Incelemede) {
            return $article->magazineIssue?->editor_id === $user->id;
        }

        return $article->author_id === $user->id
            && in_array($article->status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true);
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin')
            || ($article->author_id === $user->id && $article->status === ContentStatus::Taslak);
    }

    public function restore(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Yazar makaleyi (veya revizyon istenen makaleyi yeniden) Dergi Editörüne/onaya gönderir.
     */
    public function submit(User $user, Article $article): bool
    {
        return $article->author_id === $user->id
            && in_array($article->status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true);
    }

    /**
     * Dergi Editörü, kendi sayısına ait makaleyi inceleyip Süper Admin'e gönderir.
     */
    public function review(User $user, Article $article): bool
    {
        return $user->hasRole('dergi_editoru')
            && $article->magazineIssue?->editor_id === $user->id
            && $article->status === ContentStatus::Gonderildi;
    }

    /**
     * Onaylama/reddetme/yayınlama sadece Süper Admin yetkisindedir.
     */
    public function approve(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin') && $article->status === ContentStatus::Incelemede;
    }

    public function reject(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin') && $article->status === ContentStatus::Incelemede;
    }

    /**
     * Makale, sayısı yayında değilse tek başına yayınlanamaz — sayıyla birlikte yayına girer
     * (ContentPublisher::publishIssue). Sayı yayındayken sonradan onaylanan makale ise tek
     * başına yayınlanabilir (2026-09-27 kararı).
     */
    public function publish(User $user, Article $article): bool
    {
        return $user->hasRole('super_admin')
            && $article->status === ContentStatus::Onaylandi
            && $article->magazineIssue?->status === ContentStatus::Yayinda;
    }
}
