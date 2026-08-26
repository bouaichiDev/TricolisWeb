<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use App\Modules\Statuses\Models\Status;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Vérifie qu'un statut est décrit au référentiel, pour la bonne entité.
 *
 * Les colonnes `status` restent des chaînes — c'est la règle du projet, et elle
 * ne change pas ici. Ce qui change, c'est qu'on n'y écrit plus n'importe quoi :
 * le code doit exister dans `statuses` sous la `source` de l'entité, et y être
 * actif.
 *
 * **La source compte autant que le code.** « active » ne veut pas dire la même
 * chose pour un fournisseur et pour une commande, et un référentiel qui les
 * confondrait laisserait affecter à un véhicule l'état d'une réclamation.
 *
 * Un statut désactivé est refusé à l'écriture mais reste lisible : une donnée
 * ancienne garde le sien, sans quoi la désactivation d'un code réécrirait
 * silencieusement l'histoire.
 */
final readonly class ExistsInStatusReferential implements ValidationRule
{
    /**
     * @param  string  $source  alias de morph map — `provider`, `driver`, `vehicle`…
     */
    public function __construct(private string $source) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $exists = Status::where('source', $this->source)
            ->where('code', $value)
            ->where('active', true)
            ->exists();

        if (! $exists) {
            $fail(sprintf(
                'Le statut « %s » n’est pas défini pour cette entité. Consultez le référentiel des statuts.',
                $value,
            ));
        }
    }
}
