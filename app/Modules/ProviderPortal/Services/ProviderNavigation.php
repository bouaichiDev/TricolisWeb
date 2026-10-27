<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Services;

/**
 * Les entrées de menu du portail fournisseur que les permissions ouvrent.
 *
 * Le serveur décide : le portail affiche ce qu'il reçoit. Une entrée n'existe
 * ici que si son module est réellement livré — aucun lien vers un écran à
 * venir. Aucune entrée de gestion d'utilisateurs ni de rôles, par conception.
 */
final class ProviderNavigation
{
    /** @var array<int, array{key: string, label: string, path: string, permission: string}> */
    private const array ENTRIES = [
        ['key' => 'dashboard', 'label' => 'Tableau de bord', 'path' => '/dashboard', 'permission' => 'provider.dashboard.view'],
        ['key' => 'tours', 'label' => 'Tournées', 'path' => '/tours', 'permission' => 'provider.tours.view'],
        ['key' => 'calendar', 'label' => 'Calendrier', 'path' => '/calendar', 'permission' => 'provider.calendar.view'],
        ['key' => 'drivers', 'label' => 'Chauffeurs', 'path' => '/drivers', 'permission' => 'provider.drivers.view'],
        ['key' => 'vehicles', 'label' => 'Véhicules', 'path' => '/vehicles', 'permission' => 'provider.vehicles.view'],
        ['key' => 'profile', 'label' => 'Profil', 'path' => '/profile', 'permission' => 'provider.profile.view'],
    ];

    /**
     * @return array<int, array{key: string, label: string, path: string}>
     */
    public static function for(CurrentProviderContext $context): array
    {
        $entries = array_filter(self::ENTRIES, static fn (array $entry): bool => $context->can($entry['permission']));

        return array_values(array_map(
            static fn (array $entry): array => ['key' => $entry['key'], 'label' => $entry['label'], 'path' => $entry['path']],
            $entries,
        ));
    }
}
