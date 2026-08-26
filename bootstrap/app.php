<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
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
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->withSchedule(function (Schedule $schedule): void {
        // "Yakında Çıkacaklar" — planlanan yayın tarihi gelmiş kitapları otomatik
        // yayına alır. Sunucuda gerçekten çalışması için cron'a `php artisan
        // schedule:run` eklenmesi gerekiyor (bkz. DEPLOYMENT.md).
        $schedule->command('books:publish-scheduled')->everyMinute();
    })
    ->create();
