<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, module: string, action: string}>
     */
    private array $permissions = [
        ['code' => 'dashboard.view', 'name' => 'Voir le tableau de bord', 'module' => 'dashboard', 'action' => 'view'],

        ['code' => 'organizations.view', 'name' => 'Voir les organisations', 'module' => 'organizations', 'action' => 'view'],
        ['code' => 'organizations.create', 'name' => 'Créer une organisation', 'module' => 'organizations', 'action' => 'create'],
        ['code' => 'organizations.update', 'name' => 'Modifier une organisation', 'module' => 'organizations', 'action' => 'update'],

        ['code' => 'subscriptions.view', 'name' => 'Voir l\'abonnement', 'module' => 'subscriptions', 'action' => 'view'],
        ['code' => 'subscriptions.create', 'name' => 'Souscrire un abonnement', 'module' => 'subscriptions', 'action' => 'create'],
        ['code' => 'subscriptions.update', 'name' => 'Modifier l\'abonnement', 'module' => 'subscriptions', 'action' => 'update'],
        ['code' => 'subscriptions.delete', 'name' => 'Résilier l\'abonnement', 'module' => 'subscriptions', 'action' => 'delete'],

        ['code' => 'users.view', 'name' => 'Voir les utilisateurs', 'module' => 'users', 'action' => 'view'],
        ['code' => 'users.create', 'name' => 'Créer un utilisateur', 'module' => 'users', 'action' => 'create'],
        ['code' => 'users.update', 'name' => 'Modifier un utilisateur', 'module' => 'users', 'action' => 'update'],
        ['code' => 'users.disable', 'name' => 'Désactiver un utilisateur', 'module' => 'users', 'action' => 'disable'],
        ['code' => 'users.assign_roles', 'name' => 'Affecter des rôles', 'module' => 'users', 'action' => 'assign_roles'],

        ['code' => 'roles.view', 'name' => 'Voir les rôles', 'module' => 'roles', 'action' => 'view'],
        ['code' => 'roles.create', 'name' => 'Créer un rôle', 'module' => 'roles', 'action' => 'create'],
        ['code' => 'roles.update', 'name' => 'Modifier un rôle', 'module' => 'roles', 'action' => 'update'],
        ['code' => 'roles.delete', 'name' => 'Supprimer un rôle', 'module' => 'roles', 'action' => 'delete'],
        ['code' => 'roles.assign_permissions', 'name' => 'Affecter des permissions', 'module' => 'roles', 'action' => 'assign_permissions'],

        ['code' => 'agencies.view', 'name' => 'Voir les agences', 'module' => 'agencies', 'action' => 'view'],
        ['code' => 'agencies.create', 'name' => 'Créer une agence', 'module' => 'agencies', 'action' => 'create'],
        ['code' => 'agencies.update', 'name' => 'Modifier une agence', 'module' => 'agencies', 'action' => 'update'],
        ['code' => 'agencies.delete', 'name' => 'Supprimer une agence', 'module' => 'agencies', 'action' => 'delete'],

        ['code' => 'depots.view', 'name' => 'Voir les dépôts', 'module' => 'depots', 'action' => 'view'],
        ['code' => 'depots.create', 'name' => 'Créer un dépôt', 'module' => 'depots', 'action' => 'create'],
        ['code' => 'depots.update', 'name' => 'Modifier un dépôt', 'module' => 'depots', 'action' => 'update'],
        ['code' => 'depots.delete', 'name' => 'Supprimer un dépôt', 'module' => 'depots', 'action' => 'delete'],

        ['code' => 'customers.view', 'name' => 'Voir les clients', 'module' => 'customers', 'action' => 'view'],
        ['code' => 'customers.create', 'name' => 'Créer un client', 'module' => 'customers', 'action' => 'create'],
        ['code' => 'customers.update', 'name' => 'Modifier un client', 'module' => 'customers', 'action' => 'update'],
        ['code' => 'customers.delete', 'name' => 'Supprimer un client', 'module' => 'customers', 'action' => 'delete'],
        ['code' => 'customers.block', 'name' => 'Bloquer un client', 'module' => 'customers', 'action' => 'block'],

        ['code' => 'addresses.view', 'name' => 'Voir les adresses', 'module' => 'addresses', 'action' => 'view'],
        ['code' => 'addresses.create', 'name' => 'Créer une adresse', 'module' => 'addresses', 'action' => 'create'],
        ['code' => 'addresses.update', 'name' => 'Modifier une adresse', 'module' => 'addresses', 'action' => 'update'],
        ['code' => 'addresses.delete', 'name' => 'Supprimer une adresse', 'module' => 'addresses', 'action' => 'delete'],

        ['code' => 'contacts.view', 'name' => 'Voir les contacts', 'module' => 'contacts', 'action' => 'view'],
        ['code' => 'contacts.create', 'name' => 'Créer un contact', 'module' => 'contacts', 'action' => 'create'],
        ['code' => 'contacts.update', 'name' => 'Modifier un contact', 'module' => 'contacts', 'action' => 'update'],
        ['code' => 'contacts.delete', 'name' => 'Supprimer un contact', 'module' => 'contacts', 'action' => 'delete'],

        ['code' => 'customer_sites.view', 'name' => 'Voir les sites client', 'module' => 'customer_sites', 'action' => 'view'],
        ['code' => 'customer_sites.create', 'name' => 'Créer un site client', 'module' => 'customer_sites', 'action' => 'create'],
        ['code' => 'customer_sites.update', 'name' => 'Modifier un site client', 'module' => 'customer_sites', 'action' => 'update'],
        ['code' => 'customer_sites.delete', 'name' => 'Supprimer un site client', 'module' => 'customer_sites', 'action' => 'delete'],

        ['code' => 'documents.view', 'name' => 'Voir les documents', 'module' => 'documents', 'action' => 'view'],
        ['code' => 'documents.upload', 'name' => 'Téléverser un document', 'module' => 'documents', 'action' => 'upload'],
        ['code' => 'documents.delete', 'name' => 'Supprimer un document', 'module' => 'documents', 'action' => 'delete'],

        ['code' => 'audit.view', 'name' => 'Voir le journal d\'audit', 'module' => 'audit', 'action' => 'view'],

        ['code' => 'catalogs.view', 'name' => 'Voir les catalogues', 'module' => 'catalogs', 'action' => 'view'],
        ['code' => 'catalogs.create', 'name' => 'Créer un catalogue', 'module' => 'catalogs', 'action' => 'create'],
        ['code' => 'catalogs.update', 'name' => 'Modifier un catalogue', 'module' => 'catalogs', 'action' => 'update'],
        ['code' => 'catalogs.delete', 'name' => 'Supprimer un catalogue', 'module' => 'catalogs', 'action' => 'delete'],

        ['code' => 'orders.view', 'name' => 'Voir les commandes', 'module' => 'orders', 'action' => 'view'],
        ['code' => 'orders.create', 'name' => 'Créer une commande', 'module' => 'orders', 'action' => 'create'],
        ['code' => 'orders.update', 'name' => 'Modifier une commande', 'module' => 'orders', 'action' => 'update'],
        ['code' => 'orders.delete', 'name' => 'Supprimer une commande', 'module' => 'orders', 'action' => 'delete'],
        ['code' => 'orders.cancel', 'name' => 'Annuler une commande', 'module' => 'orders', 'action' => 'cancel'],
        ['code' => 'orders.duplicate', 'name' => 'Dupliquer une commande', 'module' => 'orders', 'action' => 'duplicate'],
        ['code' => 'orders.change_status', 'name' => 'Changer le statut d’une commande', 'module' => 'orders', 'action' => 'change_status'],

        ['code' => 'order_lines.view', 'name' => 'Voir les lignes de commande', 'module' => 'order_lines', 'action' => 'view'],
        ['code' => 'order_lines.create', 'name' => 'Ajouter une ligne de commande', 'module' => 'order_lines', 'action' => 'create'],
        ['code' => 'order_lines.update', 'name' => 'Modifier une ligne de commande', 'module' => 'order_lines', 'action' => 'update'],
        ['code' => 'order_lines.delete', 'name' => 'Supprimer une ligne de commande', 'module' => 'order_lines', 'action' => 'delete'],

        // Les référentiels « types de colis » et « types de regroupement » sont
        // gouvernés par les permissions du module Colis : le cahier des charges
        // ne prévoit pas de permission propre, et en inventer une créerait un
        // code que rien ne vérifie ailleurs.
        ['code' => 'packages.view', 'name' => 'Voir les colis', 'module' => 'packages', 'action' => 'view'],
        ['code' => 'packages.create', 'name' => 'Créer un colis', 'module' => 'packages', 'action' => 'create'],
        ['code' => 'packages.update', 'name' => 'Modifier un colis', 'module' => 'packages', 'action' => 'update'],
        ['code' => 'packages.delete', 'name' => 'Supprimer un colis', 'module' => 'packages', 'action' => 'delete'],

        ['code' => 'services.view', 'name' => 'Voir les services', 'module' => 'services', 'action' => 'view'],
        ['code' => 'services.create', 'name' => 'Créer un service', 'module' => 'services', 'action' => 'create'],
        ['code' => 'services.update', 'name' => 'Modifier un service', 'module' => 'services', 'action' => 'update'],
        ['code' => 'services.delete', 'name' => 'Supprimer un service', 'module' => 'services', 'action' => 'delete'],

        ['code' => 'order_services.view', 'name' => 'Voir les services d’une commande', 'module' => 'order_services', 'action' => 'view'],
        ['code' => 'order_services.create', 'name' => 'Ajouter un service à une commande', 'module' => 'order_services', 'action' => 'create'],
        ['code' => 'order_services.update', 'name' => 'Modifier un service de commande', 'module' => 'order_services', 'action' => 'update'],
        ['code' => 'order_services.delete', 'name' => 'Supprimer un service de commande', 'module' => 'order_services', 'action' => 'delete'],
        ['code' => 'order_services.change_status', 'name' => 'Changer le statut d’un service', 'module' => 'order_services', 'action' => 'change_status'],

        ['code' => 'providers.view', 'name' => 'Voir les fournisseurs', 'module' => 'providers', 'action' => 'view'],
        ['code' => 'providers.create', 'name' => 'Créer un fournisseur', 'module' => 'providers', 'action' => 'create'],
        ['code' => 'providers.update', 'name' => 'Modifier un fournisseur', 'module' => 'providers', 'action' => 'update'],
        ['code' => 'providers.delete', 'name' => 'Supprimer un fournisseur', 'module' => 'providers', 'action' => 'delete'],

        ['code' => 'drivers.view', 'name' => 'Voir les chauffeurs', 'module' => 'drivers', 'action' => 'view'],
        ['code' => 'drivers.create', 'name' => 'Créer un chauffeur', 'module' => 'drivers', 'action' => 'create'],
        ['code' => 'drivers.update', 'name' => 'Modifier un chauffeur', 'module' => 'drivers', 'action' => 'update'],
        ['code' => 'drivers.delete', 'name' => 'Supprimer un chauffeur', 'module' => 'drivers', 'action' => 'delete'],

        ['code' => 'vehicle_types.view', 'name' => 'Voir les types de véhicule', 'module' => 'vehicle_types', 'action' => 'view'],
        ['code' => 'vehicle_types.create', 'name' => 'Créer un type de véhicule', 'module' => 'vehicle_types', 'action' => 'create'],
        ['code' => 'vehicle_types.update', 'name' => 'Modifier un type de véhicule', 'module' => 'vehicle_types', 'action' => 'update'],
        ['code' => 'vehicle_types.delete', 'name' => 'Supprimer un type de véhicule', 'module' => 'vehicle_types', 'action' => 'delete'],

        ['code' => 'vehicles.view', 'name' => 'Voir les véhicules', 'module' => 'vehicles', 'action' => 'view'],
        ['code' => 'vehicles.create', 'name' => 'Créer un véhicule', 'module' => 'vehicles', 'action' => 'create'],
        ['code' => 'vehicles.update', 'name' => 'Modifier un véhicule', 'module' => 'vehicles', 'action' => 'update'],
        ['code' => 'vehicles.delete', 'name' => 'Supprimer un véhicule', 'module' => 'vehicles', 'action' => 'delete'],

        ['code' => 'tours.view', 'name' => 'Voir les tournées', 'module' => 'tours', 'action' => 'view'],
        ['code' => 'tours.create', 'name' => 'Créer une tournée', 'module' => 'tours', 'action' => 'create'],
        ['code' => 'tours.update', 'name' => 'Modifier une tournée', 'module' => 'tours', 'action' => 'update'],
        ['code' => 'tours.delete', 'name' => 'Supprimer une tournée', 'module' => 'tours', 'action' => 'delete'],

        ['code' => 'tour_stops.view', 'name' => 'Voir les arrêts', 'module' => 'tour_stops', 'action' => 'view'],
        ['code' => 'tour_stops.create', 'name' => 'Créer un arrêt', 'module' => 'tour_stops', 'action' => 'create'],
        ['code' => 'tour_stops.update', 'name' => 'Modifier un arrêt', 'module' => 'tour_stops', 'action' => 'update'],
        ['code' => 'tour_stops.delete', 'name' => 'Supprimer un arrêt', 'module' => 'tour_stops', 'action' => 'delete'],
        ['code' => 'tour_stops.reorder', 'name' => 'Réordonner les arrêts', 'module' => 'tour_stops', 'action' => 'reorder'],

        ['code' => 'tour_stop_services.view', 'name' => 'Voir les services planifiés', 'module' => 'tour_stop_services', 'action' => 'view'],
        ['code' => 'tour_stop_services.create', 'name' => 'Planifier un service', 'module' => 'tour_stop_services', 'action' => 'create'],
        ['code' => 'tour_stop_services.update', 'name' => 'Modifier un service planifié', 'module' => 'tour_stop_services', 'action' => 'update'],
        ['code' => 'tour_stop_services.delete', 'name' => 'Retirer un service planifié', 'module' => 'tour_stop_services', 'action' => 'delete'],
        ['code' => 'tour_stop_services.reorder', 'name' => 'Réordonner les services d\'un arrêt', 'module' => 'tour_stop_services', 'action' => 'reorder'],

        ['code' => 'tour_periods.view', 'name' => 'Voir les périodes', 'module' => 'tour_periods', 'action' => 'view'],
        ['code' => 'tour_periods.create', 'name' => 'Créer une période', 'module' => 'tour_periods', 'action' => 'create'],
        ['code' => 'tour_periods.update', 'name' => 'Modifier une période', 'module' => 'tour_periods', 'action' => 'update'],
        ['code' => 'tour_periods.delete', 'name' => 'Supprimer une période', 'module' => 'tour_periods', 'action' => 'delete'],
        ['code' => 'tour_periods.reorder', 'name' => 'Réordonner les périodes', 'module' => 'tour_periods', 'action' => 'reorder'],

        ['code' => 'tour_period_assignments.view', 'name' => 'Voir les affectations', 'module' => 'tour_period_assignments', 'action' => 'view'],
        ['code' => 'tour_period_assignments.create', 'name' => 'Créer une affectation', 'module' => 'tour_period_assignments', 'action' => 'create'],
        ['code' => 'tour_period_assignments.update', 'name' => 'Modifier une affectation', 'module' => 'tour_period_assignments', 'action' => 'update'],
        ['code' => 'tour_period_assignments.delete', 'name' => 'Supprimer une affectation', 'module' => 'tour_period_assignments', 'action' => 'delete'],

        ['code' => 'tracking_events.view', 'name' => 'Voir les événements de suivi', 'module' => 'tracking_events', 'action' => 'view'],
        ['code' => 'tracking_events.create', 'name' => 'Créer un événement de suivi', 'module' => 'tracking_events', 'action' => 'create'],

        ['code' => 'proofs_of_delivery.view', 'name' => 'Voir les preuves de livraison', 'module' => 'proofs_of_delivery', 'action' => 'view'],
        ['code' => 'proofs_of_delivery.create', 'name' => 'Créer une preuve de livraison', 'module' => 'proofs_of_delivery', 'action' => 'create'],

        ['code' => 'claims.view', 'name' => 'Voir les réclamations', 'module' => 'claims', 'action' => 'view'],
        ['code' => 'claims.create', 'name' => 'Créer une réclamation', 'module' => 'claims', 'action' => 'create'],
        ['code' => 'claims.update', 'name' => 'Modifier une réclamation', 'module' => 'claims', 'action' => 'update'],
        ['code' => 'claims.delete', 'name' => 'Supprimer une réclamation', 'module' => 'claims', 'action' => 'delete'],

        ['code' => 'invoices.view', 'name' => 'Voir les factures', 'module' => 'invoices', 'action' => 'view'],
        ['code' => 'invoices.create', 'name' => 'Créer une facture', 'module' => 'invoices', 'action' => 'create'],
        ['code' => 'invoices.update', 'name' => 'Modifier une facture', 'module' => 'invoices', 'action' => 'update'],
        ['code' => 'invoices.delete', 'name' => 'Supprimer une facture', 'module' => 'invoices', 'action' => 'delete'],

        ['code' => 'invoice_lines.view', 'name' => 'Voir les lignes de facture', 'module' => 'invoice_lines', 'action' => 'view'],
        ['code' => 'invoice_lines.create', 'name' => 'Ajouter une ligne de facture', 'module' => 'invoice_lines', 'action' => 'create'],
        ['code' => 'invoice_lines.update', 'name' => 'Modifier une ligne de facture', 'module' => 'invoice_lines', 'action' => 'update'],
        ['code' => 'invoice_lines.delete', 'name' => 'Supprimer une ligne de facture', 'module' => 'invoice_lines', 'action' => 'delete'],

        ['code' => 'provider_settlements.view', 'name' => 'Voir les décomptes fournisseurs', 'module' => 'provider_settlements', 'action' => 'view'],
        ['code' => 'provider_settlements.create', 'name' => 'Créer un décompte fournisseur', 'module' => 'provider_settlements', 'action' => 'create'],
        ['code' => 'provider_settlements.update', 'name' => 'Modifier un décompte fournisseur', 'module' => 'provider_settlements', 'action' => 'update'],
        ['code' => 'provider_settlements.delete', 'name' => 'Supprimer un décompte fournisseur', 'module' => 'provider_settlements', 'action' => 'delete'],

        ['code' => 'provider_settlement_lines.view', 'name' => 'Voir les lignes de décompte', 'module' => 'provider_settlement_lines', 'action' => 'view'],
        ['code' => 'provider_settlement_lines.create', 'name' => 'Ajouter une ligne de décompte', 'module' => 'provider_settlement_lines', 'action' => 'create'],
        ['code' => 'provider_settlement_lines.update', 'name' => 'Modifier une ligne de décompte', 'module' => 'provider_settlement_lines', 'action' => 'update'],
        ['code' => 'provider_settlement_lines.delete', 'name' => 'Supprimer une ligne de décompte', 'module' => 'provider_settlement_lines', 'action' => 'delete'],

        ['code' => 'stock_items.view', 'name' => 'Voir les articles de stock', 'module' => 'stock_items', 'action' => 'view'],
        ['code' => 'stock_items.create', 'name' => 'Créer un article de stock', 'module' => 'stock_items', 'action' => 'create'],
        ['code' => 'stock_items.update', 'name' => 'Modifier un article de stock', 'module' => 'stock_items', 'action' => 'update'],
        ['code' => 'stock_items.delete', 'name' => 'Supprimer un article de stock', 'module' => 'stock_items', 'action' => 'delete'],

        ['code' => 'stock_locations.view', 'name' => 'Voir les emplacements', 'module' => 'stock_locations', 'action' => 'view'],
        ['code' => 'stock_locations.create', 'name' => 'Créer un emplacement', 'module' => 'stock_locations', 'action' => 'create'],
        ['code' => 'stock_locations.update', 'name' => 'Modifier un emplacement', 'module' => 'stock_locations', 'action' => 'update'],
        ['code' => 'stock_locations.delete', 'name' => 'Supprimer un emplacement', 'module' => 'stock_locations', 'action' => 'delete'],

        ['code' => 'stock_balances.view', 'name' => 'Voir les soldes de stock', 'module' => 'stock_balances', 'action' => 'view'],

        ['code' => 'stock_movements.view', 'name' => 'Voir les mouvements de stock', 'module' => 'stock_movements', 'action' => 'view'],
        ['code' => 'stock_movements.create', 'name' => 'Créer un mouvement de stock', 'module' => 'stock_movements', 'action' => 'create'],

        ['code' => 'stock_reservations.view', 'name' => 'Voir les réservations de stock', 'module' => 'stock_reservations', 'action' => 'view'],
        ['code' => 'stock_reservations.create', 'name' => 'Créer une réservation de stock', 'module' => 'stock_reservations', 'action' => 'create'],
        ['code' => 'stock_reservations.update', 'name' => 'Modifier une réservation de stock', 'module' => 'stock_reservations', 'action' => 'update'],
        ['code' => 'stock_reservations.release', 'name' => 'Libérer une réservation de stock', 'module' => 'stock_reservations', 'action' => 'release'],
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                ]
            );
        }
    }
}
