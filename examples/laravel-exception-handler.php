<?php

/**
 * Pantree PHP SDK — Laravel exception handler examples
 *
 * By default PantreeServiceProvider wires up automatic exception capture.
 * Use this pattern to add extra context (user, order ID, etc.)
 * or filter which exceptions are sent.
 *
 * Place this in: bootstrap/app.php  (Laravel 11+)
 *             or: app/Exceptions/Handler.php  (Laravel 10)
 */

// ── Laravel 11+ (bootstrap/app.php) ──────────────────────────────────────────

use Illuminate\Foundation\Application;
use Pantree\Laravel\Pantree;

return Application::configure(basePath: dirname(__DIR__))
    ->withExceptions(function ($exceptions) {

        $exceptions->reportable(function (\Throwable $e) {
            // Skip validation / HTTP exceptions
            if ($e instanceof \Illuminate\Validation\ValidationException) return;
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) return;

            Pantree::captureException($e, [
                'user' => auth()->check() ? [
                    'id'    => auth()->id(),
                    'email' => auth()->user()->email,
                ] : null,
                'context' => [
                    'url'    => request()->fullUrl(),
                    'method' => request()->method(),
                    'ip'     => request()->ip(),
                ],
            ]);
        });

    })->create();


// ── Laravel 10 (app/Exceptions/Handler.php) ──────────────────────────────────

/*
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Pantree\Laravel\Pantree;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            Pantree::captureException($e, [
                'user' => auth()->check() ? ['id' => auth()->id()] : null,
            ]);
        });
    }
}
*/
