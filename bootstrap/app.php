<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\{HttpExceptionInterface, NotFoundHttpException};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $model = class_basename($previous->getModel());

                return response()->json([
                    'message' => "{$model} not found.",
                    'code' => 'MODEL_NOT_FOUND',
                ], 404);
            }

            // Genuinely unmatched route
            return response()->json([
                'message' => 'Endpoint not found.',
                'code' => 'ENDPOINT_NOT_FOUND',
            ], 404);
        });

        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => $e->getErrorCode(),
                ], $e->getStatusCode());
            }
        });

        // Catch-all for any other exception on api/* routes
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $status = $e instanceof HttpExceptionInterface
                    ? $e->getStatusCode()
                    : 500;

                return response()->json([
                    'message' => app()->isProduction() && $status === 500
                        ? 'Server error.'
                        : $e->getMessage(),
                ], $status);
            }
        });
    })->create();
