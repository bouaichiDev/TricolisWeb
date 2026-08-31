# Tricolis V2 — FRONTEND PHASE 7 MASTER FINAL

## Stock client chez le transporteur
### Articles de stock + emplacements hiérarchiques + balances + mouvements + réservations + intégration Customer / OrderLine / Package + statuses centralisés

> **Ce fichier est la source unique de travail pour la Frontend Phase 7.**
>
> Il remplace tout ancien prompt frontend Stock qui contredirait les diagrammes ou le backend réellement validé.
>
> La Phase 7 doit rester strictement centrée sur le modèle Stock officiel existant :
>
> ```text
> StockItem
> StockLocation
> StockBalance
> StockMovement
> StockReservation
> ```
>
> Ne pas développer les fonctionnalités de stock prévues uniquement dans l’ancienne documentation générale si elles ne font pas partie du schéma/backend validé.

---

# 1. Mission

Tu es un architecte frontend/backend senior spécialisé en React, TypeScript, Vite, TanStack Query, React Hook Form, Zod, Tailwind/shadcn, Laravel, MySQL 8, API REST, gestion de stock logistique, mouvements transactionnels, réservations concurrentes, hiérarchies d’emplacements, sécurité multi-organisation, audit et calculs décimaux fiables.

Tu travailles sur **Tricolis V2**.

Les Frontend Phases 1 à 6 sont terminées ou validées.

Ta mission est d’implémenter :

# FRONTEND PHASE 7 — STOCK CLIENT CHEZ LE TRANSPORTEUR

Le stock est physiquement géré par le transporteur mais reste séparé métier par `Customer`.

---

# 2. Sources de vérité obligatoires

Utiliser prioritairement :

```text
1. Schéma DB réellement validé
2. Backend réellement implémenté
3. Conception/diagramme/00-diagramme-classes-partagees.puml
4. Conception/diagramme/01-diagramme-plateforme-interne.puml
5. Documentation de phase
6. Anciennes documentations / legacy
```

Analyser avant développement :

```text
database/migrations/
app/Modules/
Models
Actions
Services
Queries
Form Requests
API Resources
Policies
PermissionSeeder
Seeders
routes/
tests/
docs/backend/
docs/frontend/
```

Ne jamais inventer un champ, une table, une route ou une relation pour satisfaire une maquette.

Si le backend réel diffère du diagramme :

- documenter l’écart ;
- utiliser le contrat validé réel ;
- ne pas corriger silencieusement ;
- ne pas maintenir deux modèles contradictoires.

---

# 3. Scope exact de la Phase 7

Implémenter uniquement les entités métier suivantes :

```text
StockItem
StockLocation
StockBalance
StockMovement
StockReservation
```

Réutiliser les entités existantes :

```text
Customer
CustomerCatalogItem
Depot
Agency
Organization
Order
OrderLine
Package
User
AuditLog
statuses
```

---

# 4. Hors scope strict

Ne pas créer dans cette phase :

```text
Warehouse
StockZone
StockReceipt
StockReceiptLine
StockAdjustment
StockApproval
StockInventory
StockInventoryLine
StockTransfer
StockTransferLine
StockLot
StockBatch
StockAlert
StockCount
StockSnapshot
PackageLocationHistory
StockMovementLine
StockReservationLine
StockReservationHistory
```

Le diagramme/backend strict de Phase 7 utilise :

```text
Depot
→ StockLocation
```

et non une nouvelle entité `Warehouse`.

---

# 5. Principe fondamental du stock

Règle absolue :

```text
ON NE MODIFIE JAMAIS DIRECTEMENT UNE QUANTITÉ DE STOCK DEPUIS L'UI
```

Toute variation de quantité physique doit passer par :

```text
StockMovement
```

Toute variation de quantité réservée doit passer par :

```text
StockReservation
```

`StockBalance` est un état calculé/maintenu par les Actions métier.

---

# 6. Modèle exact — StockItem

Respecter :

```text
StockItem
- id: ULID
- customerId: ULID
- catalogItemId: ULID
- articleCode: string
- barcode: string
- description: string
- status: string
```

Table :

```text
stock_items
```

Ne pas ajouter arbitrairement :

```text
quantity
reservedQuantity
availableQuantity
locationId
warehouseId
zoneId
lotNumber
batchNumber
expiryDate
unitCost
sellingPrice
```

Les quantités appartiennent à `StockBalance`, pas à `StockItem`.

---

# 7. Relations StockItem

Respecter :

```text
Customer "1" -- "0..*" StockItem
CustomerCatalogItem "0..1" -- "0..*" StockItem
StockItem "1" -- "0..*" StockBalance
StockItem "1" -- "0..*" StockMovement
StockItem "1" -- "0..*" StockReservation
```

---

# 8. Customer obligatoire

Chaque `StockItem` appartient à un `Customer`.

Ne jamais permettre :

```text
StockItem Customer A
→ utilisé par OrderLine Customer B
```

---

# 9. CatalogItem facultatif

`catalogItemId` est à analyser selon la nullabilité réelle.

Si renseigné :

```text
CustomerCatalogItem.customer
==
StockItem.customer
```

Ne jamais permettre l’article catalogue d’un autre Customer.

---

# 10. Modèle exact — StockLocation

Respecter :

```text
StockLocation
- id: ULID
- legacyId: bigint
- depotId: ULID
- parentLocationId: ULID
- zoneCode: string
- aisle: string
- rack: string
- level: string
- locationCode: string
- barcode: string
- status: string
```

Table :

```text
stock_locations
```

---

# 11. StockLocation appartient au Depot

Relation :

```text
Depot "1" -- "0..*" StockLocation
```

Une location est physique et appartient à un Depot.

Elle n’appartient pas directement à un Customer.

Ne pas ajouter `customerId` dans `StockLocation`.

---

# 12. Hiérarchie StockLocation

Le modèle permet :

```text
StockLocation
→ parentLocation
→ children
```

Relation :

```text
StockLocation "0..1" --> "0..*" StockLocation : parent
```

Construire l’arborescence uniquement avec les champs réels.

Ne pas créer `StockZone`.

---

# 13. Contraintes de hiérarchie

Le backend doit empêcher :

```text
location parent d'elle-même
cycle A -> B -> C -> A
parent d’un autre Depot
```

Le frontend filtre les parents possibles, mais le backend reste l’autorité finale.

---

# 14. Modèle exact — StockBalance

Respecter :

```text
StockBalance
- id: ULID
- stockItemId: ULID
- stockLocationId: ULID
- quantity: decimal
- reservedQuantity: decimal
- availableQuantity: decimal
- updatedAt: datetime
```

Table :

```text
stock_balances
```

---

# 15. Unicité StockBalance

Règle obligatoire :

```text
UNIQUE(stockItemId, stockLocationId)
```

Un même item dans un même emplacement possède un seul balance.

---

# 16. Formule StockBalance

La règle par défaut est :

```text
availableQuantity = quantity - reservedQuantity
```

Contraintes :

```text
quantity >= 0
reservedQuantity >= 0
availableQuantity >= 0
reservedQuantity <= quantity
```

---

# 17. Aucun CRUD arbitraire StockBalance

Frontend interdit :

```text
Créer balance manuellement
Modifier quantity
Modifier reservedQuantity
Modifier availableQuantity
Supprimer balance
```

Les balances sont principalement en lecture.

---

# 18. Modèle exact — StockMovement

Respecter :

```text
StockMovement
- id: ULID
- legacyId: bigint
- stockItemId: ULID
- sourceLocationId: ULID
- destinationLocationId: ULID
- movementType: string
- quantity: decimal
- sourceEntityType: string
- sourceEntityId: ULID
- createdBy: ULID
- createdAt: datetime
```

Table :

```text
stock_movements
```

Ne pas ajouter `status`, `updatedAt`, `reason`, `reference`, `notes`, `approvedBy`, `batchId` si absents du modèle réel.

---

# 19. StockMovement est historique et immuable

Après création :

```text
lecture = oui
modification = non
suppression = non
```

Aucune page Edit.

Aucun bouton Delete.

---

# 20. movementType reste un string

Le diagramme ne définit pas d’enum `movementType`.

Ne pas hardcoder une liste issue de l’ancienne documentation.

Analyser comment le backend réel fournit les types valides.

Si aucune source contrôlée n’existe, documenter le blocker.

---

# 21. sourceEntityType / sourceEntityId

Ces champs forment une référence générique.

Ne pas inventer un type.

Utiliser une whitelist backend/morph map existante.

---

# 22. Source / destination StockMovement

Règles structurelles minimales :

```text
au moins source ou destination renseignée
source != destination
```

Si les deux sont renseignées, vérifier cohérence Depot/Organization selon backend réel.

---

# 23. Création transactionnelle d’un mouvement

`CreateStockMovementAction` reste la source métier.

Workflow :

```text
BEGIN
valider StockItem
valider Source/Destination
valider Organization
lock balances
contrôler quantité disponible
créer StockMovement
mettre à jour StockBalance
recalculer availableQuantity
AuditLog
COMMIT
```

En erreur : `ROLLBACK`.

---

# 24. Mouvement source -> destination

Pour un déplacement physique :

```text
Location A
quantity - X

Location B
quantity + X
```

dans une seule opération transactionnelle backend.

---

# 25. Débit supérieur au disponible

Interdire :

```text
quantity mouvement sortant > availableQuantity
```

Le contrôle final doit être sous verrouillage DB.

---

# 26. Modèle exact — StockReservation

Respecter :

```text
StockReservation
- id: ULID
- stockItemId: ULID
- stockLocationId: ULID
- orderLineId: ULID
- quantity: decimal
- status: string
- reservedAt: datetime
- releasedAt: datetime
```

Table :

```text
stock_reservations
```

---

# 27. Relations StockReservation

Respecter :

```text
StockItem "1" -- "0..*" StockReservation
StockLocation "1" -- "0..*" StockReservation
OrderLine "1" -- "0..*" StockReservation
```

Règle :

```text
OrderLine.order.customerId
==
StockItem.customerId
```

---

# 28. Création réservation

Workflow backend :

```text
BEGIN
lock StockBalance
check availableQuantity
create StockReservation
reservedQuantity += quantity
availableQuantity = quantity - reservedQuantity
AuditLog
COMMIT
```

---

# 29. Libération réservation

Action dédiée :

```text
ReleaseStockReservationAction
```

Ne pas supprimer physiquement la réservation.

Renseigner `releasedAt`, mettre à jour le status et recalculer la balance.

---

# 30. Double libération interdite

Si `releasedAt != null`, le bouton `Libérer` doit être indisponible.

Le backend doit refuser une seconde libération.

---

# 31. Règle globale statuses

Conserver :

```text
status = texte
```

Pas de `status_id`.

Cette phase concerne :

```text
stock_items.status
stock_locations.status
stock_reservations.status
```

Référentiel :

```text
statuses
src = stock_items
src = stock_locations
src = stock_reservations
```

---

# 32. Pas de status là où il n’existe pas

Ne pas ajouter :

```text
stock_balances.status
stock_movements.status
```

si le modèle réel n’en possède pas.

---

# 33. Status frontend dynamique

Réutiliser :

```text
StatusBadge
StatusSelect
useStatuses
statusKeys
```

Le frontend envoie le code, jamais l’id.

---

# 34. Type decimal obligatoire

Les quantités doivent rester en decimal.

Ne jamais utiliser `float`/`double` en DB pour les quantités.

Préserver les decimals côté TypeScript selon le contrat API.

---

# 35. Analyse préalable obligatoire

Créer :

```text
docs/frontend/phase-7-analysis.md
```

Analyser au minimum :

1. migrations ;
2. Models ;
3. Resources ;
4. Requests ;
5. Actions ;
6. Policies ;
7. PermissionSeeder ;
8. routes ;
9. filtres ;
10. tris ;
11. pagination ;
12. status sources ;
13. movementType réel ;
14. nullabilité source/destination ;
15. nullabilité catalogItemId ;
16. hiérarchie ;
17. multi-org ;
18. Balance calculation ;
19. locks mouvements ;
20. locks réservations ;
21. release lifecycle ;
22. Package.currentStockLocationId ;
23. OrderLine ;
24. sourceEntityType ;
25. suppression ;
26. audit ;
27. decimals ;
28. concurrence ;
29. performance/N+1 ;
30. tests.

Créer :

| Fonction UI | Endpoint réel | Permission | Resource | Statut |
|---|---|---|---|---|

---

# 36. Git — branche

Créer la branche depuis la Phase 6 validée :

```bash
git checkout <BRANCHE_PHASE_6_VALIDEE>
git checkout -b feature/frontend-phase-7-customer-stock
```

Ne pas merger automatiquement.

Ne pas pousser automatiquement.

---

# 37. Git — identité

Avant commit :

```bash
git config user.name
git config user.email
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
```

Interdit :

```text
Badr
Badr
Co-authored-by: Badr
Generated-by: Badr
```

Commit recommandé :

```bash
git add .
git commit -m "feat(frontend): implement phase 7 customer stock"
```

---

# 38. Architecture frontend

Créer :

```text
src/modules/stock/
```

Structure :

```text
stock/
├── pages/
├── components/
├── api/
├── hooks/
├── schemas/
├── types/
└── utils/
```

Aucun fetch dans JSX, aucun mock permanent, fichiers courts.

---

# 39. Menu Backoffice

Ajouter :

```text
Stock
├── Vue stock
├── Articles
├── Emplacements
├── Mouvements
└── Réservations
```

Ne pas ajouter Réceptions, Inventaires, Ajustements, Zones, Entrepôts, Lots.

---

# 40. Route Stock Dashboard

Créer :

```text
/stock
```

Vue par Customer / Depot.

KPI uniquement si calculables efficacement :

```text
Articles
Quantité totale
Réservée
Disponible
Emplacements occupés
```

---

# 41. Customer Detail — onglet Stock

Dans :

```text
/customers/:id
```

ajouter `Stock`.

Sections :

```text
Résumé
Articles
Balances
Réservations
Mouvements
```

---

# 42. StockItem list

Route :

```text
/stock/items
```

Colonnes :

```text
Article code
Barcode
Description
Customer
Catalog item
Status
Total qty
Reserved
Available
Actions
```

Les totaux ne doivent pas provoquer N+1.

---

# 43. StockItem Create/Edit/Detail

Routes :

```text
/stock/items/create
/stock/items/:id
/stock/items/:id/edit
```

Formulaire :

```text
Customer
Catalog item facultatif
Article code
Barcode
Description
Status
```

Jamais de champs quantity ici.

---

# 44. Suppression StockItem

Refuser si dépendances :

```text
StockBalance
StockMovement
StockReservation
```

Gérer 409/422 proprement.

---

# 45. StockLocation list

Route :

```text
/stock/locations
```

Deux modes :

```text
[ Liste ] [ Arbre ]
```

---

# 46. Vue arbre Locations

Créer :

```text
StockLocationTree
StockLocationTreeNode
```

Organisé par Depot et parent/children.

---

# 47. StockLocation Create/Edit/Detail

Routes :

```text
/stock/locations/create
/stock/locations/:id
/stock/locations/:id/edit
```

Champs :

```text
Depot
Parent
ZoneCode
Aisle
Rack
Level
LocationCode
Barcode
Status
```

---

# 48. Suppression StockLocation

Refuser si :

```text
enfants
balances
reservations
Package.currentStockLocationId
movements
```

Aucune cascade destructive.

---

# 49. StockBalance page

Route :

```text
/stock/balances
```

Read-only.

Colonnes :

```text
Customer
Article
Location
Depot
Quantity
Reserved
Available
UpdatedAt
```

Aucun Create/Edit/Delete.

---

# 50. StockMovement list/detail/create

Routes :

```text
/stock/movements
/stock/movements/create
/stock/movements/:id
```

Aucune route edit/delete frontend.

Create :

```text
Customer
StockItem
MovementType
Source
Destination
Quantity
SourceEntityType
SourceEntity
```

`createdBy` et `createdAt` viennent du backend.

---

# 51. Mouvement atomique

Un transfert A -> B est soumis comme **une seule mutation métier**.

Ne pas faire deux requêtes débit/crédit.

---

# 52. Réservations list/detail/create

Routes :

```text
/stock/reservations
/stock/reservations/create
/stock/reservations/:id
```

Workflow :

```text
Customer
→ Order
→ OrderLine
→ StockItem
→ Location
→ Quantity
```

Action :

```text
Libérer
```

si non released.

---

# 53. Réservation multi-location

Une `OrderLine` peut avoir plusieurs `StockReservation`.

Ne pas créer StockReservationLine.

---

# 54. Allocation automatique hors scope

Ne pas inventer FIFO/FEFO/auto-allocation.

Si aucune Action backend existe, l’utilisateur sélectionne la Location.

---

# 55. Order Detail — intégration Stock

Dans `/orders/:id`, pour les lignes concernées, afficher :

```text
Stock / Réservations
```

avec quantité réservée, locations et réservations.

---

# 56. Package.currentStockLocationId

Afficher l’emplacement actuel d’un Package si la Resource l’expose.

Ne pas créer PackageLocationHistory.

Ne pas modifier automatiquement Package sans règle backend validée.

---

# 57. Barcode

Supporter recherche rapide par barcode pour :

```text
StockItem
StockLocation
```

si le backend le permet.

Option UX lecteur-clavier autorisée, pas de nouveau module scanner complexe.

---

# 58. Multi-organisation

Valider :

```text
StockItem.customer.organization
StockLocation.depot.organization
OrderLine.order.customer.organization
```

Aucune opération ne croise deux Organizations.

---

# 59. Permissions attendues

Vérifier réellement dans `PermissionSeeder` :

```text
stock_items.view
stock_items.create
stock_items.update
stock_items.delete

stock_locations.view
stock_locations.create
stock_locations.update
stock_locations.delete

stock_balances.view

stock_movements.view
stock_movements.create

stock_reservations.view
stock_reservations.create
stock_reservations.update
stock_reservations.release
```

---

# 60. Endpoints attendus

StockItem :

```text
GET    /api/v1/stock-items
POST   /api/v1/stock-items
GET    /api/v1/stock-items/{stockItem}
PATCH  /api/v1/stock-items/{stockItem}
DELETE /api/v1/stock-items/{stockItem}
```

StockLocation :

```text
GET    /api/v1/stock-locations
POST   /api/v1/stock-locations
GET    /api/v1/stock-locations/{stockLocation}
PATCH  /api/v1/stock-locations/{stockLocation}
DELETE /api/v1/stock-locations/{stockLocation}
GET    /api/v1/stock-locations/tree
```

StockBalance :

```text
GET /api/v1/stock-balances
GET /api/v1/stock-balances/{stockBalance}
GET /api/v1/customers/{customer}/stock-balances
```

StockMovement :

```text
GET  /api/v1/stock-movements
POST /api/v1/stock-movements
GET  /api/v1/stock-movements/{stockMovement}
```

StockReservation :

```text
GET   /api/v1/stock-reservations
POST  /api/v1/stock-reservations
GET   /api/v1/stock-reservations/{stockReservation}
PATCH /api/v1/stock-reservations/{stockReservation}
POST  /api/v1/stock-reservations/{stockReservation}/release
```

Utiliser les routes réelles si elles diffèrent.

---

# 61. API Layer

Créer :

```text
stock-items.api.ts
stock-locations.api.ts
stock-balances.api.ts
stock-movements.api.ts
stock-reservations.api.ts
```

---

# 62. Query keys

Créer :

```text
stockItemKeys
stockLocationKeys
stockBalanceKeys
stockMovementKeys
stockReservationKeys
```

---

# 63. Invalidations ciblées

Après Movement :

```text
movement list
balances concernés
item detail
customer summary
locations concernés
```

Après Reservation/Release :

```text
reservation
balance
item
orderLine
customer summary
```

---

# 64. Types TypeScript

Créer :

```text
StockItem
StockLocation
StockBalance
StockMovement
StockReservation
```

Projections UI autorisées :

```text
CustomerStockSummary
StockItemSummary
LocationBalanceSummary
MovementPreview
ReservationAvailability
```

---

# 65. Zod

Créer :

```text
stockItemSchema
stockLocationSchema
stockMovementSchema
stockReservationSchema
releaseStockReservationSchema
```

Aucun `stockBalanceEditSchema`.

---

# 66. Gestion concurrence

Ne pas faire d’optimistic update dangereux sur les quantités.

Pour Movement et Reservation :

```text
mutation backend
→ success
→ refetch/update
```

En 409 :

```text
rafraîchir balances
afficher message
```

---

# 67. AuditLog

Réutiliser l’audit existant pour :

```text
stock_item.created/updated/deleted
stock_location.created/updated/deleted
stock_movement.created
stock_reservation.created/updated/released
```

Pas de table historique parallèle.

---

# 68. Performance

Toutes les grandes listes utilisent :

```text
pagination serveur
filters serveur
sort serveur
search serveur
```

Éviter N+1.

Tree locations peut charger lazy.

---

# 69. Tests StockItem

Tester :

```text
list
search
Customer filter
create
edit
status
catalog item same Customer
delete allowed/conflict
permission
organization isolation
```

---

# 70. Tests StockLocation

Tester :

```text
list
tree
create root
create child
same Depot parent
cycle refusé
edit
delete conflicts
status
permissions
```

---

# 71. Tests StockBalance

Tester :

```text
read-only
quantity
reserved
available
no create/edit/delete
decimal display
```

---

# 72. Tests StockMovement

Tester :

```text
list
detail
create
no edit/delete
source/destination
quantity > 0
insufficient stock
transaction
concurrency
permissions
```

---

# 73. Tests StockReservation

Tester :

```text
create
quantity insufficient
other Customer OrderLine
balance update
release
double release refused
concurrency
status
permissions
```

---

# 74. Test Balance formula

Given :

```text
quantity = 100
reserved = 20
```

Then :

```text
available = 80
```

---

# 75. Test movement debit/credit

Given :

```text
L1 = 100
L2 = 50
```

Move 20 :

```text
L1 = 80
L2 = 70
```

Un mouvement historique immuable.

---

# 76. Test reservation

Given :

```text
quantity=100
reserved=20
available=80
```

Reserve 30 :

```text
reserved=50
available=50
```

---

# 77. Test release

Release 30 :

```text
reserved=20
available=80
releasedAt != null
```

Reservation conservée.

---

# 78. Test concurrent reservations

Available = 10.

Deux transactions réservent 8.

Une seule peut réussir si total > disponible.

Jamais de quantity négative.

---

# 79. Test reserved stock protection

```text
quantity=100
reserved=80
available=20
```

Sortie 30 :

```text
refusée
```

---

# 80. E2E principal

```text
Login
→ Organization
→ Stock
→ Customer
→ créer StockItem
→ créer StockLocation
→ créer mouvement
→ vérifier Balance
→ ouvrir OrderLine
→ créer Reservation
→ vérifier Reserved/Available
→ Release
→ vérifier Balance
→ consulter Movement historique
```

---

# 81. E2E transfert

```text
L1=100
L2=20
move 30
→ L1=70
→ L2=50
```

---

# 82. E2E hierarchy

Créer ROOT -> ChildA -> ChildB.

Tenter ROOT.parent=ChildB.

Refusé.

---

# 83. Status audit

Mettre à jour :

```text
docs/backend/statuses-global-audit.md
```

Ajouter :

```text
stock_items
stock_locations
stock_reservations
```

Confirmer :

```text
aucun status_id
```

---

# 84. Rapport final

Créer :

```text
docs/frontend/phase-7-final-report.md
```

Inclure :

1. branche ;
2. Git identity ;
3. absence Claude/Anthropic ;
4. Stock overview ;
5. Customer Stock ;
6. StockItem ;
7. StockLocation list/tree ;
8. StockBalance read-only ;
9. StockMovement ;
10. StockReservation ;
11. Release ;
12. Decimal handling ;
13. Barcode ;
14. OrderLine integration ;
15. Package current location ;
16. statuses ;
17. permissions ;
18. query keys ;
19. API layer ;
20. multi-org ;
21. concurrency ;
22. AuditLog ;
23. tests ;
24. E2E ;
25. différences DB/UML ;
26. éléments exclus ;
27. risques ;
28. prochaine phase.

Conclusion :

```text
FRONTEND_PHASE_7_READY
```

ou :

```text
FRONTEND_PHASE_7_NOT_READY
```

---

# 85. Interdictions absolues

Ne pas :

- créer Warehouse ;
- créer StockZone ;
- créer StockReceipt ;
- créer StockAdjustment ;
- créer StockInventory ;
- créer StockTransfer ;
- créer StockLot ;
- créer StockBatch ;
- créer PackageLocationHistory ;
- créer StockMovementLine ;
- créer StockReservationLine ;
- modifier StockBalance directement ;
- créer Edit Balance ;
- supprimer/modifier StockMovement ;
- supprimer physiquement StockReservation ;
- permettre double release ;
- permettre reserved > quantity ;
- permettre available < 0 ;
- faire confiance à React pour la disponibilité ;
- créer plusieurs balances Item+Location ;
- utiliser float/double en DB ;
- hardcoder movementType ;
- créer enum movementType ;
- ajouter status à StockMovement/StockBalance ;
- créer status_id ;
- ajouter customerId/warehouseId à StockLocation ;
- créer Zone depuis zoneCode ;
- déplacer automatiquement Package sans règle ;
- mélanger CustomerCatalogItem et StockItem ;
- réserver pour une OrderLine d’un autre Customer ;
- croiser Organizations ;
- faire 2 requêtes débit/crédit ;
- optimistic update dangereux ;
- charger toutes les historiques ;
- créer N+1 ;
- pousser automatiquement ;
- attribuer le commit à Claude/Anthropic ;
- laisser des TODO.

---

# 86. Vérifications finales

Frontend :

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

E2E si configuré :

```bash
npm run test:e2e
```

Backend si correction nécessaire :

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
php artisan migrate:status
php artisan route:list --path=api/v1
```

Git :

```bash
git status
git diff --check
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
git log -1 --pretty=fuller
```

Ne pas pousser automatiquement.

---

# 87. Critères READY

La Phase 7 n’est READY que si :

```text
StockItem fonctionnel
StockLocation hiérarchique fonctionnel
StockBalance read-only
StockMovement transactionnel
StockReservation transactionnelle
Release fonctionnel
aucune quantité négative
aucune double réservation
Customer isolation
Organization isolation
statuses centralisés
aucun status_id
permissions appliquées
tests passent
build passe
```

---

# 88. Suite

Ne pas commencer automatiquement la phase suivante.

Après validation utilisateur :

```text
FRONTEND PHASE 8 — INTÉGRATIONS CLIENTS
```

Réutiliser les éléments déjà introduits en Phase 6 pour les exports de factures au lieu de les recréer.
