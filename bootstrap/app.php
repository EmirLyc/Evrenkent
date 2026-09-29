<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Railway (ve benzeri PaaS'lar) HTTPS'i kendi proxy'sinde sonlandırıyor,
        // uygulamaya düz HTTP olarak iletiyor. Bunu Laravel'e bildirmezsek
        // url()/asset() gibi yardımcılar http:// üretir ve tarayıcı bunu
        // "mixed content" diye engeller (CSS/JS hiç yüklenmez).
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Faz G2: editörün fetch istekleri (otomatik kayıt, görsel / belge yükleme, içe aktarma)
        // hataları JSON istiyor — yoksa doğrulama hatası 302'ye dönüp editör "bağlantı hatası"
        // gösteriyordu. Turbo'nun form gönderimleri JSON istemediği için yönlendirme davranışı aynı.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        // "Yakında Çıkacaklar" — planlanan yayın tarihi gelmiş kitap, dergi sayısı
        // (makaleleriyle) ve makaleleri otomatik yayına alır. Sunucuda gerçekten
        // çalışması için cron'a `php artisan schedule:run` eklenmesi gerekiyor (bkz. DEPLOYMENT.md).
        $schedule->command('content:publish-scheduled')->everyMinute();
        // Defterden çıkarılan (hiçbir defterde kalmayan) görseller.
        $schedule->command('notebooks:prune-images')->dailyAt('04:00');
    })
    ->create();
