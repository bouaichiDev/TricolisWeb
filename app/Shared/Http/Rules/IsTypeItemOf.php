<?php

declare(strict_types=1);

namespace App\Shared\Http\Rules;

use App\Modules\Types\Models\TypeItem;
use App\Shared\Organizations\CurrentOrganizationContext;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Vérifie qu'une valeur de référentiel vient bien de la source attendue.
 *
 * Depuis la fusion des référentiels, `type_items` héberge les types de
 * véhicule, de colis et de groupage. Un simple contrôle d'appartenance
 * organisationnelle laisserait donc affecter « Palette » à un véhicule : la
 * ligne existe, elle appartient à l'organisation, mais elle vient d'une autre
 * source.
 */
final readonly class IsTypeItemOf implements ValidationRule
{
    public function __construct(
        private string $typeCode,
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

        $exists = TypeItem::whereKey($value)
            ->where('organization_id', $organizationId)
            ->whereHas('type', fn ($query) => $query->where('code', $this->typeCode))
            ->exists();

        if (! $exists) {
            $fail($this->message ?? 'Cette valeur ne fait pas partie du référentiel attendu.');
        }
    }

    private function activeOrganizationId(): ?string
    {
        /** @var CurrentOrganizationContext $context */
        $context = Container::getInstance()->make(CurrentOrganizationContext::class);

        return $context->getOrganizationId();
    }
}
