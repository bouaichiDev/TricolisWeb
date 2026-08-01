# Analyse Phase 5 — Suivi, preuves de livraison et réclamations

Répond au §3 du prompt Phase 5. Aucune migration n'a été écrite avant que le
tableau de correspondance du §6 soit terminé.

---

## 1. Sources de vérité

Le §1 désigne deux `.puml` qui **n'existent pas**. Les diagrammes disponibles
font foi, comme aux Phases 3 et 4 :

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Classes relevées lignes 565-614, relations lignes 916-929.

### Conflit relevé : `Claim.legacyId`

Le §14 du prompt liste `legacyId: bigint` parmi les attributs de `Claim`. **Le
diagramme ne le contient pas** — `Claim` y compte 18 attributs, de `id` à
`closedAt`, sans `legacyId`. Le §28 le mentionne aussi dans les index.

**Arbitrage : le diagramme l'emporte.** Trois raisons convergentes :

1. le §1 pose que « les diagrammes ont priorité sur les anciens prompts » ;
2. le §2 interdit d'ajouter « un attribut non présent dans les diagrammes » ;
3. c'est la même décision qu'en Phase 3, où `legacy_id` a été retiré de
   `providers`, `drivers` et `vehicles` lors du réalignement sur les diagrammes.

`claims.legacy_id` n'est donc **pas** créée, et l'index correspondant du §28 est
sans objet. C'est le seul écart entre le prompt et le modèle livré.

Hors ce point, prompt et diagramme concordent exactement : 13 attributs pour
`TrackingEvent`, 10 pour `ProofOfDelivery`, 18 pour `Claim`.

## 2. État du code avant modification

Phases 1 à 4 livrées et vertes : **335 tests**, 1017 assertions, 214 routes, 191
opérations OpenAPI.

| Élément | Version |
|---------|---------|
| Laravel | 13.23.0 |
| PHP | 8.4.2 |
| Base | MySQL 8 |
| Auth | Sanctum 4 |
| Tests | Pest 5, `RefreshDatabase` |
| Style | Pint |

### Dépendances vers les phases précédentes

Toutes présentes :

| Table | Phase | Usage Phase 5 |
|-------|-------|---------------|
| `organizations` | 1 | `tracking_events.organization_id`, `claims.organization_id` |
| `users` | 1 | `created_by`, `responsible_user_id` |
| `customers` | 1 | `claims.customer_id` |
| `documents` | 1 | `signature_document_id`, `photo_document_id` |
| `audit_logs` | 1 | audit de la phase |
| `orders` | 2 | `order_id` sur les trois tables |
| `order_services` | 2 | `order_service_id` sur les trois tables |
| `tours` | 4 | `tracking_events.tour_id`, `claims.tour_id` |
| `tour_stops` | 4 | `tracking_events.tour_stop_id`, `proofs_of_delivery.tour_stop_id` |

La branche part de la Phase 4 et non de `main`, resté au squelette vide
`c97dc0d` : `tracking_events` référence `tours` et `tour_stops`, créées en
Phase 4. Même écart assumé qu'aux Phases 3 et 4.

### Stockage documentaire

Le module `Documents` de la Phase 1 existe et gère déjà l'upload, le stockage
(`storage_path`), le type MIME, la taille et le lien polymorphe `DocumentLink`.
`ProofOfDelivery` s'y raccorde **par identifiant** : aucune colonne
`signature_path` ni `photo_path`, aucune table `signatures` ni
`delivery_photos`, aucun endpoint d'upload dans ce module. Le §9 et le §13
l'exigent, et c'est de toute façon la seule façon de ne pas dupliquer un
mécanisme correct.

## 3. Classes implémentées

```text
TrackingEvent
ProofOfDelivery
Claim
```

Aucun enum : les trois classes ne déclarent que des `string` pour `eventType`,
`status`, `claimType`, `cause` et `result`. Le diagramme n'en énumère aucune
valeur — en faire des enums reviendrait à les inventer.

## 4. Relations et cardinalités

Relevées lignes 916-929 :

```text
Order        "1"    -- "0..*" TrackingEvent
OrderService "0..1" -- "0..*" TrackingEvent
Tour         "0..1" -- "0..*" TrackingEvent
TourStop     "0..1" -- "0..*" TrackingEvent

Order        "1"    -- "0..*" ProofOfDelivery
OrderService "0..1" -- "0..*" ProofOfDelivery
TourStop     "0..1" -- "0..*" ProofOfDelivery
Document     "0..1" -- "0..*" ProofOfDelivery

Customer     "1"    -- "0..*" Claim
Order        "0..1" -- "0..*" Claim
OrderService "0..1" -- "0..*" Claim
Tour         "0..1" -- "0..*" Claim
```

Toutes des **associations** (trait simple `--`) : aucune composition. Rien n'est
supprimé en cascade dans cette phase.

Relations Eloquent :

```text
TrackingEvent   belongsTo Organization, Order, OrderService, Tour, TourStop, User (creator)
ProofOfDelivery belongsTo Order, OrderService, TourStop,
                          Document (signatureDocument), Document (photoDocument), User (creator)
Claim           belongsTo Organization, Customer, Order, OrderService, Tour,
                          User (creator), User (responsibleUser)
```

### Isolation organisationnelle

| Classe | Porte `organizationId` | Isolation |
|--------|------------------------|-----------|
| `TrackingEvent` | **oui** | condition directe |
| `ProofOfDelivery` | **non** | via `order.organization_id` |
| `Claim` | **oui** | condition directe |

`ProofOfDelivery` n'a pas d'`organizationId` au diagramme : son périmètre passe
par la commande, comme `order_services` en Phase 2. Toute lecture joint donc
`orders`, et le scope `inOrganization` est le seul point qui applique la règle —
pour qu'aucune requête ne puisse l'oublier.

## 5. Tableau de correspondance

### TrackingEvent → `tracking_events`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `organizationId` | ULID | `organization_id` CHAR(26) | non | index | FK `organizations.id` RESTRICT |
| `orderId` | ULID | `order_id` CHAR(26) | non | index + `(order_id, occurred_at)` | FK `orders.id` RESTRICT |
| `orderServiceId` | ULID | `order_service_id` CHAR(26) | **oui** | index | FK `order_services.id` SET NULL |
| `tourId` | ULID | `tour_id` CHAR(26) | **oui** | index | FK `tours.id` SET NULL |
| `tourStopId` | ULID | `tour_stop_id` CHAR(26) | **oui** | index | FK `tour_stops.id` SET NULL |
| `eventType` | string | `event_type` VARCHAR(64) | non | index | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |
| `description` | text | `description` TEXT | **oui** | — | — |
| `latitude` | decimal | `latitude` DECIMAL(10,8) | **oui** | — | — |
| `longitude` | decimal | `longitude` DECIMAL(11,8) | **oui** | — | — |
| `occurredAt` | datetime | `occurred_at` DATETIME | non | index | — |
| `createdBy` | ULID | `created_by` CHAR(26) | **oui** | index | FK `users.id` SET NULL |

### ProofOfDelivery → `proofs_of_delivery`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `orderId` | ULID | `order_id` CHAR(26) | non | index | FK `orders.id` RESTRICT |
| `orderServiceId` | ULID | `order_service_id` CHAR(26) | **oui** | index | FK `order_services.id` SET NULL |
| `tourStopId` | ULID | `tour_stop_id` CHAR(26) | **oui** | index | FK `tour_stops.id` SET NULL |
| `recipientName` | string | `recipient_name` VARCHAR(255) | non | — | — |
| `signatureDocumentId` | ULID | `signature_document_id` CHAR(26) | **oui** | index | FK `documents.id` RESTRICT |
| `photoDocumentId` | ULID | `photo_document_id` CHAR(26) | **oui** | index | FK `documents.id` RESTRICT |
| `remark` | text | `remark` TEXT | **oui** | — | — |
| `deliveredAt` | datetime | `delivered_at` DATETIME | non | index | — |
| `createdBy` | ULID | `created_by` CHAR(26) | **oui** | index | FK `users.id` SET NULL |

### Claim → `claims`

| Attribut | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `organizationId` | ULID | `organization_id` CHAR(26) | non | index | FK `organizations.id` RESTRICT |
| `customerId` | ULID | `customer_id` CHAR(26) | non | index | FK `customers.id` RESTRICT |
| `orderId` | ULID | `order_id` CHAR(26) | **oui** | index | FK `orders.id` SET NULL |
| `orderServiceId` | ULID | `order_service_id` CHAR(26) | **oui** | index | FK `order_services.id` SET NULL |
| `tourId` | ULID | `tour_id` CHAR(26) | **oui** | index | FK `tours.id` SET NULL |
| `title` | string | `title` VARCHAR(255) | non | — | — |
| `description` | text | `description` TEXT | **oui** | — | — |
| `claimType` | string | `claim_type` VARCHAR(64) | non | index | — |
| `cause` | string | `cause` VARCHAR(255) | **oui** | — | — |
| `decision` | text | `decision` TEXT | **oui** | — | — |
| `followUp` | text | `follow_up` TEXT | **oui** | — | — |
| `result` | string | `result` VARCHAR(255) | **oui** | — | — |
| `cost` | decimal | `cost` DECIMAL(12,2) | **oui** | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |
| `createdBy` | ULID | `created_by` CHAR(26) | **oui** | index | FK `users.id` SET NULL |
| `responsibleUserId` | ULID | `responsible_user_id` CHAR(26) | **oui** | index | FK `users.id` SET NULL |
| `createdAt` | datetime | `created_at` DATETIME | non | index | — |
| `closedAt` | datetime | `closed_at` DATETIME | **oui** | index | — |

**Pas de `legacy_id`** — voir §1.

## 6. Décisions de nullabilité

| Colonne | Choix | Raison |
|---------|-------|--------|
| `tracking_events.order_id` | non | `Order "1" -- "0..*" TrackingEvent` |
| `tracking_events.order_service_id`, `tour_id`, `tour_stop_id` | **oui** | `"0..1"` chacun. Un événement peut précéder toute planification. |
| `tracking_events.event_type`, `status`, `occurred_at` | non | Le §6 les liste obligatoires. Un événement sans type ni date ne se situe ni ne se qualifie. |
| `tracking_events.description` | **oui** | Absent des obligatoires du §6 ; un événement automatique n'a rien à décrire. |
| `tracking_events.latitude`, `longitude` | **oui** | Le §6 dit « valide **si renseignée** ». Un événement saisi au bureau n'a pas de coordonnées. |
| `tracking_events.created_by` | **oui** | Le §6 demande de l'analyser « selon le contexte d'événements système ». Un événement produit par un automate n'a pas d'auteur, et le §25 distingue explicitement l'événement métier de la trace technique. Précédent direct : `documents.created_by` est nullable. |
| `proofs_of_delivery.order_id` | non | `Order "1"` |
| `proofs_of_delivery.order_service_id`, `tour_stop_id` | **oui** | `"0..1"` |
| `proofs_of_delivery.signature_document_id`, `photo_document_id` | **oui** | `Document "0..1"`. Le §10 interdit de les rendre obligatoires. |
| `proofs_of_delivery.recipient_name` | **non** | Décision documentée : une preuve de livraison sans destinataire ne prouve rien. C'est le seul champ qui identifie qui a reçu, et le diagramme ne le marque pas facultatif. |
| `proofs_of_delivery.delivered_at` | **non** | Même raison : une preuve sans date ne situe pas la livraison. |
| `proofs_of_delivery.remark` | **oui** | Texte libre. |
| `proofs_of_delivery.created_by` | **oui** | Cohérent avec `tracking_events.created_by` : une preuve importée d'un terminal chauffeur peut n'avoir aucun compte associé. |
| `claims.organization_id`, `customer_id` | non | `Customer "1" -- "0..*" Claim` |
| `claims.order_id`, `order_service_id`, `tour_id` | **oui** | `"0..1"`. Une réclamation peut viser le service global sans commande précise. |
| `claims.title`, `claim_type`, `status`, `created_at` | non | Une réclamation sans objet, sans type ni statut n'est pas exploitable. |
| `claims.description`, `cause` | **oui** | Renseignés au fil de l'instruction. |
| `claims.decision`, `follow_up`, `result`, `cost`, `responsible_user_id`, `closed_at` | **oui** | Ce sont des **informations de résolution** : le §15 interdit explicitement de les exiger à la création. Une réclamation naît ouverte, sans décision ni coût. |

## 7. Décisions de suppression

Aucune composition dans cette phase : **aucun `CASCADE`**.

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `tracking_events.organization_id` | `RESTRICT` | Le suivi est une donnée historique : il ne disparaît pas avec l'organisation. |
| `tracking_events.order_id` | `RESTRICT` | Le §26 le recommande, et supprimer une commande ne doit pas effacer son historique de suivi. |
| `tracking_events.order_service_id`, `tour_id`, `tour_stop_id` | `SET NULL` | Colonnes nullables. Supprimer une tournée ne doit pas effacer les événements qu'elle a produits — l'événement reste rattaché à sa commande, qui est l'ancrage obligatoire. |
| `tracking_events.created_by` | `SET NULL` | Cohérent avec la nullabilité ; précédent `documents.created_by`. |
| `proofs_of_delivery.order_id` | `RESTRICT` | Une preuve de livraison a valeur probante : la commande ne peut pas disparaître sous elle. |
| `proofs_of_delivery.order_service_id`, `tour_stop_id` | `SET NULL` | Colonnes nullables. |
| `proofs_of_delivery.signature_document_id`, `photo_document_id` | **`RESTRICT`** | Choix délibéré contre `SET NULL` : délier silencieusement une signature viderait la preuve de sa substance sans laisser de trace. Le §29 interdit par ailleurs de supprimer automatiquement les documents liés. |
| `proofs_of_delivery.created_by` | `SET NULL` | Idem `tracking_events`. |
| `claims.organization_id`, `customer_id` | `RESTRICT` | Le §26 le recommande ; une réclamation sans client n'a plus de sens. |
| `claims.order_id`, `order_service_id`, `tour_id` | `SET NULL` | Colonnes nullables ; la réclamation survit à la commande qu'elle vise. |
| `claims.created_by`, `responsible_user_id` | `SET NULL` | Le départ d'un collaborateur ne supprime pas les réclamations qu'il a ouvertes ou instruites. |

### Suppression métier

| Ressource | API |
|-----------|-----|
| `TrackingEvent` | **aucune route** `PATCH` ni `DELETE` |
| `ProofOfDelivery` | **aucune route** `PATCH` ni `DELETE` |
| `Claim` | `PATCH` et `DELETE`, ce dernier **refusé en 409 si la réclamation est clôturée** |

Le §29 autorise la suppression d'une réclamation « uniquement si le statut ou les
règles existantes l'autorisent ». Aucun workflow de statut n'existe, et le §16
interdit d'inventer les valeurs de `status`. Le seul critère objectif porté par
le modèle est `closed_at` : une réclamation clôturée est un dossier tranché, sa
suppression est refusée. Aucune valeur de `status` n'est interprétée.

## 8. Caractère historique

Les §7 et §12 le posent : `TrackingEvent` et `ProofOfDelivery` sont des données
**événementielles**. Traduction concrète :

- aucune route `PATCH`, aucune route `DELETE` — elles n'existent pas, plutôt que
  d'exister en renvoyant 403 ;
- aucune colonne `updated_at`, y compris pour `Claim` : le diagramme déclare
  `createdAt` seul ;
- une nouvelle occurrence produit un **nouvel** événement, jamais une mise à jour
  de l'ancien.

Deux tests vérifient qu'un `PATCH` et un `DELETE` sur ces routes renvoient 405.

`Claim` est différente : elle porte `decision`, `followUp`, `result` et
`closedAt`, qui n'ont de sens que renseignés après coup. Elle est donc modifiable.

## 9. Précision décimale

Le §27 interdit de choisir arbitrairement et demande de réutiliser la convention
existante. Elle existe :

| Grandeur | Précision | Précédent dans le projet |
|----------|-----------|--------------------------|
| Latitude | `DECIMAL(10,8)` | `addresses.latitude` |
| Longitude | `DECIMAL(11,8)` | `addresses.longitude` |
| Montant | `DECIMAL(12,2)` | `orders.*_price`, `order_services.*_cost` |

Les exemples du §27 (`10,7` et `15,2`) ne sont pas repris : ils créeraient deux
conventions concurrentes dans la même base pour les mêmes grandeurs.

Les bornes `-90/90` et `-180/180` sont validées côté Form Request. Ce sont des
contrôles techniques, pas des enums métier.

## 10. Contraintes de cohérence

Vérifiées dans les Actions, pas seulement en validation — les Actions doivent
rester sûres appelées hors HTTP :

| Contrainte | Où |
|------------|-----|
| `Order` dans l'organisation active | `TrackingScopeGuard::order()` |
| `TrackingEvent.organization_id == Order.organization_id` | forcé, non fourni par l'appelant |
| `OrderService` appartient à l'`Order` fourni | `TrackingScopeGuard::orderService()` |
| `Tour` dans l'organisation active | `TrackingScopeGuard::tour()` |
| `TourStop` appartient au `Tour` fourni | `TrackingScopeGuard::tourStop()` |
| `TourStop` fourni sans `Tour` | la tournée est **déduite** de l'arrêt — voir ci-dessous |
| `Document` dans l'organisation de la commande | `TrackingScopeGuard::document()` |
| `Customer` dans l'organisation active | `ClaimScopeGuard::customer()` |
| `Order` appartient au `Customer` | `ClaimScopeGuard::order()` |
| `OrderService` appartient à l'`Order`, ou à une commande du client | `ClaimScopeGuard::orderService()` |
| `responsibleUserId` membre de l'organisation | `ClaimScopeGuard::user()` |
| `cost >= 0` | Form Request |
| `closedAt >= createdAt` | `ClaimScopeGuard::assertClosedAtIsCoherent()` |
| Latitude et longitude dans leurs bornes | Form Request |

**`TourStop` sans `Tour`** : le §6 laisse le choix entre déduire et refuser. La
tournée est **déduite** (`tourStop.tour_id`). Refuser obligerait l'appelant à
transmettre une information que le modèle contient déjà, et qu'il ne pourrait
que recopier — avec le risque de la recopier faux. Si les deux sont fournis, la
cohérence est vérifiée et l'incohérence refusée en 422.

## 11. Permissions prévues

8 permissions, idempotentes :

```text
tracking_events.view / create
proofs_of_delivery.view / create
claims.view / create / update / delete
```

Pas de `tracking_events.update` ni `.delete` : les routes n'existent pas, la
permission n'aurait rien à protéger.

## 12. Endpoints prévus

15 routes :

```text
GET|POST  /tracking-events
GET       /tracking-events/{trackingEvent}
GET       /orders/{order}/tracking-events
GET       /orders/{order}/services/{orderService}/tracking-events
GET       /tours/{tour}/tracking-events
GET       /tours/{tour}/stops/{tourStop}/tracking-events

GET|POST  /proofs-of-delivery
GET       /proofs-of-delivery/{proofOfDelivery}
GET|POST  /orders/{order}/proofs-of-delivery

GET|POST         /claims
GET|PATCH|DELETE /claims/{claim}
GET|POST         /customers/{customer}/claims
GET              /orders/{order}/claims
GET              /tours/{tour}/claims
```

Les six routes imbriquées de `TrackingEvent` **réutilisent le même Query
Object** : le §8 interdit de dupliquer la logique. Chacune ne fait qu'imposer un
filtre supplémentaire avant de déléguer.

Aucun endpoint d'upload dans ce module : les documents passent par le module
Documents existant, puis sont liés par ULID.

## 13. Tests prévus

Un fichier par entité, plus un fichier de permissions : création minimale,
chaque contrainte de périmètre, bornes de coordonnées, cohérence des dates, coût
négatif, absence de `PATCH`/`DELETE`, IDOR, permissions, audit, filtres,
recherche, tri, pagination, et l'absence effective de `claimNumber`, `severity`
et des colonnes `path`.

## 14. Ordre des migrations

```text
1. tracking_events
2. proofs_of_delivery
3. claims
```

Ordre du §26. Chaque table ne référence que des tables déjà créées.

**Aucun soft delete. Aucun `updated_at`.** `claims.created_at` existe seul, parce
que le diagramme le déclare ; `$timestamps = false` sur les trois modèles, la
date de création étant posée explicitement par l'Action.

## 15. Éléments exclus

Classes non créées (§2) :

```text
DeliveryExecution   DriverNote          Incident            LocationEvent
LiveLocation        TrackingSession     TrackingToken       ClaimAction
ClaimComment        ClaimAttachment     ClaimStatusHistory  ProofOfDeliveryPhoto
ProofOfDeliverySignature                Signature           DeliveryPhoto
Recipient           ClaimSeverity       ClaimType           ClaimCause
ClaimDecision
```

Attributs non ajoutés :

```text
metadata      settings      softDeletes   updated_at    resolved_at
assigned_at   severity      priority      device_id     driver_id
vehicle_id    signature_path              photo_path    status_history
claim_number  attachments   comments      resolution    estimated_cost
final_cost    latitude_accuracy           longitude_accuracy
```

Plus `legacy_id` sur `claims` — absent du diagramme, voir §1.

Également exclus : toute géolocalisation temps réel, toute session ou token
public de suivi, tout enum pour `eventType`, `status`, `claimType`, `cause` ou
`result`.
