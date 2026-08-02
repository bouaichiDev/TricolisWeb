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

        ['code' => 'orders.view', 'name' => 'Voir les commandes', 'module' => 'orders', 'action' => 'view'],
        ['code' => 'orders.create', 'name' => 'Créer une commande', 'module' => 'orders', 'action' => 'create'],
        ['code' => 'orders.update', 'name' => 'Modifier une commande', 'module' => 'orders', 'action' => 'update'],
        ['code' => 'orders.delete', 'name' => 'Supprimer une commande', 'module' => 'orders', 'action' => 'delete'],
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
