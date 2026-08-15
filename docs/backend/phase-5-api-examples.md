# Exemples d'API — Phase 5 : suivi, preuves de livraison et réclamations

Répond au §36. Tous les exemples portent exactement les attributs du diagramme :
aucun champ supplémentaire n'est accepté ni renvoyé.

En-têtes communs :

```http
Authorization: Bearer <token>
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
Content-Type: application/json
Accept: application/json
```

---

## Événements de suivi

### `POST /api/v1/tracking-events` — création minimale

Permission : `tracking_events.create`. Quatre champs suffisent.

```json
{
  "orderId": "01JC0000000000000000000O01",
  "eventType": "pickup",
  "status": "done",
  "occurredAt": "2026-09-01T08:00:00Z"
}
```

`organizationId` et `createdBy` **ne sont pas acceptés** : le premier est pris
sur la commande — le §18 l'exige — le second est l'utilisateur authentifié. Les
accepter permettrait de les contredire.

`eventType` et `status` sont des chaînes libres : le diagramme n'en énumère
aucune valeur. `pickup` et `done` sont des exemples, sans portée normative.

Réponse `201` :

```json
{
  "data": {
    "id": "01JC0000000000000000000E01",
    "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
    "orderId": "01JC0000000000000000000O01",
    "orderServiceId": null,
    "tourId": null,
    "tourStopId": null,
    "eventType": "pickup",
    "status": "done",
    "description": null,
    "latitude": null,
    "longitude": null,
    "occurredAt": "2026-09-01T08:00:00+00:00",
    "createdBy": "01JC0000000000000000000U01"
  },
  "meta": {}
}
```

### `POST /api/v1/tracking-events` — avec tournée, arrêt et coordonnées

```json
{
  "orderId": "01JC0000000000000000000O01",
  "orderServiceId": "01JC0000000000000000000S01",
  "tourId": "01JC0000000000000000000T01",
  "tourStopId": "01JC0000000000000000000P01",
  "eventType": "delivery",
  "status": "done",
  "description": "Remis au gardien",
  "latitude": 33.5731,
  "longitude": -7.5898,
  "occurredAt": "2026-09-01T14:30:00Z"
}
```

**L'arrêt seul suffit.** Si `tourStopId` est fourni sans `tourId`, la tournée est
**déduite** de l'arrêt puis vérifiée dans l'organisation :

```json
{
  "orderId": "01JC0000000000000000000O01",
  "tourStopId": "01JC0000000000000000000P01",
  "eventType": "arrival",
  "status": "done",
  "occurredAt": "2026-09-01T14:10:00Z"
}
```

La réponse porte alors `tourId` renseigné. Le §6 laissait le choix entre déduire
et refuser : obliger l'appelant à recopier une information que le modèle contient
déjà n'ajouterait qu'un risque de la recopier faux.

Si les deux sont fournis et incohérents, `422` :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "tourStopId": ["Cet arrêt n’appartient pas à la tournée fournie."] }
}
```

### `GET /api/v1/tracking-events`

Permission : `tracking_events.view`.

| Paramètre | Effet |
|-----------|-------|
| `search` | `description`, `event_type`, `status` |
| `orderId`, `orderServiceId`, `tourId`, `tourStopId` | Filtres exacts |
| `eventType`, `status`, `createdBy` | Filtres exacts |
| `occurredFrom`, `occurredTo` | Intervalle sur `occurred_at` |
| `sort` | `occurred_at`, `event_type`, `status` |
| `page`, `perPage` | Pagination, `perPage` borné à 100 |

**Ordre par défaut : `occurred_at` décroissant** — le plus récent d'abord, comme
le §8 le pose.

### Consultations imbriquées

Quatre routes, toutes en lecture seule, servies par **le même Query Object** —
le §8 interdit de dupliquer la logique :

```text
GET /api/v1/orders/{order}/tracking-events
GET /api/v1/orders/{order}/services/{orderService}/tracking-events
GET /api/v1/tours/{tour}/tracking-events
GET /api/v1/tours/{tour}/stops/{tourStop}/tracking-events
```

Elles acceptent les mêmes filtres, et imposent simplement un critère
supplémentaire.

### Pas de modification, pas de suppression

```http
PATCH  /api/v1/tracking-events/{trackingEvent}   → 405
DELETE /api/v1/tracking-events/{trackingEvent}   → 405
```

Les routes **n'existent pas**, plutôt que d'exister en renvoyant 403. Un
événement est une donnée historique : une nouvelle occurrence produit un nouvel
événement.

---

## Preuves de livraison

### `POST /api/v1/proofs-of-delivery` — sans document

Permission : `proofs_of_delivery.create`.

```json
{
  "orderId": "01JC0000000000000000000O01",
  "recipientName": "Karim Bensaïd",
  "deliveredAt": "2026-09-01T14:30:00Z"
}
```

Signature et photo sont facultatives : le diagramme pose
`Document "0..1" -- "0..*" ProofOfDelivery`.

### `POST /api/v1/proofs-of-delivery` — avec signature et photo

```json
{
  "orderId": "01JC0000000000000000000O01",
  "orderServiceId": "01JC0000000000000000000S01",
  "tourStopId": "01JC0000000000000000000P01",
  "recipientName": "Karim Bensaïd",
  "signatureDocumentId": "01JC0000000000000000000D01",
  "photoDocumentId": "01JC0000000000000000000D02",
  "remark": "Colis remis en main propre",
  "deliveredAt": "2026-09-01T14:30:00Z"
}
```

**Aucun fichier n'est envoyé ici.** Les deux documents sont créés au préalable
par le module Documents (`POST /api/v1/documents`), puis référencés par ULID. Le
§13 l'exige, et il n'existe ni colonne `signature_path`, ni colonne
`photo_path`, ni table `signatures`, ni table `delivery_photos`.

Les deux peuvent désigner **le même** document : le §11 interdit d'imposer
qu'ils diffèrent sans besoin métier documenté.

Réponse `201`, détail avec documents chargés :

```json
{
  "data": {
    "id": "01JC0000000000000000000V01",
    "orderId": "01JC0000000000000000000O01",
    "orderServiceId": "01JC0000000000000000000S01",
    "tourStopId": "01JC0000000000000000000P01",
    "recipientName": "Karim Bensaïd",
    "signatureDocumentId": "01JC0000000000000000000D01",
    "photoDocumentId": "01JC0000000000000000000D02",
    "remark": "Colis remis en main propre",
    "deliveredAt": "2026-09-01T14:30:00+00:00",
    "createdBy": "01JC0000000000000000000U01"
  },
  "meta": {}
}
```

### Route imbriquée

```text
GET  /api/v1/orders/{order}/proofs-of-delivery
POST /api/v1/orders/{order}/proofs-of-delivery
```

En `POST`, `orderId` vient de l'URL : le fournir dans le corps est inutile, et
en fournir un autre est impossible.

### Aucun statut, aucune modification

`ProofOfDelivery` n'a **pas** de champ `status` : le diagramme n'en définit pas,
et le §12 interdit d'en inventer un.

`PATCH` et `DELETE` renvoient `405`. Une correction se fait par une nouvelle
preuve, pas en réécrivant l'ancienne.

**Aucun changement de statut n'est déclenché** sur la commande, le service ou la
tournée. Le §11 l'interdit sans règle explicite déjà validée : aucune n'existe
dans les Phases 1 à 4.

---

## Réclamations

### `POST /api/v1/claims` — création minimale

Permission : `claims.create`. Quatre champs.

```json
{
  "customerId": "01JC0000000000000000000C01",
  "title": "Colis endommagé à la livraison",
  "claimType": "damage",
  "status": "open"
}
```

Les champs de résolution — `decision`, `followUp`, `result`, `cost`, `closedAt`
— **ne sont pas acceptés à la création** : le §15 l'interdit, une réclamation
naît ouverte.

Il n'y a **pas** de `claimNumber` : le §14 le dit, le diagramme n'en contient
pas. Ni `severity`, ni `legacyId`.

### `POST /api/v1/claims` — liée à un service et une tournée

```json
{
  "customerId": "01JC0000000000000000000C01",
  "orderId": "01JC0000000000000000000O01",
  "orderServiceId": "01JC0000000000000000000S01",
  "tourId": "01JC0000000000000000000T01",
  "title": "Retard de deux jours",
  "description": "Livraison promise le 1er, effectuée le 3.",
  "claimType": "delay",
  "cause": "Véhicule immobilisé",
  "status": "open",
  "responsibleUserId": "01JC0000000000000000000U02"
}
```

`responsibleUserId` est acceptable dès l'ouverture : affecter un dossier n'est
pas le résoudre.

### `PATCH /api/v1/claims/{claim}` — instruction et clôture

Permission : `claims.update`.

```json
{
  "decision": "Remboursement partiel accordé",
  "followUp": "Avoir émis le 12/09",
  "result": "accepted",
  "cost": 240.50,
  "status": "resolved",
  "closedAt": "2026-09-12T16:00:00Z"
}
```

Renseigner `closedAt` clôture le dossier et produit **deux** entrées d'audit :
`claim.updated` et `claim.closed`. La seconde décrit un événement de dossier, et
se cherche autrement dans le journal.

`customerId`, `createdBy` et `createdAt` ne sont pas modifiables : transférer une
réclamation d'un client à l'autre, ou réécrire sa date d'ouverture, ferait perdre
la trace du dossier d'origine.

### `DELETE /api/v1/claims/{claim}`

Permission : `claims.delete`. `204` si la réclamation est ouverte ; `409` sinon :

```json
{ "message": "Une réclamation clôturée ne peut pas être supprimée." }
```

`closedAt` est le seul critère : aucune valeur de `status` n'est interprétée, le
diagramme n'en énumère aucune.

### `GET /api/v1/claims`

| Paramètre | Effet |
|-----------|-------|
| `search` | `title`, `description`, `cause`, `decision`, `follow_up`, `result` |
| `customerId`, `orderId`, `orderServiceId`, `tourId` | Filtres exacts |
| `claimType`, `status`, `responsibleUserId` | Filtres exacts |
| `createdFrom`, `createdTo`, `closedFrom`, `closedTo` | Intervalles |
| `sort` | `created_at`, `closed_at`, `cost`, `status`, `title` |

Ordre par défaut : `created_at` décroissant.

### Routes imbriquées

```text
GET  /api/v1/customers/{customer}/claims
POST /api/v1/customers/{customer}/claims
GET  /api/v1/orders/{order}/claims
GET  /api/v1/tours/{tour}/claims
```

Aucune sous-ressource `actions`, `comments` ou `attachments` : le §17 les écarte,
et les classes correspondantes n'existent pas au diagramme.

---

## Erreurs de périmètre

Le §18 impose de ne jamais se contenter d'un `exists`. Les contrôles réellement
appliqués :

| Cas | Champ | Message |
|-----|-------|---------|
| Commande d'une autre organisation | `orderId` | Cette commande n’appartient pas à l’organisation active. |
| Service d'une autre commande | `orderServiceId` | Ce service n’appartient pas à la commande visée. |
| Tournée d'une autre organisation | `tourId` | Cette tournée n’appartient pas à l’organisation active. |
| Arrêt d'une autre tournée | `tourStopId` | Cet arrêt n’appartient pas à la tournée fournie. |
| Document d'une autre organisation | `signatureDocumentId` / `photoDocumentId` | Ce document n’appartient pas à l’organisation de la commande. |
| Client d'une autre organisation | `customerId` | Ce client n’appartient pas à l’organisation active. |
| Commande d'un autre client | `orderId` | Cette commande n’appartient pas au client de la réclamation. |
| Responsable hors organisation | `responsibleUserId` | Cet utilisateur n’est pas accessible dans l’organisation active. |
| Clôture antérieure à l'ouverture | `closedAt` | La date de clôture ne peut pas précéder la date de création. |

## Codes de réponse

| Statut | Cas |
|--------|-----|
| `401` | Jeton absent, expiré ou révoqué |
| `403` | Permission manquante, ou en-tête `X-Organization-Id` absent |
| `404` | Ressource d'une autre organisation, ou enfant d'un autre parent |
| `405` | `PATCH`/`DELETE` sur un événement ou une preuve — les routes n'existent pas |
| `409` | Suppression d'une réclamation clôturée |
| `422` | Validation : périmètre, coordonnées hors bornes, coût négatif, dates incohérentes, tri interdit |

Une ressource hors périmètre renvoie **404**, jamais 403 : son existence ne se
révèle pas.
