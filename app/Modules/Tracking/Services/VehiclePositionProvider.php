<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Interroge la télématique de l'organisme pour les positions d'une tournée.
 *
 * **Le secret ne quitte jamais le serveur.** C'est toute la raison d'être de
 * cette classe : le navigateur demande à Tricolis, Tricolis demande au
 * fournisseur. Un jeton posé dans du JavaScript servi au navigateur est lisible
 * par quiconque ouvre les outils de développement — et il donne accès à
 * l'historique de tous les véhicules, pas seulement à celui qu'on regarde.
 *
 * Le format interrogé est celui de Flespi : `GET /gw/channels/{id}/messages`
 * avec un filtre, et l'en-tête `Authorization: FlespiToken …`. Le `baseUrl` et
 * le canal viennent de la configuration, pas du code.
 */
final readonly class VehiclePositionProvider
{
    /** Code de la configuration qui rend les positions. */
    public const string CONFIGURATION_CODE = 'driver_position';

    /**
     * Positions d'une tournée, de la plus ancienne à la plus récente.
     *
     * @return list<array{latitude: float, longitude: float, occurredAt: string|null}>
     */
    public function forReference(OrganizationApiConfiguration $configuration, string $reference, int $limit = 200): array
    {
        $filter = json_encode(['filter' => "Planid={$reference}", 'count' => $limit], JSON_THROW_ON_ERROR);

        try {
            $response = Http::withHeaders($this->headers($configuration))
                ->timeout($configuration->timeout_seconds)
                ->get(rtrim($configuration->base_url, '/').'/gw/channels/'.$reference.'/messages', ['data' => $filter]);
        } catch (\Throwable $exception) {
            // Le fournisseur injoignable ne doit pas faire echouer l'ecran : la
            // commande reste consultable, seule la carte manque.
            Log::warning('Telematique injoignable', ['message' => $exception->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Telematique en erreur', ['status' => $response->status()]);

            return [];
        }

        $configuration->forceFill(['last_used_at' => now()])->saveQuietly();

        return $this->normalise($response->json('result') ?? []);
    }

    /**
     * @return array<string, string>
     */
    private function headers(OrganizationApiConfiguration $configuration): array
    {
        $secret = $configuration->credentials();

        return match ($configuration->auth_type) {
            'bearer' => ['Authorization' => 'Bearer '.$secret],
            // Flespi n'emploie ni Bearer ni un en-tete propre : son schema est
            // « FlespiToken », d'ou ce cas distinct.
            'api_key' => ['Authorization' => 'FlespiToken '.$secret],
            'basic' => ['Authorization' => 'Basic '.base64_encode((string) $secret)],
            default => [],
        };
    }

    /**
     * Ramène des messages hétérogènes à trois champs.
     *
     * Les noms varient d'un fournisseur à l'autre — `position.latitude` chez
     * Flespi, parfois `lat` ailleurs. Ce qui manque est ignoré plutôt
     * qu'interprété : une position sans coordonnées n'est pas une position.
     *
     * @param  array<int, mixed>  $messages
     * @return list<array{latitude: float, longitude: float, occurredAt: string|null}>
     */
    private function normalise(array $messages): array
    {
        $points = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $latitude = $message['position.latitude'] ?? $message['latitude'] ?? null;
            $longitude = $message['position.longitude'] ?? $message['longitude'] ?? null;

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }

            $timestamp = $message['timestamp'] ?? null;

            $points[] = [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'occurredAt' => is_numeric($timestamp)
                    ? now()->setTimestamp((int) $timestamp)->toIso8601String()
                    : null,
            ];
        }

        return $points;
    }
}
