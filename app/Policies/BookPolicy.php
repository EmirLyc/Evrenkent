<?php

namespace App\Policies;

use App\Enums\ContentStatus;
use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * Kitap onay akışında Dergi Editörü adımı yoktur: Yazar -> doğrudan Süper Admin.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'yazar']);
    }

    public function view(User $user, Book $book): bool
    {
        return $user->hasRole('super_admin') || $book->author_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'yazar']);
    }

    public function update(User $user, Book $book): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $book->author_id === $user->id
            && in_array($book->status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true);
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->hasRole('super_admin')
            // Kalıcı reddedilen (2026-09-27) kitabını da silebilir — düzenleyip tekrar gönderemez.
            || ($book->author_id === $user->id && in_array($book->status, [ContentStatus::Taslak, ContentStatus::Reddedildi], true));
    }

    public function restore(User $user, Book $book): bool
    {
        return $user->hasRole('super_admin');
    }

    public function forceDelete(User $user, Book $book): bool
    {
        return $user->hasRole('super_admin');
    }

    /**
     * Yazar taslağı (veya revizyon istenen kitabı yeniden) Süper Admin onayına gönderir.
     */
    public function submit(User $user, Book $book): bool
    {
        return $book->author_id === $user->id
            && in_array($book->status, [ContentStatus::Taslak, ContentStatus::RevizyonIstendi], true);
    }

    /**
     * Onaylama/reddetme/yayınlama sadece Süper Admin yetkisindedir.
     */
    public function approve(User $user, Book $book): bool
    {
        return $user->hasRole('super_admin') && $book->status === ContentStatus::Gonderildi;
    }

    public function reject(User $user, Book $book): bool
    {
        return $user->hasRole('super_admin') && $book->status === ContentStatus::Gonderildi;
    }

    public function publish(User $user, Book $book): bool
    {
        return $user->hasRole('super_admin') && $book->status === ContentStatus::Onaylandi;
    }
}
