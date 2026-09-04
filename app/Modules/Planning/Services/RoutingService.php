<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Planning\DTOs\RouteLeg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * Distance et durée entre des points, dans l'ordre donné.
 *
 * **Aucune table n'est créée.** Le résultat est un objet de transport : il
 * alimente `tours.distance_meters` et les périodes, qui existent déjà.
 *
 * **Le calcul se fait par paires.** Le service appelé rend un total, sans
 * détail par segment ; or l'écran doit afficher la distance entre chaque arrêt
 * (§95). Additionner des paires donne les deux : le segment et le total. Un
 * appel global ne donnerait que le second.
 *
 * L'URL et le profil viennent d'`organization_api_configurations` :
 *
 * ```json
 * {
 *   "path": "/TRC_GPS_API_V2/api/values/calculateRoute",
 *   "profile": "truckfast"
 * }
 * ```
 */
final readonly class RoutingService
{
    /** Code de la configuration qui calcule les itinéraires. */
    public const string CONFIGURATION_CODE = 'gps_routing';

    /** Profil par défaut, celui du service actuel. */
    private const string DEFAULT_PROFILE = 'truckfast';

    /** Entre deux points fixes, la route ne change pas d'un jour a l'autre. */
    private const int CACHE_DAYS = 30;

    /**
     * Segments successifs entre les points fournis.
     *
     * @param  list<array{0: float, 1: float}>  $points  latitude, longitude, dans l'ordre
     * @return list<RouteLeg> un élément de moins que de points ; vide si le calcul échoue
     */
    public function legs(array $points, string $organizationId): array
    {
        if (count($points) < 2) {
            return [];
        }

        $configuration = $this->configurationFor($organizationId);

        if ($configuration === null) {
            Log::warning('Itinéraire sans configuration', ['organization' => $organizationId]);

            return [];
        }

        $legs = [];

        for ($index = 0; $index < count($points) - 1; $index++) {
            $leg = $this->remembered($configuration, $points[$index], $points[$index + 1]);

            if ($leg === null) {
                // Un segment manquant rendrait le total faux sans le dire :
                // mieux vaut ne rien annoncer que d'annoncer trop court.
                return [];
            }

            $legs[] = $leg;
        }

        return $legs;
    }

    /**
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    /**
     * Un segment, repris du cache quand la même paire a déjà été demandée.
     *
     * C'est ce qui permet de calculer pendant la planification plutôt qu'en
     * file d'attente. Ajouter un arrêt à une tournée de douze n'appelle plus le
     * service distant onze fois mais une : les dix autres paires n'ont pas
     * bougé. Réordonner n'appelle que pour les paires que l'ordre crée.
     *
     * **Rien n'est mis en cache quand l'appel échoue.** Une panne passagère du
     * service figerait sinon une tournée sans itinéraire pour la journée.
     *
     * La durée retenue est longue : entre deux points fixes, la route ne change
     * pas d'un jour à l'autre. Le trafic, lui, n'entre pas dans ce que l'on
     * conserve — {@see RouteLeg} le porte à part, et il n'est pas persisté.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    private function remembered(
        OrganizationApiConfiguration $configuration,
        array $from,
        array $to,
    ): ?RouteLeg {
        $settings = $configuration->settings ?? [];
        $profile = is_string($settings['profile'] ?? null) ? $settings['profile'] : self::DEFAULT_PROFILE;

        $key = sprintf(
            'routing:%s:%s:%s',
            $configuration->organization_id,
            $profile,
            $this->waypoints($from, $to),
        );

        $cached = Cache::get($key);

        if ($cached instanceof RouteLeg) {
            return $cached;
        }

        $leg = $this->between($configuration, $from, $to);

        if ($leg !== null) {
            Cache::put($key, $leg, self::CACHE_DAYS * 86400);
        }

        return $leg;
    }

    private function between(OrganizationApiConfiguration $configuration, array $from, array $to): ?RouteLeg
    {
        $settings = $configuration->settings ?? [];
        $path = is_string($settings['path'] ?? null) ? $settings['path'] : '';
        $profile = is_string($settings['profile'] ?? null) ? $settings['profile'] : self::DEFAULT_PROFILE;

        try {
            $response = Http::timeout($configuration->timeout_seconds)
                ->get(rtrim($configuration->base_url, '/').'/'.ltrim($path, '/'), [
                    'profile' => $profile,
                    'waypoints' => $this->waypoints($from, $to),
                ]);
        } catch (Throwable $exception) {
            Log::warning('Itinéraire injoignable', ['message' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Itinéraire en erreur', ['status' => $response->status()]);

            return null;
        }

        return $this->parse($response->body());
    }

    /**
     * `wy{lat}~{lng}*wy{lat}~{lng}` — la forme attendue par le service.
     *
     * @param  array{0: float, 1: float}  $from
     * @param  array{0: float, 1: float}  $to
     */
    public function waypoints(array $from, array $to): string
    {
        return sprintf('wy%s~%s*wy%s~%s', $from[0], $from[1], $to[0], $to[1]);
    }

    /**
     * Lit `<Result><Distance/><TravelTime/>…</Result>`.
     *
     * **Les unités sont mètres et secondes.** Vérifié sur la réponse de
     * référence Paris → Lyon : `Distance = 465536` et `TravelTime = 23611`,
     * soit 465 km en 6 h 33 — cohérent avec le trajet réel, et incompatible
     * avec toute autre lecture. Un test fige cette réponse.
     */
    public function parse(string $body): ?RouteLeg
    {
        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            Log::warning('Itinéraire : réponse illisible');

            return null;
        }

        $distance = (string) ($xml->Distance ?? '');
        $travel = (string) ($xml->TravelTime ?? '');

        if (! is_numeric($distance) || ! is_numeric($travel)) {
            return null;
        }

        return new RouteLeg(
            distanceMeters: (int) $distance,
            travelSeconds: (int) $travel,
            trafficSeconds: is_numeric((string) ($xml->TrafficTime ?? '')) ? (int) $xml->TrafficTime : null,
            baseSeconds: is_numeric((string) ($xml->BaseTime ?? '')) ? (int) $xml->BaseTime : null,
        );
    }

    private function configurationFor(string $organizationId): ?OrganizationApiConfiguration
    {
        return OrganizationApiConfiguration::where('organization_id', $organizationId)
            ->where('code', self::CONFIGURATION_CODE)
            ->where('is_active', true)
            ->first();
    }
}
