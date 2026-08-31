# Audit global des colonnes `status`

Relevé sur la base réelle le 26 août 2026. La liste n'est pas écrite à la main :
elle vient de `StatusSources::all()`, qui parcourt la morph map et retient les
entités dont la table porte réellement une colonne `status`. **38 entités** sont
concernées ; 13 disposaient d'entrées au référentiel au 26 août, 17 après les
phases 4 et 6.

Toutes les colonnes sont des `varchar` en `utf8mb4` — aucune n'est un entier,
aucune n'est une clé étrangère. C'est ce que demande la règle projet : le code
textuel reste la valeur métier persistée.

## Colonne « Action »

- **Phase 4** — statuts créés au référentiel et validation backend activée dans
  cette phase.
- **Phase 6** — statuts créés au référentiel avec la facturation : `invoice`,
  `invoice_line`, `provider_settlement` et `export_job`.
- **À sa phase** — l'entité relève d'un domaine non encore repris ; la règle §56
  s'y appliquera quand cette phase arrivera. Le statut reste accepté en chaîne
  libre d'ici là, comme aujourd'hui.

| Entité (`source`) | Table | Type | Codes en base | Au référentiel | Action |
|---|---|---|---|---|---|
| `provider` | `providers` | `varchar(32)` | `active` | non | **Phase 4** |
| `driver` | `drivers` | `varchar(32)` | `active` | non | **Phase 4** |
| `vehicle` | `vehicles` | `varchar(32)` | `active` | non | **Phase 4** |
| `type` | `types` | `varchar(32)` | `active` | non | **Phase 4** |
| `type_item` | `type_items` | `varchar(32)` | `active` | non | **Phase 4** |
| `order` | `orders` | `varchar(32)` | `draft`, `confirmed` | oui | — |
| `order_line` | `order_lines` | `varchar(32)` | `active` | oui | — |
| `order_service` | `order_services` | `varchar(32)` | `draft`, `pending`, `planned` | oui | — |
| `order_communication` | `order_communications` | `varchar(16)` | `scheduled` | oui | — |
| `package` | `packages` | `varchar(32)` | `draft`, `received` | oui | — |
| `claim` | `claims` | `varchar(32)` | `Effectuer` | oui | — |
| `customer` | `customers` | `varchar(20)` | `active` | oui | — |
| `organization` | `organizations` | `varchar(20)` | `active` | oui | — |
| `service` | `services` | `varchar(32)` | `active` | oui | — |
| `subscription` | `subscriptions` | `varchar(20)` | — | oui | — |
| `tour` | `tours` | `varchar(32)` | — | oui | — |
| `tour_stop` | `tour_stops` | `varchar(32)` | — | oui | — |
| `user` | `users` | `varchar(20)` | `active` | oui | — |
| `address` | `addresses` | `varchar(20)` | `active` | non | À sa phase |
| `agency` | `agencies` | `varchar(20)` | `active` | non | À sa phase |
| `customer_catalog` | `customer_catalogs` | `varchar(32)` | `active` | non | À sa phase |
| `customer_catalog_item` | `customer_catalog_items` | `varchar(32)` | `active` | non | À sa phase |
| `customer_site` | `customer_sites` | `varchar(20)` | `active` | non | À sa phase |
| `depot` | `depots` | `varchar(20)` | `active` | non | À sa phase |
| `document` | `documents` | `varchar(20)` | `active`, `received` | non | À sa phase |
| `export_job` | `export_jobs` | `varchar(32)` | `pending`, `processing`, `sent`, `failed` | oui | **Phase 6** |
| `invoice` | `invoices` | `varchar(32)` | `draft`, `closed` | oui | **Phase 6** |
| `invoice_line` | `invoice_lines` | `varchar(32)` | `billable` | oui | **Phase 6** |
| `order_service_package` | `order_service_packages` | `varchar(32)` | `pending` | non | À sa phase |
| `organization_user` | `organization_users` | `varchar(20)` | `active` | non | À sa phase |
| `provider_settlement` | `provider_settlements` | `varchar(32)` | `draft`, `closed` | oui | **Phase 6** |
| `role` | `roles` | `varchar(20)` | `active` | non | À sa phase |
| `stock_item` | `stock_items` | `varchar(32)` | `active`, `archived` | oui | **Phase 7** |
| `stock_location` | `stock_locations` | `varchar(32)` | `active`, `inactive`, `blocked` | oui | **Phase 7** |
| `stock_reservation` | `stock_reservations` | `varchar(32)` | `active`, `confirmed`, `released` | oui | **Phase 7** |
| `tour_period` | `tour_periods` | `varchar(32)` | — | non | À sa phase |
| `tour_stop_service` | `tour_stop_services` | `varchar(32)` | — | non | À sa phase |
| `tracking_event` | `tracking_events` | `varchar(32)` | `planned` | non | À sa phase |

## Pourquoi la validation n'est pas activée partout d'un coup

Activer le contrôle sur les 38 entités reviendrait à refuser, du jour au
lendemain, toute écriture portant un code non encore décrit — sur des domaines
que cette phase ne touche pas et dont les codes n'ont pas été arrêtés. Le §56 du
prompt pose d'ailleurs la règle **pour les tables à venir** ; la reprise des
domaines déjà livrés se fera à leur phase, avec le seeding qui va avec.

La commande `tricolis:check-statuses` (§48) balaie **les 38 entités** et signale
les valeurs orphelines sans jamais les supprimer. Elle donne la mesure du
travail restant sans imposer un refus prématuré.

## Valeurs orphelines au 26 août 2026

`php artisan tricolis:check-statuses` en relevait **15**, toutes hors du
périmètre de la phase 6 — `provider`, `driver`, `vehicle`, `type` et
`type_item` étaient déjà couverts. La phase 7 en retire deux de plus :
`stock_item` et `stock_location` (voir plus bas).

| Source | Table | Code | Lignes |
|---|---|---|---|
| `address` | `addresses` | `active` | 7 |
| `agency` | `agencies` | `active` | 2 |
| `customer_catalog` | `customer_catalogs` | `active` | 2 |
| `customer_catalog_item` | `customer_catalog_items` | `active` | 2 |
| `customer_site` | `customer_sites` | `active` | 1 |
| `depot` | `depots` | `active` | 2 |
| `document` | `documents` | `active` | 2 |
| `document` | `documents` | `received` | 2 |
| `order_service_package` | `order_service_packages` | `pending` | 6 |
| `organization_user` | `organization_users` | `active` | 3 |
| `role` | `roles` | `active` | 5 |
| `service` | `services` | `active` | 3 |
| `tracking_event` | `tracking_events` | `planned` | 1 |

Le cas de `service` mérite attention : la source **a** des entrées au
référentiel — six codes issus de son énumération — mais aucune ne s'appelle
`active`, qui est pourtant la valeur stockée sur les trois services existants.
Une source présente au référentiel ne garantit donc pas que ses données y soient
décrites ; seul le couple `source` + `code` le dit. C'est exactement ce que la
commande vérifie, et c'est pourquoi la colonne « Au référentiel » du tableau
ci-dessus, qui raisonne par source, ne suffit pas à conclure.

Aucune de ces lignes n'est modifiée : ce sont des données réelles, et leur
arbitrage — ajouter le code au référentiel ou corriger la donnée — appartient à
la phase qui reprendra ce domaine.

## Ce que la phase 6 a ajouté — 28 août 2026

Quatre sources rejoignent le référentiel avec la facturation.

| Source | Codes | Transitions |
|---|---|---|
| `invoice` | `draft`, `closed` | `draft → closed` |
| `invoice_line` | `billable` | aucune |
| `provider_settlement` | `draft`, `closed` | `draft → closed` |
| `export_job` | `pending`, `processing`, `sent`, `failed` | aucune |

**Un seul passage pour la facture, et il ne revient pas.** Le §22 fige un
document clôturé : le client le détient peut-être déjà, et un retour au
brouillon laisserait deux vérités. La table des transitions dit donc `draft →
closed`, et rien d'autre — c'est le référentiel, non le code, qui refuse la
réouverture.

**Une ligne de facture n'a qu'un état.** `billable` décrit ce qu'elle est ; le
diagramme n'en énumère pas d'autre, et en inventer un — `invoiced`, `paid` —
aurait créé un cycle de vie que rien ne fait avancer.

**Les états d'un envoi ne se décrivent pas par des transitions.** Un envoi passe
de `pending` à `processing`, puis à `sent` ou `failed` — mais une relance le
ramène à `pending` depuis `failed`, et le rejeu d'un envoi bloqué depuis
`processing`. Les décrire comme un cycle contraint aurait interdit la reprise
que le §147 exige. L'état est donc au référentiel pour être nommé et filtré,
sans machine qui le gouverne.

**`provider_settlement` porte `closed` sans qu'aucune action ne l'y mène.** Le
statut existe au référentiel, et la mise à jour générique l'accepte ; aucun
écran ne le propose, la phase 6 ne décrivant pas de clôture de décompte. Le
noter vaut mieux que d'inventer une action que la spécification ne demande pas.

### Le code exact de « Clôturée »

Le §174 demande qu'il soit écrit noir sur blanc, parce que tout le mécanisme
d'envoi en dépend :

```text
source = invoice
code   = closed
label  = Clôturée
```

`closed` est la valeur **stockée** dans `invoices.status` ; « Clôturée » n'est
que son libellé d'affichage, porté par la table `statuses`. Le code applicatif
ne connaît que `closed` — `InvoiceClosure::CLOSED` — et jamais le libellé, qui
peut être traduit ou réécrit sans rien casser.

### Aucun `status_id`

Vérifié sur les quatre tables de cette phase : `invoices`, `invoice_lines`,
`provider_settlements` et `export_jobs` portent chacune une colonne `status` en
`varchar(32)`, et **aucune** ne porte de clé étrangère vers `statuses`. Le
référentiel décrit les codes, il ne les héberge pas : une facture reste lisible
si le référentiel change, et le §170 l'interdit explicitement.



## Ce que la phase 7 a ajouté — 31 août 2026

Trois sources entrent au référentiel : `stock_item`, `stock_location` et
`stock_reservation`. Elles étaient déclarées dans `MorphMap` depuis la phase 7
backend, mais `StatusSeeder` les ignorait — le frontend écrivait donc `active`
en dur dans ses formulaires, et aucun statut ajouté par un administrateur
n'aurait été proposé.

| Source | Codes semés | Origine du code |
|---|---|---|
| `stock_item` | `active`, `archived` | fabrique (`active`) et `StockItemTest` (`archived`) |
| `stock_location` | `active`, `inactive`, `blocked` | fabrique (`active`), `StockLocationTest` (`blocked`), `inactive` par symétrie avec les autres référentiels |
| `stock_reservation` | `active`, `confirmed`, `released` | fabrique (`active`, `released`) et `StockReservationTest` (`confirmed`) |

**Aucun code n'est inventé** : chacun est déjà écrit en base par le projet, à
l'exception d'`inactive` pour les emplacements, qui est le pendant employé par
toutes les autres ressources du référentiel.

### Deux tables sans statut, et c'est voulu

`stock_balances` et `stock_movements` **n'ont pas de colonne `status`**, et n'en
reçoivent pas. Un solde est un état calculé, un mouvement est un fait daté :
ni l'un ni l'autre n'a de cycle de vie. Le §32 l'interdit explicitement.

### Aucun `status_id`

Vérifié sur les trois tables de cette phase : `stock_items`, `stock_locations`
et `stock_reservations` portent chacune une colonne `status` en `varchar(32)`,
et **aucune** ne porte de clé étrangère vers `statuses`.

### `released` n'est pas un statut de formulaire

`ReleaseStockReservationAction` écrit `status` et `released_at` dans la même
transaction. Le code figure au référentiel pour être **affiché**, mais aucun
formulaire de création ne le propose : une réservation ne naît pas libérée.


## Ce que la phase 8 a confirmé — 31 août 2026

**Aucune source n'a été ajoutée**, et c'est le résultat attendu.

`export_job` était déjà au référentiel depuis la phase 6, avec ses quatre codes
`pending`, `processing`, `sent`, `failed`. Le §101 demandait de l'ajouter ou de
le confirmer : il est confirmé, et le frontend le consomme désormais par
`StatusBadge source="export_job"` sur les trois écrans d'envoi — liste globale,
fiche, et historique d'un client. Aucune couleur ni aucun libellé n'est écrit en
dur.

### La source s'appelle `export_job`, au singulier

Le prompt de la phase 8 écrit `src = export_jobs`. La valeur réelle est
`export_job` : c'est l'alias de `MorphMap::EXPORT_JOB`, et c'est lui que la
colonne `statuses.source` porte. Le pluriel est le nom de la table, pas celui de
la source.

### Les configurations n'entrent pas au référentiel

`customer_import_configurations`, `customer_api_configurations` et
`customer_export_configurations` portent un booléen `is_active`, **pas** une
colonne `status`. Elles ne sont donc pas décrites par `statuses`, et le §53
l'interdit explicitement : un booléen n'a pas de cycle de vie à décrire, et lui
inventer un statut créerait deux vérités sur la même question.

### Aucun `status_id`

Vérifié sur la seule table de cette phase qui porte un statut : `export_jobs` a
une colonne `status` en `varchar(32)` et **aucune** clé étrangère vers
`statuses`.
