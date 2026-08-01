# Exemples d'API — Phase 3 : fournisseurs et ressources

Ce document répond au §35. Tous les exemples portent exactement les attributs
des diagrammes : aucun champ supplémentaire n'est accepté ni renvoyé.

En-têtes communs à toutes les requêtes :

```http
Authorization: Bearer <token>
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
Content-Type: application/json
Accept: application/json
```

L'en-tête d'organisation est obligatoire : sans lui, toute route renvoie 403.

**`legacyId` n'est jamais renvoyé.** Il est accepté en entrée pour les scripts
de reprise, mais reste masqué dans les réponses.

---

## Fournisseurs

### `GET /api/v1/providers`

Permission : `providers.view`.

| Paramètre | Effet |
|-----------|-------|
| `search` | Recherche sur `code` et `name` |
| `status`, `providerType`, `legacyId` | Filtres exacts |
| `sort` | `code`, `name`, `provider_type`, `status`, `legacy_id` |
| `direction` | `asc` \| `desc` |
| `page`, `perPage` | Pagination, `perPage` borné à 100 |

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000001",
      "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
      "code": "PRV-0001",
      "name": "Transports Atlas",
      "providerType": "subcontractor",
      "status": "active",
      "driverCount": 12,
      "vehicleCount": 8
    }
  ],
  "meta": { "currentPage": 1, "perPage": 25, "total": 1, "lastPage": 1 },
  "links": { "first": "…", "last": "…", "prev": null, "next": null }
}
```

### `POST /api/v1/providers`

Permission : `providers.create`. `organizationId` n'est **pas** accepté : le
fournisseur est créé dans l'organisation active.

```json
{
  "code": "PRV-0002",
  "name": "Logistique du Détroit",
  "providerType": "carrier",
  "status": "active",
  "legacyId": 44821
}
```

Réponse `201` : voir `GET /providers/{provider}` ci-dessous.

### `GET /api/v1/providers/{provider}`

Permission : `providers.view`.

```json
{
  "data": {
    "id": "01JC0000000000000000000002",
    "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
    "code": "PRV-0002",
    "name": "Logistique du Détroit",
    "providerType": "carrier",
    "status": "active",
    "driverCount": 0,
    "vehicleCount": 0
  },
  "meta": {}
}
```

### `PATCH /api/v1/providers/{provider}`

Permission : `providers.update`. Le statut se change ici — il n'y a pas
d'endpoint dédié, faute de workflow de transitions.

```json
{ "name": "Logistique du Détroit SARL", "status": "suspended" }
```

### `DELETE /api/v1/providers/{provider}`

Permission : `providers.delete`. `204` si le fournisseur n'a ni chauffeur ni
véhicule ; `409` sinon :

```json
{ "message": "Impossible de supprimer un fournisseur qui possède encore des chauffeurs ou des véhicules." }
```

---

## Chauffeurs

### `GET /api/v1/drivers`

Permission : `drivers.view`.

| Paramètre | Effet |
|-----------|-------|
| `search` | `code`, `first_name`, `last_name`, `phone`, `email` |
| `providerId`, `userId`, `status`, `legacyId` | Filtres exacts |
| `sort` | `code`, `first_name`, `last_name`, `status`, `legacy_id` |

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000010",
      "providerId": "01JC0000000000000000000001",
      "userId": null,
      "code": "DRV-0001",
      "firstName": "Karim",
      "lastName": "Bensaïd",
      "fullName": "Karim Bensaïd",
      "phone": "+212661000000",
      "email": "karim@atlas.dev",
      "status": "active",
      "providerName": "Transports Atlas"
    }
  ],
  "meta": { "currentPage": 1, "perPage": 25, "total": 1, "lastPage": 1 },
  "links": { "first": "…", "last": "…", "prev": null, "next": null }
}
```

### `POST /api/v1/drivers`

Permission : `drivers.create`.

`providerId` doit désigner un fournisseur de l'organisation active. `userId` est
facultatif — un chauffeur n'a pas nécessairement de compte — et doit désigner un
membre de l'organisation active s'il est fourni.

```json
{
  "providerId": "01JC0000000000000000000001",
  "userId": null,
  "code": "DRV-0002",
  "firstName": "Yassine",
  "lastName": "Bennani",
  "phone": "+212661223344",
  "email": "yassine@atlas.dev",
  "status": "active"
}
```

### `GET /api/v1/drivers/{driver}`

Permission : `drivers.view`. Le compte lié n'est exposé que par son identité —
ni statut, ni rôles, ni aucune donnée sensible.

```json
{
  "data": {
    "id": "01JC0000000000000000000010",
    "providerId": "01JC0000000000000000000001",
    "userId": "01JC0000000000000000000099",
    "code": "DRV-0001",
    "firstName": "Karim",
    "lastName": "Bensaïd",
    "fullName": "Karim Bensaïd",
    "phone": "+212661000000",
    "email": "karim@atlas.dev",
    "status": "active",
    "provider": {
      "id": "01JC0000000000000000000001",
      "code": "PRV-0001",
      "name": "Transports Atlas",
      "providerType": "subcontractor",
      "status": "active"
    },
    "user": {
      "id": "01JC0000000000000000000099",
      "fullName": "Karim Bensaïd",
      "email": "karim@tricolis.dev"
    }
  },
  "meta": {}
}
```

### `PATCH` / `DELETE /api/v1/drivers/{driver}`

Permissions : `drivers.update`, `drivers.delete`. Réaffecter un chauffeur reste
possible, mais uniquement vers un fournisseur de la même organisation.

---

## Types de véhicule

### `GET /api/v1/vehicle-types`

Permission : `vehicle_types.view`. Recherche sur `code` et `name`, filtre
`status`, tri sur `code`, `name`, `status`.

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000020",
      "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
      "code": "VT-VAN",
      "name": "Fourgon 20m³",
      "status": "active",
      "vehicleCount": 5
    }
  ],
  "meta": { "currentPage": 1, "perPage": 25, "total": 1, "lastPage": 1 },
  "links": { "first": "…", "last": "…", "prev": null, "next": null }
}
```

### `POST /api/v1/vehicle-types`

Permission : `vehicle_types.create`.

```json
{ "code": "VT-TRUCK12", "name": "Porteur 12T", "status": "active" }
```

### `DELETE /api/v1/vehicle-types/{vehicleType}`

Permission : `vehicle_types.delete`. `409` si un véhicule l'utilise — les
véhicules ne sont jamais supprimés en cascade :

```json
{ "message": "Impossible de supprimer un type de véhicule utilisé par des véhicules." }
```

---

## Véhicules

### `GET /api/v1/vehicles`

Permission : `vehicles.view`.

| Paramètre | Effet |
|-----------|-------|
| `search` | `code`, `registration_number` |
| `providerId`, `vehicleTypeId`, `status`, `legacyId` | Filtres exacts |
| `payloadCapacityMin`, `volumeCapacityMin`, `palletCapacityMin` | Minima — trouver un véhicule capable de porter une charge |
| `sort` | `code`, `registration_number`, `payload_capacity`, `volume_capacity`, `pallet_capacity`, `status`, `legacy_id` |

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000030",
      "providerId": "01JC0000000000000000000001",
      "vehicleTypeId": "01JC0000000000000000000020",
      "code": "VEH-0001",
      "registrationNumber": "12-A-34567",
      "payloadCapacity": "3500.000",
      "volumeCapacity": "22.5000",
      "palletCapacity": 8,
      "status": "active",
      "providerName": "Transports Atlas",
      "vehicleTypeName": "Fourgon 20m³"
    }
  ],
  "meta": { "currentPage": 1, "perPage": 25, "total": 1, "lastPage": 1 },
  "links": { "first": "…", "last": "…", "prev": null, "next": null }
}
```

### `POST /api/v1/vehicles`

Permission : `vehicles.create`. Le fournisseur **et** le type doivent appartenir
à l'organisation active — et à la même.

```json
{
  "providerId": "01JC0000000000000000000001",
  "vehicleTypeId": "01JC0000000000000000000020",
  "code": "VEH-0002",
  "registrationNumber": "56-B-78901",
  "payloadCapacity": 12000,
  "volumeCapacity": 48.75,
  "palletCapacity": 18,
  "status": "active"
}
```

### `GET /api/v1/vehicles/{vehicle}`

```json
{
  "data": {
    "id": "01JC0000000000000000000030",
    "providerId": "01JC0000000000000000000001",
    "vehicleTypeId": "01JC0000000000000000000020",
    "code": "VEH-0001",
    "registrationNumber": "12-A-34567",
    "payloadCapacity": "3500.000",
    "volumeCapacity": "22.5000",
    "palletCapacity": 8,
    "status": "active",
    "provider": {
      "id": "01JC0000000000000000000001",
      "code": "PRV-0001",
      "name": "Transports Atlas",
      "providerType": "subcontractor",
      "status": "active"
    },
    "vehicleType": {
      "id": "01JC0000000000000000000020",
      "code": "VT-VAN",
      "name": "Fourgon 20m³",
      "status": "active"
    }
  },
  "meta": {}
}
```

---

## Erreurs

Format de validation, identique aux Phases 1 et 2 :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": {
    "providerId": ["Ce fournisseur n’appartient pas à l’organisation active."]
  }
}
```

| Statut | Cas |
|--------|-----|
| `401` | Jeton absent, expiré ou révoqué |
| `403` | Permission manquante, ou en-tête `X-Organization-Id` absent |
| `404` | Ressource d'une autre organisation — l'existence n'est jamais révélée |
| `409` | Fournisseur avec ressources, type de véhicule utilisé |
| `422` | Validation : code dupliqué, immatriculation dupliquée, capacité négative, clé étrangère hors périmètre, tri interdit, `perPage` hors bornes |

Cas de validation les plus fréquents :

| Champ | Message |
|-------|---------|
| `code` | Déjà utilisé dans l'organisation (fournisseurs, types) ou chez le fournisseur (chauffeurs, véhicules) |
| `registrationNumber` | Déjà utilisée — une plaque identifie un véhicule physique |
| `providerId` | N'appartient pas à l'organisation active |
| `vehicleTypeId` | N'appartient pas à l'organisation active |
| `userId` | N'est pas accessible dans l'organisation active |
| `payloadCapacity`, `volumeCapacity`, `palletCapacity` | Doivent être positifs ou nuls |
| `email` | Doit être une adresse valide si renseignée |

---

## Endpoints volontairement absents

Aucune route de contrat, de version de contrat, de disponibilité de chauffeur ou
de véhicule, ni de liste de prix : ces entités ne figurent pas dans les
diagrammes officiels. Voir [`phase-3-analysis.md`](phase-3-analysis.md) §1.

Aucun endpoint `PATCH /providers/{provider}/status` non plus : `status` est une
chaîne libre sans workflow, `PATCH` sur la ressource suffit.
