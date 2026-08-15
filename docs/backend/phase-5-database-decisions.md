# Décisions base de données — Phase 5

Répond au §26. Complète les décisions des Phases 1 à 4, dont les principes
restent valables : ULID, isolation organisationnelle, morph map à valeurs
métier, statuts en `VARCHAR`.

Tout est compatible **MySQL 8**. Aucune fonctionnalité PostgreSQL : ni `JSONB`,
ni `ILIKE`, ni PostGIS, ni index partiel, ni enum SQL.

---

## 1. Ordre des migrations

| # | Migration | Dépend de |
|---|-----------|-----------|
| 1 | `2026_08_03_100001_create_tracking_events_table` | `organizations`, `orders`, `order_services`, `tours`, `tour_stops`, `users` |
| 2 | `2026_08_03_100002_create_proofs_of_delivery_table` | `orders`, `order_services`, `tour_stops`, `documents`, `users` |
| 3 | `2026_08_03_100003_create_claims_table` | `organizations`, `customers`, `orders`, `order_services`, `tours`, `users` |

Ordre du §26. Les trois tables sont indépendantes entre elles : aucune ne
référence les deux autres.

## 2. Nullabilité

Deux sources : les **cardinalités** pour les clés étrangères, les sections
**« Contraintes »** du prompt pour les scalaires.

| Colonne | Nullable | Raison |
|---------|----------|--------|
| `tracking_events.order_id` | non | `Order "1" -- "0..*" TrackingEvent` |
| `tracking_events.order_service_id`, `tour_id`, `tour_stop_id` | **oui** | `"0..1"` chacun. Un événement peut précéder toute planification. |
| `tracking_events.event_type`, `status`, `occurred_at` | non | §6. Un événement sans type ni date ne se situe ni ne se qualifie. |
| `tracking_events.description` | **oui** | Un événement automatique n'a rien à décrire. |
| `tracking_events.latitude`, `longitude` | **oui** | Le §6 dit « valide **si renseignée** ». Un événement saisi au bureau n'a pas de coordonnées. |
| `proofs_of_delivery.order_id` | non | `Order "1"` |
| `proofs_of_delivery.order_service_id`, `tour_stop_id` | **oui** | `"0..1"` |
| `proofs_of_delivery.signature_document_id`, `photo_document_id` | **oui** | `Document "0..1"`. Le §10 interdit de les rendre obligatoires. |
| `claims.organization_id`, `customer_id` | non | `Customer "1" -- "0..*" Claim` |
| `claims.order_id`, `order_service_id`, `tour_id` | **oui** | `"0..1"`. Une réclamation peut viser la prestation globale. |
| `claims.title`, `claim_type`, `status`, `created_at` | non | Une réclamation sans objet, sans type ni statut n'est pas exploitable. |

### Les deux décisions non dictées par le diagramme

Le §10 demande explicitement de documenter quatre champs. Deux méritent d'être
justifiés :

**`proofs_of_delivery.recipient_name` — obligatoire.** Une preuve de livraison
sans destinataire ne prouve rien : c'est le seul champ qui identifie qui a reçu.
Le diagramme ne le marque pas facultatif, et le rendre nullable viderait la
classe de sa fonction.

**`proofs_of_delivery.delivered_at` — obligatoire.** Même raisonnement : une
preuve qui ne situe pas la remise dans le temps n'en est pas une.

**`created_by` — nullable sur les trois tables.** Le §6 demande de l'analyser
« selon le contexte d'événements système ». Un événement produit par un automate,
ou une preuve importée d'un terminal chauffeur, n'a pas de compte associé.
Précédent direct : `documents.created_by` est nullable depuis la Phase 1.

### Champs de résolution d'une réclamation

`decision`, `follow_up`, `result`, `cost`, `responsible_user_id` et `closed_at`
sont **tous nullables**. Le §15 l'exige : « ne pas rendre obligatoires des
informations de résolution lors de la création ». Une réclamation naît ouverte,
sans décision ni coût — ces champs se renseignent au fil de l'instruction, par
`PATCH`.

## 3. Stratégies de suppression

Aucune composition dans cette phase : les douze relations du diagramme sont des
associations (`--`). **Aucun `CASCADE`.**

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `tracking_events.organization_id` | `RESTRICT` | Le suivi est historique : il ne disparaît pas avec l'organisation. |
| `tracking_events.order_id` | `RESTRICT` | Supprimer une commande ne doit pas effacer son historique de suivi. |
| `tracking_events.order_service_id`, `tour_id`, `tour_stop_id` | `SET NULL` | Colonnes nullables. Supprimer une tournée n'efface pas les événements qu'elle a produits : l'événement reste rattaché à sa commande, son ancrage obligatoire. |
| `tracking_events.created_by` | `SET NULL` | Cohérent avec la nullabilité ; précédent `documents.created_by`. |
| `proofs_of_delivery.order_id` | `RESTRICT` | Une preuve a valeur probante : la commande ne peut pas disparaître sous elle. |
| `proofs_of_delivery.order_service_id`, `tour_stop_id` | `SET NULL` | Colonnes nullables. |
| `proofs_of_delivery.signature_document_id`, `photo_document_id` | **`RESTRICT`** | Voir ci-dessous. |
| `proofs_of_delivery.created_by` | `SET NULL` | Idem `tracking_events`. |
| `claims.organization_id`, `customer_id` | `RESTRICT` | Une réclamation sans client n'a plus de sens. |
| `claims.order_id`, `order_service_id`, `tour_id` | `SET NULL` | La réclamation survit à la commande qu'elle vise. |
| `claims.created_by`, `responsible_user_id` | `SET NULL` | Le départ d'un collaborateur ne supprime pas ses dossiers. |

### Pourquoi `RESTRICT` sur les deux documents

Le §26 laisse le choix entre `nullOnDelete` et `restrictOnDelete`. `SET NULL` a
été écarté : délier silencieusement une signature viderait la preuve de sa
substance **sans laisser de trace**. La ligne subsisterait, apparemment valide,
alors que ce qui la fonde aurait disparu.

Avec `RESTRICT`, un document référencé par une preuve ne peut pas être supprimé.
C'est cohérent avec le §29 : « ne pas supprimer les Documents liés
automatiquement ».

## 4. Suppression métier

| Ressource | API |
|-----------|-----|
| `TrackingEvent` | **aucune route** `PATCH` ni `DELETE` |
| `ProofOfDelivery` | **aucune route** `PATCH` ni `DELETE` |
| `Claim` | `PATCH` et `DELETE`, ce dernier refusé en **409** si la réclamation est clôturée |

Les routes absentes renvoient **405**, pas 403 : elles n'existent pas, plutôt
que d'exister en refusant. Deux tests le vérifient.

### Le critère de suppression d'une réclamation

Le §29 n'autorise la suppression que « si le statut ou les règles existantes
l'autorisent ». Or aucun workflow de statut n'existe, et le §16 interdit
d'inventer les valeurs de `status` — les interpréter reviendrait à décider
lesquelles sont supprimables, ce que personne n'a arrêté.

**Le seul critère objectif porté par le modèle est `closed_at`.** Une réclamation
clôturée documente une décision et parfois un coût : sa suppression est refusée.
Une réclamation ouverte reste supprimable par qui détient `claims.delete`.

## 5. Index

| Table | Index |
|-------|-------|
| `tracking_events` | `organization_id`, `order_id`, `order_service_id`, `tour_id`, `tour_stop_id`, `event_type`, `status`, `occurred_at`, `created_by`, **`(order_id, occurred_at)`** |
| `proofs_of_delivery` | `order_id`, `order_service_id`, `tour_stop_id`, `signature_document_id`, `photo_document_id`, `delivered_at`, `created_by` |
| `claims` | `organization_id`, `customer_id`, `order_id`, `order_service_id`, `tour_id`, `claim_type`, `status`, `responsible_user_id`, `created_at`, `closed_at` |

Exactement les index du §28 — moins `legacy_id`, la colonne n'existant pas.

L'index composite `(order_id, occurred_at)` sert l'usage nominal : la
consultation chronologique du suivi d'une commande, qui est exactement ce que
fait `GET /orders/{order}/tracking-events`.

## 6. Contraintes uniques

**Aucune.** Les trois classes sont événementielles ou déclaratives : rien n'y
est unique. Deux événements peuvent porter le même type au même instant sur la
même commande — c'est même le cas nominal quand plusieurs colis sont traités
ensemble.

## 7. Précision des décimaux

Le §27 interdit de choisir arbitrairement et demande de réutiliser la convention
existante. Elle existe :

| Grandeur | Précision | Précédent |
|----------|-----------|-----------|
| Latitude | `DECIMAL(10,8)` | `addresses.latitude` (Phase 1) |
| Longitude | `DECIMAL(11,8)` | `addresses.longitude` (Phase 1) |
| Montant | `DECIMAL(12,2)` | `orders.*_price`, `order_services.*_cost` (Phase 2) |

Les exemples du §27 — `decimal('latitude', 10, 7)` et `decimal('cost', 15, 2)` —
**ne sont pas repris** : ils créeraient deux conventions concurrentes dans la
même base pour les mêmes grandeurs. Une latitude stockée sur 7 décimales ici et
8 ailleurs produirait des écarts d'arrondi entre l'adresse d'un arrêt et
l'événement qui s'y produit.

Les bornes `-90/90` et `-180/180` sont validées côté Form Request. Ce sont des
contrôles techniques, pas des enums métier — le §6 le précise.

## 8. Timestamps

| Table | `created_at` | `updated_at` |
|-------|--------------|--------------|
| `tracking_events` | non | non |
| `proofs_of_delivery` | non | non |
| `claims` | **oui** | non |

`claims.created_at` existe parce que le diagramme déclare `createdAt` ;
`updated_at` n'existe nulle part, pour la même raison. Les trois modèles portent
`$timestamps = false`, et la date de création d'une réclamation est posée
explicitement par `CreateClaimAction`.

Le §7 est explicite : « ne pas créer de champ `updated_at` uniquement pour
permettre l'édition ».

Pour `tracking_events` et `proofs_of_delivery`, la date métier — `occurred_at`,
`delivered_at` — remplace avantageusement une date technique : c'est elle qui
compte, et c'est sur elle que porte le tri par défaut.

## 9. Absence de soft delete et d'enums

**Aucun soft delete.** Aucune des trois classes ne déclare `deletedAt`, et le §2
range `softDeletes` parmi les ajouts interdits. Le caractère historique est tenu
par l'absence de routes de suppression, pas par un drapeau.

**Aucun enum.** `event_type`, `status` (des trois tables), `claim_type`, `cause`
et `result` sont des `VARCHAR`. Le diagramme les déclare `string` sans énumérer
de valeurs, et les §6, §16 et §35 interdisent explicitement d'en inventer.

Les factories emploient `pickup`, `done`, `damage`, `open` à titre d'exemples de
test, sans valeur normative — le §35 l'autorise pour les jeux de démonstration
et interdit d'en faire des listes de référence.

## 10. Absence de `legacy_id` sur `claims`

Le §14 du prompt liste `legacyId: bigint` parmi les attributs de `Claim`, et le
§28 le mentionne dans les index. **Le diagramme ne le contient pas** : `Claim` y
compte 18 attributs, de `id` à `closedAt`.

La colonne n'est pas créée. Le §1 pose que « les diagrammes ont priorité sur les
anciens prompts », le §2 interdit d'ajouter un attribut absent des diagrammes, et
c'est la même décision qu'en Phase 3, où `legacy_id` a été retiré de `providers`,
`drivers` et `vehicles` lors du réalignement. Un test vérifie son absence.
