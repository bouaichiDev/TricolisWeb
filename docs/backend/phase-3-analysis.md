# Analyse Phase 3 — Fournisseurs et ressources

Ce document répond au §3 du prompt « Tricolis V2 — Backend Phase 3 ». Il fige le
modèle avant toute migration, comme l'exige la règle : aucune migration ne doit
être écrite avant que le tableau de correspondance du §6 soit terminé.

---

## 1. Sources de vérité — écart constaté

Le prompt désigne comme sources officielles :

```text
Conception/diagramme/00-diagramme-classes-partagees.puml
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

**Ces deux fichiers n'existent pas dans le dépôt.** Le répertoire ne contient que :

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Et le modèle décrit par le prompt **contredit** ces `.txt` sur trois des quatre
classes :

| Classe | Prompt Phase 3 (§7, §9, §13) | `.txt` présent (juillet) |
|--------|------------------------------|--------------------------|
| `Provider` | `legacyId`, `providerType` ; « ne définit **pas** de `addressId`, `contactId` » | `addressId`, `contactId` ; ni `legacyId`, ni `providerType` |
| `Driver` | `userId`, `firstName`, `lastName`, `phone`, `email`, `legacyId` ; pas d'`organizationId` | `organizationId`, `addressId`, `contactId`, `name` ; ni `userId`, ni `phone`, ni `email` |
| `Vehicle` | + `legacyId` | pas de `legacyId` |
| `VehicleType` | identique | identique |

`Driver` n'a quasiment aucun attribut commun entre les deux versions.

**Arbitrage retenu, validé par le porteur du projet** : le prompt Phase 3 fait
foi. Il énumère les attributs, les colonnes MySQL, les relations, les index et
les exclusions de façon exhaustive — il constitue la transcription du diagramme
à jour.

**Action requise côté conception** : remplacer les deux `.txt` par les `.puml`
officiels. Tant qu'ils sont absents, aucune vérification automatique n'est
possible entre le modèle livré et le diagramme.

## 2. État du code avant modification

Phases 1 et 2 livrées et vertes : **189 tests**, 513 assertions, 143 routes.

| Élément | Version / état |
|---------|----------------|
| Laravel | 13.23.0 |
| PHP | 8.4.2 |
| Base | MySQL 8 (aucune fonctionnalité PostgreSQL) |
| Auth | Sanctum 4 |
| Tests | Pest 5, `RefreshDatabase` |
| Style | Pint |
| Doc API | Scramble (`dedoc/scramble`) — déjà installé, aucune dépendance ajoutée |

Tables existantes utiles à cette phase : `organizations`, `users`,
`organization_users`, `roles`, `permissions`, `audit_logs`.

**Aucune** des quatre tables de la Phase 3 n'existe : `providers`, `drivers`,
`vehicle_types` et `vehicles` sont toutes à créer.

Mécanismes réutilisés sans duplication :

- `App\Shared\Database\Concerns\HasUlid` — génération ULID ;
- `App\Shared\Organizations\CurrentOrganizationContext` + middleware
  `organization` — contexte organisationnel ;
- `App\Policies\BaseOrganizationPolicy` — vérification permission + appartenance ;
- `App\Modules\Audit\Actions\WriteAuditLog` — audit explicite ;
- `App\Shared\Http\Responses\ApiResponse` — enveloppe `data` / `meta` / `links` ;
- `App\Shared\Http\Requests\ListRequest` — pagination, tri, recherche ;
- `App\Shared\Support\InputMapper` — camelCase → snake_case ;
- `App\Shared\Database\MorphMap` — alias métier pour `AuditLog.entityType`.

### Conventions réellement utilisées, et écart avec le §5

Le §5 propose de placer `Http/` dans chaque module. Le projet fait autrement
depuis la Phase 1 : la couche HTTP vit dans `app/Http/{Controllers,Requests,Resources}`,
le métier dans `app/Modules/<Domaine>/{Models,Actions,Queries,DTOs}`, les
Policies dans `app/Policies`.

**Décision** : conserver la convention existante. Le §5 demande lui-même de
« respecter l'architecture modulaire déjà utilisée » et le §26 d'« adapter aux
conventions réelles du projet ». Introduire une seconde structure produirait
deux emplacements concurrents pour la même responsabilité.

## 3. Classes implémentées

Quatre, et seulement quatre : `Provider`, `Driver`, `VehicleType`, `Vehicle`.

## 4. Relations et cardinalités

```text
Organization "1" -- "0..*" Provider
Organization "1" -- "0..*" VehicleType
Provider     "1" -- "0..*" Driver
Provider     "1" -- "0..*" Vehicle
VehicleType  "1" -- "0..*" Vehicle
Driver       "0..*" --> "0..1" User
```

Relations Eloquent :

```text
Provider    belongsTo Organization ; hasMany Driver ; hasMany Vehicle
Driver      belongsTo Provider     ; belongsTo User
VehicleType belongsTo Organization ; hasMany Vehicle
Vehicle     belongsTo Provider     ; belongsTo VehicleType
```

**Aucune relation vers `Address`, `Contact` ou `Document`** — le §7 l'interdit
explicitement pour `Provider`, et aucune n'est définie pour les trois autres.
Si une liaison devient nécessaire, elle passera par les mécanismes partagés
`EntityAddress` / `EntityContact` / `DocumentLink` de la Phase 1, sans colonne
dédiée.

### Isolation organisationnelle

`Driver` et `Vehicle` **ne portent pas** d'`organizationId`. Leur appartenance
passe par le fournisseur :

```text
Driver  -> provider.organization_id
Vehicle -> provider.organization_id
```

Toute requête sur ces deux tables joint donc `providers`. Le filtre est
centralisé dans les Query Objects et dans un service de garde unique, pour
qu'aucun contrôleur ne puisse l'omettre — c'est ce qui empêche une fuite entre
organisations.

Invariant supplémentaire (§15) : le `Provider` et le `VehicleType` d'un
`Vehicle` doivent appartenir à la **même** organisation. Vérifié à l'écriture.

## 5. Enums

**Aucun.** `status` et `providerType` sont des `string` au diagramme. Le §2
interdit explicitement de créer `ProviderStatus`, `DriverStatus`,
`VehicleStatus`, `VehicleTypeStatus` ou un enum pour `providerType`.

Les colonnes restent `VARCHAR`, validées comme chaînes. Aucune valeur n'est
inventée ; aucune valeur par défaut n'est imposée côté base : le statut est
obligatoire et fourni par l'appelant.

## 6. Tableau de correspondance

### Provider → `providers`

| Attribut PlantUML | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `legacyId` | bigint | `legacy_id` BIGINT UNSIGNED | **oui** | index | — |
| `organizationId` | ULID | `organization_id` CHAR(26) | non | index + unique composite | FK `organizations.id` RESTRICT |
| `code` | string | `code` VARCHAR(64) | non | unique `(organization_id, code)` | — |
| `name` | string | `name` VARCHAR(255) | non | — | — |
| `providerType` | string | `provider_type` VARCHAR(64) | non | index | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### Driver → `drivers`

| Attribut PlantUML | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `legacyId` | bigint | `legacy_id` BIGINT UNSIGNED | **oui** | index | — |
| `providerId` | ULID | `provider_id` CHAR(26) | non | index + unique composite | FK `providers.id` RESTRICT |
| `userId` | ULID | `user_id` CHAR(26) | **oui** | index | FK `users.id` SET NULL |
| `code` | string | `code` VARCHAR(64) | non | unique `(provider_id, code)` | — |
| `firstName` | string | `first_name` VARCHAR(255) | non | — | — |
| `lastName` | string | `last_name` VARCHAR(255) | non | — | — |
| `phone` | string | `phone` VARCHAR(32) | **oui** | — | — |
| `email` | string | `email` VARCHAR(255) | **oui** | index | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### VehicleType → `vehicle_types`

| Attribut PlantUML | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `organizationId` | ULID | `organization_id` CHAR(26) | non | index + unique composite | FK `organizations.id` RESTRICT |
| `code` | string | `code` VARCHAR(64) | non | unique `(organization_id, code)` | — |
| `name` | string | `name` VARCHAR(255) | non | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### Vehicle → `vehicles`

| Attribut PlantUML | Type | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `legacyId` | bigint | `legacy_id` BIGINT UNSIGNED | **oui** | index | — |
| `providerId` | ULID | `provider_id` CHAR(26) | non | index + unique composite | FK `providers.id` RESTRICT |
| `vehicleTypeId` | ULID | `vehicle_type_id` CHAR(26) | non | index | FK `vehicle_types.id` RESTRICT |
| `code` | string | `code` VARCHAR(64) | non | unique `(provider_id, code)` | — |
| `registrationNumber` | string | `registration_number` VARCHAR(32) | non | **unique global** | — |
| `payloadCapacity` | decimal | `payload_capacity` DECIMAL(12,3) | non | — | — |
| `volumeCapacity` | decimal | `volume_capacity` DECIMAL(12,4) | non | — | — |
| `palletCapacity` | int | `pallet_capacity` INT UNSIGNED | non | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

## 7. Décisions de nullabilité

| Colonne | Choix | Raison |
|---------|-------|--------|
| `drivers.user_id` | **nullable** | Un chauffeur est une personne du sous-traitant ; il n'a pas nécessairement de compte sur la plateforme. L'application chauffeur est hors périmètre. Rendre la colonne obligatoire exigerait de créer un compte pour chaque chauffeur saisi. |
| `drivers.phone`, `drivers.email` | **nullable** | Le diagramme ne les marque pas obligatoires et le §9 dit « `email` validé comme email **si renseigné** ». |
| `legacy_id` (3 tables) | **nullable** | Réservé à la reprise depuis l'ancienne plateforme ASP.NET. Les données créées par l'API n'en ont pas. Indexé, jamais clé primaire, **non exposé** dans les Resources publiques. |
| Tout le reste | **non nullable** | Les §7, §9, §11 et §13 les déclarent obligatoires. |

## 8. Décisions de suppression

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `providers.organization_id` | `RESTRICT` | Conforme au §16. Supprimer une organisation ne doit pas emporter ses fournisseurs. |
| `drivers.provider_id` | `RESTRICT` | Un fournisseur avec chauffeurs ne disparaît pas silencieusement. |
| `drivers.user_id` | `SET NULL` | Cohérent avec la nullabilité : supprimer un compte ne doit pas supprimer le chauffeur. |
| `vehicle_types.organization_id` | `RESTRICT` | |
| `vehicles.provider_id` | `RESTRICT` | |
| `vehicles.vehicle_type_id` | `RESTRICT` | Le §16 l'exige : supprimer un type ne supprime pas les véhicules. |

Aucun `cascadeOnDelete()`. Aucun soft delete — le §2 interdit d'en ajouter, et
la protection métier passe par les refus en 409.

Refus applicatifs avant suppression (§24) :

- **Provider** : refusé s'il a des chauffeurs ou des véhicules → 409.
- **VehicleType** : refusé s'il est utilisé par un véhicule → 409.
- **Driver**, **Vehicle** : le §24 demande de refuser s'ils sont référencés par
  une tournée. **Le module Tours n'existe pas encore** : la vérification est
  donc sans objet aujourd'hui et sera ajoutée avec la Phase Planification. Ce
  report est signalé dans les risques du rapport final.

## 9. Timestamps et soft deletes

**Aucune des quatre tables ne porte `created_at`, `updated_at` ni `deleted_at`.**

Le §6 impose de respecter strictement les attributs du diagramme, et le §2 range
les « timestamps non définis » et les « soft deletes » parmi les ajouts
interdits. Les diagrammes ne les définissent pour aucune des quatre classes.

Conséquence assumée : la date de création d'un fournisseur n'est pas lisible
sur la ligne. Elle reste reconstituable depuis `audit_logs`, qui horodate chaque
création, modification et suppression. Le tri par défaut se fait donc sur `code`,
et non sur une date.

Cette convention est cohérente avec d'autres tables du projet qui n'ont pas de
timestamps (`order_lines`, `services`, `roles`, `permissions`, `entity_addresses`).

## 10. Précision des décimales

Convention déjà employée en Phases 1 et 2, reprise ici :

| Grandeur | Précision | Exemples existants |
|----------|-----------|--------------------|
| Poids | `DECIMAL(12,3)` | `orders.weight`, `order_lines.weight`, `packages.weight` |
| Volume | `DECIMAL(12,4)` | `orders.volume`, `order_lines.volume`, `packages.volume` |

D'où `payload_capacity DECIMAL(12,3)` (une masse) et
`volume_capacity DECIMAL(12,4)` (un volume). Le §17 propose `12,3` pour les
deux à titre d'exemple, mais demande d'abord de « réutiliser la convention
décimale déjà employée » — c'est ce qui est fait.

`pallet_capacity` est un `INT UNSIGNED` : entier non négatif garanti par la base.

## 11. Périmètre de l'immatriculation

`registration_number` est **unique globalement**, comme le §13 le recommande.

Un numéro d'immatriculation identifie un véhicule physique : deux lignes
portant la même plaque rendraient toute recherche terrain ambiguë. La contrainte
est donc posée sur la table entière, et non par fournisseur.

Effet de bord accepté : deux organisations ne peuvent pas référencer le même
véhicule physique. Le cas ne se présente pas tant qu'un véhicule appartient à un
fournisseur, lui-même rattaché à une seule organisation.

## 12. Ordre des migrations

1. `providers` — dépend de `organizations`
2. `drivers` — dépend de `providers` et `users`
3. `vehicle_types` — dépend de `organizations`
4. `vehicles` — dépend de `providers` et `vehicle_types`

Ordre du §16 conservé tel quel : aucune dépendance ne l'impose de changer.

## 13. Endpoints prévus

```text
GET    /api/v1/providers              PATCH  /api/v1/providers/{provider}
POST   /api/v1/providers              DELETE /api/v1/providers/{provider}
GET    /api/v1/providers/{provider}

GET    /api/v1/drivers                PATCH  /api/v1/drivers/{driver}
POST   /api/v1/drivers                DELETE /api/v1/drivers/{driver}
GET    /api/v1/drivers/{driver}

GET    /api/v1/vehicle-types          PATCH  /api/v1/vehicle-types/{vehicleType}
POST   /api/v1/vehicle-types          DELETE /api/v1/vehicle-types/{vehicleType}
GET    /api/v1/vehicle-types/{vehicleType}

GET    /api/v1/vehicles               PATCH  /api/v1/vehicles/{vehicle}
POST   /api/v1/vehicles               DELETE /api/v1/vehicles/{vehicle}
GET    /api/v1/vehicles/{vehicle}
```

**Routes imbriquées non créées.** Le §10 et le §14 les rendent facultatives,
« uniquement si cette convention est déjà utilisée ». Le projet nidifie quand
l'enfant n'a pas de sens seul (`orders/{order}/lines`,
`customers/{customer}/catalogs`), et laisse à plat ce qui se consulte
transversalement. Un chauffeur et un véhicule se cherchent d'abord par
immatriculation ou par nom, pas en naviguant par fournisseur : ils restent à
plat, avec un filtre `providerId`.

**Aucun endpoint de changement de statut séparé.** Le §8 ne l'autorise que si la
convention existe déjà. Elle existe pour `customers` et `orders`, mais s'y
justifie par un workflow de transitions. Ici `status` est une chaîne libre sans
workflow : `PATCH` suffit, et créer un second chemin d'écriture serait un
doublon.

## 14. Permissions prévues

Seize permissions, exactement celles du §22 :

```text
providers.view      drivers.view       vehicle_types.view      vehicles.view
providers.create    drivers.create     vehicle_types.create    vehicles.create
providers.update    drivers.update     vehicle_types.update    vehicles.update
providers.delete    drivers.delete     vehicle_types.delete    vehicles.delete
```

`vehicle_types` reçoit ses propres permissions, contrairement aux référentiels
de colis de la Phase 2 qui réutilisent `packages.*` : le §22 les énumère
explicitement.

## 15. Tests prévus

Couverture demandée par les §30 à §33 : CRUD complet des quatre entités,
suppressions refusées, unicité par périmètre, clés étrangères hors organisation,
cohérence `Provider` ↔ `VehicleType`, capacités négatives, recherche, filtres,
tri, pagination, permission manquante, IDOR, audit.

Les tests de permis, de disponibilité et de contrat ne sont pas écrits : les
entités correspondantes n'existent pas.

Les tests référençant une tournée (§31, §33) ne sont pas écrits non plus — le
module Tours n'existe pas.

## 16. Éléments volontairement non implémentés car absents des diagrammes

```text
ProviderContract
ProviderContractVersion
ProviderContractDriver
ProviderContractVehicle
ProviderContractDocument
ProviderPriceList
DriverAvailability
VehicleAvailability
VehicleCapacity
```

Ainsi que, au niveau des attributs :

- `ProviderStatus`, `DriverStatus`, `VehicleStatus`, `VehicleTypeStatus` et tout
  enum pour `providerType` — ces propriétés sont des `string` ;
- `settings`, `metadata`, `taxNumber` ;
- `registrationNumber`, `phone`, `email`, `addressId`, `contactId` sur `Provider` ;
- permis de conduire, `employee_number`, `license_number`, `license_expiration`,
  `active`, `availability` sur `Driver` ;
- `name`, `emission_type`, `gps_provider`, `gps_external_id`, `availability`,
  capacité séparée, documents spécifiques, dates d'entretien sur `Vehicle` ;
- capacité par défaut, description, `emission_type` sur `VehicleType` ;
- timestamps et soft deletes sur les quatre tables.
