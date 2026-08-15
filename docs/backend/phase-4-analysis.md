# Analyse Phase 4 — Planification des services et tournées

Ce document répond au §3 du prompt « Tricolis V2 — Backend Phase 4 ». Il fige le
modèle avant toute migration : aucune migration n'est écrite avant que le
tableau de correspondance du §3 soit terminé.

---

## 1. Sources de vérité

Le prompt désigne comme sources officielles :

```text
Conception/diagramme/00-diagramme-classes-partagees.puml
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

Ces deux `.puml` **n'existent pas** et n'ont jamais été produits. Comme aux
Phases 3, les diagrammes réellement disponibles font foi :

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

**Aucun conflit cette fois.** Les cinq classes et les deux enums décrits par le
prompt correspondent **exactement** aux lignes 52-68 et 486-561 du diagramme
interne, attribut par attribut, dans le même ordre. C'est vérifié ci-dessous au
§4.

## 2. État du code avant modification

Phases 1, 2 et 3 livrées et vertes : **242 tests**, 705 assertions, 163
opérations OpenAPI.

| Élément | Version / état |
|---------|----------------|
| Laravel | 13.23.0 |
| PHP | 8.4.2 |
| Base | MySQL 8 (aucune fonctionnalité PostgreSQL) |
| Auth | Sanctum 4 |
| Tests | Pest 5, `RefreshDatabase` |
| Style | Pint |
| Doc API | Scramble |

### Dépendances vers les phases précédentes

Toutes présentes, aucune à créer :

| Table | Phase | Ce dont la Phase 4 a besoin |
|-------|-------|------------------------------|
| `organizations` | 1 | `tours.organization_id` |
| `agencies` | 1 | `tours.agency_id` |
| `depots` | 1 | `tours.depot_id` — rattaché à une agence |
| `addresses` | 1 | `tour_stops.address_id` |
| `audit_logs` | 1 | audit de la phase |
| `orders` | 2 | périmètre organisationnel des services et colis |
| `order_services` | 2 | `tour_stop_services.order_service_id` |
| `packages` | 2 | `tour_period_assignments.package_id` |
| `providers` | 3 | `tours.provider_id` |
| `drivers` | 3 | `tours.driver_id` |
| `vehicles` | 3 | `tours.vehicle_id` |

**C'est pourquoi la branche part de la Phase 3 et non de `main`** : `main` est
resté au commit initial `c97dc0d`, un squelette Laravel vide. Brancher depuis
lui rendrait les cinq migrations impossibles à exécuter. Écart au §0 assumé et
documenté, comme en Phase 3.

### Mécanismes réutilisés sans duplication

- `App\Shared\Database\Concerns\HasUlid` — génération ULID ;
- `App\Shared\Organizations\CurrentOrganizationContext` + middleware — contexte ;
- `App\Policies\BaseOrganizationPolicy` — permission + appartenance ;
- `App\Modules\Audit\Actions\WriteAuditLog` — audit explicite ;
- `App\Shared\Http\Responses\ApiResponse` — enveloppe `data` / `meta` / `links` ;
- `App\Shared\Http\Requests\ListRequest` — pagination, tri, recherche ;
- `App\Shared\Http\Rules\BelongsToActiveOrganization` — périmètre des FK ;
- `App\Shared\Support\PartialAttributes` + `InputMapper` — `PATCH` partiel ;
- `App\Shared\Support\AuditContext` — contexte d'audit hors HTTP ;
- `App\Shared\Database\MorphMap` — alias métier pour `AuditLog.entityType`.

### Convention de structure

La couche HTTP vit dans `app/Http/{Controllers,Requests,Resources}`, le métier
dans `app/Modules/<Domaine>/{Models,Actions,Queries,DTOs,Enums,Services,Exceptions}`,
les Policies dans `app/Policies`. Le §5 propose un `Http/` par module ; le projet
fait autrement depuis la Phase 1, et le §5 demande lui-même de « respecter
l'architecture modulaire déjà utilisée ». **Convention existante conservée.**

Un seul module est créé : `app/Modules/Tours`. Le §5 propose aussi `Planning/`,
mais les cinq classes appartiennent toutes au paquet « Planification des
services » du diagramme et partagent le même agrégat. Les séparer en deux
modules couperait `Tour` de ses `TourPeriod` sans bénéfice.

## 3. Classes implémentées

Cinq, et seulement cinq :

```text
Tour
TourStop
TourStopService
TourPeriod
TourPeriodAssignment
```

Deux enums : `TourStatus`, `TourStopStatus`.

## 4. Relations et cardinalités

Relevées telles quelles dans le diagramme (lignes 897-914) :

```text
Organization    "1"    --  "0..*" Tour
Agency          "1"    --  "0..*" Tour
Depot           "0..1" --  "0..*" Tour
Provider        "0..1" --  "0..*" Tour
Driver          "0..1" --  "0..*" Tour
Vehicle         "0..1" --  "0..*" Tour
Tour            "1"    *-- "0..*" TourStop
Address         "1"    --  "0..*" TourStop
TourStop        "1"    *-- "1..*" TourStopService
OrderService    "1"    --  "0..*" TourStopService
Tour            "1"    *-- "0..*" TourPeriod
TourStop        "0..1" --  "0..*" TourPeriod
TourPeriod      "1"    *-- "0..*" TourPeriodAssignment
TourStopService "1"    --  "0..*" TourPeriodAssignment
Package         "0..1" --  "0..*" TourPeriodAssignment
```

Le losange plein `*--` marque une **composition** : `TourStop` et `TourPeriod`
n'existent pas hors de leur `Tour`, `TourStopService` hors de son `TourStop`,
`TourPeriodAssignment` hors de sa `TourPeriod`. C'est ce qui dicte les cascades
au §8.

Le trait simple `--` marque une **association** : supprimer une `Address`, un
`OrderService`, un `Package` ou un `TourStopService` ne doit rien détruire.

Relations Eloquent :

```text
Tour                 belongsTo Organization, Agency, Depot, Provider, Driver, Vehicle
                     hasMany TourStop, TourPeriod
TourStop             belongsTo Tour, Address
                     hasMany TourStopService, TourPeriod
TourStopService      belongsTo TourStop, OrderService
                     hasMany TourPeriodAssignment
TourPeriod           belongsTo Tour, TourStop
                     hasMany TourPeriodAssignment
TourPeriodAssignment belongsTo TourPeriod, TourStopService, Package
```

### Isolation organisationnelle

| Classe | Porte `organizationId` | Isolation |
|--------|------------------------|-----------|
| `Tour` | **oui** | condition directe |
| `TourStop` | non | via `tour.organization_id` |
| `TourStopService` | non | via `tourStop.tour.organization_id` |
| `TourPeriod` | non | via `tour.organization_id` |
| `TourPeriodAssignment` | non | via `tourPeriod.tour.organization_id` |

Seul `Tour` porte l'organisation, comme le diagramme le pose. Les quatre autres
sont des enfants d'agrégat : leur périmètre est celui de leur `Tour`, et chaque
route est imbriquée sous `/tours/{tour}`, ce qui rend le contrôle systématique.

Aucune route ne prend un enfant sans son parent : `GET /tours/{tour}/stops/{tourStop}`
vérifie d'abord que le `Tour` appartient à l'organisation active, puis que le
`TourStop` appartient à ce `Tour`. Un identifiant valide sous un mauvais parent
renvoie 404, comme en Phase 2.

## 5. Enums

Deux enums PHP natifs adossés à des colonnes `VARCHAR`, convention des Phases 1
et 2 (`OrderStatus`, `OrderServiceStatus`, `CustomerStatus`).

```text
TourStatus     : DRAFT, PLANNED, CONFIRMED, IN_PROGRESS, COMPLETED, CANCELLED
TourStopStatus : PENDING, ARRIVED, IN_PROGRESS, COMPLETED, SKIPPED, CANCELLED
```

Exactement les valeurs du diagramme, ni plus ni moins. Valeurs stockées en
`snake_case` (`in_progress`), comme `OrderStatus`.

`TourStopService.status` et `TourPeriod.status` sont des `string` au diagramme,
**pas** des enums : aucune valeur n'y est énumérée. Ils restent des chaînes
libres, validées comme telles. Créer un enum reviendrait à inventer les valeurs.

### Transitions de statut

Le §21 demande de chercher un moteur existant. `OrderStatus` en possède un
(`allowedTransitions()`, `canTransitionTo()`), mais **propre à cet enum** : il
n'existe pas de moteur partagé à réutiliser.

Le diagramme n'énumère aucune transition pour `TourStatus` ni `TourStopStatus`.
**Décision : valider uniquement l'appartenance à l'enum.** Aucune transition
n'est bloquée, aucun workflow n'est inventé. Le §21 le demande explicitement :
« ne pas bloquer arbitrairement des transitions non définies ».

Conséquence assumée : une tournée peut passer de `COMPLETED` à `DRAFT`. Le jour
où les transitions seront définies, elles s'ajouteront sur le modèle
d'`OrderStatus`, sans changer de mécanisme.

## 6. Tableau de correspondance

### Tour → `tours`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `organizationId` | ULID | `organization_id` CHAR(26) | non | index + unique composite | FK `organizations.id` RESTRICT |
| `tourNumber` | string | `tour_number` VARCHAR(255) | non | unique `(organization_id, tour_number)` | — |
| `tourDate` | date | `tour_date` DATE | non | index | — |
| `agencyId` | ULID | `agency_id` CHAR(26) | non | index | FK `agencies.id` RESTRICT |
| `depotId` | ULID | `depot_id` CHAR(26) | **oui** | index | FK `depots.id` SET NULL |
| `providerId` | ULID | `provider_id` CHAR(26) | **oui** | index | FK `providers.id` SET NULL |
| `vehicleId` | ULID | `vehicle_id` CHAR(26) | **oui** | index | FK `vehicles.id` SET NULL |
| `driverId` | ULID | `driver_id` CHAR(26) | **oui** | index | FK `drivers.id` SET NULL |
| `tourType` | string | `tour_type` VARCHAR(64) | **oui** | index | — |
| `instructions` | text | `instructions` TEXT | **oui** | — | — |
| `plannedStartAt` | datetime | `planned_start_at` DATETIME | **oui** | — | — |
| `plannedEndAt` | datetime | `planned_end_at` DATETIME | **oui** | — | — |
| `actualStartAt` | datetime | `actual_start_at` DATETIME | **oui** | — | — |
| `actualEndAt` | datetime | `actual_end_at` DATETIME | **oui** | — | — |
| `totalWeight` | decimal | `total_weight` DECIMAL(12,3) | non, défaut 0 | — | — |
| `totalVolume` | decimal | `total_volume` DECIMAL(12,4) | non, défaut 0 | — | — |
| `totalPackages` | int | `total_packages` INT UNSIGNED | non, défaut 0 | — | — |
| `totalCustomers` | int | `total_customers` INT UNSIGNED | non, défaut 0 | — | — |
| `drivingTimeMinutes` | int | `driving_time_minutes` INT UNSIGNED | non, défaut 0 | — | — |
| `workingTimeMinutes` | int | `working_time_minutes` INT UNSIGNED | non, défaut 0 | — | — |
| `distanceMeters` | bigint | `distance_meters` BIGINT UNSIGNED | non, défaut 0 | — | — |
| `status` | TourStatus | `status` VARCHAR(32) | non | index | cast enum |

### TourStop → `tour_stops`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `tourId` | ULID | `tour_id` CHAR(26) | non | index + unique composite | FK `tours.id` CASCADE |
| `addressId` | ULID | `address_id` CHAR(26) | non | index | FK `addresses.id` RESTRICT |
| `sequence` | int | `sequence` INT UNSIGNED | non | unique `(tour_id, sequence)` | — |
| `groupingKey` | string | `grouping_key` VARCHAR(255) | **oui** | index | — |
| `generationMode` | string | `generation_mode` VARCHAR(64) | **oui** | — | — |
| `plannedArrivalAt` | datetime | `planned_arrival_at` DATETIME | **oui** | — | — |
| `plannedDepartureAt` | datetime | `planned_departure_at` DATETIME | **oui** | — | — |
| `actualArrivalAt` | datetime | `actual_arrival_at` DATETIME | **oui** | — | — |
| `actualDepartureAt` | datetime | `actual_departure_at` DATETIME | **oui** | — | — |
| `waitingMinutes` | int | `waiting_minutes` INT UNSIGNED | non, défaut 0 | — | — |
| `serviceMinutes` | int | `service_minutes` INT UNSIGNED | non, défaut 0 | — | — |
| `status` | TourStopStatus | `status` VARCHAR(32) | non | index | cast enum |

### TourStopService → `tour_stop_services`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `tourStopId` | ULID | `tour_stop_id` CHAR(26) | non | index + unique composite | FK `tour_stops.id` CASCADE |
| `orderServiceId` | ULID | `order_service_id` CHAR(26) | non | index | FK `order_services.id` RESTRICT |
| `sequenceWithinStop` | int | `sequence_within_stop` INT UNSIGNED | non | unique `(tour_stop_id, sequence_within_stop)` | — |
| `isActiveAssignment` | boolean | `is_active_assignment` BOOLEAN | non | index | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### TourPeriod → `tour_periods`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `tourId` | ULID | `tour_id` CHAR(26) | non | index + unique composite | FK `tours.id` CASCADE |
| `tourStopId` | ULID | `tour_stop_id` CHAR(26) | **oui** | index | FK `tour_stops.id` SET NULL |
| `periodType` | string | `period_type` VARCHAR(64) | non | index | — |
| `sequence` | int | `sequence` INT UNSIGNED | non | unique `(tour_id, sequence)` | — |
| `plannedStartAt` | datetime | `planned_start_at` DATETIME | **oui** | index | — |
| `plannedEndAt` | datetime | `planned_end_at` DATETIME | **oui** | — | — |
| `actualStartAt` | datetime | `actual_start_at` DATETIME | **oui** | — | — |
| `actualEndAt` | datetime | `actual_end_at` DATETIME | **oui** | — | — |
| `breakMinutes` | int | `break_minutes` INT UNSIGNED | non, défaut 0 | — | — |
| `serviceMinutes` | int | `service_minutes` INT UNSIGNED | non, défaut 0 | — | — |
| `waitingMinutes` | int | `waiting_minutes` INT UNSIGNED | non, défaut 0 | — | — |
| `distanceMeters` | bigint | `distance_meters` BIGINT UNSIGNED | non, défaut 0 | — | — |
| `internalRemark` | text | `internal_remark` TEXT | **oui** | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### TourPeriodAssignment → `tour_period_assignments`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `tourPeriodId` | ULID | `tour_period_id` CHAR(26) | non | index + unique composite | FK `tour_periods.id` CASCADE |
| `tourStopServiceId` | ULID | `tour_stop_service_id` CHAR(26) | non | index | FK `tour_stop_services.id` RESTRICT |
| `packageId` | ULID | `package_id` CHAR(26) | **oui** | index | FK `packages.id` RESTRICT |

Unique `(tour_period_id, tour_stop_service_id, package_id)` — voir §7.

## 7. Décisions de nullabilité

Deux sources déterminent la nullabilité, et rien d'autre :

1. **les cardinalités du diagramme** pour les clés étrangères (`0..1` → nullable,
   `1` → obligatoire) ;
2. **les sections « Contraintes » du prompt** pour les scalaires, qui énumèrent
   ce qui est obligatoire.

| Colonne | Choix | Raison |
|---------|-------|--------|
| `tours.depot_id`, `provider_id`, `driver_id`, `vehicle_id` | nullable | `Depot "0..1" -- "0..*" Tour`, idem Provider, Driver, Vehicle. Une tournée peut être planifiée avant d'être affectée. |
| `tours.agency_id` | non | `Agency "1"`. |
| `tours.tour_type`, `instructions` | nullable | Absents de la liste des obligatoires du §8. Précédent direct : `orders.order_type` et `orders.internal_remark` sont nullables. |
| `tours.planned_*`, `actual_*` | nullable | Une tournée en `DRAFT` n'a pas encore d'horaires ; les horaires réels n'existent qu'après exécution. Les imposer rendrait `DRAFT` impossible. |
| `tour_stops.grouping_key`, `generation_mode` | nullable | Absents des obligatoires du §11, et leurs valeurs ne doivent pas être inventées. Un stop saisi à la main n'a pas de clé de regroupement. |
| `tour_stops.planned_*`, `actual_*` | nullable | Même raison que pour `Tour`. |
| `tour_periods.tour_stop_id` | nullable | `TourStop "0..1" -- "0..*" TourPeriod`. Une période de conduite entre deux stops n'appartient à aucun stop. |
| `tour_period_assignments.package_id` | nullable | `Package "0..1"`, et le §17 le dit explicitement. |
| Compteurs, durées, distances | **non nullable, défaut 0** | Précédent direct : `orders.weight`, `orders.volume`, `orders.package_count` sont `default(0)`. Une somme sur des `NULL` produirait `NULL` ; le défaut à 0 rend le recalcul du §20 total. Colonnes `UNSIGNED` : la négativité est impossible au stockage, pas seulement à la validation. |
| Tout le reste | non nullable | Listé comme obligatoire par les §8, §11, §13, §15, §17. |

`period_type` et `status` de `TourPeriod`, et `status` de `TourStopService`, sont
**obligatoires sans valeur par défaut** : c'est la convention des Phases 2 et 3
(`providers.status`, `order_services.status`). Poser un défaut reviendrait à
inventer une valeur que le diagramme n'énumère pas.

## 8. Décisions de suppression

La distinction composition / association du §4 décide seule :

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `tours.organization_id` | `RESTRICT` | Supprimer une organisation ne doit pas emporter ses tournées. |
| `tours.agency_id` | `RESTRICT` | Idem. |
| `tours.depot_id`, `provider_id`, `driver_id`, `vehicle_id` | `SET NULL` | Colonnes nullables ; supprimer un véhicule ne doit pas détruire la tournée qui l'a utilisé. Précédent direct : `orders.depot_id` → `nullOnDelete`. |
| `tour_stops.tour_id` | `CASCADE` | Composition `Tour *-- TourStop`. |
| `tour_stops.address_id` | `RESTRICT` | Association : une adresse encore planifiée ne disparaît pas. |
| `tour_stop_services.tour_stop_id` | `CASCADE` | Composition `TourStop *-- TourStopService`. |
| `tour_stop_services.order_service_id` | `RESTRICT` | Association : planifier un service ne doit pas permettre de le perdre. |
| `tour_periods.tour_id` | `CASCADE` | Composition `Tour *-- TourPeriod`. |
| `tour_periods.tour_stop_id` | `SET NULL` | Association `0..1`. Voir l'ordre de suppression ci-dessous. |
| `tour_period_assignments.tour_period_id` | `CASCADE` | Composition `TourPeriod *-- TourPeriodAssignment`. |
| `tour_period_assignments.tour_stop_service_id` | `RESTRICT` | Association. Le §14 exige de refuser la suppression d'un service utilisé par une affectation. |
| `tour_period_assignments.package_id` | `RESTRICT` | Un colis planifié ne se supprime pas sans traiter l'affectation. |

### Ordre de suppression d'une tournée

Les cascades seules ne suffisent pas. En supprimant un `Tour`, MySQL supprimerait
`tour_stops` (donc `tour_stop_services`) et `tour_periods` (donc
`tour_period_assignments`) dans un ordre non garanti — et
`tour_period_assignments.tour_stop_service_id` est en `RESTRICT`. Selon l'ordre
retenu par le moteur, la suppression échouerait sur une erreur SQL brute.

`DeleteTourAction` supprime donc explicitement, dans une transaction et dans cet
ordre :

```text
1. tour_period_assignments
2. tour_periods
3. tour_stop_services
4. tour_stops
5. tour
```

Les cascades restent déclarées : elles sont le filet de sécurité si une
suppression échappe à l'Action, jamais le mécanisme nominal.

### Refus applicatifs, avant que SQL n'intervienne

| Ressource | Refus | Code |
|-----------|-------|------|
| `TourStop` | possède encore des `TourPeriod` | 409 |
| `TourStopService` | référencé par un `TourPeriodAssignment` | 409 |
| `TourStopService` | dernier service actif de son stop (cardinalité `1..*`) | 409 |
| `TourPeriod` | possède encore des `TourPeriodAssignment` | 409 |

Le §31 demande aussi de refuser la suppression d'un `Tour` référencé par
`TrackingEvent`, `ProofOfDelivery`, `Claim` ou une facture. **Ces tables
n'existent pas** — elles relèvent des phases suivantes. Aucun contrôle n'est
écrit pour des tables absentes ; le point est porté aux risques du rapport final,
et devra être traité avant que le suivi ne soit exploité.

## 9. Cardinalité `1..*` de TourStopService

`TourStop "1" *-- "1..*" TourStopService` : un stop sans service n'existe pas.

**Stratégie retenue : création atomique.** `POST /tours/{tour}/stops` exige un
tableau `services` d'au moins un élément, et crée le stop et ses services dans
la même transaction. Un stop vide n'est jamais écrit en base, même
transitoirement.

L'option « création temporaire interdite tant qu'aucun service n'est fourni »
a été écartée : elle suppose un stop existant sans service, exactement ce que la
cardinalité interdit.

Conséquence symétrique côté suppression : `DELETE` du dernier `TourStopService`
**actif** d'un stop est refusé en 409. Pour retirer le dernier service, il faut
supprimer le stop.

## 10. Affectation active — `isActiveAssignment`

Le champ distingue une affectation courante d'une affectation historique. Le §13
interdit de le remplacer par une suppression physique.

- Une affectation est créée avec `isActiveAssignment = true` par défaut ;
- `DeactivateTourStopServiceAction` la passe à `false` : la ligne est conservée,
  l'historique reste lisible ;
- Une nouvelle affectation du même `OrderService` au même stop est possible
  après désactivation de la précédente.

**Contrainte d'unicité retenue** : `(tour_stop_id, sequence_within_stop)`, telle
que le §30 la demande. Aucune contrainte n'est posée sur
`(tour_stop_id, order_service_id, is_active_assignment)` : le §13 dit
« empêcher plusieurs affectations actives incompatibles **si cette règle est
démontrée par le modèle existant** ». Elle ne l'est pas — le diagramme autorise
`OrderService "1" -- "0..*" TourStopService` sans restriction. Inventer une
contrainte partielle exigerait de surcroît un index filtré, indisponible sous
MySQL 8.

## 11. Calculs agrégés du Tour

Le §20 interdit d'inventer les formules. Recherche menée : ni le diagramme, ni
ses notes, ni les Phases 1 à 3 ne définissent de règle de calcul pour les sept
totaux de `Tour`.

`RecalculateTourTotalsAction` ne recalcule donc que ce qui est **dérivable des
données présentes**, et laisse le reste intact :

| Champ | Recalculé | Source |
|-------|-----------|--------|
| `totalWeight` | oui | somme de `order_services.weight` des `TourStopService` **actifs** de la tournée |
| `totalVolume` | oui | somme de `order_services.volume`, mêmes lignes |
| `totalPackages` | oui | somme de `order_services.package_count`, mêmes lignes |
| `totalCustomers` | oui | nombre de `orders.customer_id` distincts, mêmes lignes |
| `distanceMeters` | oui | somme de `tour_periods.distance_meters` |
| `drivingTimeMinutes` | **non** | aucune source : distinguer conduite et service exigerait de connaître les valeurs de `periodType`, que le diagramme n'énumère pas |
| `workingTimeMinutes` | **non** | même raison |

Les deux champs non recalculés restent saisis par l'appelant. Inventer
`workingTime = break + service + waiting` serait une formule métier absente du
modèle.

Le recalcul est **explicite** : appelé par les Actions qui modifient la
composition d'une tournée (services, périodes), jamais par un observateur caché.
Il est testable seul.

## 12. Numéro de tournée

Le §9 interdit d'imposer arbitrairement `TOUR-2026-000001`.

Le projet possède un mécanisme de numérotation — `GenerateOrderNumber` +
`order_number_sequences`, avec verrou `lockForUpdate` — mais il est **propre aux
commandes** : préfixe `ORD`, table du module Orders, absente des diagrammes.

**Décision : `tourNumber` est fourni par l'appelant, obligatoire, unique par
organisation** — `unique(organization_id, tour_number)`, exactement la contrainte
de `orders.order_number`. Aucun format n'est imposé, aucune génération
automatique n'est ajoutée.

Le §9 le prévoit : « Si aucun format n'est défini : permettre une valeur saisie
et validée ; documenter l'absence de règle de génération ; ne pas inventer un
format métier. » Le jour où une convention de numérotation des tournées sera
définie, le mécanisme existant l'accueillera via son paramètre `scope`, sans
changer de table.

## 13. Contraintes de cohérence

Vérifiées à l'écriture, dans les Actions, et pas seulement en validation :

| Contrainte | Où |
|------------|-----|
| `Agency` dans l'organisation active | `TourScopeGuard::agency()` |
| `Depot` rattaché à l'`Agency` de la tournée | `TourScopeGuard::depot()` |
| `Provider` dans l'organisation active | `TourScopeGuard::provider()` |
| `Driver` rattaché au `Provider` quand les deux sont fournis | `TourScopeGuard::driver()` |
| `Vehicle` rattaché au `Provider` quand les deux sont fournis | `TourScopeGuard::vehicle()` |
| `Address` du stop accessible | validation `exists` — table partagée, cf. Phase 3 |
| `OrderService` dans l'organisation de la tournée | `TourScopeGuard::orderService()` |
| `TourStop` appartient au `Tour` de la route | contrôleur, 404 |
| `TourPeriod.tour_stop_id` appartient au même `Tour` | `CreateTourPeriodAction` |
| `TourPeriodAssignment` : période et service du même `Tour` | `CreateTourPeriodAssignmentAction` |
| `Package` lié à la commande de l'`OrderService` visé | `TourScopeGuard::package()` |
| `planned_start_at <= planned_end_at` | Form Request |
| `actual_start_at <= actual_end_at` | Form Request |
| `planned_arrival_at <= planned_departure_at` | Form Request |
| Durées, distances, séquences non négatives | Form Request + `UNSIGNED` |

## 14. Permissions prévues

23 permissions, idempotentes, ajoutées au `PermissionSeeder` existant :

```text
tours.view / create / update / delete
tour_stops.view / create / update / delete / reorder
tour_stop_services.view / create / update / delete / reorder
tour_periods.view / create / update / delete / reorder
tour_period_assignments.view / create / update / delete
```

Cinq Policies étendant `BaseOrganizationPolicy`. Chacune vérifie la permission
**et** l'appartenance de la ressource à l'organisation active.

## 15. Endpoints prévus

26 routes, toutes sous `/api/v1`, protégées par `auth:sanctum` + `organization` :

```text
GET|POST           /tours
GET|PATCH|DELETE   /tours/{tour}
GET|POST           /tours/{tour}/stops
GET|PATCH|DELETE   /tours/{tour}/stops/{tourStop}
POST               /tours/{tour}/stops/reorder
GET|POST           /tours/{tour}/stops/{tourStop}/services
GET|PATCH|DELETE   /tours/{tour}/stops/{tourStop}/services/{tourStopService}
POST               /tours/{tour}/stops/{tourStop}/services/reorder
GET|POST           /tours/{tour}/periods
GET|PATCH|DELETE   /tours/{tour}/periods/{tourPeriod}
POST               /tours/{tour}/periods/reorder
GET|POST           /tours/{tour}/periods/{tourPeriod}/assignments
GET|PATCH|DELETE   /tours/{tour}/periods/{tourPeriod}/assignments/{assignment}
```

Le changement de statut passe par `PATCH` sur la ressource, convention des
Phases 2 et 3. Aucun endpoint d'optimisation, aucune action en masse.

## 16. Tests prévus

Un fichier par entité, plus un fichier de permissions, conformément aux §32 à
§36 : création minimale et complète, chaque contrainte de périmètre, séquences
dupliquées, réorganisation, dates incohérentes, valeurs négatives, IDOR,
permissions manquantes, audit, refus de suppression.

## 17. Ordre des migrations

```text
1. tours
2. tour_stops
3. tour_stop_services
4. tour_periods
5. tour_period_assignments
```

Ordre du §29 conservé : chaque table ne référence que des tables déjà créées.
`tour_periods` référence `tour_stops`, d'où sa position après elle.

**Aucun timestamp, aucun soft delete.** Les cinq classes n'en définissent aucun,
et le §2 range les « timestamps non présents » et `softDeletes` parmi les ajouts
interdits. Même convention qu'en Phase 3. L'historique reste porté par
`audit_logs`, qui horodate chaque écriture avec son auteur.

C'est un écart avec la Phase 2, où `orders`, `order_services` et `packages`
portent des timestamps — mais ces classes les déclarent au diagramme. La règle
est la même dans les deux cas : suivre le diagramme.

## 18. Éléments explicitement exclus

Classes non créées, conformément au §2 :

```text
TourResource        TourDriver          TourVehicle         TourAssistant
TourAvailability    TourConflict        TourRoute           RouteOptimization
PlanningBoard       PlanningSlot        PlanningAssignment  DriverAvailability
VehicleAvailability MapRoute            RouteGeometry       BreakRule
WorkingTimeRule     Skill               DriverSkill         VehicleSkill
```

Attributs non ajoutés :

```text
assistant_driver_id   locked_at        locked_by         version
route_geometry        current_latitude current_longitude estimated_arrival_at
optimization_score    conflict_status  capacity_status   metadata
settings              softDeletes      timestamps
```

Également exclus : tout champ de disponibilité, de géolocalisation ou de
navigation ; tout moteur d'optimisation de tournée ; toute table de liaison
directe entre `Tour` et `Order` — le diagramme planifie les `OrderService` via
`TourStopService`, et `Order` reste atteignable par
`tourStopService.orderService.order`.

`VehicleType` et les capacités du véhicule ne sont pas dupliqués dans `Tour` :
ils restent lisibles via `tour.vehicle.vehicleType`, comme le §8 l'exige.
