# Audit du schéma `statuses`

Relevé sur la base réelle (`SHOW COLUMNS FROM statuses`), le 26 août 2026, avant
tout développement de la Phase 4. **Aucune colonne n'est ajoutée** : le
référentiel existe depuis la Phase 2 et cette phase s'y branche.

## Colonnes réelles

| Colonne | Type DB | Nullable | Usage |
|---|---|---|---|
| `id` | `char(26)` | non | ULID, clé primaire |
| `source` | `varchar(64)` | non | Entité concernée, **alias de `MorphMap`** — `order`, `package`, `provider`… |
| `status` | `int unsigned` | non | Identifiant numérique du statut, unique dans sa source. Repris des systèmes qui l'attendent sous cette forme |
| `code` | `varchar(64)` | non | **Valeur réellement stockée** dans les colonnes `status` du domaine |
| `label` | `varchar(255)` | non | Libellé affiché |
| `icon` | `varchar(64)` | oui | Nom d'icône Lucide |
| `active` | `tinyint(1)` | non (déf. `1`) | Un statut désactivé ne se propose plus à la saisie |
| `is_to_send` | `tinyint(1)` | non (déf. `0`) | Déclenche l'envoi d'une communication |
| `allows_content_changes` | `tinyint(1)` | non (déf. `0`) | Le contenu de la commande reste modifiable dans cet état |
| `requires_reason` | `tinyint(1)` | non (déf. `0`) | Le passage à cet état exige un motif |
| `position` | `smallint unsigned` | oui | Rang d'affichage |
| `created_at` / `updated_at` | `timestamp` | oui | — |

Index : `unique(source, code)` et `unique(source, status)`.

## Écarts avec le document de phase

Le prompt de Phase 4 décrit un référentiel avec `src`, `color` et
`background_color`, scopé par organisation. Le référentiel réel diffère sur
trois points, et c'est lui qui fait foi (§0 du prompt : le schéma réel prime).

| Attendu au prompt | Réel | Conséquence |
|---|---|---|
| `src` | `source` | Le filtre d'API est `?source=provider`, pas `?src=` |
| `color`, `background_color` | absents ; `icon` seul | Les couleurs restent celles du système de design. Le §40 le prévoit : « sinon utiliser le style neutre global existant » |
| Scope organisation | **plateforme** | Un statut décrit le cycle de vie du domaine. Deux organismes qui nommeraient différemment « confirmée » rendraient exports et échanges incomparables |
| `src = "providers"` (nom de table) | `source = "provider"` (alias morph) | Le vocabulaire des alias est déjà employé partout — audit, liaisons polymorphes. En inventer un second en produirait deux à maintenir |

## API existante

`GET /api/v1/statuses` — filtres `source` et `active`, recherche sur `code` et
`label`, tri sur `source`, `status`, `code`. Permission `statuses.view`.
`GET /api/v1/statuses/sources` rend la liste des sources possibles.

Écriture réservée à la plateforme (`statuses.create` / `.update` / `.delete`),
ce qui est cohérent avec la portée : un organisme ne redéfinit pas le cycle de
vie du domaine.

**Aucune API n'est à créer pour cette phase.**
