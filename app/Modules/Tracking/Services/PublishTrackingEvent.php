<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Orders\Models\Order;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\TrackingEventDefinition;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Model;

/**
 * Publie l'étape du parcours qu'un changement de statut vient de franchir.
 *
 * Rien n'est publié si l'organisation n'a pas décrit d'étape pour ce couple
 * (table, statut) : le parcours est une décision de configuration, pas un effet
 * automatique de tout changement de statut.
 */
final readonly class PublishTrackingEvent
{
    /**
     * @return bool Vrai si une étape a été publiée.
     */
    public function forStatus(Model $source, string $status): bool
    {
        $alias = MorphMap::aliasFor($source::class);
        $orderId = $this->orderOf($source, $alias);

        if ($alias === null || $orderId === null) {
            return false;
        }

        // L'organisation vient de la commande : `order_services` et `packages`
        // n'en portent pas, et la deduire de la session serait faux dans un job.
        $organizationId = Order::whereKey($orderId)->value('organization_id');

        if (! is_string($organizationId)) {
            return false;
        }

        // L'etape de la prestation l'emporte sur l'etape generale : une
        // organisation qui a decrit le parcours de sa livraison n'attend pas
        // que le chargement produise les memes evenements. `service_id` nul
        // vaut « toutes les prestations », et ne sert que faute de mieux.
        $definition = TrackingEventDefinition::query()
            ->where('organization_id', $organizationId)
            ->where('source_type', $alias)
            ->where('status_code', $status)
            ->where('active', true)
            ->where(fn ($query) => $query
                ->whereNull('service_id')
                ->orWhere('service_id', $this->serviceOf($source, $alias)))
            ->orderByRaw('service_id IS NULL')
            ->first();

        if ($definition === null) {
            return false;
        }

        // Un evenement par couple (commande, code) : repasser par un statut deja
        // franchi ne reecrit pas l'histoire. Le parcours dit ou on en est, pas
        // combien de fois on y est passe.
        //
        // **Sauf quand l'etape vise une prestation** : la garde se resserre
        // alors sur elle. Sans cela, un « livre » publie par le chargement sous
        // une configuration plus ancienne empechait la livraison de produire le
        // sien, et l'etape restait a jamais marquee du mauvais moment.
        $already = TrackingEvent::query()
            ->where('order_id', $orderId)
            ->where('event_type', $definition->code)
            ->when(
                $definition->service_id !== null,
                fn ($query) => $query->where('order_service_id', $source->getKey()),
            )
            ->exists();

        if ($already) {
            return false;
        }

        TrackingEvent::create([
            'organization_id' => $organizationId,
            'order_id' => $orderId,
            'order_service_id' => $alias === MorphMap::ORDER_SERVICE ? $source->getKey() : null,
            'event_type' => $definition->code,
            'status' => $status,
            'description' => $definition->description,
            'occurred_at' => now(),
        ]);

        return true;
    }

    /**
     * La prestation du catalogue derriere cette entite, quand il y en a une.
     *
     * Seul un `order_service` en porte : un colis ou une commande n'appartient
     * a aucune prestation en particulier, et leurs etapes restent generales.
     */
    private function serviceOf(Model $source, ?string $alias): ?string
    {
        if ($alias !== MorphMap::ORDER_SERVICE) {
            return null;
        }

        $serviceId = $source->getAttribute('service_id');

        return is_string($serviceId) ? $serviceId : null;
    }

    /**
     * Commande a laquelle l'entite se rattache.
     *
     * Une entite sans commande — un vehicule, un depot — ne produit aucune
     * etape : le parcours est celui d'une commande.
     */
    private function orderOf(Model $source, ?string $alias): ?string
    {
        $value = $alias === MorphMap::ORDER
            ? $source->getKey()
            : $source->getAttribute('order_id');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
