# Rapport final — Phase 4 : planification des services et tournées

Répond au §42 du prompt « Tricolis V2 — Backend Phase 4 ».

---

## 1. Branche Git

```text
feature/backend-phase-4-tours-planning
```

Créée depuis `feature/backend-phase-3-providers-resources` (commit `6920480`),
et non depuis `main`.

**Écart assumé au §0** : `main` est resté au commit initial `c97dc0d`, un
squelette Laravel vide. `tours` référence `organizations`, `agencies`, `depots`,
`providers`, `vehicles` et `drivers` — brancher depuis `main` rendrait les cinq
migrations inexécutables. Même écart qu'en Phase 3, pour la même raison.

Aucune fusion, aucun rebase, aucune suppression de branche, aucun push
automatique.

## 2. Diagrammes utilisés

Le §1 désigne deux `.puml` qui **n'existent pas** et n'ont jamais été produits.
Les diagrammes disponibles font foi, comme tranché par le porteur du projet le
1er août 2026 :

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

**Aucun conflit cette fois.** Les cinq classes et les deux enums décrits par le
prompt correspondent **exactement** au diagramme interne :

| Élément | Lignes du diagramme | Écart |
|---------|---------------------|-------|
| `TourStatus` | 52-59 | aucun |
| `TourStopStatus` | 61-68 | aucun |
| `Tour` | 486-510 | aucun |
| `TourStop` | 512-526 | aucun |
| `TourStopService` | 528-535 | aucun |
| `TourPeriod` | 537-553 | aucun |
| `TourPeriodAssignment` | 555-560 | aucun |
| Relations | 897-914 | aucun |

Les colonnes créées le confirment, attribut pour attribut :

```text
tours                    23 colonnes  (id + 22 attributs)
tour_stops               13 colonnes
tour_stop_services        6 colonnes
tour_periods             15 colonnes
tour_period_assignments   4 colonnes
```

## 3. Classes implémentées

```text
Tour
TourStop
TourStopService
TourPeriod
TourPeriodAssignment
```

## 4. Enums implémentés

```text
TourStatus     : DRAFT, PLANNED, CONFIRMED, IN_PROGRESS, COMPLETED, CANCELLED
TourStopStatus : PENDING, ARRIVED, IN_PROGRESS, COMPLETED, SKIPPED, CANCELLED
```

Enums PHP natifs, valeurs stockées en `snake_case`, castées par Eloquent, sur
des colonnes `VARCHAR(32)` — convention d'`OrderStatus` depuis la Phase 2.

`TourStopService.status` et `TourPeriod.status` **ne sont pas** des enums : le
diagramme les déclare `string` sans énumérer de valeurs. En faire des enums
reviendrait à les inventer.

## 5. Attributs implémentés

| Classe | Attributs |
|--------|-----------|
| `Tour` | `id`, `organizationId`, `tourNumber`, `tourDate`, `agencyId`, `depotId`, `providerId`, `vehicleId`, `driverId`, `tourType`, `instructions`, `plannedStartAt`, `plannedEndAt`, `actualStartAt`, `actualEndAt`, `totalWeight`, `totalVolume`, `totalPackages`, `totalCustomers`, `drivingTimeMinutes`, `workingTimeMinutes`, `distanceMeters`, `status` |
| `TourStop` | `id`, `tourId`, `addressId`, `sequence`, `groupingKey`, `generationMode`, `plannedArrivalAt`, `plannedDepartureAt`, `actualArrivalAt`, `actualDepartureAt`, `waitingMinutes`, `serviceMinutes`, `status` |
| `TourStopService` | `id`, `tourStopId`, `orderServiceId`, `sequenceWithinStop`, `isActiveAssignment`, `status` |
| `TourPeriod` | `id`, `tourId`, `tourStopId`, `periodType`, `sequence`, `plannedStartAt`, `plannedEndAt`, `actualStartAt`, `actualEndAt`, `breakMinutes`, `serviceMinutes`, `waitingMinutes`, `distanceMeters`, `internalRemark`, `status` |
| `TourPeriodAssignment` | `id`, `tourPeriodId`, `tourStopServiceId`, `packageId` |

Tableau de correspondance complet — type, colonne MySQL, nullabilité, index,
relation — au §6 de [`phase-4-analysis.md`](phase-4-analysis.md).

## 6. Relations implémentées

```text
Tour                 belongsTo Organization, Agency, Depot, Provider, Driver, Vehicle
                     hasMany TourStop, TourPeriod
TourStop             belongsTo Tour, Address ; hasMany TourStopService, TourPeriod
TourStopService      belongsTo TourStop, OrderService ; hasMany TourPeriodAssignment
TourPeriod           belongsTo Tour, TourStop ; hasMany TourPeriodAssignment
TourPeriodAssignment belongsTo TourPeriod, TourStopService, Package
```

Le losange plein `*--` du diagramme marque une composition : c'est lui qui dicte
les cascades. Le trait simple `--` marque une association : rien n'est détruit.

## 7. Migrations créées

| # | Migration |
|---|-----------|
| 1 | `2026_08_02_100001_create_tours_table` |
| 2 | `2026_08_02_100002_create_tour_stops_table` |
| 3 | `2026_08_02_100003_create_tour_stop_services_table` |
| 4 | `2026_08_02_100004_create_tour_periods_table` |
| 5 | `2026_08_02_100005_create_tour_period_assignments_table` |

**Aucune migration existante modifiée.** Ni timestamps, ni soft deletes : les
cinq classes n'en définissent aucun.

## 8. Modèles, Actions, DTOs

**Modèles (5)** — `App\Modules\Tours\Models\{Tour, TourStop, TourStopService,
TourPeriod, TourPeriodAssignment}`.

**Actions (18)** :

```text
CreateTourAction               UpdateTourAction               DeleteTourAction
CreateTourStopAction           UpdateTourStopAction           DeleteTourStopAction
ReorderTourStopsAction
AssignOrderServiceToTourStopAction  UpdateTourStopServiceAction
DeleteTourStopServiceAction    ReorderTourStopServicesAction
CreateTourPeriodAction         UpdateTourPeriodAction         DeleteTourPeriodAction
ReorderTourPeriodsAction
CreateTourPeriodAssignmentAction    UpdateTourPeriodAssignmentAction
DeleteTourPeriodAssignmentAction
```

Plus `RecalculateTourTotals`, appelée explicitement par les Actions qui
modifient la composition d'une tournée.

**Services (4)** — `TourScopeGuard` (périmètre des six références),
`TourReferenceResolver` (contrôles croisés création/modification),
`SequenceReorderer` (réattribution en deux passes), `AssignmentConsistency`
(cohérence des affectations).

**DTOs (10)** — `Create`/`Update` × `Tour`, `TourStop`, `TourStopService`,
`TourPeriod`, `TourPeriodAssignment`.

## 9. Requests, Resources, Policies

**Form Requests (12)** — `Store`/`Update` des cinq entités, `ListTourRequest`,
`ListTourPeriodRequest`, et un `ReorderRequest` **partagé** par les trois routes
de réorganisation : elles attendent exactement la même entrée, une Request par
route n'aurait ajouté que de la duplication.

**Resources (9)** — `TourListResource`, `TourDetailResource`,
`TourCompactResource`, `TourStopResource`, `TourStopDetailResource`,
`TourStopServiceResource`, `TourPeriodResource`, `TourPeriodDetailResource`,
`TourPeriodAssignmentResource`. Toutes utilisent `whenLoaded()` / `whenCounted()`.

**Policies (5)** — `TourPolicy`, `TourStopPolicy`, `TourStopServicePolicy`,
`TourPeriodPolicy`, `TourPeriodAssignmentPolicy`, toutes dérivées de
`BaseOrganizationPolicy`.

**Query Objects (2)** — `TourListQuery`, `TourPeriodListQuery`.

## 10. Permissions

23 permissions idempotentes ajoutées au `PermissionSeeder` existant :

```text
tours.view / create / update / delete
tour_stops.view / create / update / delete / reorder
tour_stop_services.view / create / update / delete / reorder
tour_periods.view / create / update / delete / reorder
tour_period_assignments.view / create / update / delete
```

Total du projet : **114 permissions**.

## 11. Routes

**28 routes**, toutes sous `/api/v1`, protégées par `auth:sanctum` +
`organization`. Aucun doublon — vérifié sur les 214 routes du projet.

```text
GET|POST           /tours
GET|PATCH|DELETE   /tours/{tour}
GET|POST           /tours/{tour}/stops
POST               /tours/{tour}/stops/reorder
GET|PATCH|DELETE   /tours/{tour}/stops/{tourStop}
GET|POST           /tours/{tour}/stops/{tourStop}/services
POST               /tours/{tour}/stops/{tourStop}/services/reorder
GET|PATCH|DELETE   /tours/{tour}/stops/{tourStop}/services/{tourStopService}
GET|POST           /tours/{tour}/periods
POST               /tours/{tour}/periods/reorder
GET|PATCH|DELETE   /tours/{tour}/periods/{tourPeriod}
GET|POST           /tours/{tour}/periods/{tourPeriod}/assignments
GET|PATCH|DELETE   /tours/{tour}/periods/{tourPeriod}/assignments/{assignment}
```

Les routes `reorder` précèdent les `apiResource` pour qu'aucune ne soit captée
comme un identifiant.

## 12. Tests

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `Tours/TourTest` | 22 | Création minimale, dépôt facultatif, fournisseur/chauffeur/véhicule facultatifs, totaux à zéro, agence hors organisation, dépôt non rattaché à l'agence, fournisseur hors organisation, chauffeur et véhicule non rattachés au fournisseur, dates incohérentes, durée négative, statut hors enum, numéro dupliqué, même numéro dans une autre organisation, CRUD, IDOR, isolation de liste, recherche, filtres, pagination, tri interdit, audit création/modification/statut/suppression, audit limité aux champs modifiés |
| `Tours/TourStopTest` | 16 | Création avec service obligatoire, refus sans service, atomicité vérifiée (aucun arrêt orphelin), séquence dupliquée, dates incohérentes, minutes négatives, arrêt d'une autre tournée, tournée d'une autre organisation, liste par séquence, mise à jour, suppression avec ses services, refus si périodes rattachées, réorganisation, liste partielle refusée, doublon refusé, audit |
| `Tours/TourStopServiceTest` | 16 | Affectation valide, service hors organisation, séquence dupliquée, même service deux fois autorisé, désactivation sans suppression, refus de désactiver le dernier actif, refus de supprimer si affecté, refus de supprimer le dernier actif, suppression permise sinon, IDOR arrêt et organisation, liste actifs et historiques, réorganisation, audit, recalcul des totaux, exclusion des services désactivés |
| `Tours/TourPeriodTest` | 17 | Création liée à la tournée, arrêt facultatif, arrêt d'une autre tournée refusé, séquence dupliquée, dates incohérentes, durées et distance négatives, filtres, IDOR, tri interdit, mise à jour, déplacement hors tournée refusé, suppression, refus avec affectations, réorganisation, somme des distances, non-recalcul de `drivingTime`/`workingTime`, audit |
| `Tours/TourPeriodAssignmentTest` | 15 | Création sans colis, avec colis de la même commande, service d'une autre tournée refusé, colis d'une autre commande refusé, colis d'une autre organisation refusé, doublon exact refusé, même service avec colis différents autorisé, IDOR période et organisation, liste, mise à jour, déplacement hors tournée refusé, suppression, audit, suppression complète de l'agrégat dans le bon ordre |
| `Tours/TourPermissionTest` | 7 | Lecture, création, modification, suppression et réorganisation refusées sans permission ; accès accordé après attribution du rôle ; en-tête d'organisation requis ; accès non authentifié refusé |

**93 tests ajoutés.**

## 13. Résultats

```text
composer validate                                ./composer.json is valid
php artisan optimize:clear                       OK
php artisan migrate:fresh --seed --env=testing   OK (7 seeders)
php artisan test                                 335 passed (1017 assertions)
./vendor/bin/pint --test                         PASS
php artisan route:list                           214 routes, aucun doublon
OpenAPI                                          191 opérations documentées
```

242 tests des Phases 1 à 3, 93 de la Phase 4. **Aucune régression** : aucun test
existant n'a été modifié, désactivé ni marqué `skip`.

Vérifications structurelles :

```text
fichiers > 200 lignes      aucun
TODO / FIXME               aucun
classes vides              aucune
constructions PostgreSQL   aucune
tables non prévues         aucune
colonnes non prévues       aucune
enums supplémentaires      aucun
```

## 14. Décisions de nullabilité

| Colonne | Choix | Raison |
|---------|-------|--------|
| `tours.depot_id`, `provider_id`, `vehicle_id`, `driver_id` | nullable | Cardinalité `0..1` ; une tournée se planifie avant d'être affectée |
| `tours.tour_type`, `instructions` | nullable | Absents des obligatoires du §8 ; précédent `orders.order_type` |
| `tours.planned_*`, `actual_*` | nullable | Un brouillon n'a pas d'horaires |
| `tour_stops.grouping_key`, `generation_mode` | nullable | Valeurs non énumérées ; un arrêt saisi à la main n'en a pas |
| `tour_periods.tour_stop_id` | nullable | `TourStop "0..1"` : une période de conduite n'appartient à aucun arrêt |
| `tour_period_assignments.package_id` | nullable | `Package "0..1"`, dit au §17 |
| Compteurs, durées, distances | non nullable, défaut 0, `UNSIGNED` | Précédent `orders.weight` ; une somme sur `NULL` produit `NULL` |
| Tout le reste | non nullable | Listé obligatoire aux §8, §11, §13, §15, §17 |

## 15. Décisions de suppression

| Clé étrangère | Stratégie |
|---------------|-----------|
| `tours.organization_id`, `agency_id` | `RESTRICT` |
| `tours.depot_id`, `provider_id`, `vehicle_id`, `driver_id` | `SET NULL` |
| `tour_stops.tour_id` | `CASCADE` (composition) |
| `tour_stops.address_id` | `RESTRICT` |
| `tour_stop_services.tour_stop_id` | `CASCADE` (composition) |
| `tour_stop_services.order_service_id` | `RESTRICT` |
| `tour_periods.tour_id` | `CASCADE` (composition) |
| `tour_periods.tour_stop_id` | `SET NULL` |
| `tour_period_assignments.tour_period_id` | `CASCADE` (composition) |
| `tour_period_assignments.tour_stop_service_id`, `package_id` | `RESTRICT` |

Cinq refus applicatifs en 409 précèdent SQL. Détail complet dans
[`phase-4-database-decisions.md`](phase-4-database-decisions.md) §3 et §4.

## 16. Ambiguïtés levées

| # | Ambiguïté | Traitement |
|---|-----------|------------|
| A | Les deux `.puml` du §1 n'existent pas | Les `.txt` disponibles font foi, décision du porteur — aucun conflit sur cette phase |
| B | Le §9 interdit d'imposer un format de `tourNumber`, mais le projet a un mécanisme (`GenerateOrderNumber`) | Ce mécanisme est propre aux commandes (préfixe `ORD`, table du module Orders). `tourNumber` est fourni par l'appelant, unique par organisation — exactement ce que prévoit le §9 en l'absence de format défini |
| C | Le §21 demande de réutiliser un moteur de statut existant | `OrderStatus` a des transitions, mais **propres à cet enum** : il n'y a pas de moteur partagé. Seule l'appartenance à l'enum est validée, aucune transition n'est inventée |
| D | Le §20 interdit d'inventer les formules des sept totaux | Cinq sont dérivables et recalculés ; `drivingTimeMinutes` et `workingTimeMinutes` ne le sont pas et restent saisis. Un test vérifie qu'ils ne sont jamais écrasés |
| E | Cardinalité `1..*` de `TourStopService` | Création atomique de l'arrêt avec ses services ; refus de retirer ou désactiver le dernier service actif |
| F | Le §13 évoque une contrainte sur les affectations actives « si démontrée par le modèle » | Elle ne l'est pas : `OrderService "1" -- "0..*" TourStopService` est sans restriction. Aucune contrainte inventée — et un index filtré serait de toute façon indisponible sous MySQL 8 |
| G | Cascade et `RESTRICT` se contredisent à la suppression d'une tournée | Ordre imposé par `DeleteTourAction` dans une transaction ; les cascades restent le filet de sécurité |
| H | Le §31 demande de détecter `TrackingEvent`, `ProofOfDelivery`, `Claim`, facturation | Ces tables n'existent pas : aucun contrôle écrit pour des tables absentes, point porté aux risques |
| I | Le §5 propose `Http/` par module et un module `Planning/` séparé | Convention existante conservée (`app/Http`), et un seul module `Tours` : les cinq classes appartiennent au même paquet du diagramme et au même agrégat |

## 17. Fichiers créés

**57 fichiers.**

Migrations (5), modèles (5), enums (2), DTOs (10), Actions (19), exception (1),
services (4), Query Objects (2), Form Requests (12), Resources (9), Policies (5),
Controllers (5), concern `ResolvesTourScope`, factories (5), tests (6),
documentation (4).

## 18. Fichiers modifiés

**4 fichiers**, tous par ajout :

```text
routes/api.php                       + 6 imports, + 20 lignes de routes
database/seeders/PermissionSeeder    + 23 permissions
app/Providers/AuthServiceProvider    + 5 modèles, + 5 policies
app/Shared/Database/MorphMap         + 5 alias métier
```

Aucune ligne des Phases 1 à 3 n'a été supprimée ni réécrite.

## 19. Éléments exclus

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
navigation ; tout moteur d'optimisation ; toute carte ; toute table de liaison
directe entre `Tour` et `Order` ; `sequence`, `status`, `quantity` et `duration`
sur `TourPeriodAssignment`.

`VehicleType` et les capacités du véhicule ne sont pas dupliqués dans `Tour` :
ils restent lisibles via `tour.vehicle.vehicleType`, comme le §8 l'exige.

## 20. Risques

1. **Aucun contrôle de suppression contre le suivi.** Le §31 demande de refuser
   la suppression d'une tournée référencée par `TrackingEvent`,
   `ProofOfDelivery`, `Claim` ou une facture. Ces tables n'existent pas encore.
   **Le contrôle devra être ajouté avec ces modules, avant qu'ils ne soient
   exploités** — sinon une tournée exécutée restera supprimable.
2. **Aucune règle de transition de statut.** Une tournée peut passer de
   `COMPLETED` à `DRAFT`. Le diagramme n'en définit aucune et le §21 interdit
   d'en inventer. À arrêter côté métier, puis à poser sur le modèle
   d'`OrderStatus`.
3. **`drivingTimeMinutes` et `workingTimeMinutes` ne sont pas dérivés.** Ils
   dépendent de la sémantique de `periodType`, non définie. Tant qu'elle ne
   l'est pas, ces deux champs restent déclaratifs.
4. **`tourNumber` sans génération automatique.** L'unicité est garantie, mais le
   format dépend entièrement de l'appelant. Deux agences peuvent adopter des
   conventions divergentes.
5. **`periodType`, `generationMode`, `groupingKey` et les `status` libres sans
   valeurs normatives.** Rien n'empêche de saisir `driving` et `DRIVING`. Une
   liste officielle permettra de créer les enums correspondants.
6. **`SET NULL` sur `provider_id`, `driver_id`, `vehicle_id`.** Supprimer un
   véhicule délie les tournées passées sans laisser de trace de son identité.
   La Phase 3 refuse déjà de supprimer un fournisseur portant des ressources,
   ce qui limite le cas — mais ne le supprime pas.

## 21. Prochaine phase

**Non commencée, conformément au §43.** La Phase 5 attend une validation
explicite.

Éléments de la Phase 4 qu'elle devra reprendre : les contrôles de suppression du
§31 dès que `TrackingEvent`, `ProofOfDelivery` et `Claim` existeront, et le
raccordement des statuts de commande (`PARTIALLY_PLANNED`, `PLANNED`) au
contenu réel des tournées — `OrderStatus` les prévoit déjà mais rien ne les pose
aujourd'hui.
