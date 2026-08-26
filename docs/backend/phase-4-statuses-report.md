# Phase 4 — Statuts créés au référentiel

Relevé après seeding, le 26 août 2026. Les colonnes `status` des tables
concernées restent **textuelles** : aucune n'a été convertie en clé étrangère,
aucun `status_id` n'a été créé.

| `source` | `code` | `label` | actif | lignes portant ce code |
|---|---|---|---|---|
| `provider` | `active` | Actif | oui | 1 |
| `provider` | `inactive` | Inactif | oui | 0 |
| `provider` | `blocked` | Bloqué | oui | 0 |
| `driver` | `active` | Actif | oui | 1 |
| `driver` | `inactive` | Inactif | oui | 0 |
| `vehicle` | `active` | Actif | oui | 1 |
| `vehicle` | `inactive` | Inactif | oui | 0 |
| `vehicle` | `maintenance` | En maintenance | oui | 0 |
| `type` | `active` | Actif | oui | 6 |
| `type` | `inactive` | Inactif | oui | 0 |
| `type_item` | `active` | Actif | oui | 7 |
| `type_item` | `inactive` | Inactif | oui | 0 |

## D'où viennent ces codes

Aucun n'a été inventé.

- `active` est la seule valeur présente en base sur ces cinq entités.
- `inactive` est son pendant, employé par le projet depuis les premiers
  référentiels et connu de l'interface (`status.inactive`, teinte du badge).
- `blocked` et `maintenance` viennent de la suite de tests, qui les exerce déjà
  — un fournisseur bloqué, un véhicule en maintenance. Ils ont été découverts en
  faisant échouer `FleetTest` après l'activation de la validation : le refus a
  révélé un vocabulaire réel, pas une erreur de test.

Un administrateur en ajoute d'autres depuis l'écran des statuts, sans
développement. C'est la raison d'être du référentiel.

## Vérification du schéma

```text
providers.status   varchar(32)   utf8mb4
drivers.status     varchar(32)   utf8mb4
vehicles.status    varchar(32)   utf8mb4
types.status       varchar(32)   utf8mb4
type_items.status  varchar(32)   utf8mb4
```

`php artisan tricolis:check-statuses --source=provider` (idem pour les quatre
autres) ne relève aucun orphelin. Sur l'ensemble des 38 entités, 15 valeurs
restent sans définition — toutes hors du périmètre de cette phase, inventoriées
dans `statuses-global-audit.md`.
