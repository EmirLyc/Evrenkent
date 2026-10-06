<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kapak ve defter görsellerini kapak disk'inden (filesystems.covers_disk) tarayıcıya verir.
 *
 * Railway Bucket'ları herkese açık olamıyor; görseller bucket'ta duruyor, adresleri
 * /media/... (bucket_public disk'inin url'i) ve dosyayı bu controller akıtıyor. Dosya adları
 * yüklemede rastgele üretildiği için içerik hiç değişmez — tarayıcı bir yıl önbellekte tutabilir.
 * Belgeler (documents_disk) ayrı klasörde, buradan erişilemez.
 */
class MediaController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        $name = config('filesystems.covers_disk');

        // S3 disk'inin url()'i klasör önekini de yazıyor (/media/public/covers/...); disk'e
        // önek olmadan soruluyor. Önekle başlamayan yol (ör. private/...) hiç bakılmadan 404.
        $root = trim((string) config("filesystems.disks.{$name}.root"), '/');
        if (config("filesystems.disks.{$name}.driver") === 's3' && $root !== '') {
            abort_unless(str_starts_with($path, $root.'/'), 404);
            $path = substr($path, strlen($root) + 1);
        }

        $disk = Storage::disk($name);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
