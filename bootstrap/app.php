<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Aplikasi berjalan di belakang reverse proxy (nginx host / container),
        // percayai header X-Forwarded-* agar URL & cookie memakai https.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Tampilkan halaman error Inertia untuk request web. Saat APP_DEBUG aktif,
        // error 500 tetap memakai halaman debug Laravel agar stack trace terlihat.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if (! in_array($status, [401, 403, 404, 500, 503], true)
                || ($status === 500 && config('app.debug'))
                || $request->expectsJson()
                || $request->is('api/*')) {
                return $response;
            }

            try {
                return Inertia::render('error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            } catch (Throwable) {
                // Inertia/DB tidak tersedia (mis. database mati): pakai view Blade
                // statis resources/views/errors/{status}.blade.php yang sudah ada di $response.
                return $response;
            }
        });
    })->create();
