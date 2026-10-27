<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Sources;

use App\Modules\Exports\Services\RemoteTargetGuard;
use App\Modules\Integrations\Exceptions\PermanentIntegrationFailure;
use App\Modules\Integrations\Exceptions\TransientIntegrationFailure;
use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

/**
 * Un appel GET vers l'API d'un client, et le verdict sur sa réponse.
 *
 * Tenu à chaque appel : destination contrôlée (SSRF) et épinglée, aucune
 * redirection suivie, délais de connexion et de réponse, relance limitée des
 * seules erreurs de connexion, statut HTTP, `Content-Type` JSON, taille
 * plafonnée, JSON valide.
 *
 * **Rien de sensible ne ressort** : ni en-tête d'authentification, ni jeton,
 * ni corps de réponse — seulement le statut HTTP et une raison.
 */
final readonly class InboundHttpClient
{
    public function __construct(private RemoteTargetGuard $guard) {}

    /**
     * @param  array<string, scalar>  $query
     * @return array<mixed>
     */
    public function getJson(OrganizationApiConfiguration $api, RestPullSettings $settings, string $path, array $query): array
    {
        $response = $this->get($api, $settings, $path, $query);

        if (! str_contains(strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new PermanentIntegrationFailure('La réponse du système client n’est pas du JSON.');
        }

        $body = $response->body();

        if (strlen($body) > (int) config('customer_integrations.pull.max_response_bytes', 5 * 1024 * 1024)) {
            throw new PermanentIntegrationFailure('La réponse du système client dépasse la taille autorisée.');
        }

        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PermanentIntegrationFailure('La réponse du système client n’est pas un JSON valide.');
        }

        if (! is_array($decoded)) {
            throw new PermanentIntegrationFailure('La réponse du système client ne contient ni objet ni liste.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    public function get(OrganizationApiConfiguration $api, RestPullSettings $settings, string $path, array $query): Response
    {
        try {
            $url = $this->guard->httpUrl((string) $api->base_url, $path);
        } catch (RuntimeException $exception) {
            throw new PermanentIntegrationFailure($exception->getMessage());
        }

        $timeout = (int) ($api->timeout_seconds ?: config('customer_integrations.pull.timeout_seconds', 30));
        $pinned = $this->guard->pinnedResolve($url);

        try {
            $response = Http::withoutRedirecting()
                ->timeout(max(1, min(120, $timeout)))
                ->connectTimeout((int) config('customer_integrations.pull.connect_timeout_seconds', 10))
                ->acceptJson()
                ->withHeaders($this->headers($api, $settings))
                ->withOptions($pinned === [] ? [] : ['curl' => [CURLOPT_RESOLVE => $pinned]])
                ->retry(2, 500, static fn (mixed $exception): bool => $exception instanceof ConnectionException, throw: false)
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new TransientIntegrationFailure('Le système client est injoignable (délai dépassé ou connexion refusée).');
        }

        return $this->verdict($response);
    }

    private function verdict(Response $response): Response
    {
        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            return $response;
        }

        if ($status === 429 || $status >= 500) {
            throw new TransientIntegrationFailure("Le système client a répondu {$status} : nouvel essai prévu.");
        }

        if ($status >= 300 && $status < 400) {
            throw new PermanentIntegrationFailure("Le système client a répondu par une redirection ({$status}), non suivie.");
        }

        throw new PermanentIntegrationFailure("Le système client a répondu {$status}.");
    }

    /**
     * En-têtes d'appel : ceux de la configuration, sauf `Authorization`, puis
     * l'authentification construite depuis le secret chiffré.
     *
     * @return array<string, string>
     */
    private function headers(OrganizationApiConfiguration $api, RestPullSettings $settings): array
    {
        $headers = [];

        foreach ($api->headers ?? [] as $name => $value) {
            if (is_string($name) && is_scalar($value) && strcasecmp($name, 'Authorization') !== 0) {
                $headers[$name] = (string) $value;
            }
        }

        $secret = $api->hasCredentials() ? (string) $api->credentials() : null;

        if ($secret === null || $secret === '') {
            return $headers;
        }

        return [...$headers, ...match ($api->auth_type) {
            'bearer' => ['Authorization' => 'Bearer '.$secret],
            'basic' => ['Authorization' => 'Basic '.base64_encode($secret)],
            'api_key' => [strcasecmp($settings->apiKeyHeader, 'Authorization') === 0 ? 'X-Api-Key' : $settings->apiKeyHeader => $secret],
            default => [],
        }];
    }
}
