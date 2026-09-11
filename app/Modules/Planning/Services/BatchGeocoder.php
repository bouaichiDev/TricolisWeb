<?php

declare(strict_types=1);

namespace App\Modules\Planning\Services;

use App\Modules\Addresses\Models\Address;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Situe **plusieurs** adresses pendant une requête, sans jamais la faire tomber.
 *
 * `GeocodingService` situe une adresse à la fois, et c'est juste pour un
 * formulaire. Pour un import, c'est ce qui a cassé : trente appels d'environ une
 * seconde chacun, l'un après l'autre, et PHP coupe la requête à trente secondes —
 * **après** que les commandes ont été écrites. L'écran annonçait alors un échec
 * pour un import réussi.
 *
 * Deux garde-fous, donc :
 *
 * - **en parallèle**, par paquets : trente adresses se situent en quelques
 *   secondes au lieu de trente ;
 * - **un budget de temps** : ce qui ne tient pas dedans n'est pas tenté et
 *   revient `pending`. Le Job mis en file à la création de la commande s'en
 *   chargera — il est déjà là, et il ignore une adresse déjà située.
 *
 * Un échec du service laisse l'adresse sans point, jamais avec un point faux :
 * la lecture de la réponse est celle de `GeocodingService::parse()`.
 */
final readonly class BatchGeocoder
{
    /** Appels simultanés : assez pour aller vite, pas assez pour saturer le service. */
    private const int CONCURRENCY = 8;

    public function __construct(private GeocodingService $geocoding) {}

    /**
     * @param  iterable<Address>  $addresses
     * @return array{located: int, unlocated: int, pending: int}
     */
    public function locate(iterable $addresses, string $organizationId, float $budgetSeconds): array
    {
        $result = ['located' => 0, 'unlocated' => 0, 'pending' => 0];
        $todo = [];

        foreach ($addresses as $address) {
            if ($address->latitude !== null && $address->longitude !== null) {
                $result['located']++;
            } elseif ($this->geocoding->describe($address) === '') {
                $result['unlocated']++;
            } else {
                $todo[] = $address;
            }
        }

        $configuration = $todo === [] ? null : $this->geocoding->configurationFor($organizationId);

        if ($configuration === null) {
            if ($todo !== []) {
                Log::warning('Géocodage sans configuration', ['organization' => $organizationId]);
            }

            $result['unlocated'] += count($todo);

            return $result;
        }

        [$url, $key] = $this->geocoding->endpoint($configuration);
        $deadline = microtime(true) + $budgetSeconds;

        foreach (array_chunk($todo, self::CONCURRENCY) as $batch) {
            $remaining = (int) floor($deadline - microtime(true));

            // Un paquet qui ne peut plus finir à temps n'est pas commencé : le
            // couper en route coûterait les appels sans rien écrire.
            if ($remaining < 1) {
                $result['pending'] += count($batch);

                continue;
            }

            $timeout = max(1, min($configuration->timeout_seconds, $remaining));

            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (Address $address) => $pool->as((string) $address->id)
                    ->timeout($timeout)
                    ->get($url, [$key => $this->geocoding->describe($address)]),
                $batch,
            ));

            foreach ($batch as $address) {
                $this->apply($address, $responses[(string) $address->id] ?? null)
                    ? $result['located']++
                    : $result['unlocated']++;
            }
        }

        return $result;
    }

    private function apply(Address $address, mixed $response): bool
    {
        // Un service injoignable rend une exception à la place de la réponse.
        if (! $response instanceof Response || ! $response->successful()) {
            return false;
        }

        $coordinates = $this->geocoding->parse($response->body());

        if ($coordinates === null) {
            return false;
        }

        $address->forceFill(['latitude' => $coordinates[0], 'longitude' => $coordinates[1]])->save();

        return true;
    }
}
