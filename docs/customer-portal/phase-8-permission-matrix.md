# Matrice des permissions — audit Phase 8 (§6)

Source : `CustomerPortalPermissions` (47 permissions `customer.*`, déclarées
une fois), `CustomerPortalRoles::defaults()` (4 rôles fournis), et la matrice
générée [`security-route-matrix.md`](security-route-matrix.md) pour les
routes. Vue fonctionnelle (qui fait quoi) : [`access-matrix.md`](access-matrix.md).

Colonne « Routes » : nombre de routes dont le middleware `customer.permission`
exige la permission. « contrôleur » / « ressource » : vérifiée dans le code
(`CurrentCustomerContext::can`) plutôt qu'au niveau de la route.
« réservée » : déclarée, attribuable, mais aucune route ne l'utilise encore.

L'administrateur client (`CUSTOMER_ADMIN`) reçoit **toutes** les permissions.

| Permission | Routes | Admin | Commandes | Stock | Lecteur |
|---|---|:-:|:-:|:-:|:-:|
| `customer.articles.create` | 1 | oui | — | oui | — |
| `customer.articles.update` | 3 | oui | — | oui | — |
| `customer.articles.view` | 2 | oui | oui | oui | oui |
| `customer.calendar.view` | 1 | oui | oui | — | oui |
| `customer.catalog.view` | 7 | oui | oui | oui | oui |
| `customer.claims.create` | 2 | oui | oui | — | — |
| `customer.claims.update` | 1 | oui | oui | — | — |
| `customer.claims.view` | 3 | oui | oui | — | oui |
| `customer.integrations.create` | réservée | oui | — | — | — |
| `customer.integrations.disable` | réservée | oui | — | — | — |
| `customer.integrations.export_jobs.retry` | 1 | oui | — | — | — |
| `customer.integrations.import` | 3 | oui | oui | — | — |
| `customer.integrations.logs.view` | 4 | oui | — | — | — |
| `customer.integrations.mapping.manage` | réservée | oui | — | — | — |
| `customer.integrations.mapping.view` | ressource (correspondance affichée) | oui | — | — | — |
| `customer.integrations.status_mapping.manage` | réservée | oui | — | — | — |
| `customer.integrations.test` | réservée | oui | — | — | — |
| `customer.integrations.update` | réservée | oui | — | — | — |
| `customer.integrations.view` | 12 | oui | oui | — | — |
| `customer.orders.cancel` | 1 | oui | oui | — | — |
| `customer.orders.create` | 1 | oui | oui | — | — |
| `customer.orders.documents.upload` | 1 | oui | oui | — | — |
| `customer.orders.documents.view` | 3 | oui | oui | — | oui |
| `customer.orders.duplicate` | 1 | oui | oui | — | — |
| `customer.orders.history.view` | 1 | oui | oui | — | oui |
| `customer.orders.proofs_of_delivery.view` | 3 | oui | oui | — | oui |
| `customer.orders.track` | contrôleur (suivi) | oui | oui | — | oui |
| `customer.orders.update` | 1 | oui | oui | — | — |
| `customer.orders.view` | 7 | oui | oui | — | oui |
| `customer.roles.assign` | réservée | oui | — | — | — |
| `customer.roles.view` | 1 | oui | — | — | — |
| `customer.stock.adjustment_requests.create` | 1 | oui | — | oui | — |
| `customer.stock.adjustment_requests.view` | 2 | oui | — | oui | — |
| `customer.stock.items.create` | 2 | oui | — | oui | — |
| `customer.stock.items.update` | 1 | oui | — | oui | — |
| `customer.stock.movements.view` | 2 | oui | — | oui | oui |
| `customer.stock.reservations.view` | 2 | oui | — | oui | oui |
| `customer.stock.view` | 4 | oui | — | oui | oui |
| `customer.tracking.public_link.manage` | 2 | oui | — | — | — |
| `customer.tracking.public_link.view` | 1 | oui | — | — | — |
| `customer.tracking.view` | 2 | oui | oui | — | oui |
| `customer.users.assign_role` | 1 | oui | — | — | — |
| `customer.users.create` | réservée | oui | — | — | — |
| `customer.users.disable` | 2 | oui | — | — | — |
| `customer.users.invite` | 2 | oui | — | — | — |
| `customer.users.update` | 1 | oui | — | — | — |
| `customer.users.view` | 2 | oui | — | — | — |

## Barrières qui s'ajoutent à la permission

| Barrière | Effet | Où |
|---|---|---|
| Module fermé au client | `catalog_enabled`, `stock_enabled`, `tracking_enabled` à faux → la permission correspondante est retirée du contexte (403) | `CustomerFeatureGate` |
| Client inactif, accès portail désactivé, compte inactif | 401/403 à la requête suivante | `EnsureCustomerPortalContext` |
| Organisation suspendue ou fermée | portail fermé à la requête suivante et à la connexion | `PortalOrganizationGate` |
| Délégation | un administrateur n'attribue jamais une permission qu'il n'a pas | `CustomerPermissions::delegableFor`, `PortalEscalationTest` |
| Propriétaire du compte | ni désactivable ni rétrogradable depuis le portail | `ManagedMemberGuard` |

## Constats Phase 8

- Aucune permission interne (`orders.*`, `stock.*` du back-office) n'est
  acceptée par une route du portail ; aucune permission `customer.*` n'ouvre une
  route du back-office (`PortalRouteGuardsTest`).
- Le catalogue utilise désormais `customer.catalog.view`,
  `customer.articles.view` et `customer.articles.create` (réservées jusqu'en
  Phase 7).
- 8 permissions restent réservées (création de compte direct, attribution de
  rôle par code, configuration des intégrations par le client) : leur
  ouverture est une décision produit, pas une correction.
- Une permission ajoutée le 2026-10-11 : `customer.articles.update` (modifier,
  désactiver, réactiver un article ; administrateur et gestionnaire de stock).
  En production, `php artisan db:seed --class=CustomerPortalSeeder --force` la
  crée et l'accorde aux rôles par défaut existants, sans toucher aux réglages
  du transporteur.

## Frontend

Le portail masque ce que la permission n'autorise pas (`<Can>`,
`ProtectedRoute permission=…`, navigation filtrée) ; ce masquage n'est jamais
la seule barrière — l'API refuse elle aussi (E2E `permissions.spec.ts` :
écran, URL directe et API).
