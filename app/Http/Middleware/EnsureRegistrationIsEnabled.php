<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Позволява регистрация само когато APP_REGISTRATION_ENABLED=true.
 * В production изключете регистрацията, след като създадете профилите си.
 */
class EnsureRegistrationIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('app.registration_enabled'), 404);

        return $next($request);
    }
}
