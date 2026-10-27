# Rapport de non-régression — Phase 8 (§23)

Exécutions du 2026-10-10, poste de développement Windows, PHP 8.4, Node 22,
MySQL 8.0 (Laragon). Base de test `tricolisweb_test`, jamais la base de dev
pour les tests automatiques.

## Backend — suite Pest complète

`php artisan test --log-junit …` lancé détaché (la suite dure plus de 30 min :
chaque test re-sème la base).

| Mesure | Résultat |
|---|---|
| Tests | **2219 / 2219 verts** |
| Assertions | 7058 |
| Échecs, erreurs, ignorés | 0, 0, 0 |
| Durée | 55 min 40 s (19:32 → 20:28) |

La suite inclut les tests Phase 8 écrits avant son lancement :
`PortalCatalogTest`, `ExternalApiErrorsTest`, `HealthCheckTest`, et
`PortalCrossCustomerIsolationTest` étendu au catalogue.

Tests ajoutés **après** le lancement de la suite complète, exécutés à part
juste après :

| Lot | Résultat |
|---|---|
| `CorsOriginsTest` (4), `ExternalApiDocumentationTest` (3), `PortalQueryBudgetTest` (2) | 9 / 9 verts, aucun ignoré |
| Dossiers `CustomerPortal/Security`, `Performance`, `Catalog` + `HealthCheckTest` (relancés après la modification de `config/cors.php` et d'`AppServiceProvider`) | 88 / 88 verts |
| `tests/Unit/Security` | 39 / 39 verts |

### Second passage — 2026-10-10 22:10 → 23:16, après les décisions Phase 6

Connexion FTP/SFTP en base, historique d'import, index, `retry_after` :
**2252 / 2252 verts**, 7225 assertions, 0 échec, 0 ignoré (65 min 50 s).
Puis Playwright 14/14 (2,8 min) sur base re-semée ; Vitest back-office
832/832, portail 91/91 ; lint, typecheck et builds verts.

## Backend — contrôles

| Contrôle | Résultat |
|---|---|
| `vendor/bin/pint --test` | vert, tout le dépôt (7 fichiers hors portail reformatés, style seul) |
| `php artisan customer-portal:route-matrix --check` | 89 routes externes, toutes autorisées (87 avant l'historique des imports) |
| `composer validate` | valide |
| `php artisan migrate:status` (dev) | 121 migrations « Ran », 0 « Pending » |
| `php artisan schedule:list` | 4 tâches (communications, relève, purge des imports, jetons) |
| `composer audit` | 1 avertissement faible : `laravel/framework` < 13.30, XSS de la page de débogage |
| Caches de production | `config:cache`, `route:cache`, `event:cache`, `view:cache` générés sans erreur, `/up` = 200 avec la configuration cachée ; puis `optimize:clear` (poste de développement) |
| PHPStan / Psalm | non configurés dans le projet |

## Frontend — portail client

| Contrôle | Résultat |
|---|---|
| `npm run lint` (oxlint) | 0 erreur, 3 avertissements `only-export-components` (composants UI générés, préexistants) |
| `npm run typecheck` | 0 erreur |
| `npm run test` (Vitest) | **21 fichiers, 89 tests, tous verts** |
| `VITE_API_URL=… npm run build` | OK ; aucune URL `localhost` d'API dans le bundle ; plus d'alerte de taille |
| `npm run build` sans `VITE_API_URL` | échoue volontairement (message explicite) |
| `npm audit --omit=dev` | 0 vulnérabilité |

## Frontend — back-office (non modifié en Phase 8)

| Contrôle | Résultat |
|---|---|
| `npm run lint` | 0 erreur |
| `npm run typecheck` | 0 erreur |
| `npx vitest run` | **122 fichiers, 830 tests, tous verts** |
| `npm run build` | OK |

## E2E — Playwright, portail

Base de test re-semée (`migrate:fresh --seed --env=testing` + 3 seeders de
démonstration), API 8002, portail 5182.

| Spec | Parcours | Résultat |
|---|---|---|
| `catalog.spec.ts` | admin parcourt et ajoute un article ; lecteur en lecture seule | 2/2 |
| `claims.spec.ts` | déclarer un problème et suivre la réclamation | 1/1 |
| `integrations.spec.ts` | import Excel QOQA ; flux XML IKEA sans dépôt | 2/2 |
| `isolation.spec.ts` | IKEA n'atteint ni commande, ni stock, ni intégration QOQA | 1/1 |
| `onboarding.spec.ts` | invitation, activation, lecteur sans accès QOQA | 1/1 |
| `orders.spec.ts` | création, modification, duplication, annulation | 1/1 |
| `permissions.spec.ts` | refus à l'écran, par URL et par API | 1/1 |
| `stock.spec.ts` | lecture jusqu'à la commande liée ; ajout d'article | 2/2 |
| `tracking.spec.ts` | calendrier → commande → suivi ; suivi ; lien public | 3/3 |
| **Total** | | **14/14** — 3,3 min, puis 3,4 min au passage final (après les derniers changements CORS et dates) |

### Échecs rencontrés pendant la phase, et leur cause

| Échec | Cause | Correction |
|---|---|---|
| `catalog.spec` : « Column 'volume' cannot be null » affiché dans le portail | **bogue réel** : poids et volume `NOT NULL DEFAULT 0`, le formulaire envoyait `null` ; et l'erreur SQL atteignait l'écran (débogage actif en test) | colonne laissée à son défaut ; erreurs externes génériques (`ExternalApiErrors`) ; test Pest ajouté |
| `tracking.spec` : clic bloqué par un voile de `Sheet` après « Précédent » | le découpage des pages (Phase 8) laisse le calendrier affiché pendant le chargement de la page visée ; l'assertion « titre = référence » trouvait le titre de la vue rapide, et « Précédent » partait avant la navigation | la vue rapide se ferme au clic sur ses liens ; l'assertion attend l'URL et le titre de **niveau 1** de la page — elle est plus stricte, pas assouplie |

Aucun test n'a été supprimé, désactivé ou affaibli pour passer.

## Smoke test

`scripts/customer-portal-smoke.sh` contre les serveurs de développement
(API 8000, portail 5180, compte `viewer@ikea.test`) : **SMOKE OK**, 19/19.

## Restauration

Dump de la base de développement restauré dans une base neuve : 97/97 tables,
comptages identiques table par table, application fonctionnelle (détail :
`docs/deployment/customer-portal-backup-restore.md`).
