<?php

use App\Http\Middleware\AuthenticateWithRejectionMarker;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProtectSessionResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ProtectSessionResponses::class, HandleInertiaRequests::class]);
        $middleware->alias(['auth' => AuthenticateWithRejectionMarker::class, 'active' => EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $recovery = static function (Request $request, int $status, string $reason, bool $rejected) {
            return response()->json([
                'message' => $status === 401 ? 'Autentikasi diperlukan.' : 'Verifikasi keamanan permintaan gagal.',
                'recovery' => [
                    'reason' => $reason,
                    'rejected' => $rejected ? [
                        'method' => $request->method(),
                        'path' => $request->getPathInfo(),
                        'before_action' => true,
                    ] : null,
                ],
            ], $status, ['Cache-Control' => 'no-store, private']);
        };
        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($recovery) {
            if ($request->header('X-Inertia')) {
                return $recovery($request, 401, 'authentication_required', $request->attributes->get(AuthenticateWithRejectionMarker::REJECTED) === true);
            }
        });
        $exceptions->render(function (HttpException $exception, Request $request) use ($recovery) {
            // TokenMismatch berasal dari middleware web sebelum Action; abort(419) tidak memiliki provenance ini.
            if ($request->header('X-Inertia') && $exception->getStatusCode() === 419
                && $exception->getPrevious() instanceof TokenMismatchException) {
                return $recovery($request, 419, 'csrf_mismatch', true);
            }

            if ($request->header('X-Inertia') && $exception->getStatusCode() === 403) {
                if (in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    return response()->json([
                        'message' => $exception->getMessage() ?: 'Akses ditolak.',
                    ], 403, ['Cache-Control' => 'no-store, private']);
                }
            }
        });
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'state', 'id_token', 'access_token', 'refresh_token', 'client_secret']);
    })->create();
