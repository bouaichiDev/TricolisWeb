<?php

declare(strict_types=1);

namespace App\Modules\Exports\Services;

use RuntimeException;

/**
 * Décide si une destination configurée par un client est acceptable.
 *
 * **Une URL fournie par un tiers est une arme.** Le §125 exige une protection
 * contre le SSRF : notre serveur appellerait volontiers `http://127.0.0.1:3306`
 * ou le service de métadonnées d'un hébergeur, avec ses propres droits réseau et
 * sans qu'aucun pare-feu ne s'y oppose.
 *
 * Le contrôle porte sur le schéma, puis sur l'adresse réellement résolue — un
 * nom de domaine public peut pointer vers une adresse privée, et c'est
 * exactement la manœuvre qu'on veut refuser.
 *
 * Les chemins sont contrôlés séparément : le §80 veut qu'un `remoteDirectory` ne
 * remonte pas l'arborescence du serveur du client.
 */
final readonly class RemoteTargetGuard
{
    /** @var list<string> */
    private const array SCHEMES = ['http', 'https'];

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

        $this->assertPublicHost((string) ($parts['host'] ?? ''));

        return rtrim($base, '/').'/'.ltrim($this->safePath($path), '/');
    }

    /**
     * Vérifie un hôte de transfert de fichiers.
     *
     * Même raisonnement que pour REST : un serveur FTP « du client » qui répond
     * sur `localhost` est notre propre machine.
     */
    public function assertFileHost(string $host): void
    {
        $this->assertPublicHost(trim($host));
    }

    /**
     * Un chemin distant sans remontée d'arborescence.
     *
     * `..` est retiré, et non signalé : un chemin mal saisi ne doit pas faire
     * échouer un envoi, mais il ne doit surtout pas sortir du répertoire prévu.
     */
    public function safePath(string $path): string
    {
        $segments = array_filter(
            preg_split('#[\\\\/]+#', trim($path)) ?: [],
            static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..',
        );

        return implode('/', $segments);
    }

    /**
     * L'hôte résout-il vers une adresse publique ?
     *
     * En environnement de test, la résolution ne donne rien pour les domaines
     * d'exemple : on n'exige donc pas qu'elle réussisse, seulement qu'elle ne
     * désigne pas une adresse interne quand elle aboutit. Refuser l'inconnu
     * rendrait toute suite de tests impossible sans réseau.
     */
    private function assertPublicHost(string $host): void
    {
        if ($host === '') {
            throw new RuntimeException('L’adresse configurée ne comporte pas d’hôte.');
        }

        foreach ($this->addressesOf($host) as $address) {
            $public = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($public === false) {
                throw new RuntimeException('Cette destination désigne une adresse réseau interne.');
            }
        }
    }

    /** @return list<string> */
    private function addressesOf(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }
}
