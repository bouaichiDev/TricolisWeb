<?php

declare(strict_types=1);

namespace App\Modules\Templates\Actions;

use App\Modules\Templates\DTOs\TemplateQuery;
use App\Modules\Templates\Models\Template;
use Illuminate\Database\Eloquent\Builder;

/**
 * Choisit le modèle à employer — une seule logique pour toute la plateforme.
 *
 * Deux principes, et rien d'autre :
 *
 * 1. **du plus précis au plus général.** Un modèle propre au client l'emporte
 *    sur le modèle global ; à égalité de client, celui qui vise le service
 *    l'emporte sur le générique.
 * 2. **jamais le modèle d'un tiers.** Les candidats sont soit ceux du client
 *    demandé, soit les globaux. Un modèle appartenant à un autre client n'entre
 *    jamais dans la sélection — c'est ce que le §0.9 interdit explicitement.
 *
 * L'ordre est **déterministe** : à précision égale, `is_default` tranche, puis
 * le code, par ordre alphabétique. Deux appels identiques rendent donc toujours
 * le même modèle, même si l'administrateur en a marqué deux par défaut.
 *
 * Retourne `null` quand rien ne correspond. L'appelant décide alors : le rendu
 * d'une facture retombe sur la mise en page livrée, une communication refuse.
 * Inventer un modèle ici masquerait une configuration absente.
 */
final readonly class ResolveTemplateAction
{
    public function execute(TemplateQuery $query): ?Template
    {
        $builder = Template::query()
            ->where('organization_id', $query->organizationId)
            ->where('template_type', $query->templateType->value)
            ->where('is_active', true);

        // Un canal absent n'est pas « n'importe lequel » : c'est un document.
        // Sans ce `whereNull`, une facture piocherait dans les modèles d'e-mail.
        $channel = $query->channel;
        $builder = $channel === null
            ? $builder->whereNull('channel')
            : $builder->where('channel', $channel->value);

        if ($query->language !== null) {
            $builder->where('language', $query->language);
        }

        return $builder
            ->where(fn (Builder $q) => $this->orNull($q, 'customer_id', $query->customerId))
            ->where(fn (Builder $q) => $this->orNull($q, 'service_id', $query->serviceId))
            ->orderByRaw('CASE WHEN customer_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN service_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->first();
    }

    /**
     * « Le global, ou celui-ci » — jamais celui d'un tiers.
     *
     * @param  Builder<Template>  $query
     */
    private function orNull(Builder $query, string $column, ?string $value): void
    {
        $query->whereNull($column);

        if ($value !== null) {
            $query->orWhere($column, $value);
        }
    }
}
