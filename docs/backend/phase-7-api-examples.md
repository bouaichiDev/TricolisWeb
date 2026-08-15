# Exemples d'API — Phase 7 : stock client

En-têtes communs :

```http
Authorization: Bearer <token>
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
Content-Type: application/json
Accept: application/json
```

Le module Stock est **optionnel** : il n'est utilisé que si le client dispose
d'un contrat d'entreposage, comme le note le diagramme.

---

## Articles de stock

### `POST /api/v1/stock-items`

Permission : `stock_items.create`.

```json
{
  "customerId": "01JC0000000000000000000C01",
  "catalogItemId": "01JC0000000000000000000K01",
  "articleCode": "ART-0001",
  "barcode": "3401234567890",
  "description": "Palette Europe 120×80",
  "status": "active"
}
```

**Ni quantité, ni emplacement** : le §6 les interdit ici. Le stock réel vit dans
`StockBalance`, un article pouvant être présent à plusieurs emplacements.

Deux unicités, par client :

| Cas | Message |
|-----|---------|
| Code article déjà pris chez ce client | Ce code article existe déjà chez ce client. |
| Code-barres déjà pris chez ce client | Ce code-barres existe déjà chez ce client. |

Le code-barres **n'est pas unique globalement** : deux clients peuvent employer
le même code interne pour des articles différents.

### Route imbriquée

```text
GET  /api/v1/customers/{customer}/stock-items
POST /api/v1/customers/{customer}/stock-items
```

### `DELETE /api/v1/stock-items/{stockItem}`

`204` si l'article n'engage rien ; `409` sinon :

```json
{ "message": "Impossible de supprimer un article qui porte du stock, un mouvement ou une réservation." }
```

---

## Emplacements

### `POST /api/v1/stock-locations`

Permission : `stock_locations.create`.

```json
{
  "depotId": "01JC00000000000000000000D1",
  "parentLocationId": "01JC0000000000000000000L01",
  "zoneCode": "A",
  "aisle": "12",
  "rack": "3",
  "level": "2",
  "locationCode": "A-12-03-02",
  "barcode": "LOC-A120302",
  "status": "active"
}
```

`zoneCode` reste un attribut : le §9 interdit une table `StockZone`.

Le parent doit relever du **même dépôt** — un rayon n'appartient pas à l'allée
d'un autre entrepôt.

### La hiérarchie, et ce qu'elle refuse

Quatre situations renvoient `422`, toutes testées :

| Cas | Message |
|-----|---------|
| Parent égal à soi-même | Un emplacement ne peut pas être son propre parent. |
| Parent descendant direct ou indirect | Cet emplacement parent est un descendant : le rattachement créerait une boucle. |
| Parent d'un autre dépôt | L'emplacement parent doit appartenir au même dépôt. |
| Parent introuvable | Cet emplacement parent est introuvable. |

**Aucune profondeur maximale n'est fixée** : le §10 l'interdit.

### `GET /api/v1/stock-locations/tree`

Permission : `stock_locations.view`. Paramètre optionnel `depotId`.

L'arbre est **dérivé** de `stock_locations` par un seul `SELECT`, assemblé en
mémoire : aucune table supplémentaire, aucune requête par niveau.

```json
{
  "data": [
    {
      "id": "01JC…A",
      "locationCode": "A",
      "children": [
        {
          "id": "01JC…A1",
          "locationCode": "A-1",
          "children": [
            { "id": "01JC…A11", "locationCode": "A-1-1", "children": [] }
          ]
        }
      ]
    }
  ],
  "meta": {}
}
```

---

## Mouvements

### `POST /api/v1/stock-movements` — entrée

Permission : `stock_movements.create`. Pas de source : c'est une entrée.

```json
{
  "stockItemId": "01JC0000000000000000000S01",
  "destinationLocationId": "01JC0000000000000000000L01",
  "movementType": "inbound",
  "quantity": 120
}
```

Le solde du couple article + emplacement est créé s'il n'existe pas, puis
incrémenté.

### `POST /api/v1/stock-movements` — transfert avec source métier

```json
{
  "stockItemId": "01JC0000000000000000000S01",
  "sourceLocationId": "01JC0000000000000000000L01",
  "destinationLocationId": "01JC0000000000000000000L02",
  "movementType": "transfer",
  "quantity": 12,
  "sourceEntityType": "order",
  "sourceEntityId": "01JC0000000000000000000O01"
}
```

`sourceEntityType` n'accepte que des **alias de la morph map** — `order`,
`package`, `tour`, `invoice`… La liste est dérivée de la morph map, jamais
recopiée. Un nom de classe PHP est refusé :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "sourceEntityType": ["Ce type d’entité source n’est pas reconnu."] }
}
```

`movementType` reste libre : le diagramme n'en énumère aucune valeur. `inbound`,
`outbound` et `transfer` sont des exemples, sans portée normative.

### Les trois règles structurelles

| Cas | Réponse |
|-----|---------|
| Ni source ni destination | `422` — Un mouvement doit avoir au moins une source ou une destination. |
| Source égale à destination | `422` — La source et la destination doivent être différentes. |
| Source et destination dans deux dépôts | `422` — enregistrez une sortie puis une entrée. |

Le transfert inter-dépôt est refusé faute de règle définie pour le représenter
(§21). Il se fait en deux mouvements.

### Débit supérieur au disponible

```json
{ "message": "Stock disponible insuffisant : 5.000 disponible, 10.000 demandé." }
```

`409`. Le contrôle porte sur `availableQuantity`, pas sur `quantity` : **on ne
sort pas du stock déjà réservé** pour une commande.

### Pas de modification, pas de suppression

```http
PATCH  /api/v1/stock-movements/{id}   → 405
DELETE /api/v1/stock-movements/{id}   → 405
```

Un mouvement est historique. Une correction est un nouveau mouvement.

---

## Réservations

### `POST /api/v1/stock-reservations`

Permission : `stock_reservations.create`.

```json
{
  "stockItemId": "01JC0000000000000000000S01",
  "stockLocationId": "01JC0000000000000000000L01",
  "orderLineId": "01JC0000000000000000000N01",
  "quantity": 5,
  "status": "active"
}
```

Le solde est **verrouillé** puis contrôlé. Effet sur un solde de 20 :

```text
avant   quantity 20   reserved  0   available 20
après   quantity 20   reserved  5   available 15
```

La ligne de commande doit venir d'une commande du **client de l'article** :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "orderLineId": ["Cette ligne de commande n’appartient pas au client de l’article."] }
}
```

Réserver plus que disponible renvoie `409`.

### `POST /api/v1/stock-reservations/{id}/release`

Permission : `stock_reservations.release` — distincte de `update` : libérer du
stock rend de la disponibilité à d'autres commandes.

```json
{ "status": "released" }
```

**La réservation n'est pas supprimée** : `releasedAt` est renseigné, la ligne
reste. Le solde revient à `reserved 0 / available 20`.

Une **double libération** renvoie `409` :

```json
{ "message": "Cette réservation est déjà libérée." }
```

Sans ce refus, appeler la route deux fois libérerait du stock jamais réservé.

### `PATCH /api/v1/stock-reservations/{id}`

Permission : `stock_reservations.update`. **Le statut seul** est modifiable :

```json
{ "status": "confirmed" }
```

`quantity` et les trois clés étrangères sont ignorées — les changer devrait
ajuster le solde sous verrou, ce qui est une opération de stock. Pour réserver
autrement, on libère et on recrée.

Pas de `DELETE` : une réservation libérée reste, pour la traçabilité.

---

## Soldes — lecture seule

```text
GET /api/v1/stock-balances
GET /api/v1/stock-balances/{stockBalance}
GET /api/v1/customers/{customer}/stock-balances
```

| Paramètre | Effet |
|-----------|-------|
| `stockItemId`, `stockLocationId`, `customerId` | Filtres exacts |
| `availableOnly=1` | Ne retourne que les soldes réellement disponibles |
| `sort` | `quantity`, `reserved_quantity`, `available_quantity`, `updated_at` |

```json
{
  "data": [
    {
      "id": "01JC0000000000000000000B01",
      "stockItemId": "01JC0000000000000000000S01",
      "stockLocationId": "01JC0000000000000000000L01",
      "quantity": "120.000",
      "reservedQuantity": "5.000",
      "availableQuantity": "115.000",
      "updatedAt": "2026-09-30T09:14:22+00:00",
      "articleCode": "ART-0001",
      "locationCode": "A-12-03-02"
    }
  ]
}
```

**Aucun `POST`, `PATCH` ni `DELETE`** — ils renvoient `405`. Le §14 interdit un
CRUD public qui permettrait de fixer arbitrairement les quantités. Les soldes ne
bougent que par les mouvements et les réservations, sous verrou.

`availableQuantity` est toujours `quantity − reservedQuantity`, dérivée à chaque
écriture.

---

## Erreurs

| Statut | Cas |
|--------|-----|
| `401` | Jeton absent, expiré ou révoqué |
| `403` | Permission manquante, ou en-tête `X-Organization-Id` absent |
| `404` | Ressource hors périmètre — via le client ou via le dépôt |
| `405` | `PATCH`/`DELETE` sur un mouvement, `DELETE` sur une réservation, écriture sur un solde |
| `409` | Disponibilité insuffisante, double libération, article ou emplacement encore engagé |
| `422` | Périmètre, unicité, cycle de hiérarchie, règles source/destination, quantité non positive |
