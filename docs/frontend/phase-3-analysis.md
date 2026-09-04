# Frontend Phase 3 — Analyse préalable

Suivi de commande, preuves de livraison, réclamations et communications
manuelles.

Branche : `feature/frontend-phase-3-order-followup`, partie de
`feature/frontend-phase-2-orders-catalogs` (Phase 2 validée, `5ddfff5`).

> **Pourquoi pas depuis `main`.** Même raison qu'en Phase 2 : `origin/main`
> porte les dix phases backend mais aucun dossier `frontend/`. Brancher depuis
> `main` donnerait un dépôt sans `AppLayout`, `DataTable` ni `OrderDetailPage`,
> que le §5 demande de réutiliser.

---

## 1. Les routes qui existent réellement

Relevé par `php artisan route:list`, pas par déduction.

### Tracking — `tracking_events.*`

| Méthode | Route |
| --- | --- |
| GET | `tracking-events` |
| POST | `tracking-events` |
| GET | `tracking-events/{trackingEvent}` |
| GET | `orders/{order}/tracking-events` |
| GET | `orders/{order}/services/{orderService}/tracking-events` |
| GET | `tours/{tour}/tracking-events` |
| GET | `tours/{tour}/stops/{tourStop}/tracking-events` |

**Ni `update` ni `destroy`.** Un événement de suivi est un fait daté : on ne
réécrit pas l'histoire, on ajoute un événement qui la corrige. L'écran n'offrira
donc aucune modification ni suppression.

### Preuves de livraison — `proofs_of_delivery.*`

| Méthode | Route |
| --- | --- |
| GET | `proofs-of-delivery` |
| POST | `proofs-of-delivery` |
| GET | `proofs-of-delivery/{proofOfDelivery}` |
| GET | `orders/{order}/proofs-of-delivery` |
| POST | `orders/{order}/proofs-of-delivery` |

**Ni `update` ni `destroy`** non plus, même raison.

### Réclamations — `claims.*`

CRUD complet, plus trois vues cadrées :

```text
GET|POST     claims
GET|PATCH|DELETE  claims/{claim}
GET          orders/{order}/claims
GET|POST     customers/{customer}/claims
GET          tours/{tour}/claims
```

**La route globale existe** : le §17 autorise donc la page `/claims`.

### Templates de communication — `communication_templates.*`

CRUD complet sur `communication-templates`.

### Communications de commande — `order_communications.*`

```text
GET|POST     order-communications
GET|PATCH|DELETE  order-communications/{orderCommunication}
GET|POST     orders/{order}/communications
POST         order-communications/{orderCommunication}/queue
POST         order-communications/{orderCommunication}/retry
POST         order-communications/{orderCommunication}/cancel
```

### Pièces jointes — `communication_attachments.*`

```text
GET|POST     order-communications/{orderCommunication}/attachments
GET|DELETE   order-communications/{orderCommunication}/attachments/{attachment}
```

---

## 2. Ce que le backend n'a pas

Le §35 interdit de simuler une action absente. Trois manques commandent des
choix d'interface, et sont documentés plutôt que contournés.

### Pas de route `send`

Le verbe est **`queue`**. Rien n'envoie en direct : la communication est mise en
file, et le statut passe ensuite par `queued`, `sending`, `sent`. L'écran dira
donc « Mettre en file d'envoi », pas « Envoyer » — promettre un envoi immédiat
serait faux.

### Pas de route `schedule`, mais un champ qui en tient lieu

La programmation se fait par **`scheduledAt`**, accepté à la création *et* à la
modification. `UpdateDraftOrderCommunicationAction` en tire la conséquence
lui-même : poser la date fait passer le brouillon en `SCHEDULED`, la retirer le
ramène en `DRAFT`. Le frontend n'a donc **aucun statut à envoyer** — il pose ou
retire une date, et le serveur en déduit l'état.

### Pas de route de rendu de template

`grep -rniE "function (preview|render)"` sur les contrôleurs Communications ne
renvoie **rien**. Aucun endpoint ne substitue les variables d'un template.

Conséquence assumée : la prévisualisation affiche le `subjectTemplate` et le
`bodyTemplate` **tels quels**, `{{orderNumber}}` compris, et l'écran le dit. Le
§32 demande un préremplissage « selon le rendu backend/contrat réel » : le
contrat réel étant qu'aucun rendu n'existe, prétendre substituer côté React
inventerait un moteur de template que le serveur ne connaît pas — et le message
parti ne ressemblerait pas à l'aperçu.

---

## 2 bis. Le graphe des statuts de communication

`CommunicationStatus::allowedTransitions()` porte le cycle de vie, et trois
Actions le font respecter sous verrou. Ce qui est offert à l'écran s'en déduit :

| Statut | Modifier | Supprimer | Mettre en file | Réessayer | Annuler |
| --- | --- | --- | --- | --- | --- |
| `draft` | ✓ | ✓ | ✓ | — | ✓ |
| `scheduled` | ✓ | — | ✓ | — | ✓ |
| `queued` | — | — | — | — | ✓ |
| `sending` | — | — | — | — | — |
| `sent` · `delivered` · `read` | — | — | — | — | — |
| `failed` | — | — | — | ✓ | — |
| `cancelled` | — | — | — | — | — |

Trois règles, lues dans le code et non devinées :

- `allowsContentChanges()` — `draft` et `scheduled` seulement. Le §32 demandait
  de ne permettre la modification du snapshot que si l'API l'autorise : elle
  l'autorise, tant que rien n'est parti.
- `allowsDeletion()` — `draft` seulement. Une communication programmée
  s'annule, elle ne s'efface pas.
- `RetryOrderCommunicationAction` exige `FAILED`, explicitement.

Ce tableau sera **recopié** côté React pour n'afficher que les actions
possibles. Le serveur reste l'autorité : `ApplyCommunicationTransition` relit le
statut sous verrou et refuse ce que l'écran aurait laissé passer.

---

## 3. Permissions réelles

Relevées dans `PermissionSeeder`, jamais devinées.

| Module | Codes |
| --- | --- |
| `tracking_events` | `view`, `create` |
| `proofs_of_delivery` | `view`, `create` |
| `claims` | `view`, `create`, `update`, `delete` |
| `communication_templates` | `view`, `create`, `update`, `delete` |
| `order_communications` | `view`, `create`, `update`, `delete`, `queue`, `cancel`, `retry` |
| `communication_attachments` | `view`, `create`, `delete` |

Tracking et POD n'ont **ni `update` ni `delete`**, en accord avec leurs routes.
`order_communications` a trois permissions d'action distinctes : mettre en file,
annuler et réessayer se gardent séparément.

---

## 4. Énumérations

Les cinq énumérations du §20 existent en PHP, valeurs en `snake_case` :

- `CommunicationChannel` — `email`, `sms`, `whatsapp`, `push_notification`, `internal_notification`
- `CommunicationTemplateType` — les douze types, `custom` compris
- `CommunicationStatus` — les neuf statuts
- `RecipientRole` — les six rôles
- `CommunicationEventType` — **hors périmètre**, il sert aux règles

`claimType`, `claim.status` et `trackingEvent.eventType` / `status` sont des
**chaînes libres** (`max:32` / `max:64`). Aucune énumération PHP ne les borne :
en dresser une côté React inventerait un vocabulaire métier.

### Le référentiel de statuts, déjà en place

`StatusSources` dérive ses sources de la morph map croisée aux tables portant
une colonne `status`. Trois des entités de cette phase y figurent :

| Source | Statuts décrits en base |
| --- | --- |
| `order_communication` | **9** — les `CommunicationStatus` |
| `claim` | **0** |
| `tracking_event` | **0** |

`proof_of_delivery` n'a pas de colonne `status` et n'est donc pas une source.

Les neuf statuts de communication sont déjà semés, conformément au « Save in
table statuses » du §20. Réclamations et événements de suivi n'ont **aucun**
statut décrit : `StatusSeeder` sème depuis les énumérations PHP, et il n'en
existe pas pour eux. L'écran de changement de statut réutilisera le message déjà
écrit en Phase 2 — « Aucun statut n'est décrit pour ce type d'élément » — plutôt
que d'inventer une liste.

---

## 5. Champs exposés par les ressources

Relevés dans les `*DetailResource`. Tous correspondent aux modèles du prompt,
sans champ en trop ni en moins.

```text
TrackingEvent      id organizationId orderId orderServiceId tourId tourStopId
                   eventType status description latitude longitude occurredAt
                   createdBy creator

ProofOfDelivery    id orderId orderServiceId tourStopId recipientName
                   signatureDocumentId photoDocumentId remark deliveredAt
                   createdBy signatureDocument photoDocument creator

Claim              id organizationId customerId orderId orderServiceId tourId
                   title description claimType cause decision followUp result
                   cost status createdBy responsibleUserId createdAt closedAt
                   customer order tour creator responsibleUser

CommunicationTemplate
                   id organizationId serviceId code name channel templateType
                   subjectTemplate bodyTemplate language availableVariables
                   isDefault isActive rulesCount communicationsCount
                   createdAt updatedAt

OrderCommunication id organizationId orderId templateId communicationRuleId
                   channel communicationType recipientRole recipientName
                   recipientEmail recipientPhone subject body templateVariables
                   status scheduledAt queuedAt sentAt deliveredAt readAt
                   failedAt providerMessageId providerResponse errorMessage
                   createdBy creator template attachments attachmentsCount
                   createdAt updatedAt

CommunicationAttachment
                   id communicationId documentId fileNameSnapshot
                   mimeTypeSnapshot document createdAt
```

`ProofOfDelivery` charge `signatureDocument` et `photoDocument` : la signature
et la photo sont des `Document`, et le module Documents de la Phase 2 les
affichera. Aucun `storagePath` ne sera lu — le §11 l'interdit et la Phase 2
avait déjà constaté qu'aucune route de téléchargement n'existe.

### `providerResponse` — exposé, mais pas affiché

La ressource le renvoie. Le §52 interdit de l'exposer inutilement : la réponse
brute d'un fournisseur peut contenir des identifiants techniques. L'écran
affichera `errorMessage`, rédigé pour être lu, et **jamais** `providerResponse`.

---

## 6. Ce que la création accepte, et ce qu'elle refuse

Différence structurante entre `Store` et `Update` d'une réclamation :

| Champ | Création | Modification |
| --- | --- | --- |
| `customerId` | **requis** | absent — une réclamation ne change pas de client |
| `title`, `claimType`, `status` | **requis** | facultatif |
| `orderId`, `orderServiceId`, `tourId`, `cause`, `responsibleUserId` | facultatif | facultatif |
| `decision`, `followUp`, `result`, `cost`, `closedAt` | **refusés** | facultatif |

Le §16 demandait de ne pas rendre obligatoire le traitement à la création : le
backend va plus loin, il ne l'accepte pas du tout. La section « Traitement » du
formulaire n'apparaîtra donc **qu'en modification**.

`StoreOrderCommunicationRequest` accepte `communicationRuleId` en `nullable` :
une communication manuelle l'enverra à `null`, conformément au §18.

---

## 7. Filtres de liste réellement acceptés

```text
tracking-events      orderId orderServiceId tourId tourStopId eventType
                     createdBy occurredFrom occurredTo

proofs-of-delivery   orderId orderServiceId tourStopId createdBy
                     deliveredFrom deliveredTo

claims               customerId orderId orderServiceId tourId claimType
                     responsibleUserId closedFrom closedTo

order-communications orderId templateId channel communicationType
                     recipientRole communicationRuleId createdBy
                     scheduledFrom scheduledTo sentFrom sentTo
                     failedFrom failedTo

communication-templates
                     serviceId channel templateType language isActive isDefault
```

Aucun filtre `severity` nulle part : le §17 avait raison de l'interdire.
`ListCommunicationRequest` est partagé entre templates, règles et
communications, d'où des clés qui ne s'appliquent pas à toutes les routes.

---

## 8. `CommunicationRule` — confirmé hors périmètre

Les routes existent (`communication-rules`, CRUD complet) et
`CommunicationRuleListResource` / `CommunicationRuleDetailResource` aussi.
**Aucune ne sera consommée.**

Rien ne sera créé de ce que le §53 interdit : pas de `CommunicationRulePage`,
pas de `RuleEditor`, pas de `ConditionsBuilder`, pas d'automatisme.
`CommunicationTemplateDetailResource.rulesCount` sera simplement ignoré — le
lire n'apprendrait rien d'actionnable dans cette phase.

Le champ `communicationRuleId` reste dans le type `OrderCommunication`, en
lecture seule, parce qu'il est au contrat.

---

## 9. Conséquences sur les écrans

| Le §… demande | Le backend permet | Décision |
| --- | --- | --- |
| §9 « + Ajouter un événement » | `POST tracking-events` + `tracking_events.create` | **Oui** |
| §12 création de POD | `POST proofs-of-delivery` + `proofs_of_delivery.create` | **Oui** |
| §17 page `/claims` | `GET claims` | **Oui** |
| §21 CRUD templates | CRUD complet | **Oui** |
| §23 variables du template | `availableVariables` sur la ressource | **Oui**, affichées telles quelles |
| §23 endpoint de rendu | — | **Non**, aperçu brut assumé |
| §35 `send` | `queue` | **File d'envoi**, pas « envoyer » |
| §35 `schedule` | `scheduledAt` à la création **et** en modification | **Oui**, en posant une date |
| §35 `retry`, `cancel` | routes + permissions | **Oui** |
| §11 preview/téléchargement de document | aucune route de téléchargement | **Non** — constat de la Phase 2, inchangé |

---

## 10. Ce qui n'est pas modifiable, et pourquoi c'est visible

Tracking et POD n'ont ni `update` ni `destroy`. Une interface qui offrirait des
boutons grisés laisserait croire à un problème de droits ; aucun bouton ne sera
donc affiché, et la nature « journal » de ces deux onglets sera dite en toutes
lettres dans leur en-tête.
