<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use RedirectsToCurrentTeam;

    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse([
                'redirect' => redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')))->getTargetUrl(),
            ], 200)
            : redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }
}
