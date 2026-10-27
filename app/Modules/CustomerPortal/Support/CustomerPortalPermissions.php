<?php

declare(strict_types=1);

namespace App\Modules\CustomerPortal\Support;

/**
 * Référentiel des permissions du portail client : `customer.<module>.<action>`.
 *
 * Elles vivent dans la même table `permissions` que celles de l'interne, mais
 * le préfixe `customer.` les tient à l'écart : l'administration interne ne les
 * propose ni ne les délègue, et le portail n'accepte qu'elles.
 *
 * Aucune `customer.stock.quantity.update` : un client ne touche jamais la
 * quantité physique, il demande un ajustement.
 */
final class CustomerPortalPermissions
{
    public const string PREFIX = 'customer.';

    /** @var array<string, string> code => libellé */
    private const array CATALOG = [
        'customer.users.view' => 'Voir les utilisateurs',
        'customer.users.create' => 'Créer un utilisateur',
        'customer.users.update' => 'Modifier un utilisateur',
        'customer.users.disable' => 'Activer ou désactiver un utilisateur',
        'customer.users.invite' => 'Inviter un utilisateur',
        'customer.users.assign_role' => 'Attribuer un rôle',

        'customer.roles.view' => 'Voir les rôles',
        'customer.roles.assign' => 'Attribuer les rôles',

        'customer.orders.view' => 'Voir les commandes',
        'customer.orders.create' => 'Créer une commande',
        'customer.orders.update' => 'Modifier une commande',
        'customer.orders.duplicate' => 'Dupliquer une commande',
        'customer.orders.cancel' => 'Annuler une commande',
        'customer.orders.track' => 'Suivre une commande',
        'customer.orders.documents.view' => 'Voir les documents d’une commande',
        'customer.orders.documents.upload' => 'Joindre un document à une commande',
        'customer.orders.history.view' => 'Voir l’historique d’une commande',
        'customer.orders.proofs_of_delivery.view' => 'Voir les preuves de livraison',

        'customer.calendar.view' => 'Voir le calendrier',

        'customer.tracking.view' => 'Voir le suivi',
        'customer.tracking.public_link.view' => 'Voir les liens de suivi public',
        'customer.tracking.public_link.manage' => 'Gérer les liens de suivi public',

        'customer.catalog.view' => 'Voir le catalogue',
        'customer.articles.view' => 'Voir les articles',
        'customer.articles.create' => 'Ajouter un article',
        'customer.articles.update' => 'Modifier ou désactiver un article',

        'customer.stock.view' => 'Voir le stock',
        'customer.stock.movements.view' => 'Voir les mouvements de stock',
        'customer.stock.items.create' => 'Ajouter un article en stock',
        'customer.stock.items.update' => 'Modifier un article en stock',
        'customer.stock.reservations.view' => 'Voir les réservations de stock',
        'customer.stock.adjustment_requests.view' => 'Voir les demandes d’ajustement',
        'customer.stock.adjustment_requests.create' => 'Demander un ajustement',

        'customer.claims.view' => 'Voir les réclamations',
        'customer.claims.create' => 'Créer une réclamation',
        'customer.claims.update' => 'Modifier une réclamation ouverte',

        'customer.integrations.view' => 'Voir les intégrations',
        'customer.integrations.create' => 'Créer une intégration',
        'customer.integrations.update' => 'Modifier une intégration',
        'customer.integrations.disable' => 'Désactiver une intégration',
        'customer.integrations.mapping.view' => 'Voir les correspondances',
        'customer.integrations.mapping.manage' => 'Gérer les correspondances',
        'customer.integrations.status_mapping.manage' => 'Gérer les correspondances de statuts',
        'customer.integrations.logs.view' => 'Voir les journaux d’intégration',
        'customer.integrations.test' => 'Tester une intégration',
        'customer.integrations.import' => 'Importer un fichier de commandes',
        'customer.integrations.export_jobs.retry' => 'Relancer un envoi en échec',
    ];

    public static function isCustomerPermission(string $code): bool
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
     * Lignes prêtes pour la table `permissions`.
     *
     * Le module est tout ce qui précède l'action : `customer.stock.movements.view`
     * → module `customer.stock.movements`, action `view`. Aucun module du
     * portail ne se confond ainsi avec un module interne de même nom.
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
