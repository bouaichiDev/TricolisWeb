<?php

declare(strict_types=1);

namespace App\Modules\ProviderSettlements\DTOs;

/**
 * Données de création d'un décompte fournisseur, lignes comprises.
 *
 * `subtotal` et `total` sont dérivés des lignes. `taxTotal` est en revanche
 * **saisi** : le §21 interdit d'inventer une TVA fournisseur, et le modèle ne
 * porte aucun taux au niveau ligne.
 */
final readonly class CreateProviderSettlementData
{
    /**
     * @param  list<CreateProviderSettlementLineData>  $lines
     */
    public function __construct(
        public string $providerId,
        public string $settlementNumber,
        public string $status,
        public array $lines,
        public string $taxTotal = '0',
        public ?string $periodFrom = null,
        public ?string $periodTo = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            providerId: $validated['providerId'],
            settlementNumber: $validated['settlementNumber'],
            status: $validated['status'],
            lines: array_map(
                static fn (array $line): CreateProviderSettlementLineData => CreateProviderSettlementLineData::fromValidated($line),
                $validated['lines'],
            ),
            taxTotal: (string) ($validated['taxTotal'] ?? '0'),
            periodFrom: $validated['periodFrom'] ?? null,
            periodTo: $validated['periodTo'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(string $organizationId): array
    {
        return [
            'organization_id' => $organizationId,
            'provider_id' => $this->providerId,
            'settlement_number' => $this->settlementNumber,
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'tax_total' => $this->taxTotal,
            'status' => $this->status,
        ];
    }
}
