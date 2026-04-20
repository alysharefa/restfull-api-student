<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return response()->json([
                'message' => 'Unauthorized. Bearer token is required.',
            ], 401);
        }

        try {
            $payload = app(JwtService::class)->decode($token);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Unauthorized. Invalid or expired token.',
            ], 401);
        }

        $user = User::find($payload['sub'] ?? null);

        if (! $user) {
            return response()->json([
                'message' => 'Unauthorized. User not found.',
            ], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches) !== 1) {
            return null;
        }

        $token = trim($matches[1]);

        return $token === '' ? null : $token;
    }
}
