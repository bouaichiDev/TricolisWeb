# Matrice E2E — portail client (Phase 8, §17)

**Préconditions communes** : base de test `tricolisweb_test` semée par
`migrate:fresh --seed --env=testing`, puis `CustomerPortalDemoSeeder`,
`DemoCustomerPortalStockSeeder`, `DemoCustomerPortalIntegrationsSeeder`
(IKEA, QOQA, SCHMIDT, GETAZ ; aucun secret, hôtes `*.example.invalid`). API sur
le port 8002, portail sur 5182 (`playwright.config.ts`). Comptes de démonstration :
`admin@ikea.test` (CUSTOMER_ADMIN), `orders@ikea.test` (CUSTOMER_ORDER_MANAGER),
`viewer@ikea.test` (CUSTOMER_VIEWER), `admin@qoqa.test` (CUSTOMER_ADMIN).

**Statuts** : **PW** = parcours navigateur Playwright automatisé ; **API** =
scénario automatisé de bout en bout côté API (Pest, base réelle, sans
navigateur) ; **Partiel** = une partie seulement est automatisée, le reste est
précisé. Aucun scénario n'est « simulé ».

**Exécution de référence** : 2026-10-11 — Playwright **16/16** (5,8 min), dont
`catalogIsolation.spec.ts` et `adjustmentRequest.spec.ts` ; 2026-10-10 : 14/14 ;
tests Pest cités : voir `phase-8-regression-report.md`.

| ID | Scénario | Utilisateur | Étapes → résultat attendu | Backend vérifié | Frontend vérifié | Statut |
|---|---|---|---|---|---|---|
| E2E-001 | Login Customer | tous | identifiants → tableau de bord « Bonjour … » ; mauvais mot de passe refusé | `CustomerPortalAuthTest` (10 cas : compte, accès, client inactifs, multi-client) | `login()` de chaque spec ; `LoginPage.test` | **PW** |
| E2E-002 | Dashboard | admin IKEA | connexion → titre et widgets autorisés | `CustomerDashboardTest` | `login()` attend le tableau de bord | **PW** (affichage) |
| E2E-003 | User management | admin IKEA | inviter un lecteur → mail → activation → connexion du lecteur, sans accès QOQA | `CustomerPortalUserManagementTest`, `…InvitationTest`, `PortalEscalationTest` | `onboarding.spec.ts` ; `UsersPage.test` | **PW** |
| E2E-004 | Create Order | gestionnaire IKEA | 6 étapes : informations, lignes du catalogue + ligne libre, colis, service, document, validation → détail | `CustomerOrderCreateTest` | `orders.spec.ts` | **PW** |
| E2E-005 | Edit Order | gestionnaire IKEA | modifier la référence → enregistrée ; conflit de version → 409 | `CustomerOrderWorkflowTest` | `orders.spec.ts` | **PW** |
| E2E-006 | Duplicate Order | gestionnaire IKEA | dupliquer → nouvelle commande, sans données d'exécution | `CustomerOrderWorkflowTest` | `orders.spec.ts` | **PW** |
| E2E-007 | Cancel Order | gestionnaire IKEA | annuler la copie avec motif → statut annulé, rien supprimé | `CustomerOrderWorkflowTest` | `orders.spec.ts` | **PW** |
| E2E-008 | Calendar → Order | admin IKEA | calendrier → filtre statut → vue rapide → « Voir la commande » | `CustomerCalendarTest` | `tracking.spec.ts` ; `CalendarPage.test` | **PW** |
| E2E-009 | Calendar → Tracking | admin IKEA | retour calendrier → vue rapide → « Suivre » → page de suivi | `CustomerTrackingTest` | `tracking.spec.ts` | **PW** |
| E2E-010 | Authenticated Tracking | admin IKEA | page de suivi : ce qui est connu et ce qui ne l'est pas | `CustomerTrackingTest`, `CustomerTrackingTourIsolationTest` | `tracking.spec.ts` ; `OrderTrackingPage.test` | **PW** |
| E2E-011 | Public Tracking | admin IKEA puis anonyme | créer un lien → page sans compte → révoquer → 404 | `PublicTrackingLinkTest`, `PublicTrackingEndpointTest` | `tracking.spec.ts` ; `PublicTrackingPage.test` | **PW** |
| E2E-012 | Claim | admin IKEA | depuis un service : déclarer un problème → suivre la réclamation ; QOQA ne la voit pas | `PortalClaim*Test` (4) | `claims.spec.ts` ; `ClaimsPages.test` | **PW** |
| E2E-013 | Documents | gestionnaire IKEA | joindre un PDF à la création → visible dans l'onglet Documents ; téléchargement typé, fichier dangereux refusé | `PortalDocumentAccessTest`, `PortalDocumentDownloadTest` | `orders.spec.ts` (jointure, onglet) | **Partiel** : téléchargement vérifié par API seulement |
| E2E-014 | BL / POD | admin IKEA | preuve de livraison d'une commande livrée : métadonnées, signature, photo ; celle d'un autre client → 404 | `PortalProofOfDeliveryListTest`, `…ShowTest` | `OrderServicePage.test` (composant) | **API** — pas de commande livrée avec preuve dans la démo navigateur |
| E2E-015 | Stock list/detail | admin IKEA | stock → article BILLY-80 → soldes → commande liée ; rien de QOQA | `PortalStockItemsTest`, `PortalStockBalancesTest`, `PortalStockIsolationTest` | `stock.spec.ts` ; `StockPages.test` | **PW** |
| E2E-016 | Stock movements/reservations | admin IKEA | onglet « Stock réservé » de la commande ; listes mouvements et réservations | `PortalStockMovementsTest`, `PortalStockReservationsTest` | `stock.spec.ts` (réservé) ; `StockPages.test` | **Partiel** : listes des mouvements par API et composant |
| E2E-017 | IKEA XML import | admin IKEA | le flux SFTP XML apparaît, sans action de dépôt ; relève : import, archive, doublon ignoré, XXE écarté | `FilePullIntegrationTest` (disque simulé) | `integrations.spec.ts` | **Partiel** : relève par API/job, affichage par PW |
| E2E-018 | Schmidt REST Pull | (planificateur) | relève horaire paginée → commandes `REST_API` ; 5xx rejoué, 401 définitif, SSRF refusé, jeton jamais journalisé | `RestPullIntegrationTest`, `PullSchedulerTest` (`Http::fake`) | — | **API** |
| E2E-019 | Getaz API flow | (changement de statut) | statut poussé en JSON vers l'hôte autorisé ; 4xx non relancé ; jamais pour un autre client | `OrderStatusPushTest` (`Http::fake`), `PortalExportJobsTest` (relance depuis le portail) | `Integrations.test` | **API** — Tricolis → API REST Getaz (conforme Phase 6) |
| E2E-020 | QOQA Excel import | admin QOQA | déposer un XLSX → prévisualiser → importer la commande valide ; ligne invalide signalée | `PortalExcelImportTest` | `integrations.spec.ts` ; `Integrations.test` | **PW** |
| E2E-021 | Permission denied | lecteur IKEA | pas de lien Utilisateurs ni « Nouvelle commande » ; `/users` → « Accès refusé » ; API → 403 | `CustomerPortalPermissionTest`, `PortalRouteGuardsTest` | `permissions.spec.ts` ; `guards.test` | **PW** |
| E2E-022 | Cross-Customer IDOR | admin IKEA (+ `catalogIsolation.spec.ts` : article QOQA ni lu ni modifié, ni (dés)activé, par API et URL) | commande QOQA absente de la liste ; URL directe → « Commande introuvable » ; API → 404 ; `?customerId=` → 422 ; stock et intégrations QOQA → 404 / absents | `PortalCrossCustomerIsolationTest` (toutes les routes à identifiant), `CustomerOrderIsolationTest`, `PortalStockIsolationTest`, `PortalCustomerIsolationTest` | `isolation.spec.ts` | **PW** |
| E2E-023 | Disabled Customer/User | compte ou client désactivé | connexion refusée ; jeton existant refusé à la requête suivante ; organisation suspendue → portail fermé | `CustomerPortalAuthTest`, `PortalSessionHardeningTest` | `sessionExpiry.test` (retour à la connexion) | **API** |
| E2E-024 | API key invalid/disabled | système client | sans clé / clé inconnue / clé désactivée → 401 sans dire qu'elle existe ; client inactif ou organisation suspendue → fermé ; IP hors liste refusée | `CustomerApiPortalTest`, `CustomerApiHardeningTest` | — (pas d'écran) | **API** |
| E2E-025 | Public tracking abuse | anonyme | jeton inconnu, révoqué, expiré → même 404 générique ; limiteur `public-tracking` ; aucune donnée interne exposée | `PublicTrackingEndpointTest` | `tracking.spec.ts` (révocation) | **API** + PW (révocation) |

## Isolation multi-client (§19)

Scénario Playwright `isolation.spec.ts` : IKEA ne voit pas la commande QOQA
(liste, URL directe, API), ne peut pas forcer `customerId`, n'atteint ni un
article de stock QOQA ni une intégration QOQA. Répété pour la réclamation
(`claims.spec.ts`), l'article de stock (`stock.spec.ts`) et le nouveau membre
invité (`onboarding.spec.ts`). Côté API, `PortalCrossCustomerIsolationTest`
parcourt **chaque** route à identifiant avec l'identifiant d'une ressource
QOQA — y compris l'article de catalogue depuis la Phase 8.

## Non automatisé en navigateur — et pourquoi

| Point | Raison | Couverture actuelle |
|---|---|---|
| Relève Schmidt, envoi Getaz | jobs de fond, sans écran déclencheur côté client | API avec `Http::fake` |
| Preuve de livraison | la démo navigateur n'a pas de commande livrée avec preuve | API + composant |
| Compte / client désactivé en cours de session | demande une action du back-office pendant le parcours | API |

## Lancer

```bash
php artisan migrate:fresh --seed --env=testing --force      # base de TEST uniquement
php artisan db:seed --class=CustomerPortalDemoSeeder --env=testing --force
php artisan db:seed --class=DemoCustomerPortalStockSeeder --env=testing --force
php artisan db:seed --class=DemoCustomerPortalIntegrationsSeeder --env=testing --force
cd customer-portal && npx playwright test
```

Ne pas lancer en même temps que la suite Pest : elles partagent la base de test.
