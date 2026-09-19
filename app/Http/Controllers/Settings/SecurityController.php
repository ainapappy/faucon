<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        return Inertia::render('settings/Security', $this->securityProps($request));
    }

    /**
     * Build the security page props.
     *
     * @return array{
     *     passwordRules: string,
     *     canManageTwoFactor: bool,
     *     twoFactorEnabled?: bool,
     *     requiresConfirmation?: bool,
     *     canManagePasskeys: bool,
     *     passkeys?: list<array{id: int, name: string, lastUsedAt: string|null}>
     * }
     */
    private function securityProps(TwoFactorAuthenticationRequest $request): array
    {
        $request->ensureStateIsValid();

        $props = [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Fortify::confirmsTwoFactorAuthentication();
        }

        if (Features::canManagePasskeys()) {
            $passkeys = [];

            foreach ($request->user()->passkeys()->orderBy('created_at')->get(['id', 'name', 'last_used_at']) as $passkey) {
                $passkeys[] = [
                    'id' => $passkey->id,
                    'name' => $passkey->name,
                    'lastUsedAt' => $passkey->last_used_at?->toIso8601String(),
                ];
            }

            $props['passkeys'] = $passkeys;
        }

        return $props;
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
