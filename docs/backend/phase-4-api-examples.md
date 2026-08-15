# Exemples d'API — Phase 4 : planification des services et tournées

Ce document répond au §40. Tous les exemples portent exactement les attributs du
diagramme : aucun champ supplémentaire n'est accepté ni renvoyé.

En-têtes communs :

```http
Authorization: Bearer <token>
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
Content-Type: application/json
Accept: application/json
```

L'en-tête d'organisation est obligatoire : sans lui, toute route renvoie 403.

Seule `Tour` porte `organizationId`. Ses arrêts, services, périodes et
affectations tiennent leur périmètre d'elle, et toutes leurs routes sont
imbriquées sous `/tours/{tour}`.

---

## Tournées

### `POST /api/v1/tours` — création minimale

Permission : `tours.create`. Quatre champs suffisent.

```json
{
  "tourNumber": "TRN-2026-0001",
  "tourDate": "2026-09-01",
  "agencyId": "01JC00000000000000000000A1",
  "status": "draft"
}
```

`tourNumber` est **fourni par l'appelant** et unique dans l'organisation :
aucune règle de génération n'est définie pour les tournées, et le §9 interdit
d'en inventer une. Voir [`phase-4-analysis.md`](phase-4-analysis.md) §12.

Réponse `201` — les sept totaux démarrent à zéro :

```json
{
  "data": {
    "id": "01JC0000000000000000000T01",
    "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
    "tourNumber": "TRN-2026-0001",
    "tourDate": "2026-09-01",
    "agencyId": "01JC00000000000000000000A1",
    "depotId": null,
    "providerId": null,
    "vehicleId": null,
    "driverId": null,
    "tourType": null,
    "instructions": null,
    "plannedStartAt": null,
    "plannedEndAt": null,
    "actualStartAt": null,
    "actualEndAt": null,
    "totalWeight": "0.000",
    "totalVolume": "0.0000",
    "totalPackages": 0,
    "totalCustomers": 0,
    "drivingTimeMinutes": 0,
    "workingTimeMinutes": 0,
    "distanceMeters": 0,
    "status": "draft"
  },
  "meta": {}
}
```

### `POST /api/v1/tours` — avec fournisseur, chauffeur et véhicule

Les trois sont facultatifs, mais **cohérents entre eux** : le chauffeur et le
véhicule doivent appartenir au fournisseur, et le dépôt à l'agence.

```json
{
  "tourNumber": "TRN-2026-0002",
  "tourDate": "2026-09-02",
  "agencyId": "01JC00000000000000000000A1",
  "depotId": "01JC00000000000000000000D1",
  "providerId": "01JC0000000000000000000P01",
  "driverId": "01JC0000000000000000000R01",
  "vehicleId": "01JC0000000000000000000V01",
  "tourType": "distribution",
  "instructions": "Départ 06h00, retour dépôt avant 18h00",
  "plannedStartAt": "2026-09-02T06:00:00Z",
  "plannedEndAt": "2026-09-02T18:00:00Z",
  "status": "planned"
}
```

### `GET /api/v1/tours`

Permission : `tours.view`.

| Paramètre | Effet |
|-----------|-------|
| `search` | `tour_number`, `instructions` |
| `agencyId`, `depotId`, `providerId`, `driverId`, `vehicleId` | Filtres exacts |
| `tourDate`, `tourType`, `status` | Filtres exacts |
| `tourDateFrom`, `tourDateTo` | Intervalle de dates |
| `sort` | `tour_number`, `tour_date`, `planned_start_at`, `planned_end_at`, `actual_start_at`, `actual_end_at`, `total_weight`, `total_volume`, `total_packages`, `total_customers`, `driving_time_minutes`, `working_time_minutes`, `distance_meters`, `status` |
| `page`, `perPage` | Pagination, `perPage` borné à 100 |

La liste ne charge **ni les arrêts, ni les périodes, ni les affectations** —
seulement leurs compteurs.

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000T01",
      "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
      "tourNumber": "TRN-2026-0001",
      "tourDate": "2026-09-01",
      "agencyId": "01JC00000000000000000000A1",
      "status": "planned",
      "totalPackages": 12,
      "totalCustomers": 4,
      "distanceMeters": 84200,
      "agencyName": "Agence Casablanca",
      "stopCount": 6,
      "periodCount": 11
    }
  ],
  "meta": { "currentPage": 1, "perPage": 25, "total": 1, "lastPage": 1 },
  "links": { "first": "…", "last": "…", "prev": null, "next": null }
}
```

### `PATCH /api/v1/tours/{tour}`

Permission : `tours.update`. Le statut se change ici — convention des Phases 2
et 3, il n'y a pas d'endpoint dédié.

```json
{ "status": "confirmed" }
```

Un changement de statut produit **deux** entrées d'audit : `tour.updated` et
`tour.status_changed`. La seconde décrit un événement d'exploitation, et se
cherche autrement dans le journal.

Aucune transition n'est bloquée : le diagramme n'en définit aucune, et le §21
interdit d'en inventer. Seule l'appartenance à l'enum est validée.

### `DELETE /api/v1/tours/{tour}`

Permission : `tours.delete`. Supprime tout l'agrégat — affectations, périodes,
services, arrêts — dans cet ordre, en une transaction. `204`.

---

## Arrêts

### `POST /api/v1/tours/{tour}/stops`

Permission : `tour_stops.create`.

**Au moins un service est exigé.** Le diagramme pose
`TourStop "1" *-- "1..*" TourStopService` : un arrêt sans service n'existe pas.
L'arrêt et ses services sont écrits dans la même transaction.

```json
{
  "addressId": "01JC00000000000000000000A9",
  "sequence": 1,
  "status": "pending",
  "plannedArrivalAt": "2026-09-01T08:30:00Z",
  "plannedDepartureAt": "2026-09-01T08:50:00Z",
  "serviceMinutes": 20,
  "services": [
    {
      "orderServiceId": "01JC0000000000000000000S01",
      "sequenceWithinStop": 1,
      "status": "planned"
    },
    {
      "orderServiceId": "01JC0000000000000000000S02",
      "sequenceWithinStop": 2,
      "status": "planned"
    }
  ]
}
```

Sans le tableau `services`, ou avec un tableau vide :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "services": ["Un arrêt doit porter au moins un service."] }
}
```

### `POST /api/v1/tours/{tour}/stops/reorder`

Permission : `tour_stops.reorder`. La liste doit contenir **tous** les arrêts de
la tournée, une fois chacun ; les séquences sont réécrites de 1 à N.

```json
{ "ids": ["01JC…S03", "01JC…S01", "01JC…S02"] }
```

Réponse `204`. Une liste partielle est refusée :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "ids": ["La réorganisation doit lister exactement tous les éléments, une fois chacun."] }
}
```

### `DELETE /api/v1/tours/{tour}/stops/{tourStop}`

Permission : `tour_stops.delete`. Supprime l'arrêt et ses services. `409` si des
périodes le référencent encore, ou si l'un de ses services est déjà affecté.

---

## Services planifiés

### `POST /api/v1/tours/{tour}/stops/{tourStop}/services`

Permission : `tour_stop_services.create`. Le service doit venir d'une commande
de l'organisation de la tournée.

```json
{
  "orderServiceId": "01JC0000000000000000000S03",
  "sequenceWithinStop": 3,
  "status": "planned"
}
```

`isActiveAssignment` vaut `true` par défaut.

### `PATCH …/services/{tourStopService}` — désactivation

Permission : `tour_stop_services.update`.

```json
{ "isActiveAssignment": false }
```

La ligne est **conservée** : c'est ainsi que l'historique se construit. L'audit
enregistre `tour_stop_service.deactivated`, et non `.updated`.

Désactiver le dernier service actif d'un arrêt est refusé :

```json
{ "message": "Un arrêt doit conserver au moins un service actif : supprimez l’arrêt pour retirer le dernier." }
```

### `DELETE …/services/{tourStopService}`

`409` dans deux cas : le service est affecté à une période, ou c'est le dernier
service actif de l'arrêt.

---

## Périodes

### `POST /api/v1/tours/{tour}/periods`

Permission : `tour_periods.create`. `tourStopId` est facultatif — une période de
conduite entre deux arrêts n'appartient à aucun arrêt.

```json
{
  "tourStopId": null,
  "periodType": "driving",
  "sequence": 1,
  "plannedStartAt": "2026-09-01T06:00:00Z",
  "plannedEndAt": "2026-09-01T08:30:00Z",
  "distanceMeters": 42100,
  "status": "planned"
}
```

`periodType` et `status` sont des chaînes libres : le diagramme n'en énumère
aucune valeur. `driving` et `planned` ci-dessus sont des exemples, sans portée
normative.

Fourni, `tourStopId` doit appartenir à **cette** tournée :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "tourStopId": ["Cet arrêt n’appartient pas à la tournée."] }
}
```

### `GET /api/v1/tours/{tour}/periods`

| Paramètre | Effet |
|-----------|-------|
| `tourStopId`, `periodType`, `status` | Filtres exacts |
| `plannedFrom`, `plannedTo` | Intervalle sur `planned_start_at` |
| `actualFrom`, `actualTo` | Intervalle sur `actual_start_at` |
| `sort` | `sequence`, `period_type`, `planned_start_at`, `planned_end_at`, `actual_start_at`, `actual_end_at`, `distance_meters`, `status` |

---

## Affectations

### `POST /api/v1/tours/{tour}/periods/{tourPeriod}/assignments`

Permission : `tour_period_assignments.create`. Trois clés étrangères, pas une de
plus — le §17 interdit `sequence`, `status`, `quantity` et `duration`.

```json
{
  "tourStopServiceId": "01JC0000000000000000000X01",
  "packageId": "01JC0000000000000000000K01"
}
```

Sans colis :

```json
{ "tourStopServiceId": "01JC0000000000000000000X01" }
```

Deux contrôles croisés, tous deux testés :

| Cas | Message |
|-----|---------|
| Service d'une autre tournée | `Ce service planifié n’appartient pas à la tournée de la période.` |
| Colis d'une autre commande | `Ce colis n’appartient pas à la commande du service planifié.` |
| Doublon exact | `Cette affectation existe déjà pour cette période.` |

---

## Totaux de la tournée

Les totaux sont recalculés **explicitement**, par les Actions qui modifient la
composition d'une tournée — jamais par un observateur caché.

| Champ | Recalculé | Source |
|-------|-----------|--------|
| `totalWeight`, `totalVolume`, `totalPackages` | oui | services de commande des affectations **actives** |
| `totalCustomers` | oui | clients distincts de ces mêmes commandes |
| `distanceMeters` | oui | somme des `distanceMeters` des périodes |
| `drivingTimeMinutes` | **non** | aucune source dans le modèle |
| `workingTimeMinutes` | **non** | idem |

Les deux derniers exigeraient de distinguer une période de conduite d'une
période de service, donc de connaître les valeurs de `periodType` — que le
diagramme n'énumère pas. Ils restent saisis par l'appelant, et ne sont jamais
écrasés. Voir [`phase-4-analysis.md`](phase-4-analysis.md) §11.

---

## Erreurs

| Statut | Cas |
|--------|-----|
| `401` | Jeton absent, expiré ou révoqué |
| `403` | Permission manquante, ou en-tête `X-Organization-Id` absent |
| `404` | Ressource d'une autre organisation, **ou enfant d'un autre parent** |
| `409` | Arrêt avec périodes, service affecté, dernier service actif, période avec affectations |
| `422` | Validation : séquence dupliquée, numéro de tournée dupliqué, dates incohérentes, valeurs négatives, référence hors périmètre, tri interdit |

Le 404 sur mauvais parent est délibéré : `GET /tours/{A}/stops/{arrêt de B}`
renvoie 404, jamais 403. L'existence d'une ressource hors périmètre ne se révèle
pas.

---

## Endpoints volontairement absents

Aucun endpoint d'optimisation de tournée, de calcul d'itinéraire, de carte, de
disponibilité de chauffeur ou de véhicule, ni de tableau de planification : ces
notions ne figurent pas au diagramme.

Aucune action en masse : le §18 les écarte faute de besoin démontré.

Aucune table de liaison directe entre `Tour` et `Order` : le diagramme planifie
les `OrderService` via `TourStopService`, et la commande reste atteignable par
`tourStopService.orderService.order`.
