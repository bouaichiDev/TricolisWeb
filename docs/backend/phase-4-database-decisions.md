# Décisions base de données — Phase 4

Ce document répond au §29 du prompt Phase 4. Il complète
[`phase-1`](phase-1-database-decisions.md), [`phase-2`](phase-2-database-decisions.md)
et [`phase-3`](phase-3-database-decisions.md), dont les principes restent
valables : ULID, isolation organisationnelle, morph map à valeurs métier,
statuts en `VARCHAR`.

Tout est compatible **MySQL 8**. Aucune fonctionnalité PostgreSQL n'est
employée : ni `JSONB`, ni `ILIKE`, ni PostGIS, ni index partiel, ni enum SQL.

---

## 1. Ordre des migrations

| # | Migration | Dépend de |
|---|-----------|-----------|
| 1 | `2026_08_02_100001_create_tours_table` | `organizations`, `agencies`, `depots`, `providers`, `vehicles`, `drivers` |
| 2 | `2026_08_02_100002_create_tour_stops_table` | `tours`, `addresses` |
| 3 | `2026_08_02_100003_create_tour_stop_services_table` | `tour_stops`, `order_services` |
| 4 | `2026_08_02_100004_create_tour_periods_table` | `tours`, `tour_stops` |
| 5 | `2026_08_02_100005_create_tour_period_assignments_table` | `tour_periods`, `tour_stop_services`, `packages` |

Ordre du §29 conservé. `tour_periods` référence `tour_stops`, d'où sa position
après elle — c'est la seule contrainte qui fixe l'ordre.

## 2. Nullabilité

Deux sources, et rien d'autre : les **cardinalités du diagramme** pour les clés
étrangères, les sections **« Contraintes »** du prompt pour les scalaires.

| Colonne | Nullable | Raison |
|---------|----------|--------|
| `tours.organization_id` | non | `Organization "1" -- "0..*" Tour` |
| `tours.agency_id` | non | `Agency "1" -- "0..*" Tour` |
| `tours.depot_id` | **oui** | `Depot "0..1"` |
| `tours.provider_id`, `vehicle_id`, `driver_id` | **oui** | `"0..1"` chacun. Une tournée se planifie avant d'être affectée : imposer un chauffeur interdirait le brouillon. |
| `tours.tour_type`, `instructions` | **oui** | Absents des obligatoires du §8. Précédent direct : `orders.order_type` et `orders.internal_remark`. |
| `tours.planned_*`, `actual_*` | **oui** | Une tournée en `DRAFT` n'a pas d'horaires ; les horaires réels n'existent qu'après exécution. |
| `tour_stops.tour_id`, `address_id`, `sequence`, `status` | non | §11 |
| `tour_stops.grouping_key`, `generation_mode` | **oui** | Absents des obligatoires, et leurs valeurs ne doivent pas être inventées. Un arrêt saisi à la main n'a pas de clé de regroupement. |
| `tour_stop_services.*` | non | Les cinq attributs sont obligatoires au §13. |
| `tour_periods.tour_stop_id` | **oui** | `TourStop "0..1" -- "0..*" TourPeriod` : une période de conduite entre deux arrêts n'appartient à aucun arrêt. |
| `tour_periods.internal_remark` | **oui** | Texte libre. |
| `tour_period_assignments.package_id` | **oui** | `Package "0..1"`, dit explicitement au §17. |

### Compteurs, durées et distances

**Non nullables, avec défaut 0, et `UNSIGNED`.**

```php
$table->decimal('total_weight', 12, 3)->default(0);
$table->unsignedInteger('total_packages')->default(0);
$table->unsignedBigInteger('distance_meters')->default(0);
```

Trois raisons :

1. précédent direct — `orders.weight`, `orders.volume`, `orders.package_count`
   sont tous `default(0)` depuis la Phase 2 ;
2. une somme sur des `NULL` produit `NULL` : le recalcul du §20 serait à refaire
   à chaque champ ;
3. `UNSIGNED` rend la négativité impossible **au stockage**, pas seulement à la
   validation — le §8 exige des valeurs non négatives.

### Statuts et types sans valeur par défaut

`tour_stop_services.status`, `tour_periods.period_type` et `tour_periods.status`
sont obligatoires **sans défaut**. Le diagramme ne les énumère pas : poser
`'planned'` par défaut reviendrait à inventer une valeur normative. C'est la
convention des Phases 2 et 3 (`providers.status`, `order_services.status`).

`tours.status` et `tour_stops.status` sont, eux, adossés à des enums PHP dont le
diagramme fixe les valeurs — mais restent des `VARCHAR(32)` en base, comme
`OrderStatus` depuis la Phase 2.

## 3. Stratégies de suppression

La distinction composition / association du diagramme décide seule.

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `tours.organization_id` | `RESTRICT` | Supprimer une organisation n'emporte pas ses tournées. |
| `tours.agency_id` | `RESTRICT` | Idem. |
| `tours.depot_id`, `provider_id`, `vehicle_id`, `driver_id` | `SET NULL` | Colonnes nullables ; supprimer un véhicule ne doit pas détruire la tournée qui l'a utilisé. Précédent : `orders.depot_id`. |
| `tour_stops.tour_id` | `CASCADE` | Composition `Tour *-- TourStop`. |
| `tour_stops.address_id` | `RESTRICT` | Association : une adresse encore planifiée ne disparaît pas. |
| `tour_stop_services.tour_stop_id` | `CASCADE` | Composition `TourStop *-- TourStopService`. |
| `tour_stop_services.order_service_id` | `RESTRICT` | Association : planifier un service ne doit pas permettre de le perdre. |
| `tour_periods.tour_id` | `CASCADE` | Composition `Tour *-- TourPeriod`. |
| `tour_periods.tour_stop_id` | `SET NULL` | Voir §4. |
| `tour_period_assignments.tour_period_id` | `CASCADE` | Composition `TourPeriod *-- TourPeriodAssignment`. |
| `tour_period_assignments.tour_stop_service_id` | `RESTRICT` | Le §14 exige de refuser la suppression d'un service encore affecté. |
| `tour_period_assignments.package_id` | `RESTRICT` | Un colis planifié ne se supprime pas sans traiter l'affectation. |

## 4. Ordre de suppression d'une tournée

Le point le moins évident de la phase.

Les cascades seules **ne suffisent pas**. En supprimant un `Tour`, MySQL doit
supprimer `tour_stops` (donc `tour_stop_services`) et `tour_periods` (donc
`tour_period_assignments`). Mais `tour_period_assignments.tour_stop_service_id`
est en `RESTRICT` : si le moteur choisit de supprimer les services avant les
affectations, la contrainte bloque et la suppression échoue sur une erreur SQL
brute, sans message métier.

`DeleteTourAction` impose donc l'ordre, dans une transaction :

```text
1. tour_period_assignments
2. tour_periods
3. tour_stop_services
4. tour_stops
5. tour
```

Les cascades restent déclarées : elles sont le **filet de sécurité** si une
suppression échappe à l'Action, jamais le mécanisme nominal. Un test le vérifie
sur un agrégat complet.

`tour_periods.tour_stop_id` est en `SET NULL` pour la même raison : en
`RESTRICT`, la cascade `tours → tour_stops` échouerait tant qu'une période
référencerait un arrêt. Le refus métier arrive avant — supprimer un arrêt encore
rattaché à des périodes renvoie 409.

### Refus applicatifs, avant que SQL n'intervienne

| Ressource | Refus | Code |
|-----------|-------|------|
| `TourStop` | possède encore des `TourPeriod` | 409 |
| `TourStop` | l'un de ses services est déjà affecté | 409 |
| `TourStopService` | référencé par un `TourPeriodAssignment` | 409 |
| `TourStopService` | dernier service **actif** de son arrêt | 409 |
| `TourPeriod` | possède encore des `TourPeriodAssignment` | 409 |

Le §31 demande aussi de refuser la suppression d'un `Tour` référencé par
`TrackingEvent`, `ProofOfDelivery`, `Claim` ou une facture. **Ces tables
n'existent pas** — elles relèvent des phases suivantes. Aucun contrôle n'est
écrit pour des tables absentes ; le point est porté aux risques du rapport final.

## 5. Contraintes uniques

| Table | Contrainte | Portée |
|-------|-----------|--------|
| `tours` | `(organization_id, tour_number)` | Même portée que `orders.order_number`. Deux organisations peuvent utiliser le même numéro. |
| `tour_stops` | `(tour_id, sequence)` | Exigée par le §30. |
| `tour_stop_services` | `(tour_stop_id, sequence_within_stop)` | Idem. |
| `tour_periods` | `(tour_id, sequence)` | Idem. |
| `tour_period_assignments` | `(tour_period_id, tour_stop_service_id, package_id)` | Évite le doublon exact. |

**Aucune contrainte sur `(tour_stop_id, order_service_id, is_active_assignment)`.**
Le §13 demande d'empêcher plusieurs affectations actives incompatibles « **si
cette règle est démontrée par le modèle existant** ». Elle ne l'est pas : le
diagramme pose `OrderService "1" -- "0..*" TourStopService` sans restriction, et
l'historique des affectations repose précisément sur la possibilité de planifier
deux fois le même service. Une contrainte partielle exigerait de surcroît un
index filtré, indisponible sous MySQL 8.

### Le doublon d'affectation sans colis

MySQL traite chaque `NULL` comme distinct dans un index unique :
`(période, service, NULL)` peut donc être inséré deux fois sans que l'index
proteste. Ce cas est refusé par `AssignmentConsistency::notDuplicated()`, côté
application. Un test le couvre.

## 6. Index

| Table | Index |
|-------|-------|
| `tours` | `organization_id`, `agency_id`, `depot_id`, `provider_id`, `driver_id`, `vehicle_id`, `tour_date`, `status`, `tour_type`, unique `(organization_id, tour_number)` |
| `tour_stops` | `tour_id`, `address_id`, `status`, `grouping_key`, unique `(tour_id, sequence)` |
| `tour_stop_services` | `tour_stop_id`, `order_service_id`, `is_active_assignment`, `status`, unique `(tour_stop_id, sequence_within_stop)` |
| `tour_periods` | `tour_id`, `tour_stop_id`, `period_type`, `status`, `planned_start_at`, unique `(tour_id, sequence)` |
| `tour_period_assignments` | `tour_period_id`, `tour_stop_service_id`, `package_id`, unique composite |

Exactement les index du §30, ni plus ni moins. Chaque clé étrangère est indexée,
et chaque colonne filtrable ou triable l'est aussi.

## 7. Précision des colonnes décimales

Convention des Phases 1 à 3, reprise sans invention :

| Grandeur | Précision | Précédents |
|----------|-----------|------------|
| Masse | `DECIMAL(12,3)` | `orders.weight`, `order_services.weight`, `vehicles.payload_capacity` |
| Volume | `DECIMAL(12,4)` | `orders.volume`, `order_services.volume`, `vehicles.volume_capacity` |

`distance_meters` est un `BIGINT UNSIGNED`, conforme au `bigint` du diagramme :
une tournée longue distance dépasse le million de mètres, un `INT` suffirait
mais le diagramme tranche.

## 8. Timestamps et soft deletes

**Aucune des cinq tables ne porte `created_at`, `updated_at` ni `deleted_at`.**

Les cinq classes n'en définissent aucun, et le §2 range les « timestamps non
présents » et `softDeletes` parmi les ajouts interdits. Même convention qu'en
Phase 3.

C'est un écart apparent avec la Phase 2, où `orders`, `order_services` et
`packages` portent des timestamps — mais ces classes les déclarent au diagramme.
La règle est la même dans les deux cas : **suivre le diagramme**.

Conséquence assumée : la date de création d'une tournée n'est pas lisible sur la
ligne. Elle reste reconstituable depuis `audit_logs`, qui horodate chaque
écriture avec son auteur. Le tri par défaut porte donc sur `tour_date`, une
donnée métier, et non sur une date technique.

## 9. Séquences et réorganisation

Trois tables portent une `sequence` unique par parent. Les réécrire une à une
violerait l'index dès que deux éléments s'échangent : la valeur cible de l'un est
encore occupée par l'autre.

`SequenceReorderer` procède en **deux passes**, dans une transaction :

```sql
UPDATE tour_stops SET sequence = sequence + 1000000 WHERE tour_id = ?;
-- puis, pour chaque identifiant, sa position finale
UPDATE tour_stops SET sequence = ? WHERE id = ?;
```

Le décalage se fait vers le haut : `sequence` est `UNSIGNED`, les valeurs
négatives sont impossibles.

L'appelant doit fournir **tous** les enfants du parent, une fois chacun. Une
liste partielle laisserait des lignes dans le décalage temporaire — elle est
refusée en 422. C'est ce qui garantit « aucun trou, aucun doublon » du §12.

## 10. Cardinalité `1..*` de TourStopService

`TourStop "1" *-- "1..*" TourStopService` : un arrêt sans service n'existe pas.

**Stratégie retenue : création atomique.** `POST /tours/{tour}/stops` exige un
tableau `services` d'au moins un élément et écrit l'arrêt avec ses services dans
la même transaction. Un arrêt vide n'apparaît jamais en base, même
transitoirement — un test le vérifie en soumettant un service hors périmètre et
en comptant les arrêts créés : zéro.

Côté suppression, la même règle joue en sens inverse : retirer ou désactiver le
**dernier service actif** d'un arrêt est refusé en 409. Pour retirer le dernier,
il faut supprimer l'arrêt.

## 11. Agrégats du Tour

Voir [`phase-4-analysis.md`](phase-4-analysis.md) §11 pour le détail des sources.

En résumé : cinq des sept totaux sont recalculés à partir des données présentes
(`total_weight`, `total_volume`, `total_packages`, `total_customers` depuis les
services actifs ; `distance_meters` depuis les périodes).
`driving_time_minutes` et `working_time_minutes` **ne le sont pas** — les
distinguer exigerait de connaître les valeurs de `periodType`, que le diagramme
n'énumère pas. Ils restent saisis par l'appelant, et un test vérifie qu'ils ne
sont jamais écrasés.
