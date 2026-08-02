# Exemples de payload — création de commande

Ce document répond au §27 du cahier des charges « Phase 2 ». Tous les exemples
s'adressent à :

```http
POST /api/v1/orders
Authorization: Bearer <token>
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
Content-Type: application/json
```

Permission requise : `orders.create`.

---

## Ce qu'il faut savoir avant de lire les exemples

**Le numéro de commande n'est jamais fourni.** Il est attribué par la séquence,
sous verrou, au format `ORD-2026-000001`. Un `orderNumber` envoyé est ignoré.

**Le statut initial n'est pas fourni non plus.** Toute commande naît en
brouillon et évolue par `PATCH /api/v1/orders/{order}/status`.

**Une commande contient au moins une ligne et au moins un service** — le
diagramme impose `Order 1 *-- 1..* OrderLine` et `Order 1 *-- 1..* OrderService`.

**L'adresse est portée par le service, pas par la commande.** Une commande qui
charge à Casablanca et livre à Rabat a deux services, chacun avec son adresse et
son créneau. Les arrêts physiques sont produits par la planification, en
Phase 3.

**Les colis se référencent par clé locale.** Dans une création complète, les
identifiants définitifs n'existent pas encore : `key` nomme un colis dans le
payload, `parentKey` et `packageKey` y renvoient. Les lignes se désignent par
leur index dans le tableau `lines` (`"0"`, `"1"`, …).

**Tout est atomique.** Si une sous-ressource échoue, rien n'est créé, et
l'erreur porte le chemin exact du champ : `services.0.addressId`,
`packages.1.lines.0.lineKey`.

---

## 1. Commande manuelle simple

Une livraison, deux articles saisis à la main.

```json
{
  "customerId": "01JC0000000000000000000001",
  "agencyId": "01JC0000000000000000000002",
  "orderDate": "2026-08-01T09:00:00Z",
  "customerReference": "BC-2026-4471",
  "lines": [
    { "name": "Canapé 3 places", "articleCode": "CAN-3P", "quantity": 1, "weight": 45.5, "volume": 1.8 },
    { "name": "Table basse", "articleCode": "TAB-01", "quantity": 2, "weight": 12, "volume": 0.4 }
  ],
  "services": [
    {
      "serviceId": "01JC0000000000000000000010",
      "addressId": "01JC0000000000000000000020",
      "serviceNumber": "SRV-1",
      "sequence": 1,
      "requestedDate": "2026-08-05",
      "quantity": 1,
      "unit": "delivery",
      "requiredTimeMinutes": 45,
      "remainingTimeMinutes": 45,
      "weight": 69.5,
      "volume": 2.6,
      "packageCount": 0,
      "customerUnitPrice": 350,
      "customerTotalPrice": 350,
      "providerUnitCost": 220,
      "providerTotalCost": 220,
      "status": "draft"
    }
  ]
}
```

## 2. Commande depuis le catalogue

`catalogItemId` remplace la description manuelle : nom, code article,
code-barres, poids, volume et dimensions sont **recopiés** dans la ligne. La
commande devient autonome — renommer l'article demain ne réécrit pas cette
commande.

L'article doit appartenir à un catalogue **actif** du client de la commande ;
un article d'un autre client renvoie 422 sur `lines.0.catalogItemId`.

```json
{
  "customerId": "01JC0000000000000000000001",
  "agencyId": "01JC0000000000000000000002",
  "orderDate": "2026-08-01T09:00:00Z",
  "source": "catalog",
  "lines": [
    { "catalogItemId": "01JC0000000000000000000030", "quantity": 4 },
    { "catalogItemId": "01JC0000000000000000000031", "quantity": 2, "sellingPrice": 89.9 }
  ],
  "services": [
    {
      "serviceId": "01JC0000000000000000000010",
      "addressId": "01JC0000000000000000000020",
      "serviceNumber": "SRV-1", "sequence": 1,
      "requestedDate": "2026-08-06",
      "quantity": 1, "unit": "delivery",
      "requiredTimeMinutes": 30, "remainingTimeMinutes": 30,
      "weight": 0, "volume": 0, "packageCount": 0,
      "customerUnitPrice": 0, "customerTotalPrice": 0,
      "providerUnitCost": 0, "providerTotalCost": 0,
      "status": "draft"
    }
  ]
}
```

Le second article illustre l'ajustement ponctuel : `sellingPrice` fourni prime
sur la valeur du catalogue.

## 3. Commande à plusieurs adresses

Un enlèvement chez le fournisseur, une livraison chez le client final. Deux
services, deux adresses, deux créneaux.

```json
{
  "customerId": "01JC0000000000000000000001",
  "agencyId": "01JC0000000000000000000002",
  "depotId": "01JC0000000000000000000003",
  "orderDate": "2026-08-01T09:00:00Z",
  "lines": [
    { "name": "Cuisine équipée", "quantity": 1, "weight": 320, "volume": 12 }
  ],
  "services": [
    {
      "serviceId": "01JC0000000000000000000011",
      "addressId": "01JC0000000000000000000021",
      "serviceNumber": "SRV-PICKUP", "sequence": 1,
      "requestedDate": "2026-08-04",
      "requestedFrom": "2026-08-04T08:00:00Z",
      "requestedTo": "2026-08-04T12:00:00Z",
      "quantity": 1, "unit": "pickup",
      "requiredTimeMinutes": 60, "remainingTimeMinutes": 60,
      "weight": 320, "volume": 12, "packageCount": 0,
      "customerUnitPrice": 0, "customerTotalPrice": 0,
      "providerUnitCost": 0, "providerTotalCost": 0,
      "instructions": "Quai 3, se présenter à l'accueil.",
      "status": "draft"
    },
    {
      "serviceId": "01JC0000000000000000000010",
      "addressId": "01JC0000000000000000000022",
      "serviceNumber": "SRV-DELIVERY", "sequence": 2,
      "requestedDate": "2026-08-05",
      "requestedFrom": "2026-08-05T14:00:00Z",
      "requestedTo": "2026-08-05T18:00:00Z",
      "quantity": 1, "unit": "delivery",
      "requiredTimeMinutes": 120, "remainingTimeMinutes": 120,
      "weight": 320, "volume": 12, "packageCount": 0,
      "customerUnitPrice": 1200, "customerTotalPrice": 1200,
      "providerUnitCost": 800, "providerTotalCost": 800,
      "status": "draft"
    }
  ]
}
```

`sequence` doit être unique dans la commande, `serviceNumber` aussi.

## 4. Commande avec plusieurs contacts

`contactId` rattache un contact partagé et **recopie** ses coordonnées. Sans lui,
les valeurs fournies constituent un contact ponctuel — `firstName` devient alors
obligatoire.

Un seul contact principal est admis par rôle : déclarer un second `isPrimary`
sur le même rôle rétrograde automatiquement le précédent.

```json
{
  "customerId": "01JC0000000000000000000001",
  "agencyId": "01JC0000000000000000000002",
  "orderDate": "2026-08-01T09:00:00Z",
  "lines": [{ "name": "Mobilier de bureau", "quantity": 12, "weight": 8 }],
  "services": [
    {
      "serviceId": "01JC0000000000000000000010",
      "addressId": "01JC0000000000000000000020",
      "serviceNumber": "SRV-1", "sequence": 1,
      "requestedDate": "2026-08-07",
      "quantity": 1, "unit": "delivery",
      "requiredTimeMinutes": 90, "remainingTimeMinutes": 90,
      "weight": 96, "volume": 4, "packageCount": 0,
      "customerUnitPrice": 0, "customerTotalPrice": 0,
      "providerUnitCost": 0, "providerTotalCost": 0,
      "status": "draft",
      "contacts": [
        { "contactId": "01JC0000000000000000000040", "contactRole": "delivery", "isPrimary": true },
        { "firstName": "Sanaa", "lastName": "El Idrissi", "mobile": "+212661234567", "contactRole": "operations" },
        { "firstName": "Gardien", "phone": "+212522334455", "contactRole": "emergency" }
      ]
    }
  ]
}
```

Rôles admis : `load`, `delivery`, `billing`, `operations`, `emergency`, `other`.

## 5. Commande avec colis imbriqués

Une palette qui contient deux cartons. `key` nomme un colis dans le payload,
`parentKey` désigne son parent — **le parent doit apparaître avant l'enfant**.

`lines[].lineKey` désigne une ligne par son index dans `lines`. La somme des
quantités affectées d'une ligne ne peut pas dépasser sa quantité commandée.

```json
{
  "customerId": "01JC0000000000000000000001",
  "agencyId": "01JC0000000000000000000002",
  "orderDate": "2026-08-01T09:00:00Z",
  "lines": [
    { "name": "Chaise", "articleCode": "CHA-01", "quantity": 10, "weight": 6 },
    { "name": "Coussin", "articleCode": "COU-01", "quantity": 20, "weight": 0.5 }
  ],
  "packages": [
    {
      "key": "palette-1",
      "packageTypeId": "01JC0000000000000000000050",
      "groupingTypeId": "01JC0000000000000000000051",
      "barcode": "PLT-000001",
      "reference": "Palette Europe",
      "weight": 70, "volume": 1.2
    },
    {
      "key": "carton-a",
      "parentKey": "palette-1",
      "barcode": "CTN-000001",
      "weight": 36,
      "lines": [{ "lineKey": "0", "quantity": 6 }]
    },
    {
      "key": "carton-b",
      "parentKey": "palette-1",
      "barcode": "CTN-000002",
      "weight": 34,
      "lines": [
        { "lineKey": "0", "quantity": 4 },
        { "lineKey": "1", "quantity": 20 }
      ]
    }
  ],
  "services": [
    {
      "serviceId": "01JC0000000000000000000010",
      "addressId": "01JC0000000000000000000020",
      "serviceNumber": "SRV-1", "sequence": 1,
      "requestedDate": "2026-08-08",
      "quantity": 1, "unit": "delivery",
      "requiredTimeMinutes": 45, "remainingTimeMinutes": 45,
      "weight": 70, "volume": 1.2, "packageCount": 3,
      "customerUnitPrice": 0, "customerTotalPrice": 0,
      "providerUnitCost": 0, "providerTotalCost": 0,
      "status": "draft",
      "packages": [{ "packageKey": "palette-1", "quantity": 1 }]
    }
  ]
}
```

Contraintes de la hiérarchie : le parent appartient à la même commande, aucun
cycle n'est possible, la profondeur est bornée à 5 niveaux, et le code-barres
est unique dans toute la plateforme.

## 6. Commande avec plusieurs services sur la même adresse

Livraison puis montage au même endroit : deux prestations distinctes, deux
lignes de facturation futures.

```json
{
  "customerId": "01JC0000000000000000000001",
  "agencyId": "01JC0000000000000000000002",
  "orderDate": "2026-08-01T09:00:00Z",
  "lines": [{ "name": "Armoire 3 portes", "quantity": 2, "weight": 85, "volume": 2.4 }],
  "packages": [{ "key": "colis-1", "barcode": "ARM-000001", "weight": 170 }],
  "services": [
    {
      "serviceId": "01JC0000000000000000000010",
      "addressId": "01JC0000000000000000000020",
      "serviceNumber": "SRV-DEL", "sequence": 1,
      "requestedDate": "2026-08-09",
      "quantity": 2, "unit": "delivery",
      "requiredTimeMinutes": 40, "remainingTimeMinutes": 40,
      "weight": 170, "volume": 4.8, "packageCount": 1,
      "customerUnitPrice": 200, "customerTotalPrice": 400,
      "providerUnitCost": 130, "providerTotalCost": 260,
      "status": "draft",
      "packages": [{ "packageKey": "colis-1", "quantity": 1 }]
    },
    {
      "serviceId": "01JC0000000000000000000012",
      "addressId": "01JC0000000000000000000020",
      "serviceNumber": "SRV-MNT", "sequence": 2,
      "requestedDate": "2026-08-09",
      "quantity": 2, "unit": "assembly",
      "requiredTimeMinutes": 90, "remainingTimeMinutes": 90,
      "weight": 0, "volume": 0, "packageCount": 0,
      "customerUnitPrice": 150, "customerTotalPrice": 300,
      "providerUnitCost": 100, "providerTotalCost": 200,
      "instructions": "Montage sur place, évacuation des emballages incluse.",
      "status": "draft"
    }
  ]
}
```

---

## Erreurs les plus fréquentes

| Statut | Cas | Chemin renvoyé |
|--------|-----|----------------|
| 422 | Client d'une autre organisation | `customerId` |
| 422 | Article d'un catalogue d'un autre client | `lines.0.catalogItemId` |
| 422 | Service d'une autre organisation | `services.0.serviceId` |
| 422 | Adresse non rattachée à l'organisation | `services.0.addressId` |
| 422 | `parentKey` déclaré après l'enfant | `packages.1.parentKey` |
| 422 | `lineKey` inconnu | `packages.0.lines.0.lineKey` |
| 422 | Quantité affectée supérieure à la quantité commandée | `packages.0.lines.0.quantity` |
| 422 | Code-barres déjà utilisé | `barcode` |
| 422 | `sequence` ou `serviceNumber` en double dans la commande | `sequence` / `serviceNumber` |
| 403 | Permission `orders.create` absente | — |
| 404 | Commande d'une autre organisation | — |
| 409 | Contenu modifié sur une commande engagée | — |

## Cycle de vie ensuite

```http
PATCH  /api/v1/orders/{order}/status      { "status": "confirmed" }
PATCH  /api/v1/orders/{order}/status      { "status": "cancelled", "reasonText": "Client absent" }
POST   /api/v1/orders/{order}/duplicate   { "documents": false }
GET    /api/v1/orders/{order}/history
```

Transitions posables manuellement : `draft`, `confirmed`, `ready`, `cancelled`.
Un motif est obligatoire pour annuler. Les statuts de planification et de
facturation sont produits par leurs modules.
