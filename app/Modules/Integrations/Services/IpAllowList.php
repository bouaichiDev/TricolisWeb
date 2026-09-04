<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

/**
 * Une adresse appelante est-elle dans la liste autorisée d'une clé ?
 *
 * `IpOrCidr` vérifie qu'une entrée est *bien écrite* ; ce service répond à
 * l'autre question, celle qui compte à l'exécution : cette requête vient-elle
 * d'une adresse permise. Les deux sont distincts, et confondre l'un avec
 * l'autre laisserait passer n'importe qui.
 *
 * **Une liste vide n'autorise pas rien : elle n'impose aucune restriction.**
 * C'est ce que l'écran annonce à la saisie — « la clé fonctionnera depuis
 * n'importe quelle adresse » — et l'inverse enfermerait dehors toute clé créée
 * sans liste.
 *
 * La comparaison se fait sur les **octets** de l'adresse, ce qui traite IPv4 et
 * IPv6 de la même façon. Comparer des chaînes échouerait dès la première forme
 * abrégée d'IPv6 : `::1` et `0:0:0:0:0:0:0:1` sont la même adresse.
 */
final readonly class IpAllowList
{
    /**
     * @param  list<string>|null  $allowed  adresses et blocs CIDR autorisés
     */
    public function permits(?array $allowed, string $address): bool
    {
        if ($allowed === null || $allowed === []) {
            return true;
        }

        foreach ($allowed as $entry) {
            if ($this->matches((string) $entry, $address)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $entry, string $address): bool
    {
        if (! str_contains($entry, '/')) {
            return $this->sameAddress($entry, $address);
        }

        [$network, $prefix] = explode('/', $entry, 2);

        if (! ctype_digit($prefix)) {
            return false;
        }

        return $this->inNetwork($network, (int) $prefix, $address);
    }

    private function sameAddress(string $left, string $right): bool
    {
        $a = @inet_pton($left);
        $b = @inet_pton($right);

        return $a !== false && $b !== false && $a === $b;
    }

    /**
     * Compare les `prefix` premiers bits de l'adresse et du réseau.
     *
     * Les octets pleins se comparent tels quels ; le dernier, partiel, passe
     * par un masque. Un préfixe plus long que l'adresse — /64 sur de l'IPv4 —
     * ne correspond à rien, plutôt que de déborder.
     */
    private function inNetwork(string $network, int $prefix, string $address): bool
    {
        $networkBytes = @inet_pton($network);
        $addressBytes = @inet_pton($address);

        if ($networkBytes === false || $addressBytes === false) {
            return false;
        }

        // Un réseau IPv4 ne contient pas une adresse IPv6, et réciproquement.
        if (strlen($networkBytes) !== strlen($addressBytes)) {
            return false;
        }

        if ($prefix < 0 || $prefix > strlen($addressBytes) * 8) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && strncmp($networkBytes, $addressBytes, $fullBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;

        return (ord($networkBytes[$fullBytes]) & $mask) === (ord($addressBytes[$fullBytes]) & $mask);
    }
}
