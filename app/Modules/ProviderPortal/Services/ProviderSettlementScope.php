<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

use App\Modules\Documents\Models\Document;
use App\Modules\ProviderSettlements\Models\ProviderSettlement;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Les décomptes qu'un fournisseur peut lire : les siens, dans son
 * organisation, **clôturés**. Un brouillon est un travail interne dont les
 * montants changent encore ; il répond 404 comme un décompte d'un autre
 * fournisseur. Les montants sont ceux du module financier, jamais recalculés.
 */
final readonly class ProviderSettlementScope
{
    public const string CLOSED = 'closed';

    public function __construct(private CurrentProviderContext $context) {}

    /**
     * @return Builder<ProviderSettlement>
     */
    public function settlements(): Builder
    {
        return ProviderSettlement::query()
            ->where('organization_id', $this->context->organizationId())
            ->where('provider_id', $this->context->providerId())
            ->where('status', self::CLOSED);
    }

    public function settlement(string $id): ProviderSettlement
    {
        return $this->settlements()->whereKey($id)->first()
            ?? throw new NotFoundHttpException('Décompte introuvable.');
    }

    /**
     * Les documents rattachés au décompte, dans la même organisation.
     *
     * @return Builder<Document>
     */
    public function documents(ProviderSettlement $settlement): Builder
    {
        return Document::query()
            ->where('organization_id', $settlement->organization_id)
            ->whereHas('links', static fn (Builder $link) => $link->where('entity_type', MorphMap::PROVIDER_SETTLEMENT)->where('entity_id', $settlement->id));
    }
}
