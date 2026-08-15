# Décisions base de données — Phase 7

Répond au §33. Tout est compatible **MySQL 8** : aucun `JSONB`, `ILIKE`, index
partiel ni enum SQL.

---

## 1. Ordre des migrations

```text
1. stock_items
2. stock_locations
3. stock_balances
4. stock_movements
5. stock_reservations
6. add_stock_location_foreign_key_to_packages_table
```

## 2. Isolation sans `organization_id`

Le point le plus délicat de la phase. **Ni `StockItem` ni `StockLocation` ne
portent `organizationId`**, et le §2 interdit de l'ajouter. Deux chaînes
distinctes, documentées comme le §25 l'exige :

```text
StockItem     → customer.organization_id                (1 jointure)
StockLocation → depot.agency.organization_id            (2 jointures)
```

`depots` ne porte pas d'organisation non plus : il passe par `agencies`. Les
entités dérivées suivent l'article :

| Classe | Chaîne |
|--------|--------|
| `StockBalance` | `stockItem.customer` |
| `StockMovement` | `stockItem.customer` |
| `StockReservation` | `stockItem.customer` |

**Toute opération croisant les deux chaînes vérifie les deux** : un mouvement
dont l'article appartient au client A et l'emplacement au dépôt de
l'organisation B est refusé en 422.

## 3. Nullabilité

| Colonne | Nullable | Raison |
|---------|----------|--------|
| `stock_items.catalog_item_id` | **oui** | `CustomerCatalogItem "0..1"`. Un article de stock peut exister hors catalogue. |
| `stock_items.barcode`, `description` | oui | Tous les articles ne sont pas scannés. |
| `stock_locations.parent_location_id` | **oui** | `"0..1"` : une racine n'a pas de parent. |
| `stock_locations.zone_code`, `aisle`, `rack`, `level`, `barcode` | oui | Un entrepôt simple n'a ni allée ni niveau. |
| `stock_movements.source_location_id` | **oui** | Une entrée n'a pas de source. |
| `stock_movements.destination_location_id` | **oui** | Une sortie n'a pas de destination. |
| `stock_movements.source_entity_type`, `source_entity_id` | oui | Un inventaire manuel ne vient d'aucune entité. |
| `stock_movements.created_by` | oui | Un mouvement automate n'a pas d'auteur ; précédent `documents.created_by`. |
| `stock_reservations.released_at` | oui | Une réservation active n'est pas libérée. |
| Quantités | non, défaut 0 | Une somme sur `NULL` produirait `NULL`. |

Le §16 laissait ouverte la nullabilité des deux emplacements. **Les deux sont
nullables** : c'est la seule façon de représenter à la fois une entrée et une
sortie, et le §21 pose la contrainte réelle — « au moins source ou destination
renseignée », vérifiée par l'application.

## 4. Unicité (§7)

| Contrainte | Retenue | Raison |
|------------|---------|--------|
| `(customer_id, article_code)` | **oui** | Le code article identifie la référence chez le client. |
| `(customer_id, barcode)` | **oui**, colonne nullable | Un code scanné doit désigner un article et un seul. `NULL` restant distinct, les articles sans code-barres restent possibles en nombre. |
| `(customer_id, catalog_item_id)` | **non** | Rien n'interdit deux références de stock pour le même article de catalogue — conditionnements différents. Le §7 met en garde contre l'invention silencieuse. |
| `(depot_id, location_code)` | **oui** | Un code d'emplacement identifie une position dans l'entrepôt. |
| `(depot_id, barcode)` | **oui**, nullable | Idem. |
| `(stock_item_id, stock_location_id)` | **oui** | Un seul solde par couple : c'est cette unicité qui rend le verrouillage possible et suffisant. |

**`barcode` n'est unique ni globalement, ni entre dépôts.** Le §7 l'interdit
sans justification, et deux clients emploient couramment le même code interne
pour des articles différents. Un test le vérifie.

## 5. Suppression

| Clé étrangère | Stratégie |
|---------------|-----------|
| `stock_items.customer_id` | `RESTRICT` |
| `stock_items.catalog_item_id` | `SET NULL` |
| `stock_locations.depot_id`, `parent_location_id` | `RESTRICT` |
| `stock_balances.*` | `RESTRICT` |
| `stock_movements.stock_item_id`, `source_location_id`, `destination_location_id` | `RESTRICT` |
| `stock_movements.created_by` | `SET NULL` |
| `stock_reservations.*` | `RESTRICT` |
| `packages.current_stock_location_id` | `SET NULL` |

**Aucune cascade** : le stock est un état comptable, rien ne disparaît en
chaîne.

Refus applicatifs, avant que SQL n'intervienne :

| Ressource | Refus | Code |
|-----------|-------|------|
| `StockItem` | solde non nul, mouvement ou réservation | 409 |
| `StockLocation` | enfants, solde non nul ou réservation active | 409 |
| `StockMovement` | route absente | 405 |
| `StockReservation` | route absente ; libération par `POST /release` | 405 |

## 6. Index

| Table | Index |
|-------|-------|
| `stock_items` | `customer_id`, `catalog_item_id`, `status`, unique `(customer_id, article_code)`, unique `(customer_id, barcode)` |
| `stock_locations` | `depot_id`, `parent_location_id`, `zone_code`, `status`, unique `(depot_id, location_code)`, unique `(depot_id, barcode)` |
| `stock_balances` | `stock_location_id`, unique `(stock_item_id, stock_location_id)` |
| `stock_movements` | `stock_item_id`, `source_location_id`, `destination_location_id`, `movement_type`, `(source_entity_type, source_entity_id)`, `created_by`, `created_at`, `(stock_item_id, created_at)` |
| `stock_reservations` | `stock_item_id`, `stock_location_id`, `order_line_id`, `status`, `reserved_at`, `released_at` |

## 7. Formule du solde (§13)

Aucune règle différente dans les Phases 1 à 6. La formule directe est retenue et
**centralisée dans `RecalculateStockBalance`** :

```text
availableQuantity = quantity − reservedQuantity
```

`available_quantity` est une colonne du diagramme, donc stockée — mais **jamais
acceptée en entrée**. Elle est dérivée à chaque écriture.

Trois invariants vérifiés **avant** écriture :

```text
quantity         >= 0
reservedQuantity >= 0
reservedQuantity <= quantity
```

Le troisième est le plus important : il interdit de sortir du stock déjà réservé
pour une commande. Un test le couvre — 10 en stock dont 8 réservés, une sortie de
5 est refusée.

## 8. Verrouillage pessimiste

Le §27 l'impose. `StockBalanceLocker` obtient le solde d'un couple article +
emplacement avec `lockForUpdate()`, dans une transaction — et **refuse de
travailler hors transaction** (`RuntimeException`), pour qu'un appel mal placé
échoue bruyamment plutôt que de corrompre un solde en silence.

Sans verrou, deux réservations concurrentes liraient la même `availableQuantity`
et la consommeraient chacune intégralement : le stock partirait deux fois.

Le mécanisme est celui de la Phase 2 (`GenerateOrderNumber`, allocation des
quantités de colis), y compris le rattrapage sur `UniqueConstraintViolationException`
lorsque deux transactions créent le même solde au même instant.

### Ordre déterministe des verrous

`CreateStockMovementAction` verrouille les soldes **par identifiant croissant**.
Sans cet ordre, un transfert A→B et un transfert B→A concurrents se
verrouilleraient mutuellement et s'interbloqueraient.

## 9. Précision décimale

`DECIMAL(12,3)` pour toutes les quantités — convention de
`order_services.quantity` et `packages.quantity`. Trois décimales couvrent les
unités fractionnaires (mètres, kilogrammes) sans créer de seconde convention.

## 10. Timestamps

| Table | Colonnes de date |
|-------|------------------|
| `stock_items` | aucune |
| `stock_locations` | aucune |
| `stock_balances` | `updated_at` |
| `stock_movements` | `created_at` |
| `stock_reservations` | `reserved_at`, `released_at` |

Exactement ce que le diagramme déclare. `stock_balances` est la seule table à
porter `updated_at` — un solde est un **état courant**, pas un événement ; les
mouvements et réservations portent leur date métier.

**Aucun soft delete.**

## 11. Référence générique (§18)

`stock_movements.source_entity_type` porte un **alias de la morph map**, validé
par `Rule::in(array_keys(MorphMap::registered()))` — la liste est *dérivée*, pas
recopiée : une copie divergerait au premier module ajouté.

**Aucune clé étrangère sur `source_entity_id`** : le §18 l'interdit, et c'est
structurellement impossible, la colonne pouvant désigner plusieurs tables.
Aucune relation `morphTo` non plus — elle donnerait l'illusion d'une intégrité
qui n'existe pas.

Un test vérifie qu'un nom de classe PHP est refusé.

## 12. `Package.currentStockLocationId` (§26)

Le §26 demandait de dire si une correction de migration était nécessaire.
**Elle l'était.**

`2026_08_01_100005_create_packages_table` créait la colonne sans contrainte,
avec ce commentaire : « `stock_locations` relève d'une phase ultérieure. Aucune
contrainte n'est posée tant que la table n'existe pas. »

La migration `2026_08_05_100006` pose la clé étrangère en `SET NULL` :
supprimer un emplacement ne supprime pas les colis, il les délocalise.

**La migration de la Phase 2 n'est pas modifiée** — elle est peut-être déjà
exécutée ailleurs. La correction est additive. Un test vérifie que le lien tient.

**Aucune modification automatique de `Package`** : créer un mouvement ne déplace
pas le colis, le §26 l'interdit sans règle métier explicite. Aucune table
`PackageLocationHistory`.

## 13. Absence de `legacy_id` et d'enums

Les §9 et §15 listent `legacyId` sur `StockLocation` et `StockMovement` ; **le
diagramme n'en contient aucun** — 10 attributs chacune. Les colonnes ne sont pas
créées, le §1 donnant priorité au diagramme. Un test le vérifie.

**Aucun enum.** Les trois `status`, `movementType` et `sourceEntityType` sont des
`VARCHAR` : le diagramme les déclare `string` sans énumérer de valeurs, et les
§6, §16 et §22 interdisent d'en inventer.
