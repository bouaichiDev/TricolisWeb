# Phase 9 — Exemples d'API : communication et templates

Toutes les requêtes exigent `Authorization: Bearer <token>` et
`X-Organization-Id: <ulid>`. Les réponses suivent l'enveloppe du projet :
`data`, `meta`, `links`.

Aucun attribut de ces exemples n'est absent du diagramme.

---

## 1. Créer un modèle e-mail

```http
POST /api/v1/templates
```

```json
{
  "code": "delivery-confirmation",
  "name": "Confirmation de livraison",
  "channel": "email",
  "templateType": "delivery_confirmation",
  "subjectTemplate": "Votre livraison {{ order_number }}",
  "bodyTemplate": "Bonjour {{ customer_name }}, votre commande {{ order_number }} est livrée.",
  "language": "fr",
  "availableVariables": ["order_number", "customer_name"],
  "isDefault": true,
  "isActive": true
}
```

`201 Created` :

```json
{
  "data": {
    "id": "01JZ8Q2M5F3K7P9R1T4V6X8Z0B",
    "organizationId": "01JZ8Q2M5F3K7P9R1T4V6X8Z0A",
    "serviceId": null,
    "code": "delivery-confirmation",
    "name": "Confirmation de livraison",
    "channel": "email",
    "templateType": "delivery_confirmation",
    "subjectTemplate": "Votre livraison {{ order_number }}",
    "bodyTemplate": "Bonjour {{ customer_name }}, votre commande {{ order_number }} est livrée.",
    "language": "fr",
    "availableVariables": ["order_number", "customer_name"],
    "isDefault": true,
    "isActive": true,
    "createdAt": "2026-08-07T09:00:00+00:00",
    "updatedAt": "2026-08-07T09:00:00+00:00"
  },
  "meta": []
}
```

## 2. Créer un modèle SMS — sans objet

```http
POST /api/v1/templates
```

```json
{
  "code": "arrival-soon-sms",
  "name": "Arrivée imminente",
  "channel": "sms",
  "templateType": "arrival_soon",
  "bodyTemplate": "Votre livraison {{ order_number }} arrive dans {{ minutes }} minutes.",
  "language": "fr",
  "availableVariables": ["order_number", "minutes"]
}
```

`201 Created` — `subjectTemplate` reste `null`. Un SMS n'a pas d'objet, et le
§11 interdit de l'exiger.

Le même corps avec `"channel": "email"` et sans objet donne `422` :

```json
{
  "message": "Le canal e-mail exige un objet.",
  "errors": { "subjectTemplate": ["Le canal e-mail exige un objet."] }
}
```

## 3. Créer une règle

```http
POST /api/v1/communication-rules
```

```json
{
  "templateId": "01JZ8Q2M5F3K7P9R1T4V6X8Z0B",
  "eventType": "service_completed",
  "recipientRole": "delivery_contact",
  "delayValue": 15,
  "delayUnit": "minutes",
  "conditions": {
    "all": [
      { "field": "order_status", "operator": "eq", "value": "completed" },
      { "field": "package_count", "operator": "gte", "value": 1 }
    ]
  },
  "isAutomatic": true,
  "isActive": true
}
```

`201 Created`. `delayUnit` accepte `minutes`, `hours` ou `days` — les unités que
le moteur technique sait ajouter. Aucun enum n'est créé pour elle (§17).

---

## 4. Créer une communication manuelle, sans modèle

```http
POST /api/v1/order-communications
```

```json
{
  "orderId": "01JZ8Q2M5F3K7P9R1T4V6X8Z10",
  "channel": "email",
  "communicationType": "custom",
  "recipientRole": "custom",
  "recipientName": "Marie Dupont",
  "recipientEmail": "marie.dupont@example.test",
  "subject": "Précision sur votre livraison",
  "body": "Bonjour, le chauffeur passera entre 14h et 16h."
}
```

`201 Created`, statut `draft`. Sans modèle, `body` est obligatoire.

## 5. Créer une communication depuis un modèle

```http
POST /api/v1/orders/01JZ8Q2M5F3K7P9R1T4V6X8Z10/communications
```

```json
{
  "templateId": "01JZ8Q2M5F3K7P9R1T4V6X8Z0B",
  "channel": "email",
  "communicationType": "delivery_confirmation",
  "recipientRole": "customer",
  "templateVariables": {
    "order_number": "CMD-2026-0042",
    "customer_name": "Marie"
  }
}
```

`201 Created` :

```json
{
  "data": {
    "id": "01JZ8Q2M5F3K7P9R1T4V6X8Z11",
    "orderId": "01JZ8Q2M5F3K7P9R1T4V6X8Z10",
    "templateId": "01JZ8Q2M5F3K7P9R1T4V6X8Z0B",
    "communicationRuleId": null,
    "channel": "email",
    "communicationType": "delivery_confirmation",
    "recipientRole": "customer",
    "recipientName": "Transports Atlas",
    "recipientEmail": "contact@atlas.example",
    "recipientPhone": "+212600000000",
    "subject": "Votre livraison CMD-2026-0042",
    "body": "Bonjour Marie, votre commande CMD-2026-0042 est livrée.",
    "templateVariables": { "order_number": "CMD-2026-0042", "customer_name": "Marie" },
    "status": "draft",
    "scheduledAt": null,
    "queuedAt": null,
    "sentAt": null,
    "deliveredAt": null,
    "readAt": null,
    "failedAt": null,
    "providerMessageId": null,
    "providerResponse": null,
    "errorMessage": null,
    "createdBy": "01JZ8Q2M5F3K7P9R1T4V6X8Z0C",
    "attachments": [],
    "createdAt": "2026-08-07T09:05:00+00:00",
    "updatedAt": "2026-08-07T09:05:00+00:00"
  },
  "meta": []
}
```

Trois choses s'y lisent :

- **le destinataire est déduit du rôle** — les coordonnées du client ont écrasé
  toute valeur fournie dans le payload ;
- **le contenu est rendu puis figé** — modifier le modèle ensuite ne le
  changera pas ;
- **le statut est `draft`**, jamais inventé : rien n'a été demandé qui le fasse
  partir.

## 6. Communication programmée

```json
{
  "orderId": "01JZ8Q2M5F3K7P9R1T4V6X8Z10",
  "channel": "sms",
  "communicationType": "appointment_reminder",
  "recipientRole": "delivery_contact",
  "body": "Rappel : livraison demain matin.",
  "scheduledAt": "2026-08-08T07:00:00+00:00"
}
```

`201 Created`, statut **`scheduled`**. La commande
`communications:process-scheduled`, planifiée chaque minute, la mettra en file
quand l'heure sera venue.

---

## 7. Joindre un document

```http
POST /api/v1/order-communications/01JZ8Q2M5F3K7P9R1T4V6X8Z11/attachments
```

```json
{ "documentId": "01JZ8Q2M5F3K7P9R1T4V6X8Z20" }
```

`201 Created` :

```json
{
  "data": {
    "id": "01JZ8Q2M5F3K7P9R1T4V6X8Z21",
    "communicationId": "01JZ8Q2M5F3K7P9R1T4V6X8Z11",
    "documentId": "01JZ8Q2M5F3K7P9R1T4V6X8Z20",
    "fileNameSnapshot": "bon-de-livraison.pdf",
    "mimeTypeSnapshot": "application/pdf",
    "createdAt": "2026-08-07T09:06:00+00:00"
  },
  "meta": []
}
```

Les deux snapshots sont figés : renommer le document ensuite ne les change pas.
Aucune route `PATCH` n'existe — il n'y a rien à modifier.

Le même document une seconde fois : `409`.

---

## 8. Mettre en file, annuler, relancer

```http
POST /api/v1/order-communications/{id}/queue
POST /api/v1/order-communications/{id}/cancel
POST /api/v1/order-communications/{id}/retry
```

`queue` répond `200` avec `status: "queued"` et `queuedAt` renseigné, puis
dépêche le Job d'envoi.

`cancel` accepte `draft`, `scheduled` et `queued`. Au-delà, `409` :

```json
{ "message": "Transition impossible : une communication « Envoyée » ne peut pas passer à « Annulée »." }
```

`retry` **exige `failed`**. Sur un brouillon, `409` — expédier un brouillon
relève de `queue`, qui a sa propre permission. Sur un échec, `200` :
`status` repasse à `queued`, `errorMessage` et `failedAt` sont effacés. Aucune
colonne de compteur n'est ajoutée (§29).

---

## 9. Erreurs de rendu

Une variable employée dans le modèle mais absente d'`availableVariables` :

```json
{
  "message": "La variable « secret_field » n’est pas déclarée dans les variables disponibles du modèle.",
  "errors": {
    "body": ["La variable « secret_field » n’est pas déclarée dans les variables disponibles du modèle."]
  }
}
```

`422` également pour :

- une notation à points — `{{ order.customer.email }}` ;
- une expression — `{{ phpinfo() }}` ;
- une variable déclarée mais sans valeur fournie ;
- une valeur non scalaire.

Le contenu HTML est échappé pour le canal `email` :
`<script>alert(1)</script>` devient `&lt;script&gt;alert(1)&lt;/script&gt;`. Il
ne l'est pas pour les autres canaux, où il n'est pas interprété.

---

## 10. Erreurs de canal et de destinataire

| Situation | Réponse |
|---|---|
| canal `email`, destinataire sans adresse | `422` sur `recipientEmail` |
| canal `sms` ou `whatsapp`, destinataire sans téléphone | `422` sur `recipientPhone` |
| canal `push_notification` ou `internal_notification` | aucun contact exigé |
| rôle `delivery_contact`, aucun contact de ce rôle sur la commande | `422` sur `recipientRole` |
| rôle `custom` sans `recipientName` | `422` sur `recipientName` |
| canal hors enum | `422` sur `channel` |

Le §20 est respecté dans les deux sens : jamais d'e-mail exigé pour un SMS, ni
de téléphone pour un e-mail.

---

## 11. Erreurs de périmètre

| Situation | Réponse |
|---|---|
| modèle d'une autre organisation, par identifiant | `422` sur `templateId` |
| commande d'une autre organisation | `422` sur `orderId` |
| document d'une autre organisation | `422` sur `documentId` |
| service incohérent avec celui du modèle | `422` sur `serviceId` |
| **accès direct** à une ressource d'une autre organisation | **`404`** |
| pièce jointe d'une autre communication | `404` |
| `PATCH` sur une pièce jointe | `405` |

La distinction est constante depuis la Phase 4 : `422` quand une **référence**
fournie n'est pas utilisable ici, `404` quand la **ressource visée** n'existe
pas dans le périmètre. Jamais `403` — l'existence d'une donnée appartenant à un
autre transporteur ne se révèle pas.

---

## 12. Conflits d'état

| Situation | Réponse |
|---|---|
| modifier une communication mise en file ou envoyée | `409` |
| supprimer une communication au-delà du brouillon | `409` |
| joindre ou retirer une pièce après mise en file | `409` |
| supprimer un modèle utilisé par une règle | `409` |
| supprimer un modèle ayant produit des communications | `409` |
| supprimer une règle ayant produit des communications | `409` |

---

## 13. Lister et filtrer

```http
GET /api/v1/order-communications?status=failed&channel=email&sentFrom=2026-08-01&perPage=50
GET /api/v1/templates?channel=sms&isActive=1&search=rappel
GET /api/v1/communication-rules?eventType=service_completed&recipientRole=customer
GET /api/v1/orders/{order}/communications
```

Tri par défaut : les communications les plus récentes d'abord, les modèles par
code, les règles par événement.

Un tri sur une colonne non listée est refusé en `422` — `sort=body` en
particulier : trier un `LONGTEXT` imposerait un tri de fichier sur des
mégaoctets.

---

## 14. Ce que l'API ne fait pas

- **aucun endpoint de callback fournisseur** : le §28 le conditionne à des
  intégrations existantes, il n'y en a aucune ;
- **`deliveredAt` et `readAt` ne sont alimentés par aucune route** : ils
  attendent un fournisseur capable de les signaler ;
- **aucun déclenchement automatique de règle** : les onze
  `CommunicationEventType` ne sont émis par aucune phase antérieure ;
- **SMS, WhatsApp et push échouent explicitement** : aucun fournisseur n'est
  raccordé, et un faux succès serait pire qu'un échec annoncé.

---

## Évolution du 1er septembre 2026 — modèles unifiés

Les routes de ce document ont suivi le renommage `communication-templates →
templates`. Trois exemples s'y ajoutent.

### Créer un modèle de facture — un document, pas un message

```http
POST /api/v1/templates
```

```json
{
  "code": "INVOICE_DEFAULT",
  "name": "Facture standard",
  "templateType": "invoice",
  "channel": null,
  "bodyFormat": "html",
  "bodyTemplate": "<h1>Facture {{ invoice.invoiceNumber }}</h1>{{#invoice.lines}}<p>{{ invoice.lines.description }}</p>{{/invoice.lines}}",
  "language": "fr",
  "availableVariables": ["invoice.invoiceNumber", "invoice.lines", "invoice.lines.description"],
  "isDefault": true
}
```

`201 Created`. Envoyer `"channel": "email"` avec `"templateType": "invoice"`
donne un `422` sur `channel` : une facture est un document, et le §0.7 interdit
de lui inventer un canal.

### Créer le modèle propre à un client

```json
{
  "code": "INVOICE_IKEA",
  "name": "Facture IKEA",
  "customerId": "01JQZ00000000000000CUST1",
  "templateType": "invoice",
  "bodyTemplate": "…",
  "language": "fr"
}
```

Les factures d'IKEA l'emploieront ; celles des autres clients continueront
d'employer `INVOICE_DEFAULT`. Aucun client ne reçoit jamais le modèle d'un
autre.

### N'afficher que les modèles du transporteur

```http
GET /api/v1/templates?customerId=global&templateType=invoice
```

`global` est une **sentinelle**, pas un identifiant : elle demande les modèles
dont `customer_id` est nul. Sans elle, « aucun client » et « tous les clients »
se demanderaient de la même façon.

### Prévisualiser le document d'une facture

```http
GET /api/v1/invoices/{invoice}/document
```

```json
{
  "data": {
    "html": "<h1>Facture INV-2026-001</h1>…",
    "templateId": "01JQZ0000000000000TMPL01",
    "templateName": "Facture IKEA",
    "scope": "customer",
    "isFrozen": false,
    "renderedAt": null
  }
}
```

`scope` vaut `customer`, `global`, `fallback` — aucun modèle n'existe, la mise
en page livrée sert — ou `frozen` : la facture est close, et `html` est le
document produit à sa clôture. Modifier le modèle ensuite ne le réécrit pas.
