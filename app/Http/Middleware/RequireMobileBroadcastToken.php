<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequireMobileBroadcastToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        abort_unless(
            $token instanceof PersonalAccessToken
                && ($token->can('waiter:mobile') || $token->can('kitchen:mobile')),
            403,
        );

        return $next($request);
    }
}
