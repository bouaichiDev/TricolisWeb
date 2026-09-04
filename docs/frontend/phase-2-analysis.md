# Frontend Phase 2 — Analyse préalable

Catalogues, commandes, colis et services.

Branche : `feature/frontend-phase-2-orders-catalogs`, partie de
`fix/phase-1-organization-roles-permissions`.

> **Pourquoi pas depuis `main`.** Le §0.1 demande de brancher depuis `main`.
> `origin/main` contient les dix phases backend (PR #1 à #14) mais **aucun
> dossier `frontend/`** : la Phase 1 vit sur des branches locales non
> fusionnées. Brancher depuis `main` donnerait un dépôt sans `AppLayout`,
> `DataTable` ni `PermissionGuard`, que le §3 demande de réutiliser.
> Empilement validé par l'utilisateur, comme les dix phases backend.

---

## 1. Le constat qui commande toute la phase

**Il n'existe aucun `OrderStop` dans le backend.** Ni modèle, ni ressource, ni
route, ni permission.

```
app/Modules/Orders/Models/
  Order.php  OrderLine.php  OrderService.php
  OrderServiceContact.php   OrderServicePackage.php
  Service.php  OrderNumberSequence.php
```

Le §28 anticipait ce cas : « Le modèle Tricolis a évolué pendant la
conception… Ne jamais supporter simultanément deux anciens modèles
incompatibles. »

**`OrderService` est l'arrêt.** Il porte tout ce que le §15 attribue à un arrêt :

| Ce que le §15 appelle « arrêt » | Champ réel de `OrderService` |
| --- | --- |
| Adresse | `addressId` + `address` chargée |
| Séquence | `sequence` |
| Créneau demandé | `requestedDate`, `requestedFrom`, `requestedTo` |
| Instructions | `instructions` |
| Statut | `status` — `OrderServiceStatus` |
| Contacts | `contacts` — `OrderServiceContact` |
| — | `packages`, quantités, prix, coûts |

### Conséquences sur le prompt

| § | Ce qui était demandé | Ce qui sera fait |
| --- | --- | --- |
| 13 | 6 étapes dont « Arrêts » **et** « Services » | 5 étapes : les deux n'en font qu'une |
| 15 | `OrderStopsEditor`, `OrderStopCard` | `OrderServicesEditor`, `OrderServiceCard` |
| 16 | `OrderStopContactsEditor` | `OrderServiceContactsEditor` |
| 34 | Onglet « Arrêts » | Fondu dans l'onglet « Services » |
| 47 | `order_stops.*` | **N'existe pas** — non utilisé |

Aucun composant `OrderStop` ne sera créé : ce serait une entité inventée, ce
que le §1 interdit.

---

## 2. Ce que l'API offre déjà et que le prompt supposait absent

### Création transactionnelle complète — §30

`StoreOrderRequest::rules()` fusionne `headerRules + lineRules + packageRules +
serviceRules`. **Un seul `POST /orders`** crée la commande, ses lignes, ses
colis et ses services. Le §30 est donc servi : aucune création sous-ressource
par sous-ressource.

Les liaisons internes se font par **clés temporaires**, pas par identifiants —
les lignes n'existent pas encore au moment où les colis les référencent :

```
packages[].key                     identifiant temporaire du colis
packages[].parentKey               hiérarchie des colis
packages[].lines[].lineKey         renvoie à une ligne du même envoi
services[].packages[].packageKey   renvoie à un colis du même envoi
```

C'est un point structurant pour le formulaire : il devra générer et maintenir
ces clés côté React.

### Transitions de statut — §40

`OrderDetailResource` expose déjà :

```
allowedTransitions   filtrées sur isManuallyAssignable()
allowsContentChanges
statusLabel
```

Le §40 est donc satisfait par l'API : le dialogue n'affichera que ces
transitions, sans liste écrite côté React. `allowsContentChanges` sert au §42.

### Détail complet en un appel — §63

`OrderDetailResource` renvoie `lines`, `packages`, `services` (avec `service`,
`address`, `contacts`, `packages`). Le §63 prévoit le chargement paresseux
« mais si le backend fournit un seul OrderDetailResource optimisé, réutiliser
ce contrat » — c'est le cas.

### Historique — §39

`GET /orders/{order}/history` renvoie le **journal d'audit** filtré sur la
commande. Réel, pas fabriqué depuis `updatedAt`.

---

## 3. Ce que le prompt demande et que l'API n'offre pas

| Demande | État | Décision |
| --- | --- | --- |
| §8 vue globale `/catalogs` | Absent — seulement `/customers/{customer}/catalogs` | Pas de page globale ; le §8 la conditionnait à l'API |
| §11 colonne et filtre `priority` | Aucun champ `priority` sur `Order` | Non affiché |
| §11 `requestedDateFrom` / `To` | `requestedDate` seul, date unique | Filtre à date unique |
| §15 réordonner les arrêts | Aucune route `reorder` pour les services | `sequence` saisie, pas de glisser-déposer |
| §29 `billingStatus` sur `OrderService` | Absent de la ressource | Non affiché |

Aucune de ces absences ne sera contournée par une invention.

> **Correction.** Une première lecture concluait que `/package-types` n'offrait
> aucun filtre. C'est faux : `PackageReferentialController::index` supporte
> `search` sur `code` et `name`, le filtre `status`, et le tri sur `code`,
> `name`, `created_at`. La recherche sera donc bien proposée.

---

## 4. Endpoints réels

```
GET|POST         /customers/{customer}/catalogs
GET|PATCH|DELETE /customers/{customer}/catalogs/{catalog}
GET|POST         /customers/{customer}/catalogs/{catalog}/items
GET|PATCH|DELETE /customers/{customer}/catalogs/{catalog}/items/{item}

GET|POST         /orders
GET|PATCH|DELETE /orders/{order}
POST             /orders/{order}/duplicate
PATCH            /orders/{order}/status
GET              /orders/{order}/history
GET|POST         /orders/{order}/documents

GET|POST         /orders/{order}/lines
GET|PATCH|DELETE /orders/{order}/lines/{line}

GET|POST         /orders/{order}/packages
GET|PATCH|DELETE /orders/{order}/packages/{package}
GET              /orders/{order}/packages/tree
POST             /orders/{order}/packages/{package}/lines
PATCH|DELETE     /orders/{order}/packages/{package}/lines/{line}

GET|POST         /orders/{order}/services
GET|PATCH|DELETE /orders/{order}/services/{orderService}
PATCH            /orders/{order}/services/{orderService}/status
GET|POST         /orders/{order}/services/{orderService}/contacts
PATCH|DELETE     /orders/{order}/services/{orderService}/contacts/{contact}

GET|POST         /services
GET|PATCH|DELETE /services/{service}

GET|POST         /package-types
PATCH|DELETE     /package-types/{packageType}
GET|POST         /package-grouping-types
PATCH|DELETE     /package-grouping-types/{groupingType}
```

---

## 5. Permissions réelles

Relevées dans `PermissionSeeder`, pas supposées :

```
catalogs.view create update delete
orders.view create update delete cancel duplicate change_status
order_lines.view create update delete
order_services.view create update delete change_status
packages.view create update delete
services.view create update delete
```

**`order_stops.*` n'existe pas.** Le §47 le donnait comme exemple en demandant
de vérifier ; la vérification l'écarte.

Les types de colis et de regroupement sont gouvernés par `packages.*` : le
`PermissionSeeder` le dit explicitement, aucune permission propre n'existe.

---

## 6. Filtres, tri, pagination

**Commandes** — `ListOrderRequest` :

```
search        order_number, external_reference, customer_reference
status        OrderStatus
source        OrderSource
customerId  agencyId  depotId  orderType
requestedDate   city   fromCatalog
```

Tri : `order_number`, `order_date`, `status`, `created_at`. Défaut
`order_date`. Toute autre colonne renvoie 422.

Pagination serveur partout, enveloppe `data / meta / links` de la Phase 1.

---

## 7. Énumérations réelles

```
OrderStatus         draft confirmed ready partially_planned planned
                    in_progress completed cancelled partially_invoiced invoiced

OrderSource         internal customer_portal rest_api csv_import
                    excel_import xml_import stock catalog

OrderServiceStatus  draft pending ready_to_plan planned in_progress
                    completed failed cancelled invoiced
```

Aucune valeur ne sera écrite en dur : les transitions viennent de
`allowedTransitions`, les listes déroulantes de ces énumérations.

---

## 8. Champs réellement exposés

**`CatalogResource`** — `id`, `customerId`, `code`, `name`, `description`,
`status`, `itemCount`, `items`, `createdAt`, `updatedAt`.

Le §8 demande « Version » et « Validité » : **aucun de ces champs n'existe**.
Ils ne seront pas affichés.

**`CatalogItemResource`** — `id`, `catalogId`, `articleCode`, `barcode`,
`name`, `description`, `weight`, `volume`, `length`, `width`, `height`,
`status`. Pas de `SKU` ni d'`unit` : le §10 les cite comme « typiquement »,
la ressource les ignore.

**`OrderListResource`** — `orderNumber`, `externalReference`,
`customerReference`, `customerName`, `agencyName`, `orderType`, `orderDate`,
`source`, `status`, `statusLabel`, `weight`, `volume`, `packageCount`,
`lineCount`, `serviceCount`.

**`OrderLineResource`** — `articleCode`, `barcode`, `name`, `description`,
`quantity`, `reservedQuantity`, `preparedQuantity`, `deliveredQuantity`,
`weight`, `volume`, `length`, `width`, `height`, `purchasePrice`,
`sellingPrice`, `status`, `fromCatalog`, `catalogItemId`, `parentLineId`.

**`PackageResource`** — `parentPackageId`, `packageTypeId`, `groupingTypeId`,
`barcode`, `reference`, `description`, `quantity`, `weight`, `volume`,
dimensions, `status`, `packageType`, `groupingType`, `lines`.

**`ServiceResource`** — `code`, `name`, `unit`, `defaultDurationMinutes`,
`billableToCustomer`, `payableToProvider`, `requiresAddress`,
`requiresContact`, `status`. Pas de `category` : le §26 la cite, elle n'existe
pas.

**`ReferentialResource`** — types de colis et de regroupement : `code`, `name`,
`status`. Rien d'autre.

---

## 9. Tableau des pages

| Page frontend | Endpoint | Permission | Query/Mutation | Statut |
| --- | --- | --- | --- | --- |
| `/customers/:id` onglet Catalogues | `GET /customers/{c}/catalogs` | `catalogs.view` | `useCatalogs` | à faire |
| `CatalogCreatePage` | `POST /customers/{c}/catalogs` | `catalogs.create` | `useCreateCatalog` | à faire |
| `CatalogDetailPage` | `GET /customers/{c}/catalogs/{id}` | `catalogs.view` | `useCatalog` | à faire |
| `CatalogEditPage` | `PATCH …/{id}` | `catalogs.update` | `useUpdateCatalog` | à faire |
| Articles du catalogue | `GET …/{id}/items` | `catalogs.view` | `useCatalogItems` | à faire |
| `OrderListPage` | `GET /orders` | `orders.view` | `useOrders` | à faire |
| `OrderCreatePage` | `POST /orders` | `orders.create` | `useCreateOrder` | à faire |
| `OrderDetailPage` | `GET /orders/{id}` | `orders.view` | `useOrder` | à faire |
| `OrderEditPage` | `PATCH /orders/{id}` | `orders.update` | `useUpdateOrder` | à faire |
| Dialogue statut | `PATCH /orders/{id}/status` | `orders.change_status` | `useChangeOrderStatus` | à faire |
| Dialogue duplication | `POST /orders/{id}/duplicate` | `orders.duplicate` | `useDuplicateOrder` | à faire |
| Onglet Lignes | inclus dans `OrderDetailResource` | `order_lines.view` | `useOrder` | à faire |
| Onglet Colis | `GET /orders/{id}/packages/tree` | `packages.view` | `usePackageTree` | à faire |
| Affectation ligne ↔ colis | `POST …/packages/{p}/lines` | `packages.update` | `useAssignPackageLine` | à faire |
| Onglet Services | inclus dans `OrderDetailResource` | `order_services.view` | `useOrder` | à faire |
| Statut d'un service | `PATCH …/services/{s}/status` | `order_services.change_status` | `useChangeServiceStatus` | à faire |
| Contacts d'un service | `GET …/services/{s}/contacts` | `order_services.view` | `useServiceContacts` | à faire |
| Onglet Documents | `GET /orders/{id}/documents` | `documents.view` | `useOrderDocuments` | à faire |
| Onglet Historique | `GET /orders/{id}/history` | `orders.view` | `useOrderHistory` | à faire |
| `ServiceListPage` | `GET /services` | `services.view` | `useServices` | à faire |
| `PackageTypeListPage` | `GET /package-types` | `packages.view` | `usePackageTypes` | à faire |
| `GroupingTypeListPage` | `GET /package-grouping-types` | `packages.view` | `useGroupingTypes` | à faire |

---

## 10. Réutilisé de la Phase 1

`AppLayout`, `AppSidebar`, `AppHeader`, `PermissionGuard`, `ProtectedRoute`,
`DataTable`, `DataTablePagination`, `SearchInput`, `StatusBadge`,
`ConfirmDialog`, `EmptyState`, `ErrorState`, `LoadingSkeleton`, `PageHeader`,
`EntityHeader`, `SectionCard`, `DetailField`, `TextField`, `SelectField`,
`StatusSelect`, `CheckboxField`, `FormActions`, `FormErrorSummary`,
`useApiFormError`, le client API, le contexte d'organisation.

Le module Documents de la Phase 1 est réutilisé tel quel (§38) : aucune
nouvelle gestion de fichiers.

Le §3 cite aussi `AsyncSelect`, `AddressSelector`, `ContactSelector`,
`DocumentList` et `DocumentUploader` comme composants de la Phase 1.
**Ils n'existent pas** : la Phase 1 a livré `SelectField`, `AddressFields`,
`AddressCard`, `AddressContactList` et `EntityDocumentsTab`. Les composants
manquants seront créés dans cette phase.

---

## 11. Menu

Les entrées seront ajoutées à `App\Shared\Menu\MenuCatalogue`, puis propagées
par `php artisan tricolis:sync-organization-menus` — procédure établie à la
Phase 1. Un test refuse une entrée dont la route ou la permission n'existe pas.

```
Clients        → Catalogues              catalogs.view
Exploitation   → Commandes               orders.view
               → Services                services.view
               → Types de colis          packages.view
               → Types de regroupement   packages.view
```

---

## 12. Points de vigilance

**Les clés temporaires de création.** Le formulaire devra générer `key`,
`parentKey`, `lineKey`, `packageKey` et les maintenir cohérents. Une clé
orpheline produit un 422 sur un chemin imbriqué — d'où le mapper du §31.

**Les prix sont obligatoires à la création d'un service.**
`customerUnitPrice`, `customerTotalPrice`, `providerUnitCost` et
`providerTotalCost` sont `required`. Le prompt corrigé tranche : ne pas
construire de moteur tarifaire, **et ne pas mettre `0` silencieusement**. Les
quatre champs seront donc **saisis explicitement**, sans valeur par défaut ni
calcul inventé — un `0` posé d'office serait une donnée métier fabriquée.

**Le catalogue est facultatif** (§7) : `lines.*.catalogItemId` est `nullable`,
et `name` devient requis en son absence. Les deux chemins seront testés (§60).

**`fromCatalog`** est exposé en lecture sur `OrderLineResource` et sert de
filtre de liste : c'est la source d'une ligne, au sens du §18.

**Au moins un service est obligatoire** : `services` est `required|array|min:1`,
comme `lines`. Une commande sans service ne peut pas être créée — le formulaire
doit l'annoncer avant l'étape de vérification, pas après le refus.


---

## 13. Vérifications complémentaires — prompt corrigé

Le prompt corrigé confirme l'absence d'`OrderStop` et valide la branche de
base. Trois de ses énoncés ne correspondent pourtant pas au backend réel.

### `plannedFrom`, `plannedTo`, `actualStartAt`, `actualEndAt` n'existent pas

Le §25 les liste parmi les champs d'`OrderService`. Ils sont absents **du
modèle, de la migration et de la ressource** :

```bash
grep -E "planned_from|actual_start_at" app/Modules/Orders/Models/OrderService.php   # rien
grep -E "planned_from|actual_start_at" database/migrations/*order_services*         # rien
```

C'est cohérent avec le §2 du même prompt : la planification appartient à
`TourStopService` et `TourStop`, phase ultérieure. Ces champs ne seront ni
affichés ni saisis.

### Les snapshots de contact ne portent pas le suffixe `Snapshot`

Le §27 nomme `firstNameSnapshot`, `phoneSnapshot`… Ce sont les **colonnes**.
`OrderServiceContactResource` les expose sans suffixe :

```
id  orderServiceId  contactId  contactRole
firstName  lastName  phone  mobile  email
isPrimary  createdAt
```

Le §45 demande de typer le contrat API, pas les colonnes SQL : ce sont ces
noms-là qui seront utilisés.

### La modification d'une commande ne touche ni au client ni à l'agence

`UpdateOrderRequest` n'accepte que :

```
depotId  externalReference  customerReference  orderType
groupCode  orderDate  currencyCode  internalRemark  workerRemark
```

`customerId` et `agencyId` en sont absents : une commande ne change pas de
client ni d'agence après création. Le formulaire d'édition les affichera en
lecture, comme l'email d'un membre en Phase 1.

### Ce que le prompt corrigé confirme

| Point | État |
| --- | --- |
| Branche de base `fix/phase-1-organization-roles-permissions` | conforme — §0.1 |
| Absence d'`OrderStop` et d'`order_stops.*` | confirmée |
| `OrderService` = unité opérationnelle adressée | confirmé |
| Wizard à 5 étapes, sans étape « Arrêts » | confirmé |
| Clés temporaires par `crypto.randomUUID()`, jamais l'index | §23 — retenu |
| `allowedTransitions` pilote le dialogue de statut | confirmé |
| `allowsContentChanges` pilote l'édition | confirmé |
| Pas de page globale `/catalogs` | confirmé |
| `AsyncSelect`, `AddressSelector`, `ContactSelector` à créer | §43 — confirmé |


---

## 14. Les clés temporaires : ce que le backend accepte réellement

Le §23 du prompt corrigé demande des clés stables et interdit l'index du
tableau. Le backend ne traite pas les deux cas de la même façon.

**Les colis acceptent une clé libre.** `CreateOrderPackages::execute()` indexe
le résultat par trois entrées :

```php
$created[(string) $index] = $model;
$created[$model->id] = $model;
if ($package->key !== null) { $created[$package->key] = $model; }
```

`parentKey` et `services[].packages[].packageKey` peuvent donc porter un
identifiant stable.

**Les lignes non.** `CreateOrderLines::execute()` n'indexe que par la position
et par l'identifiant :

```php
$created[(string) $index] = $model;
$created[$model->id] = $model;
```

Il n'existe pas de `lines[].key` dans `StoreOrderRequest`. **`lineKey` doit
donc valoir la position de la ligne dans le tableau envoyé.**

### La conciliation retenue

Le §23 a raison sur le fond : dans l'état du formulaire, retirer une ligne
décalerait toutes les positions suivantes et casserait les affectations déjà
faites. Chaque ligne porte donc un identifiant stable en mémoire —
`crypto.randomUUID()` — et **la position n'est calculée qu'au moment de
sérialiser**, sur le tableau définitif. L'index ne sert jamais d'identité.

### Contrainte d'ordre sur les colis

`CreateOrderPackages` construit son index au fil de la boucle : un colis
désigné comme parent doit **précéder** ses enfants dans le tableau. Le
formulaire sérialisera donc les colis en parcourant l'arbre de haut en bas.

### Ce qui manquerait côté API

Un `lines[].key` accepté par `StoreOrderRequest` et indexé comme celui des
colis rendrait les deux cas symétriques, et le formulaire n'aurait plus à
convertir. Le manque est consigné, pas contourné.
