# Rapport final — Phase 5 : suivi, preuves de livraison et réclamations

Répond au §38 du prompt « Tricolis V2 — Backend Phase 5 ».

---

## 1. Branche Git

```text
feature/backend-phase-5-tracking-pod-claims
```

Créée depuis `feature/backend-phase-4-tours-planning` (commit `1276207`), et non
depuis `main`.

**Écart assumé au §0** : `main` est resté au commit initial `c97dc0d`, un
squelette Laravel vide. `tracking_events` référence `tours` et `tour_stops`,
créées en Phase 4 ; `proofs_of_delivery` référence `documents` et `order_services`.
Brancher depuis `main` rendrait les trois migrations inexécutables. Même écart
qu'aux Phases 3 et 4.

Aucune fusion, aucun rebase, aucune suppression de branche, aucun push.

## 2. Diagrammes utilisés

Le §1 désigne deux `.puml` qui **n'existent pas**. Les diagrammes disponibles
font foi :

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Classes lignes 565-614, relations lignes 916-929.

### Conflit relevé et arbitré : `Claim.legacyId`

Le §14 du prompt liste `legacyId: bigint` parmi les attributs de `Claim`, et le
§28 le mentionne dans les index. **Le diagramme ne le contient pas** : `Claim` y
compte 18 attributs, de `id` à `closedAt`.

La colonne **n'est pas créée**. Trois raisons convergentes :

1. le §1 pose que « les diagrammes ont priorité sur les anciens prompts » ;
2. le §2 interdit d'ajouter « un attribut non présent dans les diagrammes » ;
3. c'est la décision déjà prise en Phase 3, où `legacy_id` a été retiré de
   `providers`, `drivers` et `vehicles` lors du réalignement sur les diagrammes.

Un test vérifie son absence. C'est **le seul écart** entre le prompt et le
modèle livré ; hors ce point, les trois classes correspondent exactement.

Vérification par les colonnes créées :

```text
tracking_events      13 colonnes  (13 attributs au diagramme)
proofs_of_delivery   10 colonnes  (10 attributs)
claims               18 colonnes  (18 attributs, sans legacy_id)
```

## 3. Classes implémentées

```text
TrackingEvent
ProofOfDelivery
Claim
```

**Aucun enum.** `eventType`, `status` (des trois classes), `claimType`, `cause`
et `result` sont déclarés `string` au diagramme, sans valeurs énumérées. En faire
des enums reviendrait à les inventer — les §6, §16 et §35 l'interdisent.

## 4. Attributs implémentés

| Classe | Attributs |
|--------|-----------|
| `TrackingEvent` | `id`, `organizationId`, `orderId`, `orderServiceId`, `tourId`, `tourStopId`, `eventType`, `status`, `description`, `latitude`, `longitude`, `occurredAt`, `createdBy` |
| `ProofOfDelivery` | `id`, `orderId`, `orderServiceId`, `tourStopId`, `recipientName`, `signatureDocumentId`, `photoDocumentId`, `remark`, `deliveredAt`, `createdBy` |
| `Claim` | `id`, `organizationId`, `customerId`, `orderId`, `orderServiceId`, `tourId`, `title`, `description`, `claimType`, `cause`, `decision`, `followUp`, `result`, `cost`, `status`, `createdBy`, `responsibleUserId`, `createdAt`, `closedAt` |

Tableau de correspondance complet au §5 de
[`phase-5-analysis.md`](phase-5-analysis.md).

## 5. Relations implémentées

```text
TrackingEvent   belongsTo Organization, Order, OrderService, Tour, TourStop, User (creator)
ProofOfDelivery belongsTo Order, OrderService, TourStop,
                          Document (signatureDocument), Document (photoDocument), User (creator)
Claim           belongsTo Organization, Customer, Order, OrderService, Tour,
                          User (creator), User (responsibleUser)
```

Les douze relations du diagramme sont des **associations** (trait simple `--`) :
aucune composition, donc aucune cascade dans cette phase.

`ProofOfDelivery` porte deux références distinctes vers `Document`, comme le pose
`Document "0..1" -- "0..*" ProofOfDelivery`.

## 6. Migrations

| # | Migration |
|---|-----------|
| 1 | `2026_08_03_100001_create_tracking_events_table` |
| 2 | `2026_08_03_100002_create_proofs_of_delivery_table` |
| 3 | `2026_08_03_100003_create_claims_table` |

**Aucune migration existante modifiée.** Aucun soft delete, aucun `updated_at`.
`claims.created_at` existe seul, parce que le diagramme déclare `createdAt`.

## 7. Modèles, Actions, DTOs, services

**Modèles (3)** — `App\Modules\Tracking\Models\TrackingEvent`,
`App\Modules\ProofOfDelivery\Models\ProofOfDelivery`,
`App\Modules\Claims\Models\Claim`.

**Actions (4)** :

```text
CreateTrackingEventAction
CreateProofOfDeliveryAction
CreateClaimAction   UpdateClaimAction   DeleteClaimAction
```

Pas de `CloseClaimAction`. Le §19 ne la justifie que « si elle réutilise les
champs existants » : clôturer se réduit à renseigner `closedAt`, `decision` et
`result`, exactement ce que fait `PATCH`. Une seconde Action dupliquerait les
mêmes contrôles pour le même effet. La clôture est en revanche **auditée
séparément** sous `claim.closed`.

**Services (2)** — `TrackingScopeGuard` (commande, service, tournée, arrêt,
document), `ClaimScopeGuard` (client, commande, service, tournée, responsable,
cohérence des dates).

**DTOs (4)** — `CreateTrackingEventData`, `CreateProofOfDeliveryData`,
`CreateClaimData`, `UpdateClaimData`.

**Exception (1)** — `ClaimNotDeletable`.

## 8. Requests, Resources, Policies, Queries

**Form Requests (7)** — `StoreTrackingEventRequest`, `ListTrackingEventRequest`,
`StoreProofOfDeliveryRequest`, `ListProofOfDeliveryRequest`, `StoreClaimRequest`,
`UpdateClaimRequest`, `ListClaimRequest`.

**Resources (9)** — les sept du §23, plus `UserCompactResource` et
`DocumentCompactResource`, créées dans leurs modules propriétaires (`Identity`,
`Documents`) plutôt que dupliquées dans chaque détail.

**Policies (3)** — `TrackingEventPolicy` et `ProofOfDeliveryPolicy` n'exposent
que `viewAny`, `view` et `create` : les routes de modification et de suppression
n'existent pas, une permission n'aurait rien à protéger.

**Query Objects (3)** — `TrackingEventListQuery` sert **les sept routes de
lecture** du suivi (globale + six imbriquées) via un paramètre `$scoped` : le §8
interdit de dupliquer la logique.

## 9. Permissions

8 permissions idempotentes ajoutées au `PermissionSeeder` existant :

```text
tracking_events.view / create
proofs_of_delivery.view / create
claims.view / create / update / delete
```

Total du projet : **122 permissions**.

## 10. Routes

**21 routes**, toutes sous `/api/v1`, protégées par `auth:sanctum` +
`organization`. Aucun doublon — vérifié sur les 235 routes du projet.

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

**Ni `PATCH` ni `DELETE` sur `tracking-events` et `proofs-of-delivery`** : elles
renvoient 405 parce qu'elles n'existent pas, non 403. Deux tests le vérifient.

Aucun endpoint d'upload : signature et photo passent par le module Documents,
puis sont liées par ULID.

## 11. Tests

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `Tracking/TrackingEventTest` | 21 | Création minimale, organisation forcée depuis la commande, commande hors organisation, service d'une autre commande, tournée hors organisation, arrêt hors tournée, **déduction de la tournée depuis l'arrêt**, arrêt dont la tournée est hors périmètre, latitude et longitude hors bornes, coordonnées valides, champs requis, **absence de PATCH (405)**, **absence de DELETE (405)**, isolation de liste, IDOR, recherche, filtres, ordre par défaut décroissant, tri interdit, quatre routes imbriquées, IDOR imbriqué, audit |
| `ProofOfDelivery/ProofOfDeliveryTest` | 16 | Création sans document, avec signature et photo, service et arrêt facultatifs, commande hors organisation, service d'une autre commande, document de signature hors organisation, document de photo hors organisation, **même document en signature et photo**, champs requis, **absence des colonnes `path` et des tables `signatures`/`delivery_photos`**, absence de PATCH et DELETE, isolation, IDOR, routes de commande, filtres, audit |
| `Claims/ClaimTest` | 22 | Création minimale ouverte sans champs de résolution, commande/service/tournée facultatifs, client hors organisation, commande d'un autre client, service hors commande, **service d'une commande du même client sans commande fournie**, tournée hors organisation, responsable hors organisation, route client, **absence de `claim_number`, `severity`, `legacy_id`, `updated_at` et des tables `claim_actions`/`comments`/`attachments`**, champs de résolution, coût négatif, clôture antérieure à la création, audit de clôture séparé, audit limité aux champs modifiés, suppression d'une réclamation ouverte, **refus de supprimer une réclamation clôturée**, IDOR, isolation, recherche, filtres, tri, pagination, routes imbriquées |
| `Tracking/TrackingPermissionTest` | 6 | Lecture, création, modification et suppression refusées sans permission ; accès accordé après attribution du rôle ; en-tête d'organisation requis ; accès non authentifié refusé |

**65 tests ajoutés.**

## 12. Résultats

```text
composer validate                                ./composer.json is valid
php artisan optimize:clear                       OK
php artisan migrate:fresh --seed --env=testing   OK
php artisan test                                 400 passed (1222 assertions)
./vendor/bin/pint --test                         PASS
php artisan route:list                           235 routes, aucun doublon
```

335 tests des Phases 1 à 4, 65 de la Phase 5. **Aucune régression** : aucun test
existant n'a été modifié, désactivé ni marqué `skip`.

Vérifications structurelles :

```text
fichiers > 200 lignes      aucun
TODO / FIXME               aucun
classes vides              aucune
constructions PostgreSQL   aucune
tables non prévues         aucune
colonnes non prévues       aucune
enums supplémentaires      aucun
table signatures           absente
table delivery_photos      absente
claim_actions              absente
claim_comments             absente
claim_attachments          absente
```

## 13. Décisions de nullabilité

| Colonne | Choix | Raison |
|---------|-------|--------|
| `tracking_events.order_id` | non | `Order "1"` |
| `tracking_events.order_service_id`, `tour_id`, `tour_stop_id` | nullable | `"0..1"` ; un événement peut précéder la planification |
| `tracking_events.latitude`, `longitude` | nullable | Le §6 dit « valide **si renseignée** » |
| `tracking_events.created_by` | nullable | Un événement produit par un automate n'a pas d'auteur ; précédent `documents.created_by` |
| `proofs_of_delivery.recipient_name` | **non** | Une preuve sans destinataire ne prouve rien |
| `proofs_of_delivery.delivered_at` | **non** | Une preuve sans date ne situe pas la remise |
| `proofs_of_delivery.signature_document_id`, `photo_document_id` | nullable | `Document "0..1"` ; le §10 interdit de les exiger |
| `claims.decision`, `follow_up`, `result`, `cost`, `responsible_user_id`, `closed_at` | nullable | Informations de résolution ; le §15 interdit de les exiger à la création |

## 14. Décisions de suppression

Aucune composition : **aucun `CASCADE`**.

| Clé étrangère | Stratégie |
|---------------|-----------|
| `tracking_events.organization_id`, `order_id` | `RESTRICT` |
| `tracking_events.order_service_id`, `tour_id`, `tour_stop_id`, `created_by` | `SET NULL` |
| `proofs_of_delivery.order_id` | `RESTRICT` |
| `proofs_of_delivery.signature_document_id`, `photo_document_id` | **`RESTRICT`** |
| `proofs_of_delivery.order_service_id`, `tour_stop_id`, `created_by` | `SET NULL` |
| `claims.organization_id`, `customer_id` | `RESTRICT` |
| `claims.order_id`, `order_service_id`, `tour_id`, `created_by`, `responsible_user_id` | `SET NULL` |

`RESTRICT` sur les deux documents est un choix délibéré contre `SET NULL` :
délier silencieusement une signature viderait la preuve de sa substance sans
laisser de trace.

**Suppression métier** : aucune route pour `TrackingEvent` et
`ProofOfDelivery` ; `DELETE` sur une réclamation refusé en 409 si elle est
clôturée — `closed_at` étant le seul critère objectif du modèle, aucune valeur
de `status` n'étant interprétée.

## 15. Ambiguïtés levées

| # | Ambiguïté | Traitement |
|---|-----------|------------|
| A | `Claim.legacyId` au prompt, absent du diagramme | Le diagramme l'emporte ; colonne non créée, test d'absence |
| B | Les `.puml` du §1 n'existent pas | Les `.txt` disponibles font foi, comme aux Phases 3 et 4 |
| C | `TourStop` fourni sans `Tour` (§6) | La tournée est **déduite** de l'arrêt, puis vérifiée dans l'organisation. Refuser obligerait à recopier une information que le modèle contient déjà |
| D | Nullabilité de `recipient_name` et `delivered_at` (§10) | Rendus **obligatoires** : ce sont les deux seuls champs qui situent la remise |
| E | Nullabilité de `created_by` (§6) | **Nullable** : événements système et imports terminal n'ont pas de compte associé |
| F | Changement de statut au dépôt d'une preuve (§11) | **Aucun** : le §11 l'interdit sans règle explicite déjà validée, et aucune n'existe dans les Phases 1 à 4 |
| G | Critère de suppression d'une réclamation (§29) | `closed_at`, le seul critère objectif du modèle. Aucune valeur de `status` n'est interprétée, le §16 interdisant d'en inventer |
| H | `CloseClaimAction` (§19) | Non créée : clôturer se réduit à un `PATCH` sur des champs existants. La clôture reste auditée séparément |
| I | Précision des décimaux (§27) | Convention existante reprise (`10,8`, `11,8`, `12,2`), pas les exemples du prompt qui créeraient deux conventions concurrentes |
| J | Modules `Http/` séparés (§5) | Convention existante conservée : `app/Http` pour la couche HTTP, `app/Modules` pour le métier |

## 16. Fichiers créés

**36 fichiers.**

Migrations (3), modèles (3), DTOs (4), Actions (5), exception (1), services (2),
Query Objects (3), Form Requests (7), Resources (9), Policies (3), Controllers
(3), factories (3), tests (4), documentation (4).

## 17. Fichiers modifiés

**4 fichiers**, tous par ajout :

```text
routes/api.php                       + 3 imports, + 21 routes
database/seeders/PermissionSeeder    + 8 permissions
app/Providers/AuthServiceProvider    + 3 modèles, + 3 policies
app/Shared/Database/MorphMap         + 3 alias métier
```

Aucune ligne des Phases 1 à 4 n'a été supprimée ni réécrite.

## 18. Éléments exclus

Classes non créées, conformément au §2 :

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
legacy_id (sur claims)
```

Également exclus : toute géolocalisation temps réel, toute session ou token
public de suivi, tout endpoint d'upload dans ces modules, tout enum pour
`eventType`, `status`, `claimType`, `cause` ou `result`, tout champ `status` sur
`ProofOfDelivery`.

## 19. Risques

1. **Aucun raccordement entre suivi et statuts métier.** Déposer une preuve de
   livraison ne fait pas avancer la commande, le service ni la tournée. C'est
   délibéré — le §11 l'interdit sans règle validée — mais cela signifie qu'un
   opérateur doit encore changer les statuts à la main. La règle est à arrêter
   côté métier.
2. **`eventType`, `status`, `claimType`, `cause` et `result` sans valeurs
   normatives.** Rien n'empêche `delivery` et `DELIVERY`. Une liste officielle
   permettra de créer les enums correspondants.
3. **Le §31 de la Phase 4 reste ouvert.** Les tables `tracking_events` et
   `proofs_of_delivery` existent désormais : le contrôle de suppression d'une
   tournée qui les référence **peut et doit maintenant être ajouté**. Il ne
   l'est pas dans cette phase, dont le périmètre ne couvre pas `DeleteTourAction`.
   C'est le point le plus concret à traiter.
4. **`SET NULL` sur `tracking_events.tour_id`.** Supprimer une tournée délie ses
   événements sans laisser de trace de son identité. Le point 3 le neutralise
   une fois traité.
5. **Aucune contrainte d'unicité.** Deux preuves de livraison peuvent coexister
   sur la même commande sans que rien ne le signale. C'est conforme au diagramme
   — `Order "1" -- "0..*" ProofOfDelivery` — mais une livraison partielle et un
   double dépôt accidentel sont indiscernables.

## 20. Prochaine phase

**Non commencée**, conformément au §39 : la Phase 6 (facturation et règlements
fournisseurs) attend une validation explicite.

Point à traiter en priorité, hérité de la Phase 4 : ajouter à `DeleteTourAction`
le refus de suppression lorsqu'une tournée est référencée par un
`TrackingEvent`, une `ProofOfDelivery` ou une `Claim`. Les trois tables existent
maintenant.
