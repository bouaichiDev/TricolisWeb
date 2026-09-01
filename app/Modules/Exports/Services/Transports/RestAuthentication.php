<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services\Transports;

use App\Modules\Exports\Models\CustomerExportConfiguration;
use App\Modules\Exports\Services\RemoteTargetGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Comment la requête sortante s'authentifie chez le client.
 *
 * **Le secret vit toujours dans `encrypted_password`**, quel que soit le mode.
 * Le §72 refuse un jeton en clair dans `settings`, qui est lisible en base et
 * rendu à l'écran ; le champ chiffré est le seul emplacement prévu par le
 * modèle. Ce que `settings` porte, ce sont les options non secrètes — le mode,
 * le nom d'un en-tête, une URL de jeton.
 *
 * Cinq modes, parce que les clients n'ont pas tous la même porte :
 *
 * - `none` — l'URL suffit, souvent complétée d'un secret dans le chemin ;
 * - `bearer` — le cas courant, `Authorization: Bearer <secret>` ;
 * - `basic` — identifiant et secret, encore répandu chez les ERP ;
 * - `api_key` — un en-tête nommé par le client, `X-Api-Key` le plus souvent ;
 * - `oauth2` — échange préalable d'un jeton contre des identifiants client.
 *
 * **Le jeton OAuth2 est mis en cache jusqu'à son expiration.** Le redemander à
 * chaque facture multiplierait les appels et ferait plafonner le client sur ses
 * propres quotas ; la clé de cache ne contient que l'identifiant de la
 * configuration, jamais le secret.
 */
final readonly class RestAuthentication
{
    public const string NONE = 'none';

    public const string BEARER = 'bearer';

    public const string BASIC = 'basic';

    public const string API_KEY = 'api_key';

    public const string OAUTH2 = 'oauth2';

    /** @var list<string> */
    public const array MODES = [self::NONE, self::BEARER, self::BASIC, self::API_KEY, self::OAUTH2];

    /** Une marge : un jeton qui expire pendant l'envoi ne sert à rien. */
    private const int EXPIRY_MARGIN_SECONDS = 60;

    public function __construct(private RemoteTargetGuard $guard) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public function apply(
        PendingRequest $request,
        CustomerExportConfiguration $configuration,
        array $settings,
    ): PendingRequest {
        $secret = $configuration->password();
        $mode = is_string($settings['authType'] ?? null) ? $settings['authType'] : null;

        // Sans mode declare, on garde le comportement historique : un secret
        // present vaut jeton porteur. Le dire autrement casserait les
        // configurations posees avant que les modes n'existent.
        $mode ??= $secret === null || $secret === '' ? self::NONE : self::BEARER;

        return match ($mode) {
            self::NONE => $request,
            self::BASIC => $request->withBasicAuth((string) $configuration->username, (string) $secret),
            self::API_KEY => $request->withHeaders([
                $this->headerName($settings) => (string) $secret,
            ]),
            self::OAUTH2 => $request->withToken($this->oauthToken($configuration, $settings, (string) $secret)),
            default => $request->withToken((string) $secret),
        };
    }

    /**
     * Le nom de l'en-tête qui porte la clé.
     *
     * `Authorization` est refusé : il relève des autres modes, et l'accepter
     * ici laisserait contourner leur logique par un simple réglage.
     *
     * @param  array<string, mixed>  $settings
     */
    private function headerName(array $settings): string
    {
        $name = is_string($settings['apiKeyHeader'] ?? null) ? trim($settings['apiKeyHeader']) : '';

        if ($name === '' || strcasecmp($name, 'Authorization') === 0) {
            return 'X-Api-Key';
        }

        return $name;
    }

    /**
     * Un jeton OAuth2, obtenu par identifiants client.
     *
     * Seul le flux `client_credentials` est admis : c'est celui d'un serveur
     * qui s'authentifie pour lui-même. Les flux à redirection supposent un
     * utilisateur devant un navigateur, ce qu'aucune file d'attente n'a.
     *
     * @param  array<string, mixed>  $settings
     */
    private function oauthToken(
        CustomerExportConfiguration $configuration,
        array $settings,
        string $secret,
    ): string {
        $tokenUrl = is_string($settings['tokenUrl'] ?? null) ? $settings['tokenUrl'] : '';

        if ($tokenUrl === '') {
            throw new RuntimeException('Aucune URL de jeton n’est configurée pour cette destination.');
        }

        // Le serveur de jetons est une destination distante comme une autre :
        // le §125 s'y applique aussi.
        $this->guard->httpUrl($tokenUrl, '');

        return Cache::remember(
            'export-oauth-token:'.$configuration->id,
            $this->lifetime($settings),
            fn (): string => $this->fetchToken($tokenUrl, $configuration, $settings, $secret),
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function fetchToken(
        string $tokenUrl,
        CustomerExportConfiguration $configuration,
        array $settings,
        string $secret,
    ): string {
        $payload = array_filter([
            'grant_type' => 'client_credentials',
            'client_id' => $settings['clientId'] ?? $configuration->username,
            'client_secret' => $secret,
            'scope' => $settings['scope'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $response = Http::withoutRedirecting()->asForm()->timeout(30)->post($tokenUrl, $payload);

        if (! $response->successful()) {
            // Le statut, pas le corps : une reponse d'erreur OAuth2 reprend
            // volontiers la requete, identifiants compris.
            throw new RuntimeException(sprintf(
                'Le serveur de jetons a répondu %d.',
                $response->status(),
            ));
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Le serveur de jetons n’a pas renvoyé de jeton.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function lifetime(array $settings): int
    {
        $declared = $settings['tokenLifetimeSeconds'] ?? null;

        $seconds = is_int($declared) && $declared > self::EXPIRY_MARGIN_SECONDS
            ? $declared
            : 3600;

        return $seconds - self::EXPIRY_MARGIN_SECONDS;
    }
}
