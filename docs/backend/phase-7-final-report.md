# Rapport final — Phase 7 : stock client chez le transporteur

---

## 1. Branche

```text
feature/backend-phase-7-customer-stock
```

Créée depuis `feature/backend-phase-6-billing-settlements` (commit `d47581e`),
et non depuis `main` — resté au squelette vide. `stock_reservations` référence
`order_lines` (Phase 2), `stock_locations` référence `depots` (Phase 1). Même
écart assumé qu'aux Phases 3 à 6.

Aucune fusion, aucun rebase, aucun push.

## 2. Diagrammes et conflits

Les `.puml` du §1 n'existent pas ; les `.txt` font foi. Classes lignes 379-438,
relations lignes 869 et 878-887.

### Deux conflits, même arbitrage

| # | Le prompt dit | Le diagramme dit | Décision |
|---|---------------|------------------|----------|
| A | `StockLocation.legacyId` (§9, §11) | 10 attributs, sans `legacyId` | colonne **non créée** |
| B | `StockMovement.legacyId` (§15, §19) | 10 attributs, sans `legacyId` | colonne **non créée** |

Conformité vérifiée par les colonnes créées :

```text
stock_items          7 colonnes   (7 attributs)
stock_locations     10 colonnes   (10 attributs)
stock_balances       7 colonnes   (7 attributs)
stock_movements     10 colonnes   (10 attributs)
stock_reservations   8 colonnes   (8 attributs)
```

## 3. Classes implémentées

```text
StockItem   StockLocation   StockBalance   StockMovement   StockReservation
```

Aucune composition au diagramme : que des associations. Aucun enum.

## 4. Migrations

Six, dont une correction :

```text
1. stock_items
2. stock_locations
3. stock_balances
4. stock_movements
5. stock_reservations
6. add_stock_location_foreign_key_to_packages_table
```

La sixième répond au §26 : `packages.current_stock_location_id` existait depuis
la Phase 2 **sans contrainte**, faute de table cible. La clé étrangère est
maintenant posée en `SET NULL`, de façon **additive** — la migration de la
Phase 2 n'est pas modifiée. Un test vérifie que le lien tient.

## 5. Modèles, services, Actions

**Modèles (5)**, **DTOs (8)**, **exception (1)**.

**Services (2)** :

- `StockScopeGuard` — les deux chaînes de périmètre ;
- `StockBalanceLocker` — verrou pessimiste, refuse de travailler hors
  transaction.

**Actions (11)** :

```text
CreateStockItemAction  UpdateStockItemAction  DeleteStockItemAction
CreateStockLocationAction  UpdateStockLocationAction  DeleteStockLocationAction
ValidateStockLocationHierarchy
CreateStockMovementAction
CreateStockReservationAction  UpdateStockReservationAction  ReleaseStockReservationAction
RecalculateStockBalance
```

## 6. Couche HTTP

**Form Requests (13)**, **Resources (13)**, **Query Objects (5)**,
**Controllers (5)**, **Policies (5)**, **factories (5)**.

**15 permissions** ; total du projet : **153**.

Quinze et non vingt : `stock_balances` n'en porte qu'une (`view`) — le §14
interdit un CRUD public sur les soldes — et `stock_movements` deux
(`view`, `create`), les routes de modification et de suppression n'existant pas.

**21 routes**, aucun doublon sur les 281 du projet.

## 7. Tests

| Fichier | Tests | Points saillants |
|---------|-------|------------------|
| `Stock/StockItemTest` | 16 | Unicités par client, **code-barres non unique globalement**, catalogue d'un autre client refusé, refus de suppression si stock/mouvement, absence de `organization_id`/`depot_id`/`quantity`, **clé étrangère `packages` désormais posée**, IDOR, audit |
| `Stock/StockLocationTest` | 17 | Parent d'un autre dépôt refusé, **soi-même comme parent**, **descendant direct**, **descendant indirect**, réorganisation légitime acceptée, arbre dérivé sur trois niveaux, arbre restreint à un dépôt, refus de suppression avec enfants ou stock, IDOR, audit |
| `Stock/StockMovementTest` | 18 | Entrée créant le solde, transfert, **ni source ni destination refusé**, source = destination refusé, **inter-dépôt refusé**, débit > disponible en 409, **refus de sortir du stock réservé**, alias morph accepté, **nom de classe PHP refusé**, absence de `PATCH`/`DELETE` (405), ordre décroissant, audit |
| `Stock/StockReservationTest` | 15 | Réservation mettant à jour le solde, sur-réservation en 409, cumul des réservations, ligne d'un autre client refusée, **libération sans suppression**, **double libération en 409**, `PATCH` limité au statut, absence de `DELETE`, **soldes en lecture seule (405 en écriture)**, filtre `availableOnly`, audit |
| `Stock/StockPermissionTest` | 7 | Lecture, création et libération refusées sans permission ; arbre protégé ; accès accordé après attribution ; en-tête requis ; non authentifié refusé |

**73 tests ajoutés.**

## 8. Résultats

```text
composer validate                                valid
php artisan migrate:fresh --seed --env=testing   OK
php artisan test                                 531 passed (1689 assertions)
./vendor/bin/pint --test                         PASS
php artisan route:list                           281 routes, aucun doublon
TODO / classes vides                             aucun
constructions PostgreSQL                         aucune
```

458 tests des Phases 1 à 6, 73 de la Phase 7. **Aucune régression.**

### Un fichier au-dessus de la limite recommandée

`app/Shared/Database/MorphMap.php` fait **202 lignes**, deux de plus que le
maximum *recommandé*. C'est un registre plat qui gagne une entrée par module —
49 alias à ce stade — et le style du projet impose une ligne vide entre
constantes (Pint, `class_attributes_separation`).

Le scinder en `MorphAlias` (les valeurs) et `MorphMap` (l'enregistrement)
ramènerait chaque fichier sous 200 lignes, mais renommerait 40 usages répartis
dans 20 fichiers des Phases 1 à 6. **Le compromis n'a pas été pris** : un
registre cohérent vaut mieux que deux fichiers à tenir synchronisés, et la règle
est explicitement une recommandation. Le point est signalé plutôt que contourné.

## 9. Décisions structurantes

### Isolation sans `organization_id`

Ni `StockItem` ni `StockLocation` ne portent d'organisation, et le §2 interdit
de l'ajouter. Deux chaînes :

```text
StockItem     → customer.organization_id            (1 jointure)
StockLocation → depot.agency.organization_id        (2 jointures)
```

Toute opération croisant les deux les vérifie toutes les deux.

### Verrouillage pessimiste

`StockBalanceLocker` verrouille le solde d'un couple article + emplacement et
**refuse de travailler hors transaction** — un appel mal placé échoue
bruyamment plutôt que de corrompre un solde en silence.

`CreateStockMovementAction` verrouille **par identifiant croissant** : sans cet
ordre, un transfert A→B et un transfert B→A concurrents s'interbloqueraient.

### `availableQuantity` toujours dérivée

`quantity − reservedQuantity`, recalculée à chaque écriture, jamais acceptée en
entrée. Trois invariants vérifiés avant écriture, dont
`reservedQuantity <= quantity` — c'est lui qui interdit de sortir du stock déjà
réservé pour une commande.

### Libération sans suppression

`releasedAt` est renseigné, la ligne reste (§23). Une **double libération** est
refusée en 409, avec relecture sous verrou : sans ce refus, appeler la route
deux fois libérerait du stock jamais réservé.

### Référence générique validée

`sourceEntityType` n'accepte que des alias **dérivés** de la morph map
(`MorphMap::registered()`), jamais une liste recopiée. Aucune clé étrangère sur
`sourceEntityId`, aucune relation `morphTo` — elle donnerait l'illusion d'une
intégrité qui n'existe pas.

## 10. Ambiguïtés levées

| # | Ambiguïté | Traitement |
|---|-----------|------------|
| A | `legacyId` sur deux classes | Absents du diagramme : non créés |
| B | Unicités de `StockItem` (§7) | `(customer_id, article_code)` et `(customer_id, barcode)` retenues ; `(customer_id, catalog_item_id)` écartée, rien ne l'impose |
| C | Nullabilité des deux emplacements (§16) | **Les deux nullables** : seule façon de représenter entrée et sortie |
| D | Règle inter-dépôt (§21) | **Refusée** : faute de règle définie, un transfert inter-dépôt se fait en deux mouvements |
| E | Formule du solde (§13) | Aucune règle existante ; formule directe retenue et centralisée |
| F | `Package.currentStockLocationId` (§26) | Clé étrangère manquante **confirmée et posée**, en migration additive |
| G | `PATCH` sur réservation (§24) | Limité au statut ; la quantité passe par libération + recréation |
| H | Profondeur de hiérarchie (§10) | Aucune limite fixée ; garde-fou contre les cycles déjà présents en base |

## 11. Fichiers

**60 créés**, **4 modifiés** par ajout (`routes/api.php`, `PermissionSeeder`,
`AuthServiceProvider`, `MorphMap`). Aucune ligne des Phases 1 à 6 supprimée ni
réécrite.

## 12. Éléments exclus

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
`legacy_id`.

## 13. Risques

1. **Aucun lien automatique entre stock et exécution.** Créer un mouvement ne
   met pas à jour `Package.currentStockLocationId`, et une réservation ne se
   consomme pas à la livraison. C'est délibéré — le §26 l'interdit sans règle —
   mais cela signifie que le rapprochement reste manuel.
2. **Les réservations ne s'éteignent jamais d'elles-mêmes.** Une réservation
   oubliée immobilise du stock indéfiniment. Aucune expiration n'est prévue au
   diagramme.
3. **`movementType` et les `status` sont des chaînes libres.** Rien n'empêche
   `inbound` et `INBOUND`.
4. **Le transfert inter-dépôt n'est pas représentable en un mouvement.** Il faut
   une sortie puis une entrée, sans lien entre les deux lignes.
5. **La dette des Phases 4 à 6 reste ouverte** : `DeleteTourAction` ne refuse
   toujours pas la suppression d'une tournée référencée par un `TrackingEvent`,
   une `ProofOfDelivery` ou une `Claim`.

## 14. Prochaine phase

**Non commencée** : la Phase 8 (intégrations clients) attend une validation
explicite.
