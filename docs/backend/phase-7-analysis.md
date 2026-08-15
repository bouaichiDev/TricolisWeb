# Analyse Phase 7 — Stock client chez le transporteur

Répond au §3. Aucune migration écrite avant que le tableau du §5 soit terminé.

---

## 1. Sources de vérité et conflits

Les deux `.puml` du §1 n'existent pas ; les `.txt` font foi. Classes lignes
379-438, relations lignes 869 et 878-887.

### Deux conflits, même arbitrage qu'aux Phases 3, 5 et 6

| # | Le prompt dit | Le diagramme dit | Décision |
|---|---------------|------------------|----------|
| A | `StockLocation.legacyId: bigint` (§9, §11) | 10 attributs, sans `legacyId` | colonne **non créée** |
| B | `StockMovement.legacyId: bigint` (§15, §19) | 10 attributs, sans `legacyId` | colonne **non créée** |

Le §1 donne priorité au diagramme, le §2 interdit d'ajouter un attribut absent.
Les filtres et tris `legacyId` des §11 et §19 sont sans objet. Un test vérifie.

Hors ces deux points, prompt et diagramme concordent exactement.

## 2. État du code et dépendances

Phases 1 à 6 livrées : **458 tests**, 1450 assertions, 257 routes.

| Table | Phase | Usage |
|-------|-------|-------|
| `customers` | 1 | `stock_items.customer_id` |
| `customer_catalog_items` | 2 | `stock_items.catalog_item_id` |
| `depots` | 1 | `stock_locations.depot_id` |
| `order_lines` | 2 | `stock_reservations.order_line_id` |
| `users` | 1 | `stock_movements.created_by` |
| `packages` | 2 | `current_stock_location_id` — voir §9 |

Branche partie de la Phase 6, non de `main`. Même écart assumé.

## 3. Classes et relations

```text
StockItem   StockLocation   StockBalance   StockMovement   StockReservation
```

```text
Customer            "1"    -- "0..*" StockItem
CustomerCatalogItem "0..1" -- "0..*" StockItem
Depot               "1"    -- "0..*" StockLocation
StockLocation       "0..1" --> "0..*" StockLocation : parent
StockItem           "1"    -- "0..*" StockBalance
StockLocation       "1"    -- "0..*" StockBalance
StockItem           "1"    -- "0..*" StockMovement
StockItem           "1"    -- "0..*" StockReservation
StockLocation       "1"    -- "0..*" StockReservation
OrderLine           "1"    -- "0..*" StockReservation
StockLocation       "0..1" -- "0..*" Package : currentLocation
```

**Aucune composition** : que des associations. Aucune cascade dans cette phase.

**Aucun enum** : `status` (×3), `movementType` et `sourceEntityType` sont des
`string` sans valeurs énumérées.

## 4. Isolation organisationnelle

Le point le plus délicat de la phase : **ni `StockItem` ni `StockLocation` ne
portent `organizationId`**, et le §2 interdit de l'ajouter.

Deux chaînes distinctes, documentées comme le §25 l'exige :

```text
StockItem     → customer.organization_id
StockLocation → depot.agency.organization_id
```

`depots` n'a pas d'`organization_id` non plus : il passe par `agencies`. La
chaîne est donc à **deux** jointures, et c'est le scope `inOrganization` du
modèle qui la porte — un seul endroit, pour qu'aucune lecture ne l'oublie.

Les entités dérivées suivent :

| Classe | Isolation |
|--------|-----------|
| `StockBalance` | via `stockItem.customer` |
| `StockMovement` | via `stockItem.customer` |
| `StockReservation` | via `stockItem.customer` |

**Toute opération croisant les deux chaînes vérifie les deux** : un mouvement
dont l'article appartient au client A et l'emplacement au dépôt de
l'organisation B est refusé.

## 5. Tableau de correspondance

### StockItem → `stock_items`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `customerId` | ULID | `customer_id` CHAR(26) | non | index + unique composite | FK `customers.id` RESTRICT |
| `catalogItemId` | ULID | `catalog_item_id` CHAR(26) | **oui** | index | FK `customer_catalog_items.id` SET NULL |
| `articleCode` | string | `article_code` VARCHAR(64) | non | unique `(customer_id, article_code)` | — |
| `barcode` | string | `barcode` VARCHAR(128) | **oui** | unique `(customer_id, barcode)` | — |
| `description` | string | `description` VARCHAR(255) | **oui** | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### StockLocation → `stock_locations`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `depotId` | ULID | `depot_id` CHAR(26) | non | index + unique composite | FK `depots.id` RESTRICT |
| `parentLocationId` | ULID | `parent_location_id` CHAR(26) | **oui** | index | FK `stock_locations.id` RESTRICT |
| `zoneCode` | string | `zone_code` VARCHAR(64) | **oui** | index | — |
| `aisle` | string | `aisle` VARCHAR(32) | **oui** | — | — |
| `rack` | string | `rack` VARCHAR(32) | **oui** | — | — |
| `level` | string | `level` VARCHAR(32) | **oui** | — | — |
| `locationCode` | string | `location_code` VARCHAR(64) | non | unique `(depot_id, location_code)` | — |
| `barcode` | string | `barcode` VARCHAR(128) | **oui** | unique `(depot_id, barcode)` | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### StockBalance → `stock_balances`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `stockItemId` | ULID | `stock_item_id` CHAR(26) | non | unique composite | FK `stock_items.id` RESTRICT |
| `stockLocationId` | ULID | `stock_location_id` CHAR(26) | non | unique `(stock_item_id, stock_location_id)` | FK `stock_locations.id` RESTRICT |
| `quantity` | decimal | `quantity` DECIMAL(12,3) | non, défaut 0 | — | — |
| `reservedQuantity` | decimal | `reserved_quantity` DECIMAL(12,3) | non, défaut 0 | — | — |
| `availableQuantity` | decimal | `available_quantity` DECIMAL(12,3) | non, défaut 0 | — | **dérivé** |
| `updatedAt` | datetime | `updated_at` DATETIME | non | — | — |

`stock_balances` est la **seule** table de la phase à porter `updated_at` : le
diagramme le déclare, et c'est cohérent — un solde est un état courant, pas un
événement.

### StockMovement → `stock_movements`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `stockItemId` | ULID | `stock_item_id` CHAR(26) | non | index + `(stock_item_id, created_at)` | FK `stock_items.id` RESTRICT |
| `sourceLocationId` | ULID | `source_location_id` CHAR(26) | **oui** | index | FK `stock_locations.id` RESTRICT |
| `destinationLocationId` | ULID | `destination_location_id` CHAR(26) | **oui** | index | FK `stock_locations.id` RESTRICT |
| `movementType` | string | `movement_type` VARCHAR(64) | non | index | — |
| `quantity` | decimal | `quantity` DECIMAL(12,3) | non | — | — |
| `sourceEntityType` | string | `source_entity_type` VARCHAR(64) | **oui** | index | alias morph map, **pas de FK** |
| `sourceEntityId` | ULID | `source_entity_id` CHAR(26) | **oui** | index | **pas de FK** |
| `createdBy` | ULID | `created_by` CHAR(26) | **oui** | index | FK `users.id` SET NULL |
| `createdAt` | datetime | `created_at` DATETIME | non | index | — |

### StockReservation → `stock_reservations`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `stockItemId` | ULID | `stock_item_id` CHAR(26) | non | index | FK `stock_items.id` RESTRICT |
| `stockLocationId` | ULID | `stock_location_id` CHAR(26) | non | index | FK `stock_locations.id` RESTRICT |
| `orderLineId` | ULID | `order_line_id` CHAR(26) | non | index | FK `order_lines.id` RESTRICT |
| `quantity` | decimal | `quantity` DECIMAL(12,3) | non | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |
| `reservedAt` | datetime | `reserved_at` DATETIME | non | index | — |
| `releasedAt` | datetime | `released_at` DATETIME | **oui** | index | — |

## 6. Unicité de StockItem (§7)

Le §7 demande d'analyser trois candidats et de documenter chaque choix.

| Candidat | Retenu | Raison |
|----------|--------|--------|
| `(customer_id, article_code)` | **oui** | Le code article identifie la référence chez le client. Deux articles homonymes chez le même client seraient indiscernables en préparation de commande. |
| `(customer_id, barcode)` | **oui**, sur colonne nullable | Un code-barres scanné doit désigner un article et un seul. `NULL` restant distinct sous MySQL, les articles sans code-barres restent possibles en nombre. |
| `(customer_id, catalog_item_id)` | **non** | Rien n'interdit que deux références de stock pointent le même article de catalogue — conditionnements différents, par exemple. Le §7 met en garde contre l'invention silencieuse. |

**`barcode` n'est pas unique globalement** : le §7 l'interdit sans
justification. Deux clients distincts peuvent employer le même code-barres pour
des articles différents — c'est fréquent avec les codes internes.

## 7. Formule du solde (§13)

Recherche menée : aucune règle différente dans les Phases 1 à 6. La formule
directe est retenue et **centralisée** :

```text
availableQuantity = quantity − reservedQuantity
```

`available_quantity` est une colonne du diagramme, donc stockée — mais elle
n'est **jamais acceptée en entrée**. `RecalculateStockBalance` la recalcule à
chaque écriture. Le §13 l'exige : « ne jamais faire confiance à une valeur
client incohérente ».

Trois invariants tenus par les Actions, sous verrou :

```text
quantity          >= 0
reservedQuantity  >= 0
reservedQuantity  <= quantity
```

## 8. Verrouillage pessimiste

Le §27 l'impose pour les mouvements et les réservations. Le projet a déjà ce
mécanisme depuis la Phase 2 (`GenerateOrderNumber`, allocation des quantités de
colis) : `lockForUpdate()` dans une transaction.

Sans lui, deux réservations concurrentes sur le même solde liraient la même
`availableQuantity` et réserveraient chacune la totalité. Le solde est donc
verrouillé **avant** toute lecture de quantité, et jusqu'à la fin de la
transaction.

## 9. `Package.currentStockLocationId` (§26)

Le §26 demande d'analyser cette relation et de dire si une correction de
migration est nécessaire. **Elle l'est.**

La migration `2026_08_01_100005_create_packages_table` de la Phase 2 crée bien
la colonne, avec ce commentaire :

```php
// Emplacement de stock courant : la colonne figure au diagramme mais
// `stock_locations` relève d'une phase ultérieure. Aucune contrainte
// n'est posée tant que la table n'existe pas.
$table->char('current_stock_location_id', 26)->nullable();
```

`stock_locations` existe maintenant. Une migration additive
`2026_08_05_100006_add_stock_location_foreign_key_to_packages_table` pose la clé
étrangère, en `SET NULL` : supprimer un emplacement ne doit pas supprimer les
colis qui s'y trouvaient, seulement les délocaliser.

**La migration de la Phase 2 n'est pas modifiée** — elle est peut-être déjà
exécutée en production. La correction est additive.

**Aucune modification automatique de `Package`** : le §26 l'interdit sans règle
métier explicite, et aucune n'existe. Créer un mouvement de stock ne déplace pas
le colis ; aucune table `PackageLocationHistory` n'est créée.

## 10. Référence générique de `StockMovement` (§18)

`sourceEntityType` / `sourceEntityId` sont une référence métier contrôlée.

Une morph map existe depuis la Phase 1 (`App\Shared\Database\MorphMap`) : elle
est **réutilisée**. `sourceEntityType` n'accepte que des alias qu'elle connaît —
jamais un nom de classe PHP.

**Aucune clé étrangère SQL sur `source_entity_id`** : le §18 l'interdit, et
c'est structurellement impossible, la colonne pouvant désigner plusieurs tables.

## 11. Nullabilité de `StockMovement` (§16)

| Colonne | Choix | Raison |
|---------|-------|--------|
| `source_location_id`, `destination_location_id` | **les deux nullables** | Le §21 pose la règle structurelle : « au moins source ou destination renseignée ». Une entrée n'a pas de source, une sortie pas de destination. Les rendre obligatoires interdirait l'un ou l'autre. |
| `source_entity_type`, `source_entity_id` | **nullables** | Un mouvement d'inventaire manuel ne vient d'aucune entité. |
| `created_by` | **nullable** | Cohérent avec `documents.created_by` et `tracking_events.created_by` : un mouvement produit par un automate n'a pas d'auteur. |

Contrainte applicative : **au moins l'une des deux localisations** doit être
fournie, et elles doivent différer. C'est la seule règle structurelle du §21 ;
aucun type de mouvement n'est interprété, le diagramme n'en énumérant aucun.

**Les deux emplacements doivent relever du même dépôt.** Le §21 laisse le choix
« même Depot ou règle inter-dépôt documentée » : le transfert inter-dépôt est
refusé, faute de règle définie pour le représenter — il se fait en deux
mouvements, une sortie puis une entrée.

## 12. Suppression

| Clé étrangère | Stratégie |
|---------------|-----------|
| `stock_items.customer_id` | `RESTRICT` |
| `stock_items.catalog_item_id` | `SET NULL` |
| `stock_locations.depot_id` | `RESTRICT` |
| `stock_locations.parent_location_id` | `RESTRICT` |
| `stock_balances.*` | `RESTRICT` |
| `stock_movements.stock_item_id`, `source_location_id`, `destination_location_id` | `RESTRICT` |
| `stock_movements.created_by` | `SET NULL` |
| `stock_reservations.*` | `RESTRICT` |
| `packages.current_stock_location_id` | `SET NULL` |

**Aucune cascade** : le stock est un état comptable, rien ne disparaît en
chaîne.

Refus applicatifs :

| Ressource | Refus | Code |
|-----------|-------|------|
| `StockItem` | possède un solde non nul, un mouvement ou une réservation | 409 |
| `StockLocation` | possède des enfants, un solde non nul ou une réservation active | 409 |
| `StockMovement` | **aucune route** `PATCH` ni `DELETE` | 405 |
| `StockReservation` | pas de `DELETE` ; libération par `POST /release` | — |

## 13. Hiérarchie des emplacements (§10)

Quatre interdits, tous testés :

- parent égal à soi-même ;
- parent descendant (cycle indirect) ;
- déplacement créant une boucle ;
- parent d'un autre dépôt.

`ValidateStockLocationHierarchy` remonte la chaîne des parents jusqu'à la racine
et refuse si l'emplacement modifié y apparaît. **Aucune profondeur maximale
n'est fixée** — le §10 l'interdit — mais la remontée est bornée par le nombre
d'emplacements du dépôt, ce qui la termine toujours.

`GET /stock-locations/tree` dérive l'arbre de `stock_locations` par un seul
`SELECT`, puis l'assemble en mémoire. Aucune table supplémentaire.

## 14. Permissions et endpoints

15 permissions :

```text
stock_items.view / create / update / delete
stock_locations.view / create / update / delete
stock_balances.view
stock_movements.view / create
stock_reservations.view / create / update / release
```

Pas de `stock_balances.create|update|delete` : le §14 interdit un CRUD public
sur les soldes, qui ne bougent que par les mouvements et réservations.

Pas de `stock_movements.update|delete` ni `stock_reservations.delete` : les
routes n'existent pas.

25 routes :

```text
GET|POST          /stock-items
GET|PATCH|DELETE  /stock-items/{stockItem}
GET|POST          /customers/{customer}/stock-items
GET               /stock-locations/tree
GET|POST          /stock-locations
GET|PATCH|DELETE  /stock-locations/{stockLocation}
GET               /stock-balances
GET               /stock-balances/{stockBalance}
GET               /customers/{customer}/stock-balances
GET|POST          /stock-movements
GET               /stock-movements/{stockMovement}
GET|POST          /stock-reservations
GET|PATCH         /stock-reservations/{stockReservation}
POST              /stock-reservations/{stockReservation}/release
```

`/stock-locations/tree` précède `/stock-locations/{stockLocation}`, sinon
`tree` serait capté comme un identifiant.

## 15. Ordre des migrations

```text
1. stock_items
2. stock_locations
3. stock_balances
4. stock_movements
5. stock_reservations
6. add_stock_location_foreign_key_to_packages_table
```

**Aucun soft delete.** Seul `stock_balances` porte `updated_at` ; seuls
`stock_movements` et `stock_reservations` portent une date de création
(`created_at`, `reserved_at`), toutes deux déclarées au diagramme.

## 16. Éléments exclus

```text
Warehouse  StockZone  StockReceipt  StockReceiptLine  StockAdjustment
StockApproval  StockInventory  StockInventoryLine  StockTransfer
StockTransferLine  StockLot  StockBatch  StockAlert  StockCount
StockSnapshot  PackageLocationHistory  StockMovementLine
StockReservationLine  StockReservationHistory
```

Attributs non ajoutés : `warehouse_id`, `zone_id`, `organization_id` (sur
`StockItem` et `StockLocation`), `depot_id` sur `StockItem`, `unit`,
`minimum_quantity`, `maximum_quantity`, `reorder_level`, `lot_number`,
`expiration_date`, `purchase_price`, `selling_price`, `reason`, `reference`,
`note`, `approved_by`, `approved_at`, `metadata`, `settings`, `softDeletes`,
`legacy_id` (sur `StockLocation` et `StockMovement`).
