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

**Aucun `legacyId`.** Les diagrammes ne le définissent sur aucune des quatre
classes ; il n'est ni accepté en entrée, ni renvoyé.

`addressId` et `contactId` sont **facultatifs** sur `Provider` et sur `Driver` —
le diagramme les pose en `0..1`.

---

## Fournisseurs

### `GET /api/v1/providers`

Permission : `providers.view`.

| Paramètre | Effet |
|-----------|-------|
| `search` | Recherche sur `code` et `name` |
| `status`, `addressId`, `contactId` | Filtres exacts |
| `sort` | `code`, `name`, `status` |
| `direction` | `asc` \| `desc` |
| `page`, `perPage` | Pagination, `perPage` borné à 100 |

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000001",
      "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
      "addressId": "01JC00000000000000000000A1",
      "contactId": null,
      "code": "PRV-0001",
      "name": "Transports Atlas",
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
  "status": "active",
  "addressId": "01JC00000000000000000000A2",
  "contactId": "01JC00000000000000000000C2"
}
```

Le corps minimal se limite à `code`, `name` et `status` :

```json
{ "code": "PRV-0003", "name": "Transport Rif", "status": "active" }
```

Réponse `201` : voir `GET /providers/{provider}` ci-dessous.

### `GET /api/v1/providers/{provider}`

Permission : `providers.view`. L'adresse et le contact sont restitués en entier
lorsqu'ils sont renseignés.

```json
{
  "data": {
    "id": "01JC0000000000000000000002",
    "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
    "addressId": "01JC00000000000000000000A2",
    "contactId": "01JC00000000000000000000C2",
    "code": "PRV-0002",
    "name": "Logistique du Détroit",
    "status": "active",
    "address": {
      "id": "01JC00000000000000000000A2",
      "addressLine1": "12 rue du Port",
      "city": "Tanger",
      "country": "MA"
    },
    "contact": {
      "id": "01JC00000000000000000000C2",
      "firstName": "Nadia",
      "lastName": "Zerktouni",
      "email": "nadia@detroit.dev"
    },
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

Délier une adresse se fait en envoyant `null` explicitement :

```json
{ "addressId": null }
```

### `DELETE /api/v1/providers/{provider}`

Permission : `providers.delete`. `204` si le fournisseur n'a ni chauffeur ni
véhicule ; `409` sinon :

```json
{ "message": "Impossible de supprimer un fournisseur qui possède encore des chauffeurs ou des véhicules." }
```

---

## Chauffeurs

Le diagramme pose une **seule** identité, `name` : ni prénom/nom séparés, ni
téléphone, ni courriel. Les coordonnées relèvent du `Contact` lié.

### `GET /api/v1/drivers`

Permission : `drivers.view`.

| Paramètre | Effet |
|-----------|-------|
| `search` | `code`, `name` |
| `providerId`, `addressId`, `contactId`, `status` | Filtres exacts |
| `sort` | `code`, `name`, `status` |

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000010",
      "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
      "providerId": "01JC0000000000000000000001",
      "addressId": null,
      "contactId": "01JC00000000000000000000C5",
      "code": "DRV-0001",
      "name": "Karim Bensaïd",
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

`providerId` doit désigner un fournisseur de l'organisation active.
`organizationId` n'est **pas** accepté : il est déduit du fournisseur, pour que
les deux ne puissent pas diverger.

```json
{
  "providerId": "01JC0000000000000000000001",
  "code": "DRV-0002",
  "name": "Yassine Bennani",
  "status": "active",
  "contactId": "01JC00000000000000000000C6"
}
```

### `GET /api/v1/drivers/{driver}`

Permission : `drivers.view`.

```json
{
  "data": {
    "id": "01JC0000000000000000000010",
    "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
    "providerId": "01JC0000000000000000000001",
    "addressId": null,
    "contactId": "01JC00000000000000000000C5",
    "code": "DRV-0001",
    "name": "Karim Bensaïd",
    "status": "active",
    "provider": {
      "id": "01JC0000000000000000000001",
      "code": "PRV-0001",
      "name": "Transports Atlas",
      "status": "active"
    },
    "contact": {
      "id": "01JC00000000000000000000C5",
      "firstName": "Karim",
      "lastName": "Bensaïd",
      "phone": "+212661000000"
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
| `providerId`, `vehicleTypeId`, `status` | Filtres exacts |
| `payloadCapacityMin`, `volumeCapacityMin`, `palletCapacityMin` | Minima — trouver un véhicule capable de porter une charge |
| `sort` | `code`, `registration_number`, `payload_capacity`, `volume_capacity`, `pallet_capacity`, `status` |

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

`Vehicle` n'a pas d'`organizationId` au diagramme : son périmètre passe par le
fournisseur.

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
| `addressId`, `contactId` | N'existe pas |
| `payloadCapacity`, `volumeCapacity`, `palletCapacity` | Doivent être positifs ou nuls |

`addressId` et `contactId` sont validés par simple existence : `addresses` et
`contacts` sont des tables partagées sans `organization_id`. C'est la convention
déjà retenue pour `customer_sites` et `order_services`.

---

## Endpoints volontairement absents

Aucune route de contrat, de version de contrat, de disponibilité de chauffeur ou
de véhicule, ni de liste de prix : ces entités ne figurent pas dans les
diagrammes. Voir [`phase-3-analysis.md`](phase-3-analysis.md) §1.

Aucun endpoint `PATCH /providers/{provider}/status` non plus : `status` est une
chaîne libre sans workflow, `PATCH` sur la ressource suffit.

Aucune route de rattachement d'un compte utilisateur à un chauffeur : le
diagramme ne pose pas de lien `Driver → User`.
