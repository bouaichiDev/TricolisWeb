# Décisions base de données — Phase 3

Ce document répond au §16 du prompt Phase 3. Il complète
[`phase-1-database-decisions.md`](phase-1-database-decisions.md) et
[`phase-2-database-decisions.md`](phase-2-database-decisions.md), dont les
principes restent valables : ULID, isolation organisationnelle, morph map à
valeurs métier, statuts en `VARCHAR`.

Tout est compatible **MySQL 8**. Aucune fonctionnalité PostgreSQL n'est
employée : ni `JSONB`, ni `ILIKE`, ni index partiel, ni enum SQL.

---

## 1. Ordre des migrations

| # | Migration | Dépend de |
|---|-----------|-----------|
| 1 | `2026_08_01_200001_create_providers_table` | `organizations` |
| 2 | `2026_08_01_200002_create_drivers_table` | `providers`, `users` |
| 3 | `2026_08_01_200003_create_vehicle_types_table` | `organizations` |
| 4 | `2026_08_01_200004_create_vehicles_table` | `providers`, `vehicle_types` |

Ordre du §16 conservé : aucune dépendance n'impose de le modifier, `drivers` ne
référençant pas `vehicle_types`.

## 2. Nullabilité de chaque clé étrangère

| Colonne | Nullable | Raison |
|---------|----------|--------|
| `providers.organization_id` | non | Un fournisseur appartient toujours à une organisation. |
| `drivers.provider_id` | non | `Provider 1 — 0..* Driver` : le côté `1` impose la présence. |
| `drivers.user_id` | **oui** | Un chauffeur est une personne du sous-traitant ; il n'a pas nécessairement de compte sur la plateforme, et l'application chauffeur est hors périmètre. L'imposer obligerait à créer un compte pour chaque chauffeur saisi. |
| `vehicle_types.organization_id` | non | Référentiel propre à une organisation. |
| `vehicles.provider_id` | non | `Provider 1 — 0..* Vehicle`. |
| `vehicles.vehicle_type_id` | non | `VehicleType 1 — 0..* Vehicle`. |

Colonnes non-FK nullables : `legacy_id` sur les trois tables concernées,
`drivers.phone` et `drivers.email` — le §9 précise « `email` validé comme email
**si renseigné** ».

## 3. Stratégies de suppression

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `providers.organization_id` | `RESTRICT` | Supprimer une organisation ne doit pas emporter ses fournisseurs. |
| `drivers.provider_id` | `RESTRICT` | Un fournisseur avec chauffeurs ne disparaît pas silencieusement. |
| `drivers.user_id` | `SET NULL` | Cohérent avec la nullabilité : supprimer un compte ne supprime pas le chauffeur, qui reste une personne réelle. |
| `vehicle_types.organization_id` | `RESTRICT` | |
| `vehicles.provider_id` | `RESTRICT` | |
| `vehicles.vehicle_type_id` | `RESTRICT` | Le §16 l'exige explicitement : supprimer un type ne supprime pas les véhicules. |

**Aucun `cascadeOnDelete()`.** Aucune suppression de la Phase 3 n'entraîne de
suppression en chaîne.

### Refus applicatifs, avant que SQL n'intervienne

| Ressource | Refus | Code |
|-----------|-------|------|
| `Provider` | possède encore des chauffeurs ou des véhicules | 409 |
| `VehicleType` | utilisé par au moins un véhicule | 409 |

La contrainte `RESTRICT` est le filet de sécurité ; le contrôle applicatif la
précède pour renvoyer un message métier plutôt qu'une erreur SQL brute.

`Driver` et `Vehicle` n'ont pas de refus : le §24 demande de bloquer leur
suppression s'ils sont référencés par une tournée, mais **le module Tours
n'existe pas**. Le contrôle devra être ajouté avec la phase Planification, et il
devra l'être avant que les tournées ne soient exploitées — c'est signalé dans
les risques du rapport final.

## 4. Contraintes uniques

| Table | Contrainte | Portée |
|-------|-----------|--------|
| `providers` | `(organization_id, code)` | Deux organisations peuvent utiliser le même code fournisseur. |
| `drivers` | `(provider_id, code)` | Le code chauffeur est unique chez son fournisseur, pas au-delà. |
| `vehicle_types` | `(organization_id, code)` | |
| `vehicles` | `(provider_id, code)` | |
| `vehicles` | `registration_number` — **unique global** | Voir §5. |

`legacy_id` n'est **pas** unique. Aucune stratégie de reprise ne l'impose
aujourd'hui, et une donnée de reprise incohérente bloquerait l'import entier au
lieu de se signaler ligne par ligne. La colonne est indexée pour permettre les
rapprochements.

## 5. Périmètre de l'immatriculation

`registration_number` est unique sur **toute la table**, conformément au §13.

Une plaque identifie un véhicule physique : deux lignes portant la même
rendraient toute recherche terrain ambiguë, et un scan ne saurait plus quel
véhicule désigner.

Effet de bord accepté : deux organisations ne peuvent pas référencer le même
véhicule physique. Le cas ne se pose pas tant qu'un véhicule appartient à un
fournisseur, lui-même rattaché à une seule organisation. Si un sous-traitant
devait un jour travailler pour deux transporteurs de la plateforme, la
contrainte devrait être revue en `(provider_id, registration_number)`.

## 6. Index

| Table | Index |
|-------|-------|
| `providers` | `organization_id`, `(organization_id, code)` unique, `status`, `provider_type`, `legacy_id` |
| `drivers` | `provider_id`, `(provider_id, code)` unique, `user_id`, `status`, `legacy_id`, `email` |
| `vehicle_types` | `organization_id`, `(organization_id, code)` unique, `status` |
| `vehicles` | `provider_id`, `vehicle_type_id`, `(provider_id, code)` unique, `registration_number` unique, `status`, `legacy_id` |

Exactement les index recommandés par les §7, §9, §11 et §13.

Les listes de chauffeurs et de véhicules filtrent par jointure sur
`providers.organization_id` : l'index `providers.organization_id` sert cette
jointure, et `drivers.provider_id` / `vehicles.provider_id` la résolvent.

## 7. Précision des colonnes décimales

Convention des Phases 1 et 2, reprise sans invention :

| Grandeur | Précision | Précédents dans le projet |
|----------|-----------|---------------------------|
| Masse | `DECIMAL(12,3)` | `orders.weight`, `order_lines.weight`, `packages.weight` |
| Volume | `DECIMAL(12,4)` | `orders.volume`, `order_lines.volume`, `packages.volume` |

D'où :

```php
$table->decimal('payload_capacity', 12, 3);   // une masse
$table->decimal('volume_capacity', 12, 4);    // un volume
$table->unsignedInteger('pallet_capacity');   // entier non negatif garanti
```

Le §17 propose `12,3` pour les deux à titre d'exemple, mais demande d'abord de
« réutiliser la convention décimale déjà employée » : c'est ce qui prime.
Aligner `volume_capacity` sur `12,3` aurait créé deux conventions de volume
concurrentes dans la même base.

`pallet_capacity` est `UNSIGNED` : la négativité est impossible au niveau du
stockage, pas seulement de la validation.

## 8. Stratégie `legacy_id`

- Type `BIGINT UNSIGNED`, conforme au `bigint` du diagramme.
- **Nullable** : les données créées par l'API n'en ont pas.
- **Jamais clé primaire** : l'identité reste l'ULID.
- **Indexé** sur les trois tables qui le portent, pour permettre les
  rapprochements pendant la reprise.
- **Non exposé** dans les Resources : l'attribut est déclaré dans `#[Hidden]` au
  niveau des modèles `Provider`, `Driver` et `Vehicle`, et aucune Resource ne le
  restitue. Un test le vérifie.
- Acceptable en entrée d'API pour permettre aux scripts de reprise de le poser.

`VehicleType` n'a pas de `legacy_id` : le §11 ne le définit pas.

## 9. Timestamps et soft deletes

**Aucune des quatre tables ne porte `created_at`, `updated_at` ni `deleted_at`.**

Le §6 impose de respecter strictement les attributs du diagramme ; le §2 range
les « timestamps non définis » et les « soft deletes » parmi les ajouts
interdits. Les quatre classes n'en définissent aucun.

Conséquence assumée : la date de création d'un fournisseur n'est pas lisible sur
la ligne. Elle reste reconstituable depuis `audit_logs`, qui horodate chaque
création, modification et suppression avec son auteur. Le tri par défaut porte
donc sur `code`, pas sur une date.

Cette convention est cohérente avec d'autres tables du projet dépourvues de
timestamps : `order_lines`, `services`, `roles`, `permissions`,
`entity_addresses`.

L'absence de soft delete est compensée par les refus en 409 : une ressource
encore utilisée ne peut pas être supprimée, donc aucune suppression ne détruit
d'information rattachée.

## 10. Statuts laissés en chaîne

`providers.status`, `providers.provider_type`, `drivers.status`,
`vehicle_types.status` et `vehicles.status` sont des `VARCHAR`.

Le §2 interdit explicitement de créer `ProviderStatus`, `DriverStatus`,
`VehicleStatus`, `VehicleTypeStatus` ou un enum pour `providerType` : le
diagramme les déclare comme `string`, sans énumérer de valeurs.

Aucune valeur par défaut n'est posée en base : le statut est obligatoire et
fourni par l'appelant. Poser `active` par défaut aurait été inventer une valeur.

Les seeders et factories emploient `active`, `carrier`, `subcontractor`,
`partner` à titre d'exemples de démonstration, sans valeur normative.
