# Décisions base de données — Phase 2

Ce document répond au §26 du cahier des charges « Phase 2 ». Il complète
[`phase-1-database-decisions.md`](phase-1-database-decisions.md), dont les
principes (ULID, isolation, morph map, enums en `VARCHAR`) restent valables.

---

## 1. Tables créées

| # | Table | Classe du diagramme | Rôle |
|---|-------|---------------------|------|
| 1 | `customer_catalogs` | `CustomerCatalog` | Catalogue d'articles d'un client |
| 2 | `customer_catalog_items` | `CustomerCatalogItem` | Article du catalogue |
| 3 | `package_types` | `PackageType` | Référentiel des types de colis |
| 4 | `grouping_types` | `GroupingType` | Référentiel des types de regroupement |
| 5 | `packages` | `Package` | Colis d'une commande, hiérarchisable |
| 6 | `package_order_lines` | `PackageOrderLine` | Répartition d'une ligne dans un colis |
| 7 | `order_service_contacts` | `OrderServiceContact` | Contacts d'un service, avec snapshot |
| 8 | `order_service_packages` | `OrderServicePackage` | Colis servis par un service |
| 9 | `order_number_sequences` | — | Compteur de numérotation (hors diagramme, voir §8) |

Deux migrations complètent des tables de la Phase 1 :

- `add_catalog_item_foreign_key_to_order_lines_table` : la colonne
  `order_lines.catalog_item_id` existait, mais sa contrainte n'avait pas pu être
  posée — `customer_catalog_items` n'existait pas encore.
- `add_defaults_to_order_services_table` : les agrégats et montants étaient
  `NOT NULL` sans défaut, ce qui faisait échouer en SQL brut toute création de
  service sans poids ni prix. Zéro est désormais la valeur neutre.

## 2. Clés étrangères et stratégies de suppression

| Relation | Stratégie | Justification |
|----------|-----------|---------------|
| `customer_catalogs.customer_id → customers.id` | `CASCADE` | Un catalogue n'existe pas sans son client. |
| `customer_catalog_items.catalog_id → customer_catalogs.id` | `CASCADE` | Un article n'existe pas sans son catalogue. |
| `order_lines.catalog_item_id → customer_catalog_items.id` | `RESTRICT` | Protège l'historique : un article utilisé par une commande ne se supprime pas. Le désactiver est l'opération attendue. |
| `package_types.organization_id → organizations.id` | `CASCADE` | Référentiel propre à l'organisation. |
| `grouping_types.organization_id → organizations.id` | `CASCADE` | Idem. |
| `packages.order_id → orders.id` | `CASCADE` | Le colis appartient à sa commande (`Order 1 *-- 0..* Package`). |
| `packages.parent_package_id → packages.id` | `SET NULL` | Supprimer un parent ne doit pas emporter sa descendance sans le dire. L'API refuse d'ailleurs la suppression d'un colis ayant des enfants (409). |
| `packages.package_type_id → package_types.id` | `RESTRICT` | Un type utilisé ne se supprime pas. |
| `packages.grouping_type_id → grouping_types.id` | `RESTRICT` | Idem. |
| `package_order_lines.package_id → packages.id` | `CASCADE` | L'affectation n'a pas de sens sans son colis. |
| `package_order_lines.order_line_id → order_lines.id` | `CASCADE` | Idem côté ligne. |
| `order_service_contacts.order_service_id → order_services.id` | `CASCADE` | Le contact du service disparaît avec lui. |
| `order_service_contacts.contact_id → contacts.id` | `SET NULL` | Le contact partagé peut être supprimé ; le snapshot suffit à lire la commande. |
| `order_service_packages.order_service_id → order_services.id` | `CASCADE` | |
| `order_service_packages.package_id → packages.id` | `CASCADE` | |
| `order_number_sequences.organization_id → organizations.id` | `CASCADE` | |

`packages.current_stock_location_id` figure au diagramme mais pointe vers
`StockLocation`, hors périmètre. La colonne existe, **sans contrainte** : la
poser demanderait une table qui n'existe pas. Elle sera ajoutée avec le module
Stock.

## 3. Contraintes uniques

- `customer_catalogs(customer_id, code)` — le code est unique chez un client,
  pas globalement : deux clients peuvent avoir un catalogue « CAT-01 ».
- `customer_catalog_items(catalog_id, article_code)` — même logique.
- `package_types(organization_id, code)` et `grouping_types(organization_id, code)`.
- `packages.barcode` — **unique globalement**, et non par commande. Un
  code-barres identifie un colis physique chez le transporteur : deux colis
  portant la même étiquette rendraient le scan ambigu.
- `package_order_lines(package_id, order_line_id)` — une ligne ne figure qu'une
  fois par colis ; c'est la quantité qui varie.
- `order_service_packages(order_service_id, package_id)`.
- `order_number_sequences(organization_id, scope, year)` — arbitre les créations
  concurrentes de compteur.

Héritées de la Phase 1 et toujours en vigueur : `orders(organization_id, order_number)`,
`order_services(order_id, service_number)`, `order_services(order_id, sequence)`.

## 4. Index

Conformément au §23, sur les colonnes réellement filtrées :

- `customer_catalogs(customer_id, status)`
- `customer_catalog_items(catalog_id, status)`, `customer_catalog_items.barcode`
- `package_types(organization_id, status)`, `grouping_types(organization_id, status)`
- `packages(order_id, status)`, `packages.parent_package_id`,
  `packages.current_stock_location_id`
- `package_order_lines.order_line_id`
- `order_service_contacts(order_service_id, contact_role)`, `.contact_id`
- `order_service_packages.package_id`
- `order_lines.catalog_item_id`

Déjà présents depuis la Phase 1 : `orders(organization_id, status)`,
`orders(customer_id, order_date)`, `order_lines.order_id`,
`order_lines.article_code`.

Le filtre par ville passe par `orderServices.address` : il s'appuie sur les index
existants d'`addresses` et de `order_services.address_id`.

## 5. Colonnes JSON

Aucune en Phase 2. Les structures évolutives du diagramme (`mapping`,
`validationRules`, `permissions`, `settings`) appartiennent aux configurations
d'import, d'API et d'export, hors périmètre.

## 6. Snapshots

Le diagramme prévoit un seul mécanisme de snapshot dans ce périmètre :
`OrderServiceContact` porte `firstNameSnapshot`, `lastNameSnapshot`,
`phoneSnapshot`, `mobileSnapshot` et `emailSnapshot`.

**Quand le snapshot est-il écrit ?** À la création du contact du service, et à sa
modification explicite. Jamais ensuite : modifier le contact partagé ne
réécrit aucune commande. C'est précisément le but — une commande exécutée doit
rester lisible telle qu'elle l'a été.

`contact_id` reste nullable et en `SET NULL` : un contact ponctuel, saisi pour
une seule commande, n'a pas de fiche partagée, et un contact supprimé ne doit
pas emporter l'historique.

**Il n'existe pas de snapshot d'adresse de commande.** Le cahier des charges en
demandait un (§9), mais le diagramme n'en contient aucun : le seul snapshot
d'adresse prévu est `InvoiceLineAddressSnapshot`, au moment de la facturation.
Voir [`phase-2-analysis.md`](phase-2-analysis.md) §1.

## 7. Hiérarchie des colis et prévention des cycles

`packages.parent_package_id` est auto-référencée. Trois protections, toutes
applicatives — SQL ne sait pas exprimer ces règles :

1. **Même commande** : le parent doit avoir le même `order_id`. Un parent d'une
   autre commande renvoie 404.
2. **Pas d'auto-parenté ni de cycle** : avant écriture, la chaîne des ancêtres
   du parent candidat est remontée ; si le colis courant s'y trouve, la relation
   est refusée en 422.
3. **Profondeur bornée** : `Package::MAX_DEPTH = 5`. Le diagramme n'impose
   aucune limite ; celle-ci protège des arbres pathologiques et borne le coût du
   chargement.

L'arbre (`GET /orders/{order}/packages/tree`) est construit **en mémoire** à
partir d'une seule requête plate, groupée par parent. Un chargement récursif
émettrait une requête par niveau.

## 8. Stratégie de numérotation des commandes

`order_number_sequences` n'existe pas au diagramme. Elle est ajoutée pour
satisfaire le §6, qui exige l'unicité sans collision en concurrence et interdit
explicitement de dériver le numéro d'un `count()`.

Fonctionnement :

1. la création de commande ouvre une transaction ;
2. la ligne `(organization_id, scope, year)` est lue avec `lockForUpdate()` ;
3. si elle n'existe pas, elle est créée — la contrainte unique arbitre les
   créations concurrentes, et le perdant relit la ligne du gagnant sous verrou ;
4. `last_number` est incrémenté, puis formaté en `ORD-2026-000001`.

Le verrou est détenu jusqu'au commit : deux créations simultanées dans la même
organisation sont sérialisées, celles d'organisations différentes ne
s'attendent pas. Un `RuntimeException` est levé si l'action est appelée hors
transaction — le verrou n'aurait alors aucun effet.

La colonne `scope` permettra une série par agence sans changer de mécanisme.

## 9. Stratégie des statuts

`OrderStatus` porte désormais ses règles :

- `allowedTransitions()` déclare le graphe complet ;
- `manuallyAssignable()` restreint ce qu'un opérateur peut poser à ce stade :
  `DRAFT`, `CONFIRMED`, `READY`, `CANCELLED`. Les statuts de planification
  (`PARTIALLY_PLANNED`, `PLANNED`, `IN_PROGRESS`) et de facturation
  (`PARTIALLY_INVOICED`, `INVOICED`) existent au diagramme mais seront produits
  par leurs modules — les exposer maintenant permettrait de déclarer « planifiée »
  une commande sans tournée ;
- `allowsContentChanges()` gèle lignes, colis et services au-delà de `CONFIRMED` ;
- `requiresReason()` impose un motif à l'annulation.

**L'historique n'a pas de table dédiée.** Le diagramme n'en prévoit aucune, et le
journal d'audit enregistre déjà chaque transition avec son ancien et son nouveau
statut. `GET /orders/{order}/history` lit `audit_logs` filtré sur l'entité.
Conséquence à connaître : l'historique suit la rétention de l'audit.

## 10. Agrégats de commande

`orders.weight`, `orders.volume` et `orders.package_count` figurent au diagramme
mais ne sont jamais saisis : ils dérivent des lignes et des colis, et sont
recalculés par `RecalculateOrderTotals` à chaque écriture de ligne ou de colis.
Les accepter en entrée les laisserait diverger du contenu réel.

## 11. Quantités réparties

`package_order_lines.quantity` est contrainte applicativement : la somme des
quantités affectées d'une ligne ne peut pas dépasser sa quantité commandée.

La vérification se fait sous **verrou pessimiste** sur `order_lines`
(`lockForUpdate`) : sans lui, deux affectations concurrentes liraient chacune un
total encore valide et le dépasseraient ensemble. Réduire la quantité d'une
ligne en dessous de ce qui est déjà réparti est refusé en 422 ; supprimer une
ligne encore répartie est refusé en 409.
