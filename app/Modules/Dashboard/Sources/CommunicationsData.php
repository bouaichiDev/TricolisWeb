<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;

/**
 * Communications envoyées aux clients.
 *
 * Les statuts viennent de `CommunicationStatus`, l'énumération réelle. « En
 * attente » y recouvre deux états que la mécanique sépare — programmée, puis
 * mise en file — et le compteur les additionne : celui qui regarde son tableau
 * de bord veut savoir combien partent bientôt, pas où elles en sont dans le
 * traitement.
 *
 * `sent_at` sert au compteur du jour, et non `created_at` : une communication
 * préparée hier soir et partie ce matin a été envoyée aujourd'hui.
 */
final readonly class CommunicationsData implements DashboardDataSource
{
    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function resolve(array $keys, DashboardContext $context): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->resolveOne($key, $context);
        }

        return $data;
    }

    private function resolveOne(string $key, DashboardContext $context): mixed
    {
        return match ($key) {
            'communications_scheduled' => DashboardPayload::kpi(
                $this->communications($context)->whereIn('status', [
                    CommunicationStatus::SCHEDULED->value,
                    CommunicationStatus::QUEUED->value,
                ])->count()
            ),
            'communications_failed' => DashboardPayload::alert(
                $this->communications($context)->where('status', CommunicationStatus::FAILED->value)->count()
            ),

            // « Envoyée » couvre les trois états qui suivent le départ :
            // remise et lecture ne l'annulent pas. Ne compter que `sent`
            // aurait fait baisser le chiffre du jour à mesure que les accusés
            // de réception arrivaient.
            'communications_sent_today' => DashboardPayload::kpi(
                $this->communications($context)
                    ->whereIn('status', [
                        CommunicationStatus::SENT->value,
                        CommunicationStatus::DELIVERED->value,
                        CommunicationStatus::READ->value,
                    ])
                    ->whereBetween('sent_at', $context->dayBounds())
                    ->count()
            ),

            'recent_communications' => DashboardPayload::list($this->recent($context)),

            default => null,
        };
    }

    /**
     * @return Builder<OrderCommunication>
     */
    private function communications(DashboardContext $context): Builder
    {
        return OrderCommunication::query()->where('organization_id', $context->organizationId);
    }

    /**
     * Les six dernières, sans leur contenu.
     *
     * `body` est un `longText` qui porte le message rendu, destinataire compris.
     * Une carte de tableau de bord n'en a pas l'usage, et le transporter aurait
     * mis six courriels complets dans une réponse qui tient en quelques lignes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recent(DashboardContext $context): array
    {
        return $this->communications($context)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'recipient_name', 'channel', 'status', 'created_at'])
            ->map(static fn (OrderCommunication $communication): array => [
                'id' => $communication->getKey(),
                'title' => $communication->getAttribute('recipient_name'),
                'subtitle' => $communication->getAttribute('channel')?->value,
                'status' => $communication->getAttribute('status')?->value,
                'statusSource' => MorphMap::ORDER_COMMUNICATION,
                'date' => $communication->getAttribute('created_at')?->toIso8601String(),
                'route' => '/communications/history',
            ])
            ->all();
    }
}
