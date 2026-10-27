# Inventaire final du portail client (Phase 8, §3)

État au 2026-10-10, branche `feature/customer-portal-phase-8-finalization`.
« Routes » = routes `api/v1/customer/*` du module (voir
[`phase-8-route-matrix.md`](phase-8-route-matrix.md)).

| Module | Phase | Backend | Écrans React | Tests backend | Tests front | E2E | État |
|---|---|---|---|---|---|---|---|
| Authentification, contexte client | 1 | auth (7 publiques), `context`, `me`, `permissions`, `profile`, `roles` | connexion, mot de passe oublié, réinitialisation, activation, profil | `CustomerPortalAuthTest`, `…PasswordTest`, `…InvitationTest`, `PortalSessionHardeningTest` | `LoginPage`, `guards`, `sessionExpiry` | `onboarding` | livré |
| Tableau de bord | 1 | `dashboard` | `DashboardPage` (widgets selon permissions) | `CustomerDashboardTest` | — | via `onboarding`, `orders` | livré |
| Utilisateurs et rôles | 1 | `users` (8) | liste, fiche, invitation | `CustomerPortalUserManagementTest`, `PortalEscalationTest` | `UsersPage` | `onboarding`, `permissions` | livré |
| Commandes | 2 | `orders` (21), `order-form-options`, `catalog-items` (recherche d'articles du formulaire) | liste, création en étapes, détail, modification, duplication, annulation, service | `Orders/*` (5 fichiers) | `OrdersPages`, `orderMapping`, `OrderServicePage` | `orders` | livré |
| Calendrier | 3 | `calendar/orders` | `CalendarPage` (FullCalendar, vue rapide) | `CustomerCalendarTest` | `CalendarPage`, `range` | `tracking` | livré |
| Suivi authentifié | 3 | `orders/{id}/tracking`, `…/services/{id}` | `OrderTrackingPage` (carte Leaflet) | `CustomerTrackingTest`, `…TourIsolationTest` | `OrderTrackingPage` | `tracking` | livré |
| Suivi public | 3 | `public/tracking/{token}` (+ logo) ; gestion du lien sous `orders/{id}/public-tracking-link` | `PublicTrackingPage`, `PublicLinkSection` | `PublicTrackingEndpointTest`, `PublicTrackingLinkTest` | `PublicTrackingPage`, `PublicLinkSection` | `tracking` | livré |
| Réclamations | 4 | `claims` (6) | liste, création, détail | `PortalClaim*Test` (4) | `ClaimsPages`, `claimSchema` | `claims` | livré |
| Documents, BL, preuves de livraison | 4 | `documents` (2), `proofs-of-delivery`, routes de commande | onglets du détail de commande et de service | `PortalDocument*Test`, `PortalProofOfDelivery*Test` | `OrderServicePage` | `orders`, `claims` | livré |
| Stock | 5 | `stock` (14) : articles, soldes, mouvements, réservations, demandes de correction | 5 pages | `Stock/*` (7 fichiers) | `StockPages`, `StockAdjustment`, `quantity` | `stock`, `isolation` | livré |
| Intégrations | 6 (+ historique, 2026-10-10) | `integrations` (12) : flux, correspondances, import de fichier, historique des imports, envois, relance | `IntegrationsPage` | `Integrations/*` (3) + `tests/Feature/Integrations/*` (5) | `Integrations` | `integrations`, `isolation` | livré |
| Catalogue | annoncé en 1, **livré en 8** | `catalog` (7) : catalogues, articles, fiche, ajout, modification, (dés)activation | `CatalogPage`, `CatalogItemPage`, dialogue d'ajout/modification | `PortalCatalogTest`, `PortalCatalogUpdateTest`, `PortalCatalogIsolationTest` | `CatalogPages`, `CatalogItemEdit` | `catalog`, `catalogIsolation` | livré ; modification le 2026-10-11 |
| Sécurité, audit, isolation | 7 | middlewares, limiteurs, en-têtes, masquage, matrice de routes | — | `Security/*` (9 fichiers) | — | `isolation`, `permissions` | livré |
| API client par clé | 6 | `client/me`, `client/orders` (3) | — (machine à machine) | `CustomerApiPortalTest`, `CustomerApiHardeningTest` | — | — | livré |

Toutes les listes ont la structure du back-office : filtres au-dessus, choix
du nombre de lignes (10 à 100), actions de ligne en icônes selon les capacités
renvoyées par le serveur (2026-10-11).

## Flux d'intégration de référence

| Client | Flux | Sens | Mécanisme | Test sans système réel |
|---|---|---|---|---|
| IKEA | commandes XML | entrant | relève SFTP horaire | `FilePullIntegrationTest` (disque simulé, XXE, doublons) |
| SCHMIDT | commandes JSON | entrant | relève REST horaire paginée | `RestPullIntegrationTest` (`Http::fake`, SSRF, erreurs passagères) |
| GETAZ | statuts de commande JSON | **sortant** (Tricolis → Getaz) | push REST | `OrderStatusPushTest` (`Http::fake`) |
| QOQA | commandes Excel | entrant | dépôt de fichier dans le portail | `PortalExcelImportTest` + E2E `integrations` |

> Getaz : la spécification Phase 6 (§19, « REST Push Tricolis → Customer »)
> fait pousser par Tricolis les statuts vers l'API REST de Getaz ; c'est ce qui
> est livré. La formule de la Phase 8 (« REST Push vers Tricolis ») a été
> levée le 2026-10-10 : Getaz utilise bien son API REST, alimentée par Tricolis.
> Getaz peut en plus lire ses commandes par l'API par clé (`api/v1/client/*`).

## Données de démonstration (environnement `local` uniquement)

`DatabaseSeeder` n'appelle les seeders de démonstration du portail que si
`app()->environment('local')` : `CustomerPortalDemoSeeder`,
`DemoCustomerPortalStockSeeder`, `DemoCustomerPortalIntegrationsSeeder`
(IKEA, QOQA, SCHMIDT, GETAZ, hôtes `*.example.invalid`, flux désactivés,
aucun secret). Comptes : `admin@ikea.test`, `orders@ikea.test`,
`viewer@ikea.test`, `admin@qoqa.test` — mot de passe de démonstration
`password`, **jamais en production**.

## Ce qui n'existe pas (volontairement)

- Suppression d'un article du catalogue par le client (désactivation seulement).
- Modification d'une quantité de stock par le client (demande de correction seulement).
- Configuration des flux et des secrets par le client (back-office du transporteur).
- Création de clés API par le client.
- Webhooks entrants dédiés : non prévus par la conception.
