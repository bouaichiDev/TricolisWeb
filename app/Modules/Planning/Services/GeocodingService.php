<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Addresses\Models\Address;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * Donne ses coordonnées à une adresse qui n'en a pas.
 *
 * **L'adresse existante est mise à jour, jamais dupliquée.** Une seconde ligne
 * pour les mêmes murs ferait diverger ce que deux écrans affichent.
 *
 * **L'appel se décrit dans la table, pas ici.** L'URL, le chemin et le nom du
 * paramètre viennent d'`organization_api_configurations`, comme pour la
 * télématique. Le projet n'a pas de table `configs` ; en créer une donnerait
 * deux référentiels d'API externes à tenir.
 *
 * ```json
 * {
 *   "path": "/TRC_GPS_API_V2/api/values/getLocation",
 *   "queryKey": "adress"
 * }
 * ```
 *
 * `adress` s'écrit bien ainsi : c'est l'orthographe du service appelé, et la
 * corriger ferait échouer la requête.
 */
final readonly class GeocodingService
{
    /** Code de la configuration qui géocode. */
    public const string CONFIGURATION_CODE = 'gps_geocoding';

    /** Nom du paramètre par défaut, celui du service actuel. */
    private const string DEFAULT_QUERY_KEY = 'adress';

    /**
     * Complète les coordonnées d'une adresse, et rend vrai si elle en a.
     *
     * Une adresse déjà située n'est pas redemandée : le service est payant en
     * temps comme en quota, et des murs ne se déplacent pas.
     */
    public function locate(Address $address, string $organizationId): bool
    {
        if ($address->latitude !== null && $address->longitude !== null) {
            return true;
        }

        $configuration = $this->configurationFor($organizationId);

        if ($configuration === null) {
            Log::warning('Géocodage sans configuration', ['organization' => $organizationId]);

            return false;
        }

        $query = $this->describe($address);

        if ($query === '') {
            // Une adresse vide ne se geocode pas : envoyer une chaine vide
            // rendrait un point au hasard, pire qu'un refus.
            return false;
        }

        $coordinates = $this->ask($configuration, $query);

        if ($coordinates === null) {
            return false;
        }

        [$latitude, $longitude] = $coordinates;

        $address->forceFill(['latitude' => $latitude, 'longitude' => $longitude])->save();

        return true;
    }

    /**
     * Adresse écrite en une ligne, à partir des champs renseignés.
     *
     * `name` n'y entre pas : « Entrepôt nord » ne situe rien, et l'envoyer
     * ferait dériver le résultat vers un homonyme.
     */
    public function describe(Address $address): string
    {
        $parts = [
            trim(($address->address_number ?? '').' '.($address->route ?? '')),
            $address->address_line_1,
            $address->address_line_2,
            trim(($address->postal_code ?? '').' '.($address->city ?? '')),
            $address->country,
        ];

        $kept = array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== '');

        return implode(', ', $kept);
    }

    private function configurationFor(string $organizationId): ?OrganizationApiConfiguration
    {
        return OrganizationApiConfiguration::where('organization_id', $organizationId)
            ->where('code', self::CONFIGURATION_CODE)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function ask(OrganizationApiConfiguration $configuration, string $query): ?array
    {
        $settings = $configuration->settings ?? [];
        $path = is_string($settings['path'] ?? null) ? $settings['path'] : '';
        $key = is_string($settings['queryKey'] ?? null) ? $settings['queryKey'] : self::DEFAULT_QUERY_KEY;

        try {
            $response = Http::timeout($configuration->timeout_seconds)
                ->get(rtrim($configuration->base_url, '/').'/'.ltrim($path, '/'), [$key => $query]);
        } catch (Throwable $exception) {
            Log::warning('Géocodage injoignable', ['message' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Géocodage en erreur', ['status' => $response->status()]);

            return null;
        }

        return $this->parse($response->body());
    }

    /**
     * Lit `<Result><Lat/><Lng/></Result>`.
     *
     * Le XML est analysé ici, jamais dans le navigateur : le §77 l'exige, et
     * cela évite d'exposer la forme du service à l'interface.
     *
     * @return array{0: float, 1: float}|null
     */
    public function parse(string $body): ?array
    {
        try {
            $xml = new SimpleXMLElement($body);
        } catch (Throwable) {
            Log::warning('Géocodage : réponse illisible');

            return null;
        }

        $latitude = (string) ($xml->Lat ?? '');
        $longitude = (string) ($xml->Lng ?? '');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        // Hors de ces bornes, ce n'est pas un point de la Terre. Le service
        // rend parfois 0,0 pour une adresse qu'il n'a pas trouvee : ce point
        // existe, au large du Ghana, et l'accepter afficherait un client
        // en plein ocean.
        if (abs($latitude) > 90.0 || abs($longitude) > 180.0) {
            return null;
        }

        if ($latitude === 0.0 && $longitude === 0.0) {
            return null;
        }

        return [$latitude, $longitude];
    }
}
