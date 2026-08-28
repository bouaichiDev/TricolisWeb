<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Transports;

use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\RemoteTargetGuard;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Dépose la facture sur l'API du client.
 *
 * **L'URL vient du client, donc elle est suspecte.** Le §125 impose une
 * protection contre le SSRF : une configuration pointant `http://169.254.169.254`
 * ferait interroger le service de métadonnées de l'hébergeur par notre propre
 * serveur, avec ses droits. `RemoteTargetGuard` tranche avant tout appel.
 *
 * **Aucune redirection suivie.** Une réponse 302 vers un hôte interne
 * contournerait le contrôle qu'on vient de faire.
 *
 * **Le secret ne paraît nulle part.** Ni dans le message d'erreur, ni dans les
 * journaux : le §124 l'interdit, et une exception HTTP contient volontiers
 * l'en-tête qui l'a provoquée.
 */
final readonly class RestApiExportTransporter implements ExportTransporter
{
    /** Les seules méthodes admises — le §70 refuse une méthode arbitraire. */
    private const array METHODS = ['POST', 'PUT'];

    private const int DEFAULT_TIMEOUT = 30;

    public function __construct(private RemoteTargetGuard $guard) {}

    public function send(
        CustomerExportConfiguration $configuration,
        string $fileName,
        string $contents,
        string $contentType,
    ): void {
        $settings = $configuration->settings ?? [];

        $url = $this->guard->httpUrl(
            (string) $configuration->host,
            is_string($settings['endpointPath'] ?? null) ? $settings['endpointPath'] : '',
        );

        $method = strtoupper(is_string($settings['method'] ?? null) ? $settings['method'] : 'POST');

        if (! in_array($method, self::METHODS, true)) {
            throw new RuntimeException(sprintf('Méthode « %s » non autorisée pour un export.', $method));
        }

        $request = Http::withoutRedirecting()
            ->timeout($this->timeout($settings))
            ->withBody($contents, $this->contentType($settings, $contentType))
            ->withHeaders($this->headers($settings));

        $secret = $configuration->password();

        if ($secret !== null && $secret !== '') {
            $request = $request->withToken($secret);
        }

        $response = $request->send($method, $url);

        if ($response->successful()) {
            return;
        }

        // Le statut, pas le corps : une reponse d'erreur reprend souvent la
        // requete, en-tetes compris.
        throw new RuntimeException(sprintf('Le système du client a répondu %d.', $response->status()));
    }

    /** @param array<string, mixed> $settings */
    private function timeout(array $settings): int
    {
        $timeout = $settings['timeoutSeconds'] ?? null;

        return is_int($timeout) && $timeout > 0 && $timeout <= 120 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    /** @param array<string, mixed> $settings */
    private function contentType(array $settings, string $fallback): string
    {
        $declared = $settings['contentType'] ?? null;

        return is_string($declared) && $declared !== '' ? $declared : $fallback;
    }

    /**
     * En-têtes non secrets, déclarés par le client.
     *
     * `Authorization` est refusé ici : le §72 veut que le secret passe par le
     * champ chiffré, pas par un réglage lisible en base et rendu à l'écran.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function headers(array $settings): array
    {
        $declared = is_array($settings['headers'] ?? null) ? $settings['headers'] : [];
        $headers = [];

        foreach ($declared as $name => $value) {
            if (! is_string($name) || ! is_scalar($value)) {
                continue;
            }

            if (strcasecmp($name, 'Authorization') === 0) {
                continue;
            }

            $headers[$name] = (string) $value;
        }

        return $headers;
    }
}
