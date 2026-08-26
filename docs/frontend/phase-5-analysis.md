# Frontend Phase 5 — Analyse préalable

Planification, tournées, carte, glisser-déposer, géocodage et calcul
d'itinéraire.

Relevé sur le backend et la base réels le 26 août 2026, **avant toute ligne de
code**, selon l'ordre de priorité du prompt : schéma réel > backend réel > UML >
document de phase.

---

## 1. Ce qui existe déjà, et qui ne sera pas refait

Les cinq tables de planification sont **livrées et conformes à l'UML** :

| Table | Colonnes réelles |
|---|---|
| `tours` | `id`, `organization_id`, `tour_number`, `tour_date`, `agency_id`, `depot_id`, `provider_id`, `vehicle_id`, `driver_id`, `tour_type`, `instructions`, `planned_start_at`, `planned_end_at`, `actual_start_at`, `actual_end_at`, `total_weight`, `total_volume`, `total_packages`, `total_customers`, `driving_time_minutes`, `working_time_minutes`, `distance_meters`, `status` |
| `tour_stops` | `id`, `tour_id`, `address_id`, `sequence`, `grouping_key`, `generation_mode`, `planned_arrival_at`, `planned_departure_at`, `actual_arrival_at`, `actual_departure_at`, `waiting_minutes`, `service_minutes`, `status` |
| `tour_stop_services` | `id`, `tour_stop_id`, `order_service_id`, `sequence_within_stop`, `is_active_assignment`, `status` |
| `tour_periods` | `id`, `tour_id`, `tour_stop_id`, `period_type`, `sequence`, `planned_start_at`, `planned_end_at`, `actual_start_at`, `actual_end_at`, `break_minutes`, `service_minutes`, `waiting_minutes`, `distance_meters`, `internal_remark`, `status` |
| `tour_period_assignments` | `id`, `tour_period_id`, `tour_stop_service_id`, `package_id` |

Aucune colonne interdite par les §4 à §8 n'existe. `internal_remark` est bien
présent, et non un ancien `communication`. `tour_period_assignments` n'a pas de
`status`. **Aucune migration n'est nécessaire pour le modèle.**

## 2. Endpoints réels

```text
GET|POST   /tours                          GET|PATCH|DELETE /tours/{tour}
GET|POST   /tours/{tour}/stops             GET|PATCH|DELETE /tours/{tour}/stops/{stop}
POST       /tours/{tour}/stops/reorder
GET|POST   /tours/{tour}/stops/{stop}/services   …/{service}
POST       /tours/{tour}/stops/{stop}/services/reorder
GET|POST   /tours/{tour}/periods           …/{period}
POST       /tours/{tour}/periods/reorder
GET|POST   /tours/{tour}/periods/{period}/assignments   …/{assignment}
GET        /tours/{tour}/tracking-events
GET        /tours/{tour}/claims
```

Filtres acceptés par `GET /tours` : `agencyId`, `depotId`, `providerId`,
`driverId`, `vehicleId`, `tourDate`, `tourDateFrom`, `tourDateTo`, `tourType`,
plus `search`, `status`, `page`, `perPage`, `sort`, `direction` hérités.

Permissions : `tours.*`, `tour_stops.*` (dont `tour_stops.reorder`),
`tour_stop_services.*`, `tour_periods.*`.

## 3. Ce qui manque — et qui est le vrai travail de la phase

| Besoin du prompt | État réel |
|---|---|
| Planification en masse d'une commande (§40-41) | **Aucun endpoint.** Le CRUD crée un arrêt et un service à la fois |
| Regroupement d'arrêts, `grouping_key`, `generation_mode` (§56-58) | Colonnes présentes, **aucune logique** ne les remplit |
| Propriété de la DRAFT (§24-26) | **Rien.** Aucune notion de créateur ni de réservation |
| Validation transactionnelle (§27-28) | **Aucun endpoint** de transition |
| Recalcul des totaux et de l'itinéraire (§91-97) | **Rien.** Les colonnes existent, à zéro |
| Géocodage (§74-83) | **Aucun service** |
| Calcul d'itinéraire (§84-88) | **Aucun service** |
| Historique de planification (§35-36) | `is_active_assignment` existe, **aucune projection** |
| Éligibilité à la planification (§38) | **Aucun endpoint** de pool |

Autrement dit : le socle de données est complet, **toute la logique métier de
planification reste à écrire**. C'est un travail de backend majoritairement, que
le frontend consommera.

---

## 4. Écarts entre le prompt et le projet réel

### 4.1 Il n'existe pas de table `configs`

Le §75 demande d'enregistrer les URL GPS dans `configs` en « réutilisant le
schéma réel ». **Cette table n'existe pas.** Ce qui existe est
`organization_api_configurations`, créée le 26 août 2026 pour la télématique
Flespi : `code`, `base_url`, `auth_type`, `encrypted_credentials`, `settings`
(JSON : `path`, `queryKey`, `queryTemplate`), `timeout_seconds`, `is_active`.

C'est exactement la forme requise, et elle est **par organisation** — ce que
`configs` ne garantissait pas. Proposition : deux configurations de codes
`gps_geocoding` et `gps_routing`, le profil `truckfast` vivant dans `settings`.

Créer une table `configs` en parallèle produirait deux référentiels d'API
externes à maintenir.

### 4.2 Aucun service de chargement n'est configuré

Le §51 demande de documenter les services reconnus comme chargement. Réponse au
26 août 2026 :

| Service | Code | Nom | Chargement ? |
|---|---|---|---|
| — | `DELIV` | Livraison | non |
| — | `Livraison` | Livraison | non |
| — | `Montage` | Montage | non |

**Aucun.** Les règles §62 à §65 — chargement au dépôt formant le premier arrêt,
arrêts partagés entre commandes — ne peuvent être ni exercées ni testées tant
qu'un service de chargement n'existe pas dans le référentiel. Le prompt interdit
d'inventer un enum `LOAD` (§51) ; la reconnaissance devra donc reposer sur une
configuration explicite, à décider avec le métier.

### 4.3 Les dépôts n'ont pas d'adresse

`depots` ne porte pas d'`address_id`, et les liaisons `entity_addresses`
existantes ne concernent que `organization`, `customer` et `customer_site` :
**aucune adresse n'est rattachée aux deux dépôts existants**. Or le §59 fait du
dépôt le point de départ de tout itinéraire.

`MorphMap::DEPOT` est déjà accepté par `StoreAddressRequest` : le lien est
possible, il n'a simplement jamais été créé. Sans lui, aucun itinéraire ne peut
partir.

### 4.4 Aucune adresse n'a de coordonnées

Les 7 adresses de la base ont `latitude` et `longitude` à `NULL`. La carte et le
routage dépendent donc entièrement du géocodage à construire.

### 4.5 `statuses` ne contient rien pour la planification

`tour` et `tour_stop` ont leurs entrées, mais aucune valeur n'est encore
stockée. `tour_stop_service` et `tour_period` n'ont **aucune** entrée : la règle
§11 les impose, comme la Phase 4 l'a fait pour fournisseurs, chauffeurs et
véhicules.

### 4.6 Le créateur d'une DRAFT

Le §23 interdit d'ajouter `created_by` et demande de le déduire du journal
d'audit. `audit_logs` porte bien `entity_type`, `entity_id`, `action` et
`user_id` : la projection est possible sans colonne nouvelle. Il faudra veiller
au coût — une jointure, pas une requête par tournée, comme l'a rappelé le
budget de requêtes en Phase 4.

---

## 5. Unités GPS — à confirmer avant de figer

Le §89 demande de vérifier les unités plutôt que de les supposer. La réponse
d'exemple donne `Distance = 465536` et `TravelTime = 23611` pour Paris → Lyon :
465 km et 6 h 33 min, ce qui confirme **mètres** et **secondes**. La conversion
sera vérifiée par un test sur cette réponse exacte avant d'écrire quoi que ce
soit dans `distance_meters`.

---

## 6. Découpage proposé

L'ensemble représente plusieurs jours. Découpage en tranches livrables, chacune
vérifiable et commitable :

| # | Tranche | Contenu |
|---|---|---|
| 1 | Socle statuts et configuration | Statuts des quatre sources au référentiel, validation, configurations GPS déclarées |
| 2 | Géocodage | `GeocodingService`, adresse du dépôt, gestion des échecs, tests |
| 3 | Itinéraire | `RoutingService`, segments, totaux, unités vérifiées |
| 4 | Planification en masse | Endpoint transactionnel, éligibilité, regroupement, `grouping_key` |
| 5 | Propriété et cycle de la DRAFT | Créateur par audit, exclusivité, validation, annulation |
| 6 | Écran Planning | Pool, tournées DRAFT, glisser-déposer, réordonnancement |
| 7 | Écran Carte | Marqueurs, projection par adresse, actions |
| 8 | Historique de replanification | `is_active_assignment`, onglets commande et tournée |

Les tranches 1 à 5 sont du backend ; 6 et 7 en dépendent entièrement.

---

## 7. Trois points bloquants à trancher avec le métier

1. **Quel service est un chargement ?** Aucun n'existe. Sans réponse, les règles
   du dépôt de départ ne peuvent pas être implémentées.
2. **Quelle adresse pour les dépôts ?** Aucune n'est rattachée. Sans elle,
   aucun itinéraire ne part.
3. **Que faire des services non éligibles lors du glisser d'une commande ?**
   Le §42 exige de trancher : tout ou rien, ou planifier les éligibles et
   rendre la liste des refusés.

Ces trois points sont documentés ici plutôt que tranchés en silence.
