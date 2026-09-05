<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias(['role' => EnsureRole::class]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'data' => null,
                    'error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage(), 'details' => $exception->details],
                    'meta' => ['requestId' => $request->header('X-Request-Id', (string) Str::uuid()), 'serverTime' => now()->toIso8601String()],
                ], $exception->statusCode);
            }
            if ($request->is('admin/*')) {
                return back()->withInput()->with('error', $exception->getMessage());
            }

            return redirect()->route('error', ['code' => $exception->errorCode]);
        });
    })->create();
