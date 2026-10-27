# Matrice des routes — audit Phase 8 (§4)

La matrice détaillée, route par route (méthode, URI, authentification,
contexte, permission, isolation, limiteur), est **générée depuis le routeur** :
[`security-route-matrix.md`](security-route-matrix.md). Elle a été régénérée en
Phase 8 après l'ajout du catalogue.

```bash
php artisan customer-portal:route-matrix --check     # échoue si une route externe perd son autorisation
php artisan customer-portal:route-matrix --output=docs/customer-portal/security-route-matrix.md
```

La CI (`deploy-production.yml`, job `backend`) lance `--check` à chaque push et
pull request.

## Synthèse

**92 routes externes**, toutes autorisées explicitement (résultat de `--check`).

| Authentification | Routes |
|---|---:|
| Jeton Sanctum du portail (`auth:sanctum` + `customer.portal`) | 82 |
| Aucune — authentification elle-même (connexion, activation, mot de passe) | 5 |
| Aucune — suivi public par jeton aléatoire | 2 |
| Clé API client (`customer-api`) | 3 |

| Méthode | Routes |
|---|---:|
| GET | 60 |
| POST | 24 |
| PUT | 4 |
| PATCH | 3 |
| DELETE | 1 |

| Préfixe | Routes |
|---|---:|
| `customer/orders` (+ documents, historique, suivi, lien public, preuves) | 21 |
| `customer/stock` | 14 |
| `customer/integrations` (dont historique des imports) | 12 |
| `customer/users` | 8 |
| `customer/auth` | 7 |
| `customer/claims` | 6 |
| `customer/catalog` (Phase 8, + modification 2026-10-11) | 7 |
| `customer/documents`, `customer/profile` | 2 + 2 |
| `customer/context`, `dashboard`, `permissions`, `roles`, `calendar`, `order-form-options`, `catalog-items`, `proofs-of-delivery` | 1 chacune |
| `public/tracking` | 2 |
| `client/*` | 3 |

## Points vérifiés

| Contrôle | Résultat |
|---|---|
| Toute route sous `api/v1/customer` hors `auth/*` exige `auth:sanctum` + `customer.portal` | oui (`PortalRouteGuardsTest`) |
| Toute route métier exige une permission `customer.*` (`customer.permission`) | oui, sauf : `auth/logout`, `auth/me`, `context`, `permissions`, `profile` (GET/PUT) — la session elle-même ; `dashboard` — chaque widget exige sa permission ; `documents/{document}` (+ `download`) — permission vérifiée selon l'objet de rattachement (commande, service, réclamation) |
| Isolation déclarée pour chaque préfixe (`PortalRouteSecurity::ISOLATION`) | oui, y compris `catalog` → `CustomerCatalogScope` |
| Limiteur de débit sur chaque route externe | oui (`customer-portal`, `customer-portal-login`, `public-tracking`, `customer-api`, limiteurs dédiés import et relance) |
| Aucun `customerId` / `organizationId` accepté en entrée | oui : 422 (`ServerControlledFields`, tests `PortalEscalationTest`, `PortalCatalogTest`) |
| Jeton du portail refusé sur le back-office, et inversement | oui (`PortalRouteGuardsTest`) |
| Ressource d'un autre client | 404 (jamais 403, qui confirmerait l'existence) — `PortalCrossCustomerIsolationTest` |
| Message d'un 404 / 500 sur le périmètre externe | générique, sans classe ni SQL ni trace, même avec `APP_DEBUG` (`ExternalApiErrors`, Phase 8, `ExternalApiErrorsTest`) |

## Corrections de routes en Phase 8

- Ajout : 4 routes `customer/catalog/*` (catalogues, articles, fiche, ajout).
- Ajout (2026-10-10, décision Phase 6) : `customer/integrations/import-runs` et
  `import-runs/{importRun}` — historique des imports, `customer.integrations.logs.view`.
- Ajout (2026-10-11) : `PATCH customer/catalog/items/{item}`, `POST …/deactivate`,
  `POST …/activate` — `customer.articles.update`, article résolu par
  `CustomerCatalogScope::lockedItem()` (portée du client, sous verrou).
- Aucune route existante modifiée ni supprimée.
