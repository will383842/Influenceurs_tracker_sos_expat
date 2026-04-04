<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-to-machine authentication via static Bearer token.
 * Used by automated scripts (Q/R generator, blog importer, etc.)
 * that cannot go through the Sanctum login flow.
 *
 * Configure in .env:
 *   MACHINE_API_TOKEN=<long-random-string>
 */
class MachineTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.machine_api_token');

        if (! $expectedToken) {
            return response()->json(['message' => 'Machine token not configured.'], 500);
        }

        $providedToken = $request->bearerToken();

        if (! $providedToken || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json(['message' => 'Token machine invalide.'], 401);
        }

        return $next($request);
    }
}
