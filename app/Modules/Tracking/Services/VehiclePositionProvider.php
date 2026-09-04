<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Services;

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Interroge la télématique de l'organisme pour les positions d'une tournée.
 *
 * **Le secret ne quitte jamais le serveur.** C'est toute la raison d'être de
 * cette classe : le navigateur demande à Tricolis, Tricolis demande au
 * fournisseur. Un jeton posé dans du JavaScript servi au navigateur est lisible
 * par quiconque ouvre les outils de développement — et il donne accès à
 * l'historique de tous les véhicules, pas seulement à celui qu'on regarde.
 *
 * **L'appel se décrit dans la table, pas ici.** Le chemin et le gabarit de
 * requête viennent de `settings` ; cette classe sait substituer deux jetons et
 * lire trois champs, rien de plus. Coder Flespi en dur aurait demandé de
 * modifier le code au premier fournisseur suivant — et confondait le *canal* de
 * l'organisme, fixe, avec la *référence de la course*, variable.
 *
 * ```json
 * {
 *   "path": "/gw/channels/1234371/messages",
 *   "queryKey": "data",
 *   "queryTemplate": "{\"filter\":\"Planid={reference}\",\"count\":{limit}}"
 * }
 * ```
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
    public function forReference(
        OrganizationApiConfiguration $configuration,
        string $reference,
        int $limit = 200,
    ): array {
        $settings = $configuration->settings ?? [];
        $path = $settings['path'] ?? null;

        if (! is_string($path) || $path === '') {
            // Sans chemin, il n'y a pas d'appel a faire. Le dire vaut mieux que
            // deviner une adresse et interroger n'importe quoi.
            Log::warning('Telematique sans chemin configure', ['code' => $configuration->code]);

            return [];
        }

        try {
            $response = Http::withHeaders($this->headers($configuration))
                ->timeout($configuration->timeout_seconds)
                ->get(
                    rtrim($configuration->base_url, '/').'/'.ltrim($this->fill($path, $reference, $limit), '/'),
                    $this->query($settings, $reference, $limit),
                );
        } catch (Throwable $exception) {
            // Le fournisseur injoignable ne doit pas faire echouer l'ecran : la
            // commande reste consultable, seule la position manque.
            Log::warning('Telematique injoignable', ['message' => $exception->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Telematique en erreur', ['status' => $response->status()]);

            return [];
        }

        $configuration->forceFill(['last_used_at' => now()])->saveQuietly();

        return $this->normalise($response->json('result') ?? $response->json() ?? []);
    }

    /**
     * Paramètres d'URL, quand la configuration en décrit.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function query(array $settings, string $reference, int $limit): array
    {
        $key = $settings['queryKey'] ?? null;
        $template = $settings['queryTemplate'] ?? null;

        if (! is_string($key) || ! is_string($template)) {
            return [];
        }

        return [$key => $this->fill($template, $reference, $limit)];
    }

    /** Les deux seuls jetons reconnus : la référence de la course, et le nombre voulu. */
    private function fill(string $template, string $reference, int $limit): string
    {
        return str_replace(['{reference}', '{limit}'], [$reference, (string) $limit], $template);
    }

    /**
     * @return array<string, string>
     */
    private function headers(OrganizationApiConfiguration $configuration): array
    {
        $secret = $configuration->credentials();
        $extra = $configuration->headers ?? [];

        $auth = match ($configuration->auth_type) {
            'bearer' => ['Authorization' => 'Bearer '.$secret],
            // Flespi n'emploie ni Bearer ni un en-tete propre : son schema est
            // « FlespiToken », d'ou ce cas distinct.
            'api_key' => ['Authorization' => 'FlespiToken '.$secret],
            'basic' => ['Authorization' => 'Basic '.base64_encode((string) $secret)],
            default => [],
        };

        return [...$extra, ...$auth];
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

            $latitude = $message['position.latitude'] ?? $message['latitude'] ?? $message['lat'] ?? null;
            $longitude = $message['position.longitude'] ?? $message['longitude'] ?? $message['lng'] ?? null;

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
