<?php

namespace App\Console\Commands;

use App\Support\ContentPublisher;
use Illuminate\Console\Command;

class PublishScheduledContent extends Command
{
    /**
     * Önceden sadece kitapları yayınlayan books:publish-scheduled'ın genişletilmiş hâli —
     * artık dergi sayıları (makaleleriyle birlikte) ve makaleler de. Eski ad, sunucudaki
     * cron/dokümantasyon kırılmasın diye takma ad olarak duruyor.
     *
     * @var string
     */
    protected $signature = 'content:publish-scheduled';

    /** @var array<int, string> */
    protected $aliases = ['books:publish-scheduled'];

    /**
     * @var string
     */
    protected $description = 'Planlanan yayın tarihi gelmiş kitap, dergi sayısı ve makaleleri otomatik yayına alır.';

    public function handle(): int
    {
        $counts = ContentPublisher::publishDue();

        $this->info("{$counts['books']} kitap, {$counts['issues']} dergi sayısı, {$counts['articles']} makale yayınlandı.");

        return self::SUCCESS;
    }
}
