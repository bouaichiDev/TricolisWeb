# Analyse Phase 2 — Catalogues, commandes, colis et services

Ce document répond au §1 et au §28.3 du cahier des charges « Phase 2 ». Il
confronte le périmètre demandé au modèle réellement décrit par les diagrammes,
et signale un conflit structurant à arbitrer avant d'écrire les migrations.

Sources : `Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme
interne.txt` et `— Diagramme de classes partagées.txt`, plus le code de la
Phase 1 ([`phase-1-analysis.md`](phase-1-analysis.md),
[`phase-1-database-decisions.md`](phase-1-database-decisions.md)).

---

## 1. Conflit structurant : « arrêts de commande »

Le §2 du cahier des charges demande quatre éléments qui **n'existent pas dans le
diagramme de la plateforme interne** :

| Demandé (§2) | Détaillé en | Présent au diagramme ? |
|---|---|---|
| Arrêts de commande | §8 | ❌ aucune classe `OrderStop` |
| Contacts des arrêts | §10 | ❌ aucune classe `OrderStopContact` |
| Snapshots d'adresses | §9 | ❌ aucun snapshot d'adresse de commande |
| Historique des statuts | §2.16 | ❌ aucune classe d'historique de statut |

Ce que le diagramme décrit à la place :

```text
Order "1" *-- "1..*" OrderService
Service "1" -- "0..*" OrderService
Address "1" -- "0..*" OrderService          ← l'adresse est portée par le service
OrderService "1" *-- "0..*" OrderServiceContact
Contact "0..1" -- "0..*" OrderServiceContact
OrderService "1" -- "0..*" OrderServicePackage
Package "1" -- "0..*" OrderServicePackage
```

Et la note attachée à `OrderService` est explicite :

> Le service est l'unité principale de planification. Chaque service possède :
> sa propre adresse ; ses contacts ; ses packages ; son créneau demandé ; son
> prix et son coût.

Les « arrêts » existent bien dans le modèle, mais sous le nom `TourStop`, et la
note attachée précise :

> Les stops sont générés automatiquement **après planification des services**.
> Le regroupement considère : la tournée ; l'adresse ; la date ; les créneaux
> compatibles.

Autrement dit : dans la conception, un arrêt n'est **pas** une donnée saisie à la
commande, c'est un **résultat calculé de la planification**, qui appartient à la
phase Tournées — explicitement hors périmètre Phase 2 (§2, « ne développe pas
encore : tournées, planning »).

Quant aux snapshots, le diagramme en prévoit deux, aucun sur une adresse de
commande :

- `OrderServiceContact` porte `firstNameSnapshot`, `lastNameSnapshot`,
  `phoneSnapshot`, `mobileSnapshot`, `emailSnapshot` — c'est bien le mécanisme
  d'historisation demandé au §10, mais **sur le service** ;
- `InvoiceLineAddressSnapshot` fige l'adresse **au moment de la facturation**,
  ce qui relève de la phase Facturation.

### Conséquence

Le §5 affirme : « Une commande ne contient pas une adresse unique. Elle contient
plusieurs arrêts. » La première moitié est conforme au diagramme, la seconde ne
l'est pas : une commande contient plusieurs **services**, et c'est le service qui
porte l'adresse.

Suivre le §8 à la lettre imposerait de créer `order_stops`,
`order_stop_contacts` et `order_stop_address_snapshots`, puis de déplacer
`OrderService.addressId` vers `OrderService.orderStopId`. Cela contredirait le
diagramme sur un point central du modèle, et rendrait la Phase 3 (tournées)
incohérente, puisque `TourStop` deviendrait un doublon d'`OrderStop`.

**Cette décision appartient au porteur du projet.** Elle est posée en question
ouverte plutôt que tranchée unilatéralement : c'est le cœur du modèle de
commande, et se tromper coûterait une refonte complète de la Phase 2 et de la
Phase 3. Les options sont détaillées au §7 de ce document.

## 2. Périmètre Phase 2 confronté au diagramme

| # | Demandé (§2) | Classe du diagramme | Statut |
|---|---|---|---|
| 1 | Catalogues clients | `CustomerCatalog` | à créer |
| 2 | Articles des catalogues | `CustomerCatalogItem` | à créer |
| 3 | Commandes | `Order` | **existe déjà**, conforme |
| 4 | Lignes de commande | `OrderLine` | **existe déjà**, conforme |
| 5 | Arrêts de commande | — | ⚠️ absent du diagramme |
| 6 | Contacts des arrêts | `OrderServiceContact` | à créer, mais rattaché au **service** |
| 7 | Snapshots d'adresses | — | ⚠️ absent (snapshots de **contact** uniquement) |
| 8 | Types de colis | `PackageType` | à créer |
| 9 | Types de regroupement | `GroupingType` | à créer (nom exact : `GroupingType`, pas `PackageGroupingType`) |
| 10 | Colis | `Package` | à créer |
| 11 | Hiérarchie des colis | `Package.parentPackageId` | à créer |
| 12 | Liaison colis-lignes | `PackageOrderLine` | à créer |
| 13 | Catalogue de services | `Service` | **existe déjà** (modèle + table), CRUD à exposer |
| 14 | Services d'une commande | `OrderService` | **existe déjà**, CRUD à compléter |
| 15 | Liaison services / arrêts / colis | `OrderServicePackage` | services↔colis à créer ; services↔arrêts = `TourStopService`, Phase 3 |
| 16 | Historique des statuts | — | ⚠️ absent du diagramme |
| 17 | Documents liés aux commandes | `DocumentLink` polymorphe | mécanisme livré en Phase 1, endpoints à exposer |
| 18 | Audit | `AuditLog` | livré en Phase 1, à étendre aux nouvelles actions |
| 19 | API REST | — | conventions livrées en Phase 1 |
| 20 | Tests | — | à écrire |

## 3. État du code hérité de la Phase 1

Le module Commandes a été livré en Phase 1 bien qu'il fût hors périmètre. Bonne
nouvelle : **son schéma correspond au diagramme attribut par attribut**.

| Table | Conformité |
|---|---|
| `orders` | conforme, y compris `parent_order_id`, `group_code`, `updated_by` |
| `order_lines` | conforme, y compris `reserved_quantity`, `prepared_quantity`, `delivered_quantity`, `parent_line_id` |
| `services` | conforme |
| `order_services` | conforme, `address_id` porté par le service |

Ce qui manque côté API : `PATCH /orders/{order}`, la duplication, les
transitions de statut, les filtres avancés, et le CRUD des services de commande.

## 4. Enums

Déjà créés en Phase 1 et conformes : `OrderSource` (8 valeurs), `OrderStatus`
(10 valeurs), `OrderServiceStatus` (9 valeurs), `CustomerStatus`.

À vérifier au moment de l'implémentation : `OrderStatus` du diagramme comporte
`PARTIALLY_PLANNED`, `PARTIALLY_INVOICED` et `INVOICED`, qui ne prendront leur
sens qu'avec les phases Planification et Facturation. Les transitions de la
Phase 2 devront donc n'autoriser que le sous-ensemble réellement atteignable.

Aucun enum n'est à créer pour la Phase 2 : `PackageType`, `GroupingType` et les
statuts de colis sont des chaînes libres au diagramme, comme les autres
référentiels.

## 5. Tables à créer et ordre des migrations

1. `customer_catalogs` — dépend de `customers`
2. `customer_catalog_items` — dépend de `customer_catalogs`
3. `package_types` — dépend de `organizations`
4. `grouping_types` — dépend de `organizations`
5. `packages` — dépend de `orders`, `package_types`, `grouping_types`, et d'elle-même
6. `package_order_lines` — dépend de `packages` et `order_lines`
7. `order_service_contacts` — dépend de `order_services` et `contacts`
8. `order_service_packages` — dépend de `order_services` et `packages`
9. `order_lines.catalog_item_id` — clé étrangère à ajouter : la colonne existe
   déjà mais la contrainte n'a pas pu être posée en Phase 1, `customer_catalog_items`
   n'existant pas encore

`Package.currentStockLocationId` est au diagramme mais pointe vers
`StockLocation`, hors périmètre Phase 2. La colonne sera créée sans contrainte,
ou reportée — voir la question ouverte au §7.

## 6. Points d'attention relevés

| # | Point | Traitement prévu |
|---|---|---|
| A | Le §11 nomme l'entité `PackageGroupingType`, le diagramme `GroupingType` | Le diagramme fait foi : table `grouping_types`. Les URL restent `/package-grouping-types` comme demandé au §11 |
| B | Le §6 impose un numéro de commande sans `count()` ni collision | Table de compteurs verrouillée en base, incrémentée dans la transaction de création |
| C | Le §13 demande que la somme des quantités affectées ne dépasse pas la ligne | Vérification sous verrou pessimiste sur `order_lines`, avec test de concurrence |
| D | Le §21 demande 404 plutôt que 403 pour ne pas révéler l'existence d'une ressource | Change la convention de la Phase 1, qui renvoie 403. À appliquer aux nouvelles ressources ; l'harmonisation des anciennes est une question ouverte |
| E | Le §12 impose d'empêcher les cycles parent-enfant des colis | Vérification applicative en remontant la chaîne, plus profondeur maximale |
| F | `Order.weight`, `volume`, `packageCount` sont des agrégats | Recalculés à l'écriture des lignes et colis, jamais saisis directement |
| G | Le §15 interdit de calculer les prix | Les champs financiers sont enregistrés tels que fournis, jamais dérivés |

## 7. Questions ouvertes, à trancher avant implémentation

1. **Arrêts de commande** (bloquant) — suivre le diagramme (l'adresse reste sur
   `OrderService`, les arrêts arrivent en Phase 3 sous forme de `TourStop`), ou
   suivre le §8 et créer `OrderStop` en s'écartant de la conception ?
2. **Historique des statuts** — le diagramme n'en prévoit pas. Faut-il créer une
   table `order_status_histories` hors diagramme, ou dériver l'historique du
   journal d'audit, qui enregistre déjà chaque changement de statut avec ses
   anciennes et nouvelles valeurs ?
3. **`Package.currentStockLocationId`** — créer la colonne dès maintenant sans
   contrainte, ou attendre la phase Stock ?
4. **Convention 403 / 404** — appliquer le §21 aux seules nouvelles ressources,
   ou harmoniser aussi celles de la Phase 1 ?

## 8. Hors périmètre Phase 2

Conformément au §2 : stock et ses mouvements, réservations, fournisseurs,
véhicules, chauffeurs, contrats, tournées, planning, périodes, tarification
avancée, facturation, décomptes fournisseurs, réclamations, communications,
imports asynchrones, tracking chauffeur.

Sont également hors périmètre, bien que présents au diagramme et rattachés aux
clients : `CustomerUser`, `CustomerImportConfiguration`, `CustomerApiConfiguration`,
`CustomerExportConfiguration` et `ExportJob`.
