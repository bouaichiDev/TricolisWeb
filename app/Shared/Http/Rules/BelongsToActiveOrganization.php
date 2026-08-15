<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use App\Shared\Organizations\CurrentOrganizationContext;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Vérifie qu'un identifiant désigne une ressource de l'organisation active.
 *
 * `exists:providers,id` seul laisserait passer le fournisseur d'une autre
 * organisation : la règle refuserait la valeur uniquement si elle n'existe
 * nulle part. Cette règle ajoute le filtre de périmètre, directement dans la
 * validation, avant que l'Action ne soit atteinte.
 */
final readonly class BelongsToActiveOrganization implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     * @param  string|null  $through  relation à traverser lorsque le modèle ne
     *                                porte pas lui-même `organization_id`
     */
    public function __construct(
        private string $model,
        private ?string $through = null,
        private ?string $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $organizationId = $this->activeOrganizationId();

        if ($organizationId === null) {
            $fail('Aucune organisation active : impossible de valider cette référence.');

            return;
        }

        $query = $this->model::query()->whereKey($value);

        if ($this->through === null) {
            $query->where('organization_id', $organizationId);
        } else {
            $query->whereHas($this->through, fn ($related) => $related->where('organization_id', $organizationId));
        }

        if (! $query->exists()) {
            $fail($this->message ?? 'Cette ressource n’est pas accessible dans l’organisation active.');
        }
    }

    private function activeOrganizationId(): ?string
    {
        /** @var CurrentOrganizationContext $context */
        $context = Container::getInstance()->make(CurrentOrganizationContext::class);

        return $context->getOrganizationId();
    }
}
