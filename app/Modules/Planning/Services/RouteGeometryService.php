<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Le tracé routier entre des points, quand un fournisseur sait le rendre.
 *
 * **Un second fournisseur, et c'est assumé.** Le service GPS du projet rend
 * `Distance`, `TravelTime`, `TrafficTime`, `BaseTime` — et rien d'autre : ni
 * polyligne, ni points intermédiaires, quel que soit le paramètre essayé. Le
 * §101 le constate et ouvre la porte : « si un fournisseur de carte renvoie plus
 * tard une geometry validée, elle pourra être utilisée. »
 *
 * **Les distances restent celles du service du projet.** Ce fournisseur-ci ne
 * sert qu'à dessiner : mélanger deux sources de distance ferait diverger le
 * tracé du chiffre affiché, et c'est le chiffre qui engage.
 *
 * **Aucune table.** Le §117 interdit d'inventer une entité `RouteGeometry` ; le
 * tracé se recalcule à la demande et ne vit qu'en cache.
 *
 * L'URL vient d'`organization_api_configurations`, comme les deux autres :
 *
 * ```json
 * {
 *   "path": "/route/v1/driving/{coordinates}",
 *   "query": { "overview": "full", "geometries": "geojson" }
 * }
 * ```
 *
 * `{coordinates}` est remplacé par `lon,lat;lon,lat…` — l'ordre d'OSRM, qui
 * met la longitude d'abord.
 */
final readonly class RouteGeometryService
{
    /** Code de la configuration qui rend un tracé. */
    public const string CONFIGURATION_CODE = 'gps_route_geometry';

    /**
     * Points du tracé, dans l'ordre, en `[latitude, longitude]`.
     *
     * Rend `[]` quand aucun fournisseur n'est déclaré, qu'il ne répond pas, ou
     * que sa réponse est illisible : l'écran retombe alors sur ses segments à
     * vol d'oiseau, et le dit.
     *
     * @param  list<array{0: float, 1: float}>  $points  latitude, longitude, dans l'ordre
     * @return list<array{0: float, 1: float}>
     */
    public function trace(array $points, string $organizationId): array
    {
        if (count($points) < 2) {
            return [];
        }

        $configuration = $this->configurationFor($organizationId);

        if ($configuration === null) {
            return [];
        }

        $settings = $configuration->settings ?? [];
        $path = is_string($settings['path'] ?? null) ? $settings['path'] : '';
        $query = is_array($settings['query'] ?? null) ? $settings['query'] : [];

        $url = rtrim($configuration->base_url, '/').'/'.ltrim(
            str_replace('{coordinates}', $this->coordinates($points), $path),
            '/',
        );

        try {
            $response = Http::timeout($configuration->timeout_seconds)->get($url, $query);
        } catch (Throwable $exception) {
            Log::warning('Tracé injoignable', ['message' => $exception->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Tracé en erreur', ['status' => $response->status()]);

            return [];
        }

        return $this->parse($response->json() ?? []);
    }

    /**
     * `lon,lat;lon,lat` — la longitude d'abord, comme OSRM l'attend.
     *
     * L'inverse de l'ordre usuel : une erreur ici pose la tournée en Somalie
     * sans rien signaler.
     *
     * @param  list<array{0: float, 1: float}>  $points
     */
    public function coordinates(array $points): string
    {
        return implode(';', array_map(
            static fn (array $point): string => sprintf('%s,%s', $point[1], $point[0]),
            $points,
        ));
    }

    /**
     * Lit `routes[0].geometry.coordinates`, en `[lon, lat]`.
     *
     * @param  array<mixed>  $body
     * @return list<array{0: float, 1: float}>
     */
    public function parse(array $body): array
    {
        $coordinates = data_get($body, 'routes.0.geometry.coordinates');

        if (! is_array($coordinates) || $coordinates === []) {
            return [];
        }

        $trace = [];

        foreach ($coordinates as $pair) {
            if (! is_array($pair) || ! isset($pair[0], $pair[1]) || ! is_numeric($pair[0]) || ! is_numeric($pair[1])) {
                return [];
            }

            // Retour a l'ordre latitude/longitude, celui de tout le reste du
            // projet : laisser passer l'ordre d'OSRM contaminerait la carte.
            $trace[] = [(float) $pair[1], (float) $pair[0]];
        }

        return $trace;
    }

    private function configurationFor(string $organizationId): ?OrganizationApiConfiguration
    {
        return OrganizationApiConfiguration::where('organization_id', $organizationId)
            ->where('code', self::CONFIGURATION_CODE)
            ->where('is_active', true)
            ->first();
    }
}
