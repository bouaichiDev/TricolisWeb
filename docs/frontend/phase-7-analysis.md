# Phase 7 Frontend — Analyse préalable

> Analyse conduite avant écriture de code, sur le backend réellement implémenté.
> Le comparatif « existant / à ajouter » est dans
> [phase-7-gap-analysis.md](phase-7-gap-analysis.md).

## 1. Migrations

`2026_08_05_100001` à `100006`. Cinq tables, plus une clé étrangère ajoutée à
`packages`.

| Table | Particularités relevées |
|---|---|
| `stock_items` | `unique(customer_id, article_code)` **et** `unique(customer_id, barcode)` ; `catalog_item_id` nullable, `nullOnDelete` |
| `stock_locations` | `unique(depot_id, location_code)` et `unique(depot_id, barcode)` ; `parent_location_id` auto-référent, `restrictOnDelete` |
| `stock_balances` | `unique(stock_item_id, stock_location_id)` ; trois `decimal(12,3)` ; `updated_at` non nullable, **sans** `created_at` |
| `stock_movements` | `created_at` seul (pas d'`updated_at`) — la table est un journal ; `created_by` `nullOnDelete` |
| `stock_reservations` | `released_at` nullable ; index sur `status`, `reserved_at`, `released_at` |

**Aucune colonne `legacy_id`** sur `stock_locations` ni `stock_movements`,
contrairement aux §10 et §18 du prompt. La migration le documente : « le
diagramme n'en mentionne aucun ». Écart acté, rien n'est typé côté frontend.

Toutes les suppressions sont `restrictOnDelete` : un refus arrive donc en 409,
jamais en cascade silencieuse.

## 2. Models

`StockItem`, `StockLocation`, `StockBalance`, `StockMovement`,
`StockReservation`, tous sous `App\Modules\Stock\Models`. Chacun expose un scope
`inOrganization()` — c'est lui qui porte l'isolation multi-organisation.
`StockReservation::isReleased()` existe et sert au refus de double libération.

## 3. Resources

13 ressources, trois niveaux : `List`, `Detail`, `Compact`. Champs exposés :

- `StockItemList` : + `customerName` (`whenLoaded`). **Aucune quantité.**
- `StockItemDetail` : + `customer`, `balances`.
- `StockLocationList` : tous les champs + `childCount` (`whenCounted`).
- `StockLocationDetail` : + `parent`, `children`, `balances`.
- `StockLocationTree` : sous-ensemble + `children` récursifs.
- `StockBalanceList` : + `articleCode`, `locationCode` (`whenLoaded`).
- `StockMovementDetail` : + `stockItem`, `sourceLocation`, `destinationLocation`, `creator`.
- `StockReservationList` / `Detail` : `Detail` ajoute `stockItem`, `stockLocation`.

**`StockReservationDetailResource` n'expose pas la ligne de commande**, seulement
`orderLineId`. Une fiche réservation ne peut donc pas nommer la commande sans un
second appel — voir §23.

## 4. Requests

`ListRequest` de base donne `page`, `perPage` (max **100**), `search`, `sort`,
`direction`, `status`, `compact`, `createdFrom/To`.

| Requête | Filtres propres |
|---|---|
| `ListStockItemRequest` | `customerId`, `catalogItemId`, `articleCode`, `barcode` |
| `ListStockLocationRequest` | `depotId`, `parentLocationId`, `zoneCode`, `aisle`, `rack`, `level`, `locationCode`, `barcode` |
| `ListStockBalanceRequest` | `stockItemId`, `stockLocationId`, `customerId`, `availableOnly` |
| `ListStockMovementRequest` | `stockItemId`, `sourceLocationId`, `destinationLocationId`, `movementType`, `sourceEntityType`, `sourceEntityId`, `createdBy` |
| `ListStockReservationRequest` | `stockItemId`, `stockLocationId`, `orderLineId`, `reservedFrom/To`, `releasedFrom/To` |

Écritures : `status` est `required|string|max:32` partout où la colonne existe.
`quantity` est `required|numeric|gt:0` pour mouvement et réservation.
`Release` et `Update` de réservation n'acceptent **que** `status`.

## 5. Actions

12 Actions. `CreateStockMovementAction` et `CreateStockReservationAction`
enveloppent tout dans `DB::transaction` avec `StockBalanceLocker` en
`lockForUpdate`, puis `RecalculateStockBalance`. `ReleaseStockReservationAction`
vérifie `isReleased()` **deux fois** : avant la transaction et sous verrou.

## 6. Policies

Cinq policies. Chacune reçoit `$organizationId` en second argument pour les
capacités de classe (`viewAny`, `create`). `StockBalancePolicy` n'expose que
`viewAny` et `view` — aucune écriture n'est autorisable.

## 7. PermissionSeeder

Les 20 codes du §59 existent, tous rattachés à `MenuSection::STOCK` via
`PermissionMenuMap`. Aucune permission à ajouter.

## 8. Routes

Voir le tableau « Fonction UI → endpoint » en fin de document. Deux points :

- `stock-locations/tree` est déclarée **avant** `apiResource`, sinon `tree`
  serait lue comme un identifiant.
- `stock-movements` est limitée à `index`, `store`, `show` ; `stock-reservations`
  à `index`, `store`, `show`, `update`, plus `release`. Aucune route de
  suppression n'existe : l'immuabilité est structurelle, pas conventionnelle.

## 9–11. Filtres, tris, pagination

Tris autorisés (liste blanche serveur ; toute autre valeur donne un 422) :

| Entité | `sort` acceptés | Défaut |
|---|---|---|
| Items | `article_code`, `barcode`, `status` | `article_code` |
| Locations | `zone_code`, `aisle`, `rack`, `level`, `location_code`, `status` | `location_code` |
| Balances | `quantity`, `reserved_quantity`, `available_quantity`, `updated_at` | `updated_at` |
| Movements | `created_at`, `quantity`, `movement_type` | `created_at` |
| Reservations | `reserved_at`, `released_at`, `quantity`, `status` | `reserved_at` |

Recherche plein texte : items sur `article_code`/`barcode`/`description` ;
locations sur les six champs de coordonnées ; movements sur `movement_type` et
`source_entity_type`. **Balances et réservations n'ont pas de recherche.**

Pagination serveur partout, `perPage` plafonné à 100.

## 12. Sources de statut

`MorphMap` déclare `stock_item`, `stock_location`, `stock_balance`,
`stock_movement`, `stock_reservation`.

**`StatusSeeder` n'en sème aucune.** Les trois colonnes `status` réelles
(`stock_items`, `stock_locations`, `stock_reservations`) sont donc des chaînes
libres sans référentiel, et le frontend existant écrivait `'active'` en dur.
`docs/backend/statuses-global-audit.md` les marque « À sa phase » — c'est cette
phase. **Correction portée par la Phase 7.**

## 13. movementType réel

`string(64)`, `required`, aucune énumération, aucune table de référence, aucune
constante PHP. `CreateStockMovementAction` ne l'interprète pas.

**Blocker documenté** : il n'existe aucune source contrôlée de types de
mouvement. Conformément au §20, rien n'est codé en dur ; le champ reste une
saisie libre, assistée par les valeurs déjà employées (autocomplétion sur les
mouvements existants via le filtre `movementType`).

## 14. Nullabilité source/destination

Les deux sont nullables. `CreateStockMovementAction` impose : au moins une des
deux, les deux différentes, et **même dépôt** si les deux sont présentes. Un
transfert inter-dépôts est donc refusé — il s'enregistre en sortie puis entrée.

## 15. Nullabilité catalogItemId

Nullable en base, `nullOnDelete`. Une marchandise peut être suivie sans figurer
au catalogue.

## 16. Hiérarchie

`parent_location_id` auto-référent. `ValidateStockLocationHierarchy` refuse
l'auto-parentage, les cycles et un parent d'un autre dépôt. `tree()` charge tout
en une requête puis regroupe en mémoire — pas de N+1, mais pas de pagination non
plus : l'arbre est chargé entier, d'où le filtre par dépôt.

## 17. Multi-organisation

`inOrganization()` sur chaque modèle, plus `abort_unless(... 404)` dans chaque
`show`. Un identifiant d'une autre organisation renvoie 404, pas 403.

## 18. Calcul du solde

`RecalculateStockBalance` applique `available = quantity - reserved` et refuse
tout résultat négatif. `StockBalanceLocker::lockOrCreate` prend le verrou.

## 19–21. Verrous et cycle de libération

Mouvements et réservations verrouillent le solde avant contrôle. Un conflit
(stock insuffisant, double libération) sort en `StockConflict`, rendu **409**
avec un message rédigé, à afficher tel quel.

## 22. Package.currentStockLocationId

La colonne existe, la clé étrangère est posée, `PackageResource` l'expose, et le
frontend l'affiche déjà **brut** dans `OrderPackageFields`. À résoudre en code
d'emplacement lisible. Aucune écriture automatique n'est prévue.

## 23. OrderLine

`OrderLineResource` expose `reservedQuantity`. La réservation référence
`order_line_id` ; le chemin inverse (réservations d'une ligne) passe par
`GET /stock-reservations?orderLineId=`.

## 24. sourceEntityType

Validé par `Rule::in(array_keys(MorphMap::registered()))`. La liste est donc
fournie par le serveur — aucune valeur ne doit être inventée côté frontend.
`sourceEntityId` est `required_with:sourceEntityType`.

## 25. Suppression

Items et locations seulement, en 409 si des dépendances subsistent. Mouvements et
réservations : aucune route.

## 26. Audit

`BuildsAuditContext` sur chaque contrôleur écrivant. Événements :
`stock_item.created/updated/deleted`, `stock_location.*`,
`stock_movement.created`, `stock_reservation.created/updated/released`.

## 27. Décimaux

`decimal(12,3)` en base, rendus **en chaînes** par l'API. Les types frontend
acceptent `number | string` et ne convertissent qu'à l'affichage — jamais avant
de renvoyer une valeur.

## 28. Concurrence

Aucune mise à jour optimiste sur les quantités. Un 409 déclenche un rafraîchissement
des soldes et l'affichage du message serveur.

## 29. Performance / N+1

Les Queries chargent explicitement (`with(...)`, `withCount(...)`).

**Blocker relevé** : `StockItemListResource` n'expose **aucune quantité** et
aucune requête d'agrégat n'existe. Les colonnes « Total / Réservé / Disponible »
du §42 et les KPI du §40 sont donc impossibles sans N+1 — que le §42 interdit
explicitement. Les quantités d'un article se lisent sur sa fiche, où
`StockItemDetailResource` charge `balances`. Le tableau de bord agrège la page
courante des soldes et **le dit**.

## 30. Tests

Backend : 5 suites (`StockItemTest`, `StockLocationTest`, `StockMovementTest`,
`StockReservationTest`, `StockPermissionTest`), plus
`OrderStockConsumptionTest`. Frontend : 2 fichiers avant cette phase.

---

## Tableau Fonction UI → Endpoint

| Fonction UI | Endpoint réel | Permission | Resource | Statut |
|---|---|---|---|---|
| Tableau de bord stock | `GET /stock-balances` | `stock_balances.view` | `StockBalanceList` | À faire |
| Liste articles | `GET /stock-items` | `stock_items.view` | `StockItemList` | À faire |
| Articles d'un client | `GET /customers/{c}/stock-items` | `stock_items.view` | `StockItemList` | À faire |
| Fiche article | `GET /stock-items/{id}` | `stock_items.view` | `StockItemDetail` | À faire |
| Créer article | `POST /customers/{c}/stock-items` | `stock_items.create` | `StockItemDetail` | Existant |
| Modifier article | `PATCH /stock-items/{id}` | `stock_items.update` | `StockItemDetail` | À faire |
| Supprimer article | `DELETE /stock-items/{id}` | `stock_items.delete` | — (409) | À faire |
| Liste emplacements | `GET /stock-locations` | `stock_locations.view` | `StockLocationList` | Existant |
| Arbre emplacements | `GET /stock-locations/tree` | `stock_locations.view` | `StockLocationTree` | À faire |
| Fiche emplacement | `GET /stock-locations/{id}` | `stock_locations.view` | `StockLocationDetail` | À faire |
| Créer / modifier emplacement | `POST` / `PATCH /stock-locations` | `.create` / `.update` | `StockLocationDetail` | Existant, à compléter |
| Supprimer emplacement | `DELETE /stock-locations/{id}` | `stock_locations.delete` | — (409) | Existant |
| Liste soldes | `GET /stock-balances` | `stock_balances.view` | `StockBalanceList` | À faire |
| Soldes d'un client | `GET /customers/{c}/stock-balances` | `stock_balances.view` | `StockBalanceList` | À faire |
| Liste mouvements | `GET /stock-movements` | `stock_movements.view` | `StockMovementList` | À faire |
| Fiche mouvement | `GET /stock-movements/{id}` | `stock_movements.view` | `StockMovementDetail` | À faire |
| Créer mouvement | `POST /stock-movements` | `stock_movements.create` | `StockMovementDetail` | Existant |
| Liste réservations | `GET /stock-reservations` | `stock_reservations.view` | `StockReservationList` | À faire |
| Fiche réservation | `GET /stock-reservations/{id}` | `stock_reservations.view` | `StockReservationDetail` | À faire |
| Créer réservation | `POST /stock-reservations` | `stock_reservations.create` | `StockReservationDetail` | À faire |
| Modifier statut réservation | `PATCH /stock-reservations/{id}` | `stock_reservations.update` | `StockReservationDetail` | À faire |
| Libérer réservation | `POST /stock-reservations/{id}/release` | `stock_reservations.release` | `StockReservationDetail` | À faire |
| Réservations d'une ligne | `GET /stock-reservations?orderLineId=` | `stock_reservations.view` | `StockReservationList` | À faire |

## Blockers

1. **`movementType` sans source contrôlée** (§20). Saisie libre assistée ;
   aucune énumération inventée.
2. **Pas d'agrégat de quantités par article** (§40, §42). Colonnes de totaux
   retirées de la liste des articles ; KPI limités à ce que la page charge, et
   l'écran le dit.
3. **`StatusSeeder` ne connaît pas les sources de stock** (§31). Corrigé dans
   cette phase, côté backend.
