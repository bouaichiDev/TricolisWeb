# Exemples d'API — Phase 6 : facturation et décomptes

Répond au §40 du prompt. Tous les exemples portent exactement les attributs du
diagramme.

En-têtes communs :

```http
Authorization: Bearer <token>
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
Content-Type: application/json
Accept: application/json
```

---

## Factures

### `POST /api/v1/invoices`

Permission : `invoices.create`. **Au moins une ligne est exigée** —
`Invoice "1" *-- "1..*" InvoiceLine` interdit une facture vide.

```json
{
  "customerId": "01JC0000000000000000000C01",
  "invoiceNumber": "INV-2026-0001",
  "invoiceDate": "2026-09-30",
  "periodFrom": "2026-09-01",
  "periodTo": "2026-09-30",
  "currencyCode": "MAD",
  "externalReference": "PO-4471",
  "remark": "Facturation mensuelle",
  "status": "draft",
  "lines": [
    {
      "orderServiceId": "01JC0000000000000000000S01",
      "orderId": "01JC0000000000000000000O01",
      "lineNumber": 1,
      "serviceCode": "TRANSPORT",
      "description": "Transport Casablanca — Rabat",
      "customerOrderReference": "CMD-8891",
      "quantity": 2,
      "unitPrice": 100,
      "taxRate": 20,
      "serviceCompletedAt": "2026-09-12T16:00:00Z",
      "status": "billable",
      "addressSnapshot": {
        "addressCode": "ADR-042",
        "name": "IKEA Casablanca",
        "addressLine1": "12 boulevard Zerktouni",
        "postalCode": "20250",
        "city": "Casablanca",
        "country": "MA"
      }
    },
    {
      "lineNumber": 2,
      "description": "Frais de dossier",
      "quantity": 1,
      "unitPrice": 50,
      "discountRate": 10,
      "taxRate": 20,
      "status": "billable"
    }
  ]
}
```

La seconde ligne n'a **ni service ni commande** : c'est une ligne libre, que la
nullabilité de `orderServiceId` autorise explicitement.

`invoiceNumber` est fourni par l'appelant et unique dans l'organisation : aucune
règle de génération n'est définie pour les factures.

Réponse `201` — **les totaux sont calculés** :

```json
{
  "data": {
    "id": "01JC0000000000000000000I01",
    "organizationId": "01JABCDEFGHJKMNPQRSTVWXYZ",
    "customerId": "01JC0000000000000000000C01",
    "invoiceNumber": "INV-2026-0001",
    "invoiceDate": "2026-09-30",
    "periodFrom": "2026-09-01",
    "periodTo": "2026-09-30",
    "currencyCode": "MAD",
    "subtotal": "245.00",
    "taxTotal": "49.00",
    "total": "294.00",
    "externalReference": "PO-4471",
    "remark": "Facturation mensuelle",
    "status": "draft",
    "createdAt": "2026-09-30T09:14:22+00:00",
    "lines": [
      {
        "id": "01JC0000000000000000000L01",
        "lineNumber": 1,
        "description": "Transport Casablanca — Rabat",
        "quantity": "2.000",
        "unitPrice": "100.00",
        "discountRate": "0.00",
        "taxRate": "20.00",
        "totalExcludingTax": "200.00",
        "totalIncludingTax": "240.00",
        "status": "billable",
        "addressSnapshot": { "city": "Casablanca", "country": "MA" }
      }
    ]
  },
  "meta": {}
}
```

Détail du calcul :

```text
ligne 1 : 2 × 100 = 200 HT ; +20 % = 240 TTC
ligne 2 : 1 × 50 = 50, −10 % = 45 HT ; +20 % = 54 TTC
facture : subtotal 245 ; total 294 ; taxTotal = 294 − 245 = 49
```

### Les totaux envoyés sont ignorés

```json
{ "…": "…", "subtotal": 99999, "total": 99999 }
```

La facture porte quand même `245.00` et `294.00`. Le §11 l'exige : « ne jamais
faire confiance aux totaux envoyés sans validation ». Les six champs calculés
sont absents des Form Requests.

### `GET /api/v1/invoices`

| Paramètre | Effet |
|-----------|-------|
| `search` | `invoice_number`, `external_reference`, `remark` |
| `customerId`, `invoiceNumber`, `currencyCode`, `status`, `externalReference` | Filtres exacts |
| `invoiceDateFrom`, `invoiceDateTo`, `periodFrom`, `periodTo` | Intervalles |
| `sort` | `invoice_number`, `invoice_date`, `period_from`, `period_to`, `subtotal`, `tax_total`, `total`, `status`, `created_at` |

La liste ne charge **aucune ligne** — seulement `lineCount`.

### `PATCH /api/v1/invoices/{invoice}`

Permission : `invoices.update`.

```json
{ "status": "issued", "remark": "Envoyée le 30/09" }
```

`customerId` n'est pas modifiable : les lignes référencent des commandes de ce
client. Les totaux non plus : ils sont dérivés.

### `DELETE /api/v1/invoices/{invoice}`

Permission : `invoices.delete`. `204`, lignes et snapshots compris.

---

## Lignes de facture

### `POST /api/v1/invoices/{invoice}/lines`

Permission : `invoice_lines.create`. Ajoute une ligne et recalcule les totaux.

```json
{
  "orderServiceId": "01JC0000000000000000000S02",
  "lineNumber": 3,
  "description": "Manutention",
  "quantity": 1,
  "unitPrice": 75,
  "taxRate": 20,
  "status": "billable"
}
```

Trois refus, tous testés :

| Cas | Champ | Message |
|-----|-------|---------|
| Service d'un autre client | `orderServiceId` | Ce service n’appartient à aucune commande de ce client. |
| Service déjà facturé | `orderServiceId` | Ce service est déjà facturé sur une autre ligne. |
| Commande différente de celle du service | `orderId` | Cette commande n’est pas celle du service facturé. |

### `PATCH /api/v1/invoices/{invoice}/lines/{line}`

Modifier `quantity`, `unitPrice`, `discountRate` ou `taxRate` **recalcule** les
deux totaux de la ligne, puis les trois de la facture.

```json
{ "quantity": 3, "taxRate": 10 }
```

### `DELETE /api/v1/invoices/{invoice}/lines/{line}`

`204`, sauf si c'est la dernière — `409` :

```json
{ "message": "Une facture doit conserver au moins une ligne : supprimez la facture pour retirer la dernière." }
```

---

## Décomptes fournisseurs

### `POST /api/v1/provider-settlements`

Permission : `provider_settlements.create`.

```json
{
  "providerId": "01JC0000000000000000000P01",
  "settlementNumber": "STL-2026-0001",
  "periodFrom": "2026-09-01",
  "periodTo": "2026-09-30",
  "taxTotal": 30,
  "status": "draft",
  "lines": [
    {
      "orderServiceId": "01JC0000000000000000000S01",
      "description": "Course sous-traitée Casablanca — Rabat",
      "quantity": 2,
      "unitCost": 80
    },
    {
      "description": "Forfait carburant",
      "quantity": 1,
      "unitCost": 40
    }
  ]
}
```

`taxTotal` est **saisi**, pas calculé : le §21 interdit d'inventer une TVA
fournisseur, et la ligne de décompte ne porte aucun taux — le §18 interdit d'en
ajouter.

Réponse `201` :

```json
{
  "data": {
    "id": "01JC0000000000000000000T01",
    "providerId": "01JC0000000000000000000P01",
    "settlementNumber": "STL-2026-0001",
    "subtotal": "200.00",
    "taxTotal": "30.00",
    "total": "230.00",
    "status": "draft",
    "lines": [
      { "description": "Course sous-traitée Casablanca — Rabat", "quantity": "2.000", "unitCost": "80.00", "totalCost": "160.00" },
      { "description": "Forfait carburant", "quantity": "1.000", "unitCost": "40.00", "totalCost": "40.00" }
    ]
  },
  "meta": {}
}
```

### Le contrôle croisé fournisseur ↔ tournée

Le §18 demande que le service soit « cohérent avec le Provider du settlement
**lorsque cette relation existe via Tour/affectation** ». Elle existe depuis la
Phase 4 : service → arrêt → tournée → fournisseur.

Le contrôle est donc **conditionnel** :

- service jamais planifié, ou planifié sur une tournée sans fournisseur → **accepté**,
  sinon il serait impossible de payer une prestation sous-traitée hors tournée ;
- service planifié sur une tournée d'un **autre** fournisseur → **refusé** :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "lines.0.orderServiceId": ["Ce service a été planifié chez un autre fournisseur."] }
}
```

### Route imbriquée

```text
GET  /api/v1/providers/{provider}/settlements
POST /api/v1/providers/{provider}/settlements
```

En `POST`, `providerId` vient de l'URL.

### `PATCH /api/v1/provider-settlements/{providerSettlement}`

Modifier `taxTotal` recalcule `total` :

```json
{ "taxTotal": 16 }
```

---

## Les deux flux d'un même service

Le point à retenir de la phase. Un `OrderService` peut être :

- **facturé une fois** au client — `invoice_lines.order_service_id` UNIQUE ;
- **décompté une fois** au fournisseur — `provider_settlement_lines.order_service_id` UNIQUE.

Les deux contraintes sont indépendantes : le même service peut porter les deux.
Le §22 le pose, un test le vérifie.

Facturer deux fois le même service, ou le décompter deux fois, renvoie `422`.

**Aucun statut n'est modifié.** Créer une ligne ne fait pas passer le service à
`INVOICED` : le §23 l'interdit sans règle existante, et `OrderServiceStatus` n'a
pas de moteur de transition. Le fait « facturé » se lit à l'existence de la
ligne, pas à un statut recopié qui pourrait diverger.

---

## Erreurs

| Statut | Cas |
|--------|-----|
| `401` | Jeton absent, expiré ou révoqué |
| `403` | Permission manquante, ou en-tête `X-Organization-Id` absent |
| `404` | Ressource d'une autre organisation, ou ligne d'un autre document |
| `409` | Suppression de la dernière ligne d'une facture ou d'un décompte |
| `422` | Périmètre, numéro dupliqué, service déjà facturé ou décompté, période inversée, montant négatif, taux hors `0–100`, tri interdit |

## Endpoints volontairement absents

Aucun `/pay`, `/validate`, `/approve`, `/send`, `/export`, `/credit-note` : les
§8 et §17 les écartent, et les classes correspondantes ne figurent pas au
diagramme.
