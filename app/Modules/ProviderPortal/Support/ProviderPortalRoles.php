<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Support;

/**
 * Rôles livrés au portail fournisseur, et leurs permissions initiales.
 *
 * Ce ne sont que des **graines** : chaque transporteur reçoit son propre jeu.
 * Aucune règle du code ne lit ces codes pour décider d'un droit — seules les
 * permissions portées par le rôle comptent.
 *
 * Chacun porte la base (accès, tableau de bord, profil, tournées, calendrier)
 * puis son métier : le gestionnaire flotte gère chauffeurs et véhicules,
 * l'exploitant affecte chauffeurs et véhicules aux tournées, le lecteur
 * consulte. Les décomptes (montants, documents) restent à l'administrateur.
 */
final class ProviderPortalRoles
{
    public const string ADMIN = 'PROVIDER_ADMIN';

    public const string DISPATCHER = 'PROVIDER_DISPATCHER';

    public const string FLEET_MANAGER = 'PROVIDER_FLEET_MANAGER';

    public const string VIEWER = 'PROVIDER_VIEWER';

    private const array BASE = [
        'provider.portal.access',
        'provider.dashboard.view',
        'provider.profile.view',
        'provider.tours.view',
        'provider.calendar.view',
        'provider.tracking.view',
        'provider.documents.view',
        'provider.pod.view',
        'provider.communications.view',
    ];

    /** Organiser et exécuter les tournées : le métier de l'exploitant. */
    private const array EXECUTION = ['provider.tours.assign-resources', 'provider.tours.execute', 'provider.tour-stops.update-status', 'provider.pod.create'];

    private const array FLEET_READ = ['provider.drivers.view', 'provider.vehicles.view'];

    private const array FLEET_WRITE = [
        'provider.drivers.create', 'provider.drivers.update',
        'provider.vehicles.create', 'provider.vehicles.update',
    ];

    /**
     * @return array<string, array{name: string, permissions: array<int, string>}>
     */
    public static function defaults(): array
    {
        return [
            self::ADMIN => [
                'name' => 'Administrateur fournisseur',
                'permissions' => ProviderPortalPermissions::codes(),
            ],
            self::DISPATCHER => [
                'name' => 'Exploitant',
                'permissions' => [...self::BASE, ...self::FLEET_READ, ...self::EXECUTION],
            ],
            self::FLEET_MANAGER => [
                'name' => 'Gestionnaire flotte',
                'permissions' => [...self::BASE, ...self::FLEET_READ, ...self::FLEET_WRITE],
            ],
            self::VIEWER => [
                'name' => 'Lecture',
                'permissions' => [...self::BASE, ...self::FLEET_READ],
            ],
        ];
    }
}
