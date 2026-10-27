# Rapport final — Phase 8 portail client

Finalisation, tests, documentation, déploiement. 2026-10-10.

**1. Branche** — `feature/customer-portal-phase-8-finalization`, créée depuis `main`.
Aucun commit, aucun push : le travail des Phases 1 à 8 est dans l'arbre de travail.

**2. Commit de base** — `a52dd1e` (`feat(claims): …`).

**3. Sources de vérité** — spécifications `Project/portailClient/Phase1…Phase8` ;
rapports et documents des Phases 1 à 7 (`docs/customer-portal/*`) ; code et
tests existants ; `docs/DEPLOYMENT_VPS.md` ; `.github/workflows/deploy-production.yml`.

**4. Modules audités** — authentification et contexte, tableau de bord,
utilisateurs et rôles, commandes, calendrier, suivi, suivi public, réclamations,
documents/BL/POD, stock, intégrations (IKEA, SCHMIDT, GETAZ, QOQA), API par clé,
catalogue, sécurité/audit. Inventaire : `phase-8-feature-inventory.md`.

**5. Fichiers backend modifiés** — nouveaux : catalogue (`CustomerCatalogScope`,
`CreateCustomerCatalogItem`, `CustomerCatalogController`, 2 requêtes, 1 ressource,
`routes/customer-catalog.php`), `Shared/Health/CheckApplicationHealth`,
`Shared/Http/Errors/ExternalApiErrors`, `OpenApi/DocumentExternalPerimeter`,
`scripts/customer-portal-smoke.sh`. Modifiés : `bootstrap/app.php`,
`AppServiceProvider`, `PortalRouteSecurity`, `routes/customer.php`,
`config/cors.php`, `config/queue.php` (`retry_after` 1900 s), `docs/DEPLOYMENT_VPS.md` (Supervisor), `DemoCustomerPortalIntegrationsSeeder`, `.env.example`,
workflows (`ci.yml` nouveau, `deploy-production.yml`). Style seul (Pint) :
`ClaimController`, `StoreOrderRequest`, `ClaimDetailResource`,
`TourListResource`, `TourListQuery`, `DashboardPeriodTest`, `AccessRequestTest`.

**6. Fichiers frontend modifiés** — nouveaux : module `catalog/` (types, API,
hooks, dialogue, 2 pages, tests), `shared/api/apiBase.ts`,
`app/router/lazyPages.ts`, `shared/utils/format.test.ts`. Modifiés :
`AppRouter`, `paths`, `navigation`, `PortalLayout`, `OrderEventSheet`,
`format.ts`, `client.ts`, `platform.ts`, `publicTrackingApi.ts`,
`vite.config.ts`, `.env.example`, `README.md`. Back-office React : aucun.

**7. Routes corrigées** — 4 ajoutées (`customer/catalog/*`) ; aucune modifiée.
89 routes externes, toutes autorisées (`route-matrix --check`), dont 2 pour l'historique des imports.

**8. Permissions corrigées** — aucune ajoutée ni renommée ; `customer.catalog.view`,
`customer.articles.view`, `.create` passent de réservées à utilisées.

**9. Policies corrigées** — aucune : le portail n'utilise pas les Policies
internes ; le contrôle passe par `customer.permission` + scopes, audités.

**10. IDOR corrigés** — aucun trouvé. Le test d'isolation global couvre
désormais l'article de catalogue.

**11. Isolation Customer** — aucune fuite trouvée ; `CustomerCatalogScope`
filtre client **et** organisation dès sa création.

**12. Isolation Organization** — aucune fuite trouvée.

**13. Tests backend ajoutés** — `PortalCatalogTest` (11), `ExternalApiErrorsTest` (6),
`HealthCheckTest` (2), `PortalQueryBudgetTest` (2), `CorsOriginsTest` (4),
`ExternalApiDocumentationTest` (3), `QueueRetryAfterTest` (2) ; isolation globale étendue au catalogue.

**14. Tests frontend ajoutés** — `CatalogPages.test` (4), `format.test` (3, fuseaux).

**15. Tests E2E ajoutés** — `catalog.spec` (2), `isolation.spec` (1),
`permissions.spec` (1) ; `tracking.spec` : assertion rendue précise.

**16. Résultats backend** — suite Pest complète **2219/2219** (7058 assertions,
55 min 40 s) ; puis les 9 tests écrits pendant son exécution (9/9) et les 88
tests Security/Performance/Catalog/Health relancés après les derniers
changements (88/88) ; `tests/Unit/Security` 39/39. Caches de production
(`config`, `route`, `event`, `view`) générés sans erreur. Détail :
`phase-8-regression-report.md`.

**17. Résultats frontend** — portail : lint OK (3 avertissements), typecheck OK,
Vitest 89/89, build OK (472 ko). Back-office : lint, typecheck OK, Vitest
830/830, build OK.

**18. Résultats E2E** — Playwright portail 14/14, deux fois (le second passage après les derniers changements), base de test re-semée.

**19. Pint** — `pint --test` vert sur tout le dépôt.

**20. Analyse statique** — ni PHPStan ni Psalm configurés : non exécutée.

**21. Performance** — 14 à 25 requêtes par endpoint, < 60 ms en dev, aucun
chargement paresseux ; bundle 803 → 472 ko. `phase-8-performance-report.md`.

**22. Timezone** — UTC côté serveur, ISO 8601 `Z`, heures murales pour les
créneaux ; défaut des dates seules corrigé. `phase-8-timezone-strategy.md`.

**23. Secrets** — aucun dans Git ; `.env.example` sans valeur ; `composer
audit` : 1 avertissement faible (page de débogage) ; `npm audit` : 0.

**24. Intégrations** — 4 flux de référence testés sans système réel (disque
simulé, `Http::fake`, fixture XLSX). Démo : SCHMIDT et GETAZ ajoutés, flux
désactivés, hôtes `*.example.invalid`.

**25. Documentation** — `docs/customer-portal/` : `README.md`, `api.md`,
7 documents `phase-8-*`, `access-matrix.md` et `retention-decisions.md` mis à
jour, `security-route-matrix.md` régénérée. `docs/deployment/` : queue,
scheduler, backup-restore, rollback, runbook, monitoring, smoke-tests,
préproduction, production, go-live. `customer-portal/README.md` complété.

**26. CI/CD** — avant : déploiement de `main` sans aucun test. Après : `ci.yml`
(Pint, matrice de routes, Pest ; lint, typecheck, Vitest, build des deux
applications React) sur chaque PR et avant chaque déploiement. **Jamais
exécuté sur GitHub.** Le portail n'est toujours pas déployé (B1).

**27. Migrations production pending** — inconnues (pas d'accès). Le portail
apporte 10 migrations, toutes additives (dont les 3 du 2026-10-10 validées par l'utilisateur) ; aucune destructive.

**28. Backup** — inexistant en production ; procédure écrite.

**29. Restore test** — base de développement restaurée dans une base neuve :
97/97 tables, comptages identiques, 0 migration pendante, application
fonctionnelle. Fichiers et production : non testés.

**30. Rollback** — revert + CI + redéploiement ; jamais `migrate:rollback`.

**31. Smoke tests** — script non destructif, `SMOKE OK` 19/19 en dev ; jamais
lancé en préproduction ni en production.

**32. Monitoring** — signaux existants documentés ; `/up` vérifie base,
stockage, cache.

**33. Alerting** — 10 alertes définies ; aucune branchée.

**34. Risques restants** — voir `customer-portal-go-live-report.md` (M1 à M7).

**35. BLOCKED_DESIGN_DECISION** — reste : fuseau par défaut d'organisation
(mineur). Levés le 2026-10-10 avec l'accord de l'utilisateur : sens du flux
Getaz (Tricolis → API REST Getaz, conforme Phase 6) ; identifiants FTP/SFTP
entrants (colonnes `connection` + `encrypted_password`, réglés au back-office) ;
historique d'import (table `customer_import_runs`, écrans portail et
back-office) ; index `orders(customer_id, external_reference)`.

**36. BLOCKED_COMPLIANCE_DECISION** — 8 durées de conservation, dont les sauvegardes et l’historique des imports.

**37. BLOCKERS go-live** — B1 portail sans mécanisme de déploiement ; B2 aucune
sauvegarde avant migration.

**38. Recommandations post-production** — (file : `retry_after` 1900 s par défaut et Supervisor documenté, corrigés) ; file dédiée aux imports ; E2E en CI ;
mesures de charge en préproduction ; montée `laravel/framework` ≥ 13.30 ;
supervision externe.

**Addendum — décisions levées le 2026-10-10 (accord de l'utilisateur)**

- *File* : `retry_after` 1900 s par défaut (`config/queue.php`), Supervisor
  `--timeout=1800`, 2 workers (`docs/DEPLOYMENT_VPS.md`) ; `QueueRetryAfterTest`.
- *Getaz* : Tricolis → API REST Getaz, conforme à la Phase 6 ; plus d'écart.
- *Connexion FTP/SFTP* : migration `…100000` (`connection`, `encrypted_password`),
  `InboundFileConnections` lit la base, `SaveFileConnectionRequest`, même route
  `PUT …/connection` que le REST ; variable `CUSTOMER_INTEGRATIONS_INBOUND_SOURCES`
  supprimée ; formulaire `FileConnectionForm` au back-office. L'audit garde
  `accessChanged` (l'ancien `credentialsChanged` était masqué par `SensitiveData`).
- *Historique d'import* : migration `…100001` (`customer_import_runs`),
  `CustomerImportHistory` écrit par les 4 chemins (portail, back-office « tout
  ou rien », relève REST, relève SFTP) succès comme échecs ; routes
  `GET customer/integrations/import-runs[/{id}]` et
  `GET customer-import-configurations/{id}/runs[/{run}]` ; onglet « Imports » du
  portail, panneau « Historique des exécutions » du back-office.
- *Index* : migration `…100002`, `orders(customer_id, external_reference)`.
- Tests ajoutés : `ImportFileConnectionTest` (9), `CustomerImportHistoryTest` (5),
  `PortalImportHistoryTest` (5), `ImportOrdersHistoryTest` (2), isolation globale
  étendue à `{importRun}` ; Vitest `ImportRunsPanel.test` (2),
  `ImportRuns.test` (2), cas FTP de `ImportConnectionPanel.test` réécrit pour
  le nouveau comportement.

**Addendum — catalogue modifiable, demandes, tableaux (2026-10-11)**

- Spec `docs/superpowers/specs/2026-10-11-portail-catalogue-stock-tableaux-design.md`,
  plan `docs/superpowers/plans/2026-10-11-portail-catalogue-stock-tableaux.md`.
- Catalogue : `customer.articles.update` ; `PATCH customer/catalog/items/{item}`,
  `POST …/deactivate|activate` ; `UpdateCustomerCatalogItem` relit l'article
  sous verrou par `CustomerCatalogScope::lockedItem()` — l'article d'un autre
  client répond le même 404 qu'un identifiant inexistant, rien n'est écrit ni
  audité ; `catalogId`/`customerId`/`organizationId`/`status` interdits.
  Tests : `PortalCatalogUpdateTest`, `PortalCatalogIsolationTest` (GETAZ, QOQA,
  autre organisation × modifier/désactiver/activer), E2E `catalogIsolation`.
- Stock : bouton « Nouvelle demande » (`NewAdjustmentRequest`) ; stock de
  démonstration semé dans la base de dev ; règle Phase 5 inchangée.
- Tableaux : structure du back-office sur toutes les listes (choix 10–100
  lignes, actions en icônes `RowActions`, filtres au-dessus comme le
  back-office) ; `DataTable` accepte aussi des filtres sous en-tête.
- Revue indépendante finale : isolation confirmée, 0 critique. Corrigés à la
  suite : actions de ligne manquantes (commandes : suivre, dupliquer, annuler ;
  stock : modifier, demander une correction ; utilisateurs : modifier,
  désactiver, réactiver), poids/volume vidés remis à 0, mesures trop grandes
  refusées en 422, article de catalogue désactivé non rattachable au stock.
- Passe qualité (2026-10-27) : cycle complet d'une demande de correction
  vérifié (portail → validation/refus interne par `ApproveStockAdjustmentRequestAction`
  / `CreateStockMovementAction`, aucune écriture de quantité côté portail) ;
  écriture concurrente du même code → 422 au lieu de 500 ; aucun audit pour
  une modification sans effet ; recherche d'article différée (350 ms) ;
  rôles par défaut = permissions déclarées exactement, après rejeux ; seeders
  de démonstration inertes en production même avec `--force`
  (`DemoCustomerPortalIntegrationsSeeder` n'avait pas de garde).
- 92 routes externes ; Pest 2305/2305 ; Playwright 16/16 ; Vitest portail
  118/118 et back-office 832/832 ; lint, typecheck, builds, Pint verts.

**39. Statut final**

```text
CUSTOMER_PORTAL_NOT_READY_FOR_PRODUCTION
```
