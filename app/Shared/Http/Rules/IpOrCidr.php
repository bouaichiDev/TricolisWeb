<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Adresse IP ou bloc CIDR.
 *
 * Le §14 note que le diagramme ne précise pas la structure d'`allowedIps` et
 * interdit de l'inventer silencieusement. **Structure retenue et documentée :
 * une liste plate de chaînes**, chacune une adresse ou un bloc.
 *
 * Un bloc CIDR est accepté parce qu'une intégration sort rarement d'une seule
 * adresse : imposer l'énumération ferait écrire des listes de 256 entrées.
 */
final readonly class IpOrCidr implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Chaque entrée doit être une adresse IP ou un bloc CIDR.');

            return;
        }

        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return;
        }

        if (! $this->isValidCidr($value)) {
            $fail("« {$value} » n’est ni une adresse IP ni un bloc CIDR valide.");
        }
    }

    private function isValidCidr(string $value): bool
    {
        if (substr_count($value, '/') !== 1) {
            return false;
        }

        [$address, $prefix] = explode('/', $value);

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (! ctype_digit($prefix)) {
            return false;
        }

        $max = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return (int) $prefix >= 0 && (int) $prefix <= $max;
    }
}
