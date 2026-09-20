<?php

namespace App\Enums;

enum IntegrationType: string
{
    case GenericHttp = 'generic_http';
    case Smtp = 'smtp';

    /**
     * Get the display label for the integration type.
     */
    public function label(): string
    {
        return match ($this) {
            self::GenericHttp => 'HTTP générique',
            self::Smtp => 'SMTP / E-mail',
        };
    }
}
