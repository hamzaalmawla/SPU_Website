<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

class EmailValidator
{
    public function normalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false ? $normalized : null;
    }
}
