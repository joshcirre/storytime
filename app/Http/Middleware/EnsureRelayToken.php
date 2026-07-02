<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRelayToken
{
    /**
     * Authenticate the RPC relay process via a shared secret header.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.relay.token');

        abort_if(blank($token), 503, 'Relay token is not configured.');

        abort_unless(hash_equals($token, (string) $request->header('X-Relay-Token')), 403);

        return $next($request);
    }
}
