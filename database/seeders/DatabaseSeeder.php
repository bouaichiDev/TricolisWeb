<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            // Referentiel commun a la plateforme : seme a partir des
            // enumerations existantes, avant toute donnee de demonstration.
            StatusSeeder::class,
            StatusTransitionSeeder::class,
            // Le catalogue de variables tarifaires : un referentiel de
            // plateforme, qui doit exister avant le premier bareme.
            PricingVariableSeeder::class,
            DevelopmentOrganizationSeeder::class,
            RoleSeeder::class,
            // Apres RoleSeeder : les roles du portail client s'accrochent aux
            // organisations, et restent hors du role `admin` interne.
            CustomerPortalSeeder::class,
            // Même raison pour les rôles du portail fournisseur.
            ProviderPortalSeeder::class,
            // Après RoleSeeder : le compte s'accroche au rôle plateforme que
            // celui-ci vient de créer.
            PlatformAdminSeeder::class,
            DemoAgencySeeder::class,
            DemoCustomerSeeder::class,
            // Le courriel de reinitialisation, pose pour etre modifie : un
            // modele vide ne se modifie pas.
            PasswordResetTemplateSeeder::class,
            DemoFleetSeeder::class,
            // Les deux services GPS : sans eux, geocodage et itineraires
            // s'executent sans rien changer, ce qui ressemble a une panne.
            GpsApiConfigurationSeeder::class,
            // Un mois de commandes suisses, pretes a planifier : depot d'abord,
            // les commandes s'y accrochent.
            SwissDepotSeeder::class,
            SwissOrderSeeder::class,
            // Et l'autre bout du parcours : un mois deja livre, sur lequel la
            // facturation a prise. Apres les commandes a planifier, dont il
            // reutilise le depot et le carnet d'adresses.
            DeliveredOrderSeeder::class,
        ]);

        // La demonstration du portail client, en developpement seulement : en
        // test, elle fausserait les comptages de la suite existante. Les tests
        // du portail la sement eux-memes (`CustomerPortalDemoSeeder`).
        if (app()->environment('local')) {
            $this->call(CustomerPortalDemoSeeder::class);
            // Stock de démonstration du portail (Phase 5) : hors du semis de test,
            // sa réservation figerait les lignes de la commande de démonstration.
            $this->call(DemoCustomerPortalStockSeeder::class);
            // Intégrations de démonstration (Phase 6) : un dépôt Excel, une
            // relève XML — configurations génériques, aucune connexion réelle.
            $this->call(DemoCustomerPortalIntegrationsSeeder::class);
            // Catalogues QOQA / GETAZ : l'isolation du catalogue se voit et se teste.
            $this->call(DemoCustomerPortalCatalogSeeder::class);
            // Portail fournisseur : Transports Alpha et Beta, et leurs comptes.
            $this->call(DemoProviderPortalSeeder::class);
        }
    }
}
