<?php

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
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureAccountIsActive::class);
        $middleware->validateCsrfTokens(except: ['webhooks/paystack']);
        $middleware->alias(['admin' => \App\Http\Middleware\EnsureUserIsAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An upload bigger than PHP's post_max_size. Answer before the debug page tries to load the whole raw body
        // into memory (which exhausts memory_limit and turns this into a bare 500).
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, Request $request) {
            $limit = \App\Support\UploadLimits::mb(\App\Support\UploadLimits::requestKb());
            $message = "That upload was too large: the server accepts at most {$limit} at a time. Upload fewer or smaller files at once.";
            \Illuminate\Support\Facades\Log::warning("Upload rejected: request larger than post_max_size ({$limit}). Raise post_max_size and upload_max_filesize in php.ini to accept bigger files.", ['url' => $request->path(), 'bytes' => $request->server('CONTENT_LENGTH')]);

            return $request->expectsJson()
                ? response()->json(['message' => $message], 413)
                : redirect()->to(url()->previous() ?: '/')->with('error', $message);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
