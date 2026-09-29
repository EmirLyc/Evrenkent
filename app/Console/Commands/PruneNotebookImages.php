<?php

namespace App\Console\Commands;

use App\Support\NotebookHtml;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneNotebookImages extends Command
{
    /**
     * Defterden çıkarılan görseller (defter silinince zaten hemen siliniyor): yazarken bir görsel
     * silinip geri alınabildiği için her kayıtta değil, günde bir kez ve sadece bir günden eski
     * dosyalar. Hesabı silinmiş okurun klasörü de temizlenir (defteri kalmadığı için hepsi boşta).
     *
     * @var string
     */
    protected $signature = 'notebooks:prune-images';

    /**
     * @var string
     */
    protected $description = 'Hiçbir defterde kullanılmayan Defterim görsellerini siler.';

    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.covers_disk'));
        $deleted = 0;

        foreach ($disk->directories('defterler') as $directory) {
            $userId = (int) basename($directory);
            if ($userId > 0) {
                $deleted += NotebookHtml::pruneImages($userId, null, now()->subDay());
            }
        }

        $this->info("{$deleted} kullanılmayan defter görseli silindi.");

        return self::SUCCESS;
    }
}
