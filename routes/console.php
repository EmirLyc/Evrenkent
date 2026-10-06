<?php

use App\Models\User;
use Database\Seeders\DemoContentSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Railway demo: entrypoint her açılışta çağırıyor (SEED_DEMO=true ise). Veritabanında tek bir
// kullanıcı bile varsa dokunmuyor — sonradan eklenen/silinen içerik deploy'da geri gelmesin.
Artisan::command('demo:seed-if-empty', function () {
    if (User::query()->exists()) {
        $this->info('Veritabanında kullanıcı var, demo verisi eklenmedi.');

        return;
    }

    $this->call('db:seed', ['--force' => true]);
    $this->call('db:seed', ['--class' => DemoContentSeeder::class, '--force' => true]);
})->purpose('Boş veritabanına bir kereliğine demo hesapları ve içeriği ekler');
