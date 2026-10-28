<?php

declare(strict_types=1);

namespace App\Modules\ProviderPortal\Support;

/**
 * Référentiel des permissions du portail fournisseur : `provider.<module>.<action>`.
 *
 * Même table `permissions` que l'interne, mais le préfixe `provider.` les tient
 * à l'écart : l'administration interne ne les compose dans aucun rôle interne,
 * et le portail fournisseur n'accepte qu'elles.
 *
 * Aucune permission de gestion d'utilisateurs ni de rôles : ces opérations
 * restent au back-office. Le catalogue grandit phase après phase ; le seeder
 * accorde chaque nouvelle venue aux rôles par défaut qui la prévoient.
 */
final class ProviderPortalPermissions
{
    public const string PREFIX = 'provider.';

    /** @var array<string, string> code => libellé */
    private const array CATALOG = [
        'provider.portal.access' => 'Accéder au portail fournisseur',
        'provider.dashboard.view' => 'Voir le tableau de bord',
        'provider.profile.view' => 'Voir le profil du fournisseur',

        'provider.tours.view' => 'Voir les tournées attribuées',
        'provider.tours.assign-resources' => 'Affecter chauffeur et véhicule à une tournée',
        'provider.calendar.view' => 'Voir le calendrier des tournées',
        'provider.tours.execute' => 'Démarrer et terminer une tournée',
        'provider.tour-stops.update-status' => 'Faire avancer les arrêts d’une tournée',

        'provider.drivers.view' => 'Voir les chauffeurs',
        'provider.drivers.create' => 'Ajouter un chauffeur',
        'provider.drivers.update' => 'Modifier, désactiver ou réactiver un chauffeur',

        'provider.vehicles.view' => 'Voir les véhicules',
        'provider.vehicles.create' => 'Ajouter un véhicule',
        'provider.vehicles.update' => 'Modifier, désactiver ou réactiver un véhicule',
    ];

    public static function isProviderPermission(string $code): bool
    {
        return str_starts_with($code, self::PREFIX);
    }

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::CATALOG);
    }

    /**
     * Lignes prêtes pour la table `permissions` : le module est tout ce qui
     * précède l'action (`provider.dashboard.view` → `provider.dashboard`).
     *
     * @return array<int, array{code: string, name: string, module: string, action: string}>
     */
    public static function definitions(): array
    {
        $rows = [];

        foreach (self::CATALOG as $code => $name) {
            $position = (int) strrpos($code, '.');
            $rows[] = [
                'code' => $code,
                'name' => $name,
                'module' => substr($code, 0, $position),
                'action' => substr($code, $position + 1),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public static function modules(): array
    {
        return array_values(array_unique(array_column(self::definitions(), 'module')));
    }
}
