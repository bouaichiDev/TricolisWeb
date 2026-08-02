# Tricolis V2 — Contrat de l'API pour le frontend

Destiné à l'application React. Ce document décrit **ce que le backend garantit**
aujourd'hui, pas ce qu'il pourrait faire. Aucun endpoint listé ici n'est
hypothétique : les 308 sont exécutés par la suite de tests.

> **Périmètre : la plateforme interne.** Cette API est celle du back-office
> qu'utilisent les collaborateurs du transporteur. Les portails **client**,
> **fournisseur** et **chauffeur** relèvent d'un second backend, à construire :
> aucune route de ce document ne s'adresse à un utilisateur externe.

---

## 1. URL de base

```text
développement   http://localhost:8000/api/v1
```

Le préfixe `api/v1` est porté par la configuration du routeur : il ne peut pas
manquer sur une route.

---

## 2. Authentification

**Laravel Sanctum**, jetons personnels. Quatre routes sont publiques ; toutes les
autres exigent un jeton.

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@tricolis.dev", "password": "…" }
```

```json
{
  "data": {
    "token": "1|Xk9…",
    "user": { "id": "01JZ…", "firstName": "Admin", "lastName": "Tricolis", "email": "…" },
    "organizations": [ { "id": "01JZ…", "code": "tricolis-dev", "name": "…", "isOwner": true } ]
  },
  "meta": []
}
```

Le jeton est retourné **une seule fois**. Toutes les requêtes suivantes :

```http
Authorization: Bearer 1|Xk9…
```

| Route publique | Usage |
|---|---|
| `POST /auth/register` | Création de compte |
| `POST /auth/login` | Connexion |
| `POST /auth/forgot-password` | Demande de réinitialisation |
| `POST /auth/reset-password` | Réinitialisation par jeton |

| Route personnelle (jeton seul, **sans** en-tête d'organisation) | Usage |
|---|---|
| `GET /auth/me` | Profil et organisations accessibles |
| `PATCH /auth/profile` · `PATCH /auth/password` | Modification de son compte |
| `GET /auth/sessions` · `DELETE /auth/sessions/{tokenId}` | Jetons actifs |
| `POST /auth/logout` · `POST /auth/logout-all` | Déconnexion |

---

## 3. Organisation active — l'en-tête obligatoire

**296 des 308 routes exigent :**

```http
X-Organization-Id: 01JZ8Q2M5F3K7P9R1T4V6X8Z0A
```

C'est le pivot de l'isolation : toute donnée retournée appartient à cette
organisation, toute donnée créée y est rattachée.

| Situation | Réponse |
|---|---|
| En-tête absent | `403` |
| En-tête malformé (pas un ULID) | `422` |
| Organisation dont l'utilisateur n'est pas membre | `403` |

Un utilisateur peut appartenir à plusieurs organisations : `GET /auth/me` les
liste, et l'interface doit proposer d'en choisir une. **Changer d'organisation,
c'est changer d'en-tête** — il n'y a pas de session côté serveur.

Les cinq routes `/organizations` font exception : elles traitent l'organisation
comme ressource et filtrent par l'appartenance de l'utilisateur.

---

## 4. Format de réponse

### Ressource

```json
{ "data": { "id": "01JZ…", "…": "…" }, "meta": [] }
```

### Collection paginée

```json
{
  "data": [ { "id": "01JZ…" } ],
  "meta": { "currentPage": 1, "perPage": 25, "total": 137, "lastPage": 6 },
  "links": {
    "first": "…?page=1", "last": "…?page=6",
    "prev": null, "next": "…?page=2"
  }
}
```

**Toutes les listes paginent.** Aucun endpoint ne retourne une collection non
bornée.

### Suppression

`204`, corps vide.

---

## 5. Erreurs

| Code | Signification | Corps |
|---|---|---|
| `401` | Jeton absent, invalide ou expiré | `{ "message": "Unauthenticated." }` |
| `403` | Membre de l'organisation, **mais sans la permission** | `{ "message": "…" }` |
| `404` | La ressource n'existe pas **dans cette organisation** | `{ "message": "…" }` |
| `405` | Verbe non exposé (ressource historique) | — |
| `409` | L'état du système interdit l'opération | `{ "message": "…" }` |
| `422` | Validation, ou référence hors périmètre | `{ "message": "…", "errors": { … } }` |

### Ce que 403 et 404 veulent dire

C'est la distinction la plus utile à comprendre côté interface :

- **`403`** — l'utilisateur voit l'organisation mais n'a pas le droit. Message
  utile : « demandez la permission `customers.update` à votre administrateur ».
- **`404`** — la ressource n'existe pas *ici*. Elle existe peut-être ailleurs,
  mais le backend ne le dira jamais. Message utile : « ressource introuvable ».

Ne présentez jamais un 404 comme un problème de droits : ce serait révéler ce que
le backend s'applique à taire.

### 422 — deux origines

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": {
    "recipientEmail": ["Le canal e-mail exige une adresse pour ce destinataire."],
    "customerId": ["Ce client n’appartient pas à l’organisation active."]
  }
}
```

Les clés d'`errors` sont les **noms de champs du payload**, en camelCase, avec
notation à points pour les tableaux (`allowedIps.0`, `conditions.all.2.field`).
Elles se branchent directement sur un formulaire.

### 409 — refus métier

Toujours une phrase, jamais un code :

```json
{ "message": "Une communication au statut « Envoyée » ne peut plus être modifiée : seul un brouillon l’est." }
```

Ces messages sont rédigés pour être **affichés tels quels**.

---

## 6. Permissions

187 permissions, de la forme `module.action`. Le backend ne renvoie pas la liste
des permissions de l'utilisateur sur `/auth/me` : l'interface doit soit les
demander à `GET /api/v1/roles`, soit — plus simplement — **tenter l'action et
traiter le 403**.

Les actions qui ne sont pas du CRUD ont leur propre permission. À traiter comme
des boutons distincts, pas comme des variantes d'« éditer » :

| Permission | Bouton |
|---|---|
| `customers.block` | Bloquer un client |
| `orders.change_status`, `orders.cancel`, `orders.duplicate` | Statut, annuler, dupliquer |
| `order_communications.queue` / `.cancel` / `.retry` | Envoyer, annuler, relancer |
| `customer_api_configurations.rotate_key` | Renouveler la clé |
| `export_jobs.retry` | Relancer l'export |
| `stock_reservations.release` | Libérer la réservation |
| `tour_stops.reorder` et voisines | Réordonner |

---

## 7. Conventions

| Sujet | Règle |
|---|---|
| **Nommage** | `camelCase` partout, en entrée comme en sortie |
| **Identifiants** | ULID, 26 caractères — `01JZ8Q2M5F3K7P9R1T4V6X8Z0A` |
| **Dates en sortie** | ISO 8601 avec fuseau : `2026-08-07T09:00:00+00:00` |
| **Dates en entrée** | Tout format compris par Laravel ; **envoyez de l'ISO 8601 UTC** |
| **Montants** | **Chaînes** : `"450.00"`. Ne les convertissez pas en `Number` — utilisez une bibliothèque décimale |
| **Booléens** | `true` / `false` ; en query string, `1` / `0` |
| **Modification** | `PATCH` **partiel** : n'envoyez que ce qui change. Un champ absent n'est pas effacé ; envoyer `null` efface |

### Le stockage des dates

Tout est stocké en **UTC** et restitué avec le décalage. La conversion vers le
fuseau de l'utilisateur est un travail d'interface : le backend n'a pas de notion
de fuseau par agence ou par organisation.

---

## 8. Listes : filtres, recherche, tri

Paramètres communs à toutes les listes :

```text
?page=2&perPage=50&search=atlas&sort=created_at&direction=desc
```

| Paramètre | Valeur |
|---|---|
| `page` | ≥ 1 |
| `perPage` | 1 à 100, défaut 25 |
| `search` | texte libre, sur les colonnes déclarées par le module |
| `sort` | colonne **de la liste blanche du module** — sinon `422` |
| `direction` | `asc` ou `desc` |
| `createdFrom` / `createdTo` | dates |

Un tri hors liste blanche renvoie `422` avec le message « Cette colonne ne peut
pas être utilisée pour le tri. » Les colonnes de texte long ne sont jamais
triables.

Filtres propres à chaque module — quelques exemples :

```text
/orders?status=confirmed&customerId=01JZ…
/tours?tourDate=2026-09-01&providerId=01JZ…
/order-communications?status=failed&channel=email&sentFrom=2026-08-01
/stock-balances?stockItemId=01JZ…
/invoices?customerId=01JZ…&status=draft
```

---

## 9. Routes par module

### Identité et organisation

```text
GET|POST         /organizations                  GET|PATCH|DELETE /organizations/{id}
GET|POST|PATCH|DELETE /subscription
GET|POST         /users                          GET|PATCH|DELETE /users/{id}
GET|POST         /organization-users             GET|PATCH|DELETE /organization-users/{id}
GET|POST         /roles                          GET|PATCH|DELETE /roles/{id}
GET              /permissions                    GET /permissions/{id}
GET              /audit-logs
```

### Réseau et référentiels

```text
GET|POST /agencies              GET|PATCH|DELETE /agencies/{id}
GET|POST /agencies/{agency}/depots     GET|PATCH|DELETE /agencies/{agency}/depots/{depot}
GET|POST /addresses             GET|PATCH|DELETE /addresses/{id}
GET|POST /contacts              GET|PATCH|DELETE /contacts/{id}
GET|POST /services              GET|PATCH|DELETE /services/{id}
GET|POST /package-types         PATCH|DELETE /package-types/{id}
GET|POST /package-grouping-types PATCH|DELETE /package-grouping-types/{id}
GET|POST /vehicle-types         GET|PATCH|DELETE /vehicle-types/{id}
GET|POST /documents             GET|DELETE /documents/{id}     GET /documents/{id}/download
```

### Clients

```text
GET|POST /customers             GET|PATCH|DELETE /customers/{id}
PATCH    /customers/{id}/status
GET|POST /customers/{customer}/sites          GET|PATCH|DELETE .../sites/{site}
GET|POST /customers/{customer}/catalogs       GET|PATCH|DELETE .../catalogs/{catalog}
GET|POST /customers/{customer}/catalogs/{catalog}/items
GET|POST /customers/{customer}/stock-items    GET /customers/{customer}/stock-balances
```

### Commandes

```text
GET|POST /orders                GET|PATCH|DELETE /orders/{id}
PATCH    /orders/{id}/status    POST /orders/{id}/duplicate    GET /orders/{id}/history
GET|POST /orders/{order}/lines            GET|PATCH|DELETE .../lines/{line}
GET|POST /orders/{order}/services         GET|PATCH|DELETE .../services/{service}
PATCH    /orders/{order}/services/{service}/status
GET|POST /orders/{order}/services/{service}/contacts
GET|POST /orders/{order}/packages         GET /orders/{order}/packages/tree
GET|POST /orders/{order}/documents        GET /orders/{order}/claims
GET|POST /orders/{order}/proofs-of-delivery
GET|POST /orders/{order}/communications
GET      /orders/{order}/tracking-events
```

**Créer une commande crée aussi ses lignes et ses services**, en une seule
transaction : `lines` et `services` sont obligatoires dans le payload.

### Transport

```text
GET|POST /providers             GET|PATCH|DELETE /providers/{id}
GET|POST /drivers               GET|PATCH|DELETE /drivers/{id}
GET|POST /vehicles              GET|PATCH|DELETE /vehicles/{id}
GET|POST /tours                 GET|PATCH|DELETE /tours/{id}
GET|POST /tours/{tour}/stops    GET|PATCH|DELETE .../stops/{stop}   POST .../stops/reorder
GET|POST /tours/{tour}/stops/{stop}/services       POST .../services/reorder
GET|POST /tours/{tour}/periods  GET|PATCH|DELETE .../periods/{period}  POST .../periods/reorder
GET|POST /tours/{tour}/periods/{period}/assignments
GET|POST /tracking-events       GET /tracking-events/{id}
GET|POST /proofs-of-delivery    GET /proofs-of-delivery/{id}
GET|POST /claims                GET|PATCH|DELETE /claims/{id}
```

### Facturation

```text
GET|POST /invoices              GET|PATCH|DELETE /invoices/{id}
GET|POST /invoices/{invoice}/lines        GET|PATCH|DELETE .../lines/{line}
GET|POST /provider-settlements  GET|PATCH|DELETE /provider-settlements/{id}
GET|POST /provider-settlements/{settlement}/lines
GET|POST /providers/{provider}/settlements
```

### Stock

```text
GET|POST /stock-items           GET|PATCH|DELETE /stock-items/{id}
GET|POST /stock-locations       GET|PATCH|DELETE /stock-locations/{id}
GET      /stock-locations/tree
GET      /stock-balances        GET /stock-balances/{id}
GET|POST /stock-movements       GET /stock-movements/{id}
GET|POST /stock-reservations    GET|PATCH /stock-reservations/{id}
POST     /stock-reservations/{id}/release
```

Les soldes sont **produits par les mouvements** : aucune écriture directe.

### Intégrations et exports

```text
GET|POST /customer-import-configurations   GET|PATCH|DELETE .../{id}
GET|POST /customer-api-configurations      GET|PATCH|DELETE .../{id}
POST     /customer-api-configurations/{id}/rotate-key
GET|POST /customer-export-configurations   GET|PATCH|DELETE .../{id}
GET|POST /export-jobs           GET /export-jobs/{id}    POST /export-jobs/{id}/retry
```

### Communication

```text
GET|POST /communication-templates   GET|PATCH|DELETE .../{id}
GET|POST /communication-rules       GET|PATCH|DELETE .../{id}
GET|POST /order-communications      GET|PATCH|DELETE .../{id}
POST     /order-communications/{id}/queue | cancel | retry
GET|POST /order-communications/{id}/attachments    GET|DELETE .../attachments/{attachment}
```

---

## 10. Enums

Toutes les valeurs sont en `snake_case`. Ce sont des listes **closes** : le
backend refuse toute autre valeur en `422`.

```text
OrganizationStatus   pending · active · suspended · closed
UserStatus           invited · active · suspended · disabled
ContactRole          load · delivery · billing · operations · emergency · other
CustomerStatus       active · inactive · blocked

OrderSource          internal · customer_portal · rest_api · csv_import
                     excel_import · xml_import · stock · catalog
OrderStatus          draft · confirmed · ready · partially_planned · planned
                     in_progress · completed · cancelled · partially_invoiced · invoiced
OrderServiceStatus   draft · pending · ready_to_plan · planned · in_progress
                     completed · failed · cancelled · invoiced

TourStatus           draft · planned · confirmed · in_progress · completed · cancelled
TourStopStatus       pending · arrived · in_progress · completed · skipped · cancelled

ExportFormat         xml · csv · json · pdf
ExportTransport      ftp · sftp · rest_api · email · manual

CommunicationChannel       email · sms · whatsapp · push_notification · internal_notification
CommunicationTemplateType  appointment_request · appointment_confirmation · appointment_reminder
                           driver_assigned · driver_departed · arrival_estimate · arrival_soon
                           delivery_confirmation · delivery_failed · pod_available
                           order_cancelled · custom
CommunicationEventType     order_created · order_confirmed · order_cancelled · service_planned
                           appointment_requested · appointment_confirmed · driver_assigned
                           tour_stop_approaching · service_completed · pod_created · claim_created
CommunicationStatus        draft · scheduled · queued · sending · sent
                           delivered · read · failed · cancelled
RecipientRole              customer · load_contact · delivery_contact
                           billing_contact · internal_user · custom
```

### Champs libres

Ces champs sont des **chaînes**, pas des enums — n'en figez pas les valeurs dans
l'interface :

```text
Document.documentType · Document.status · Claim.claimType · Claim.status
Invoice.status · ProviderSettlement.status · StockMovement.movementType
StockItem.status · TrackingEvent.eventType · ExportJob.status
CommunicationRule.delayUnit  (validé : minutes · hours · days)
```

### Transitions de statut exposées

`GET /orders/{id}` retourne `allowedTransitions` : la liste des statuts
atteignables depuis l'état courant. **Utilisez-la pour construire le menu de
changement de statut** plutôt que de recopier le graphe côté client.

---

## 11. Exemples de payloads

### Créer une commande

```json
POST /orders
{
  "customerId": "01JZ…",
  "agencyId": "01JZ…",
  "orderDate": "2026-08-07T09:00:00Z",
  "lines": [
    { "name": "Palette de carrelage", "articleCode": "CAR-60", "quantity": 4 }
  ],
  "services": [
    {
      "serviceId": "01JZ…", "addressId": "01JZ…",
      "serviceNumber": "SRV-1", "sequence": 1,
      "requestedDate": "2026-08-08", "quantity": 1, "unit": "delivery",
      "requiredTimeMinutes": 30, "remainingTimeMinutes": 30,
      "weight": 0, "volume": 0, "packageCount": 0,
      "customerUnitPrice": 0, "customerTotalPrice": 0,
      "providerUnitCost": 0, "providerTotalCost": 0,
      "status": "draft"
    }
  ]
}
```

`orderNumber` est **attribué par le serveur** : ne l'envoyez pas.

### Créer un arrêt de tournée

```json
POST /tours/{tour}/stops
{
  "addressId": "01JZ…", "sequence": 1, "status": "pending",
  "services": [
    { "orderServiceId": "01JZ…", "sequenceWithinStop": 1, "status": "planned" }
  ]
}
```

Un arrêt **exige au moins un service**.

### Créer une communication depuis un modèle

```json
POST /orders/{order}/communications
{
  "templateId": "01JZ…",
  "channel": "email",
  "communicationType": "pod_available",
  "recipientRole": "customer",
  "templateVariables": { "order_number": "ORD-2026-000042" }
}
```

Le destinataire est **déduit du rôle** : les coordonnées que vous enverriez
seraient ignorées, sauf pour `recipientRole: "custom"`.

### Réordonner

```json
POST /tours/{tour}/stops/reorder
{ "orderedIds": ["01JZ…", "01JZ…", "01JZ…"] }
```

La liste doit contenir **tous** les enfants, sans omission ni doublon.

---

## 12. Fichiers

```http
POST /api/v1/documents
Content-Type: multipart/form-data

file: <binaire>
documentType: proof
```

| Règle | Valeur |
|---|---|
| Taille maximale | 10 Mo |
| Types acceptés | PDF, JPEG, PNG, CSV, XLSX — validés sur le **contenu**, pas l'extension |
| Nom de fichier | Nettoyé côté serveur ; la traversée de chemin est refusée |
| Stockage | Disque privé — **jamais d'URL directe** |
| Téléchargement | `GET /documents/{id}/download`, sous permission et périmètre |

`storagePath` **n'est jamais retourné**. Pour joindre un document à une
communication, utilisez son `id`.

---

## 13. Ce que le backend ne fait pas encore

À savoir avant de dessiner un écran :

| Absent | Conséquence pour l'interface |
|---|---|
| **Envoi SMS, WhatsApp, push** | Ces communications passent en `failed` avec un `errorMessage` explicite. Affichez-le ; ne présentez pas ces canaux comme opérationnels. |
| **Déclenchement automatique des règles** | Les `CommunicationRule` se créent et se listent, mais rien ne les exécute. |
| **Callbacks fournisseur** | `deliveredAt` et `readAt` restent toujours nuls. |
| **Génération de fichier d'export** | `ExportJob.hasFile` reste `false` ; `generatedAt` et `sentAt` restent nuls. |
| **Portails client, fournisseur et chauffeur** | Hors périmètre : cette API est celle de la **plateforme interne**. Aucun utilisateur ne peut être rattaché à un client, un fournisseur ou un chauffeur. Les portails feront l'objet d'un second backend. |
| **Tableau de bord** | Aucun endpoint d'agrégation ; la permission `dashboard.view` existe mais ne garde rien. |
| **Liste des permissions de l'utilisateur** | Non exposée sur `/auth/me`. Tentez l'action, traitez le 403. |

---

## 14. Prérequis d'exécution

Deux processus doivent tourner pour que les communications partent :

```bash
php artisan queue:work       # sans lui, tout reste en « queued »
php artisan schedule:work    # sans lui, tout reste en « scheduled »
```

Si une communication reste bloquée dans l'un de ces deux états, c'est la
première chose à vérifier — ce n'est pas un défaut de l'API.
