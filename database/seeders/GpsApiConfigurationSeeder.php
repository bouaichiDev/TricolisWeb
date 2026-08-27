<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Integrations\Models\OrganizationApiConfiguration;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Planning\Services\GeocodingService;
use App\Modules\Planning\Services\RoutingService;
use Illuminate\Database\Seeder;

/**
 * Déclare les deux services GPS de l'organisation de développement.
 *
 * Sans ces lignes, le géocodage et le calcul d'itinéraire s'exécutent, notent un
 * avertissement et ne changent rien : la carte reste vide et la distance affiche
 * « non calculé ». C'est un symptôme difficile à distinguer d'une panne, d'où ce
 * semis.
 *
 * **Aucun secret ici.** Les deux points d'entrée du §75 et du §84 ne demandent pas
 * d'authentification : `auth_type` reste à `none` et `encrypted_credentials`
 * nul. Une API qui en exigerait se configure depuis l'écran « API externes »,
 * où le secret est chiffré — il n'a rien à faire dans un fichier versionné.
 *
 * `firstOrCreate` et non `updateOrCreate` : une URL corrigée à la main dans
 * l'écran ne doit pas être écrasée au prochain semis.
 *
 * **Toutes les organisations locales sont servies, pas seulement celle de
 * développement.** Une organisation créée à la main pour essayer le produit se
 * retrouvait sans configuration : le géocodage notait « sans configuration »
 * dans le journal, l'écran affichait des adresses introuvables, et rien ne
 * distinguait ce silence d'une panne du service.
 *
 * En production, chaque organisation déclare les siennes depuis l'écran
 * « API externes » — ce semis ne s'exécute qu'en local.
 */
class GpsApiConfigurationSeeder extends Seeder
{
    /**
     * Hôte fourni par le §75, port compris.
     *
     * Les deux services vivent derrière la même base ; seul le chemin les
     * distingue, et il vit dans `settings`.
     */
    private const string BASE_URL = 'https://duperrex.mine.nu:8443';

    public function run(): void
    {
        // `local` seulement : en test, chaque suite declare la configuration
        // qu'elle veut, et une ligne semee entrerait en collision avec la
        // sienne — l'unicite `(organization_id, code)` s'en charge.
        if (! app()->environment('local')) {
            return;
        }

        foreach (Organization::cursor() as $organization) {
            $this->declare($organization);
        }
    }

    private function declare(Organization $organization): void
    {
        OrganizationApiConfiguration::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => GeocodingService::CONFIGURATION_CODE],
            [
                'name' => 'Géocodage GPS',
                'base_url' => self::BASE_URL,
                'auth_type' => 'none',
                // `adress` s'ecrit bien ainsi : c'est l'orthographe du service
                // appele, et la corriger ferait echouer la requete.
                'settings' => [
                    'path' => '/TRC_GPS_API_V2/api/values/getLocation',
                    'queryKey' => 'adress',
                ],
                'timeout_seconds' => 15,
                'is_active' => true,
            ],
        );

        OrganizationApiConfiguration::firstOrCreate(
            ['organization_id' => $organization->id, 'code' => RoutingService::CONFIGURATION_CODE],
            [
                'name' => 'Itinéraires GPS',
                'base_url' => self::BASE_URL,
                'auth_type' => 'none',
                'settings' => [
                    'path' => '/TRC_GPS_API_V2/api/values/calculateRoute',
                    'profile' => 'truckfast',
                ],
                'timeout_seconds' => 15,
                'is_active' => true,
            ],
        );
    }
}
