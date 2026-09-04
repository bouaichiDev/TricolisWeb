<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Sources;

use App\Modules\Communications\Enums\CommunicationStatus;
use App\Modules\Communications\Models\OrderCommunication;
use App\Modules\Dashboard\Services\DailySeries;
use App\Modules\Dashboard\Services\DashboardContext;
use App\Modules\Dashboard\Services\DashboardDataSource;
use App\Modules\Dashboard\Services\DashboardPayload;
use App\Shared\Database\MorphMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
    /** Deux semaines pleines : assez pour voir un creux se repeter. */
    private const int COLUMN_DAYS = 14;

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

            // `labels` et non `source` : les canaux viennent d'une enumeration
            // PHP, que personne ne renomme. Le referentiel des statuts ne les
            // connait pas, et l'y chercher aurait rendu des codes bruts.
            'communications_by_channel' => DashboardPayload::donut(
                $this->byChannel($context),
                labels: 'communicationChannels',
            ),

            'communications_per_day' => $this->perDay($context),

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
     * Le volume quotidien des envois, par canal.
     *
     * `created_at` et non `sent_at` : la question est « combien en a-t-on
     * produit ce jour-la », et une communication programmee pour la semaine
     * prochaine compte aujourd'hui. La compter au depart aurait laisse les
     * derniers jours vides, en donnant a croire que rien n'a ete prepare.
     *
     * @return array<string, mixed>
     */
    private function perDay(DashboardContext $context): array
    {
        $rows = $this->communications($context)
            ->toBase()
            ->whereBetween('created_at', $context->window(self::COLUMN_DAYS))
            ->selectRaw('DATE(created_at) as day, channel as code, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'), 'channel')
            ->get();

        $built = DailySeries::build($rows, $context->windowStart(self::COLUMN_DAYS), self::COLUMN_DAYS);

        return DashboardPayload::timeseries(
            $built['buckets'],
            $built['series'],
            labels: 'communicationChannels',
        );
    }

    /**
     * La repartition par canal.
     *
     * Hors d'Eloquent, comme les autres agregats : hydrater un modele pour lire
     * un `GROUP BY` ferait passer `channel` par sa conversion en enumeration,
     * qui leve sur une valeur qu'elle ne connait pas. Un canal retire du code
     * ferait alors echouer le tableau de bord entier, pour une ligne.
     *
     * @return array<int, array{code: string, value: int}>
     */
    private function byChannel(DashboardContext $context): array
    {
        return $this->communications($context)
            ->toBase()
            ->selectRaw('channel, COUNT(*) as total')
            ->groupBy('channel')
            ->orderBy('channel')
            ->get()
            ->map(static fn (object $row): array => [
                'code' => (string) $row->channel,
                'value' => (int) $row->total,
            ])
            ->all();
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
