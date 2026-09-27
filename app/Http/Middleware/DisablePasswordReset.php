<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The app sends no email, so Statamic's password-reset flow can't work. Close it entirely
 * (recovery is done over SSH, see docs/deployment.md).
 */
class DisablePasswordReset
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->is(config('statamic.cp.route').'/auth/password/*'), 404);

        return $next($request);
    }
}
