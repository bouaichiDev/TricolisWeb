# Audit des statuses — Phase 10

Relevé du 2 septembre 2026 sur la base réelle `tricolisweb`. Les colonnes ne
sont pas recopiées d'un document : elles viennent de `StatusSources::all()`,
qui parcourt la morph map et retient les entités dont la table porte réellement
une colonne `status`.

## 1. Les trois règles, et leur vérification

| Règle du §16 | Vérification | Résultat |
|---|---|---|
| `source.status` est un **code texte** | `SHOW COLUMNS` sur les 38 tables | **38 / 38 en `varchar`** — aucune exception |
| `statuses` porte la métadonnée | `StatusSeeder` + écran `/statuses` | 24 sources décrites |
| Aucun `status_id` | `information_schema.key_column_usage` vers `statuses` | **0 clé étrangère** |

La troisième mérite d'être dite autrement : la recherche n'a pas porté sur le
texte `status_id` mais sur les **contraintes réellement posées en base**. Une
colonne nommée autrement mais pointant vers `statuses` aurait été trouvée. Il
n'y en a aucune.

## 2. Les 38 sources

`semé` = la source a des entrées dans `statuses`. `codes` = valeurs réellement
présentes en base, six au plus.

| Source | Table | Semé | Codes observés |
|---|---|---|---|
| `order` | `orders` | oui | cancelled, completed, confirmed, draft |
| `order_line` | `order_lines` | oui | active, reception |
| `order_service` | `order_services` | oui | completed, draft, planned, ready_to_plan |
| `order_communication` | `order_communications` | oui | scheduled |
| `package` | `packages` | oui | draft, load, ready, received |
| `claim` | `claims` | oui | Effectuer |
| `customer` | `customers` | oui | active |
| `organization` | `organizations` | oui | active |
| `subscription` | `subscriptions` | oui | — |
| `user` | `users` | oui | active, invited |
| `service` | `services` | oui | active |
| `provider` | `providers` | oui | active |
| `driver` | `drivers` | oui | active |
| `vehicle` | `vehicles` | oui | active |
| `type` | `types` | oui | active |
| `type_item` | `type_items` | oui | active |
| `tour` | `tours` | oui | completed, confirmed, draft |
| `tour_stop` | `tour_stops` | oui | completed, pending |
| `tour_stop_service` | `tour_stop_services` | oui | completed, planned |
| `tour_period` | `tour_periods` | oui | planned |
| `invoice` | `invoices` | oui | closed, draft |
| `invoice_line` | `invoice_lines` | oui | billable |
| `provider_settlement` | `provider_settlements` | oui | draft |
| `export_job` | `export_jobs` | oui | — |
| `address` | `addresses` | **non** | active |
| `agency` | `agencies` | **non** | active |
| `depot` | `depots` | **non** | active |
| `document` | `documents` | **non** | active, received |
| `role` | `roles` | **non** | active |
| `organization_user` | `organization_users` | **non** | active, invited |
| `customer_site` | `customer_sites` | **non** | active |
| `customer_catalog` | `customer_catalogs` | **non** | active |
| `customer_catalog_item` | `customer_catalog_items` | **non** | active |
| `order_service_package` | `order_service_packages` | **non** | delivered, pending |
| `stock_item` | `stock_items` | **non** | active |
| `stock_location` | `stock_locations` | **non** | active |
| `stock_reservation` | `stock_reservations` | **non** | — |
| `tracking_event` | `tracking_events` | **non** | completed, planned |

**24 semées, 14 non.**

## 3. Les quatorze non semées — analyse, pas correction

Le §16 interdit de supprimer aveuglément et demande d'analyser. Trois cas
distincts, et aucun n'est un défaut :

### Neuf référentiels sans cycle de vie

`address`, `agency`, `depot`, `role`, `customer_site`, `customer_catalog`,
`customer_catalog_item`, `stock_item`, `stock_location`.

Toutes portent `active` et rien d'autre. Ce ne sont pas des états métier : une
adresse sert ou ne sert plus. `StatusSeeder` le dit déjà pour les entités
équivalentes qu'il sème — *« ressources et référentiels n'ont pas de cycle de
vie »*. Les semer ajouterait une ligne « Actif » sans que personne n'en tire
quoi que ce soit.

### Trois états gouvernés ailleurs

| Source | Gouverné par |
|---|---|
| `document` | le module Documents ; `received` vient d'un dépôt réel |
| `organization_user` | l'invitation ; `invited` → `active` |
| `order_service_package` | l'avancement de la prestation |

Ils **mériteraient** d'entrer au référentiel le jour où un écran voudra en
changer le libellé ou la couleur. Aucun ne le fait aujourd'hui : les afficher
passe par `StatusBadge` sans `source`, qui rend le code tel quel.

### Deux sans aucune donnée

`stock_reservation` et `export_job` — cette dernière **est** semée, mais la base
de développement n'a encore aucun envoi. Colonne vide n'est pas colonne absente.

## 4. Ce que le frontend en fait

`StatusBadge` accepte une `source` facultative :

```tsx
<StatusBadge status={order.status} source="order" />   // libellé du référentiel
<StatusBadge status={row.status} />                     // code tel quel
```

Sans `source`, le composant retombe sur une table de teintes par code
(`active`, `pending`, `blocked`…) et affiche le code. C'est le comportement
voulu pour les colonnes restées en chaîne libre : inventer une couleur pour
chacune serait faux.

### Un seul badge écrit ses propres teintes

`CommunicationStatusBadge` porte une table de neuf couleurs en dur. C'est une
entorse assumée et documentée dans le composant : les neuf `CommunicationStatus`
sont clos côté PHP, et leur progression — brouillon, programmé, envoyé, échec —
porte un sens visuel que le référentiel ne décrit pas. Le libellé, lui, vient
bien de `communicationStatuses.*`.

Aucun autre composant ne code de couleur ni de libellé de statut.

## 5. Ce qui n'entre pas dans `statuses`, et pourquoi

Le §16 les nomme, et la vérification confirme qu'aucun n'y est :

```text
CommunicationChannel   TemplateType   CommunicationEventType
RecipientRole          ExportFormat   ExportTransport
```

Ce sont des énumérations de **nature**, pas des états. Une communication n'est
pas « en e-mail » comme elle est « envoyée ». Les y forcer aurait laissé un
administrateur en désactiver un depuis l'écran des statuts, et cassé la
validation `Rule::in(...)` côté serveur.

Leurs libellés vivent en i18n : `communicationChannels.*`, `templateTypes.*`,
`communicationEvents.*`, `recipientRoles.*`.

## 6. Conclusion

Aucune correction n'est requise par cet audit.

```text
38 colonnes status, toutes textuelles
0  clé étrangère vers statuses
0  status_id
24 sources décrites au référentiel
14 non décrites, toutes analysées et justifiées
```
