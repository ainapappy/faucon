<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers applied to every web response, public endpoints
 * included (S12). The three headers are safe on any response.
 */
final class SetSecurityHeaders
{
    public const FrameOptions = 'DENY';

    public const ContentTypeOptions = 'nosniff';

    public const ReferrerPolicy = 'strict-origin-when-cross-origin';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', self::FrameOptions);
        $response->headers->set('X-Content-Type-Options', self::ContentTypeOptions);
        $response->headers->set('Referrer-Policy', self::ReferrerPolicy);

        return $response;
    }
}
