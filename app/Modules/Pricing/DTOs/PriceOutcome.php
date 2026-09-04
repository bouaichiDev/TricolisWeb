<?php

declare(strict_types=1);

namespace App\Modules\Pricing\DTOs;

/**
 * Le résultat d'un calcul tarifaire — prix, ou raison de son absence.
 *
 * **« Pas de tarif » n'est pas zéro.** Le §169B et le §169AJ l'exigent : zéro
 * reste un prix qu'une formule peut produire volontairement, et le confondre
 * avec l'absence de barème ferait partir des factures à zéro sans que personne
 * ne s'en aperçoive. L'issue porte donc explicitement son état.
 */
final readonly class PriceOutcome
{
    private function __construct(
        public bool $priced,
        public ?string $amount,
        public ?ResolvedPricing $pricing,
        public ?string $reason,
        /** @var array<string, string|null> */
        public array $variables = [],
    ) {}

    /**
     * @param  array<string, string|null>  $variables
     */
    public static function priced(string $amount, ResolvedPricing $pricing, array $variables): self
    {
        return new self(true, $amount, $pricing, null, $variables);
    }

    /** Aucune règle ne couvre cette prestation. */
    public static function notConfigured(): self
    {
        return new self(false, null, null, 'Tarif non configuré', []);
    }

    /**
     * Une règle existe, mais sa formule n'a pas pu être calculée.
     *
     * Distinct du cas précédent : ici quelqu'un a écrit un tarif, et il faut le
     * corriger — pas en créer un.
     *
     * @param  array<string, string|null>  $variables
     */
    public static function failed(string $reason, ResolvedPricing $pricing, array $variables): self
    {
        return new self(false, null, $pricing, $reason, $variables);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'priced' => $this->priced,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'variables' => $this->variables,
            'pricing' => $this->pricing?->toArray(),
        ];
    }
}
