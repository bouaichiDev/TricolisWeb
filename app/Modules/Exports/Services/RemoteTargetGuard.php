<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use RuntimeException;

/**
 * Décide si une destination configurée par un client est acceptable.
 *
 * **Une URL fournie par un tiers est une arme.** Notre serveur appellerait
 * volontiers `http://127.0.0.1:3306` ou le service de métadonnées d'un
 * hébergeur, avec ses propres droits réseau. Le contrôle porte donc :
 *
 * - sur le schéma (`http`/`https`), l'absence d'identifiants dans l'URL et le
 *   port ;
 * - sur le nom (`localhost`, `*.localhost`, noms de métadonnées) ;
 * - sur **l'adresse réellement résolue** — un domaine public peut pointer vers
 *   une adresse privée, loopback, link-local (169.254.169.254) ou réservée ;
 * - et la résolution retenue peut être **épinglée** (`pinnedResolve`) pour que
 *   l'appel parte vers l'adresse contrôlée, pas vers une seconde réponse DNS.
 *
 * Un hôte interne n'est admis que s'il figure dans
 * `customer_integrations.network.allowed_private_hosts` — décision explicite
 * de l'exploitant, jamais du client. Utilisé par les exports (REST, FTP/SFTP)
 * comme par les imports (pull REST, relève FTP/SFTP).
 */
final readonly class RemoteTargetGuard
{
    /** @var list<string> */
    private const array SCHEMES = ['http', 'https'];

    /** @var list<string> */
    private const array BLOCKED_NAMES = ['localhost', 'metadata', 'metadata.google.internal', 'instance-data'];

    /**
     * @param  (\Closure(string): list<string>)|null  $resolver  résolution DNS remplaçable — les tests n'interrogent aucun serveur réel
     */
    public function __construct(private ?\Closure $resolver = null) {}

    /**
     * URL complète et vérifiée, à partir de la base et d'un chemin.
     */
    public function httpUrl(string $baseUrl, string $path): string
    {
        $base = trim($baseUrl);

        if ($base === '') {
            throw new RuntimeException('Aucune adresse n’est configurée pour cette destination.');
        }

        $parts = parse_url($base);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, self::SCHEMES, true)) {
            throw new RuntimeException('Seules les adresses http et https sont acceptées.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Une adresse ne porte pas d’identifiants : ils se configurent à part.');
        }

        $this->assertPort($parts['port'] ?? null);
        $this->assertPublicHost((string) ($parts['host'] ?? ''));

        return rtrim($base, '/').'/'.ltrim($this->safePath($path), '/');
    }

    /**
     * Vérifie un hôte de transfert de fichiers.
     */
    public function assertFileHost(string $host, ?int $port = null): void
    {
        $this->assertPort($port);
        $this->assertPublicHost(trim($host));
    }

    /**
     * Entrées `CURLOPT_RESOLVE` qui fixent l'adresse contrôlée pour l'appel.
     *
     * @return list<string>
     */
    public function pinnedResolve(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($host === '' || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false || $this->allowedPrivate($host)) {
            return [];
        }

        $addresses = $this->addressesOf($host);
        $port = $parts['port'] ?? (strtolower((string) ($parts['scheme'] ?? '')) === 'http' ? 80 : 443);

        return $addresses === [] ? [] : ["{$host}:{$port}:{$addresses[0]}"];
    }

    /**
     * Un chemin distant sans remontée d'arborescence : `..` est retiré.
     */
    public function safePath(string $path): string
    {
        $segments = array_filter(
            preg_split('#[\\\\/]+#', trim($path)) ?: [],
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..',
        );

        return implode('/', $segments);
    }

    private function assertPort(mixed $port): void
    {
        if ($port !== null && (! is_int($port) || $port < 1 || $port > 65535)) {
            throw new RuntimeException('Le port configuré n’est pas valide.');
        }
    }

    /**
     * L'hôte résout-il vers une adresse publique ?
     *
     * En test, la résolution ne donne rien pour les domaines d'exemple : on
     * n'exige donc pas qu'elle réussisse, seulement qu'elle ne désigne pas une
     * adresse interne quand elle aboutit. Les noms et adresses littérales
     * internes, eux, sont toujours refusés.
     */
    private function assertPublicHost(string $host): void
    {
        $host = strtolower(trim($host, "[]. \t"));

        if ($host === '') {
            throw new RuntimeException('L’adresse configurée ne comporte pas d’hôte.');
        }

        if ($this->allowedPrivate($host)) {
            return;
        }

        if (in_array($host, self::BLOCKED_NAMES, true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            throw new RuntimeException('Cette destination désigne une adresse réseau interne.');
        }

        foreach ($this->addressesOf($host) as $address) {
            if (! $this->isPublic($address)) {
                throw new RuntimeException('Cette destination désigne une adresse réseau interne.');
            }
        }
    }

    private function isPublic(string $address): bool
    {
        // ::ffff:127.0.0.1 est 127.0.0.1 : l'adresse encapsulée est jugée.
        if (preg_match('/^::ffff:(\d{1,3}(\.\d{1,3}){3})$/i', $address, $matches) === 1) {
            $address = $matches[1];
        }

        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false && $address !== '0.0.0.0' && $address !== '::';
    }

    private function allowedPrivate(string $host): bool
    {
        // Hors application (test unitaire pur), aucune exception n'est admise.
        $allowed = app()->bound('config') ? config('customer_integrations.network.allowed_private_hosts', []) : [];

        return is_array($allowed) && in_array($host, array_map('strtolower', $allowed), true);
    }

    /** @return list<string> */
    private function addressesOf(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if ($this->resolver !== null) {
            return ($this->resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }
}
