<?php

namespace App\Http\Requests\Integrations;

use App\Enums\IntegrationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared shape of the integration create/update requests: identity fields
 * plus type-conditional credentials validation (§2.10, via after() — errors
 * are always addressed at `credentials.<field>`).
 */
abstract class IntegrationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(IntegrationType::class)],
            'credentials' => $this->credentialsRules(),
        ];
    }

    /**
     * Presence of the credentials payload: required on create, optional on
     * update (absent = unchanged).
     *
     * @return array<int, string>
     */
    abstract protected function credentialsRules(): array;

    /**
     * Get the after-validation callables (type-conditional credentials).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateCredentials($validator);
            },
        ];
    }

    /**
     * Get the validated credentials (missing optional fields untouched).
     *
     * @return array<string, mixed>|null
     */
    public function credentials(): ?array
    {
        $credentials = $this->input('credentials');

        return is_array($credentials) ? $credentials : null;
    }

    /**
     * Dispatch the conditional validation on the integration type.
     */
    private function validateCredentials(Validator $validator): void
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return;
        }

        match ((string) $this->input('type')) {
            IntegrationType::GenericHttp->value => $this->validateGenericHttp($validator, $credentials),
            IntegrationType::Smtp->value => $this->validateSmtp($validator, $credentials),
            default => null,
        };
    }

    /**
     * Validate the generic_http credentials (baseUrl, auth + conditional fields).
     *
     * @param  array<string, mixed>  $credentials
     */
    private function validateGenericHttp(Validator $validator, array $credentials): void
    {
        $baseUrl = $credentials['baseUrl'] ?? null;

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            $validator->errors()->add('credentials.baseUrl', __('L’URL de base est requise.'));
        } else {
            if (! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
                $validator->errors()->add('credentials.baseUrl', __('L’URL de base doit commencer par http(s)://.'));
            }

            if (mb_strlen($baseUrl) > 2048) {
                $validator->errors()->add('credentials.baseUrl', __('L’URL de base ne doit pas dépasser 2048 caractères.'));
            }
        }

        $auth = $credentials['auth'] ?? null;

        if (! in_array($auth, ['none', 'bearer', 'basic', 'header'], true)) {
            $validator->errors()->add('credentials.auth', __('Le mode d’authentification est invalide.'));
        } elseif ($auth === 'bearer') {
            $this->requireString($validator, $credentials, 'token', __('Le jeton est requis pour l’authentification Bearer.'), 1024);
        } elseif ($auth === 'basic') {
            $this->requireString($validator, $credentials, 'username', __('Le nom d’utilisateur est requis pour l’authentification Basic.'), 255);
            $this->requireString($validator, $credentials, 'password', __('Le mot de passe est requis pour l’authentification Basic.'), 1024);
        } elseif ($auth === 'header') {
            $headerName = $credentials['headerName'] ?? null;

            if (! is_string($headerName) || trim($headerName) === '') {
                $validator->errors()->add('credentials.headerName', __('Le nom d’en-tête est requis.'));
            } else {
                if (mb_strlen($headerName) > 128) {
                    $validator->errors()->add('credentials.headerName', __('Le nom d’en-tête ne doit pas dépasser 128 caractères.'));
                }

                if (preg_match('/^[A-Za-z0-9-]+$/', $headerName) !== 1) {
                    $validator->errors()->add('credentials.headerName', __('Le nom d’en-tête ne doit contenir que des lettres, des chiffres et des tirets.'));
                }
            }

            $this->requireString($validator, $credentials, 'headerValue', __('La valeur d’en-tête est requise.'), 2000);
        }
    }

    /**
     * Validate the smtp credentials (host, port, encryption, optional fields).
     *
     * @param  array<string, mixed>  $credentials
     */
    private function validateSmtp(Validator $validator, array $credentials): void
    {
        $host = $credentials['host'] ?? null;

        if (! is_string($host) || trim($host) === '') {
            $validator->errors()->add('credentials.host', __('L’hôte SMTP est requis.'));
        } else {
            if (str_contains($host, '://')) {
                $validator->errors()->add('credentials.host', __('L’hôte SMTP ne doit pas contenir de schéma.'));
            }

            if (mb_strlen($host) > 255) {
                $validator->errors()->add('credentials.host', __('L’hôte SMTP ne doit pas dépasser 255 caractères.'));
            }
        }

        $port = $credentials['port'] ?? null;

        if (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            $validator->errors()->add('credentials.port', __('Le port doit être un nombre entre 1 et 65535.'));
        }

        $encryption = $credentials['encryption'] ?? 'tls';

        if (! in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $validator->errors()->add('credentials.encryption', __('Le chiffrement est invalide.'));
        }

        $this->optionalString($validator, $credentials, 'username', 255);
        $this->optionalString($validator, $credentials, 'password', 1024);

        $from = $credentials['from'] ?? null;

        if (is_string($from) && $from !== '') {
            if (mb_strlen($from) > 255) {
                $validator->errors()->add('credentials.from', __('L’adresse expéditeur ne doit pas dépasser 255 caractères.'));
            } elseif (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
                $validator->errors()->add('credentials.from', __('L’adresse expéditeur n’est pas valide.'));
            }
        }
    }

    /**
     * Add an error when the field is missing or exceeds its maximum length.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function requireString(Validator $validator, array $credentials, string $field, string $message, int $max): void
    {
        $value = $credentials[$field] ?? null;

        if (! is_string($value) || trim($value) === '') {
            $validator->errors()->add("credentials.{$field}", $message);

            return;
        }

        if (mb_strlen($value) > $max) {
            $validator->errors()->add("credentials.{$field}", __('Le champ « :field » ne doit pas dépasser :max caractères.', ['field' => $field, 'max' => $max]));
        }
    }

    /**
     * Add an error when the optional field exceeds its maximum length.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function optionalString(Validator $validator, array $credentials, string $field, int $max): void
    {
        $value = $credentials[$field] ?? null;

        if (is_string($value) && mb_strlen($value) > $max) {
            $validator->errors()->add("credentials.{$field}", __('Le champ « :field » ne doit pas dépasser :max caractères.', ['field' => $field, 'max' => $max]));
        }
    }
}
