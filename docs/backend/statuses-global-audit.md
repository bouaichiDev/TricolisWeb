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
| `stock_item` | `stock_items` | `varchar(32)` | `active` | non | À sa phase |
| `stock_location` | `stock_locations` | `varchar(32)` | `active` | non | À sa phase |
| `stock_reservation` | `stock_reservations` | `varchar(32)` | — | non | À sa phase |
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

`php artisan tricolis:check-statuses` en relève **15**, toutes hors du
périmètre de cette phase — `provider`, `driver`, `vehicle`, `type` et
`type_item` sont désormais couverts.

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
| `stock_item` | `stock_items` | `active` | 1 |
| `stock_location` | `stock_locations` | `active` | 2 |
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

