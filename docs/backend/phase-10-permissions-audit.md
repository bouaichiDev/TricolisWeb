# Phase 10 — Audit des permissions

Document exigé par le §12.

---

## 1. Résultat d'ensemble

```text
permissions déclarées au seeder      187
doublons                               0
fautes de nommage                      0
permissions sans usage (avant)         4
permissions sans usage (après)         3
```

Toutes suivent la forme `module.action`, en `snake_case`, sans exception. Le
seeder est **idempotent** : `firstOrCreate` sur le code, aucune suppression, ce
qui permet de le rejouer sans perdre les affectations existantes.

## 2. Répartition

| Module | Permissions | Module | Permissions |
|---|:-:|---|:-:|
| `dashboard` | 1 | `tours` | 4 |
| `organizations` | 3 | `tour_stops` | 5 |
| `subscriptions` | 4 | `tour_stop_services` | 5 |
| `users` | 5 | `tour_periods` | 5 |
| `roles` | 5 | `tour_period_assignments` | 4 |
| `agencies` | 4 | `tracking_events` | 2 |
| `depots` | 4 | `proofs_of_delivery` | 2 |
| `customers` | 5 | `claims` | 4 |
| `customer_sites` | 4 | `invoices` | 4 |
| `addresses` | 4 | `invoice_lines` | 4 |
| `contacts` | 4 | `provider_settlements` | 4 |
| `documents` | 3 | `provider_settlement_lines` | 4 |
| `orders` | 8 | `stock_items` | 4 |
| `order_lines` | 4 | `stock_locations` | 4 |
| `order_services` | 5 | `stock_balances` | 1 |
| `services` | 4 | `stock_movements` | 2 |
| `catalogs` | 4 | `stock_reservations` | 4 |
| `packages` | 4 | `customer_import_configurations` | 4 |
| `package_types` | 4 | `customer_api_configurations` | 5 |
| `grouping_types` | 4 | `customer_export_configurations` | 4 |
| `providers` | 4 | `export_jobs` | 3 |
| `drivers` | 4 | `communication_templates` | 4 |
| `vehicle_types` | 4 | `communication_rules` | 4 |
| `vehicles` | 4 | `order_communications` | 7 |
| `audit_logs` | 1 | `communication_attachments` | 3 |
| `permissions` | 1 | | |

## 3. Permissions déclarées sans contrôle

Recherche exhaustive du code de chaque permission dans l'ensemble de `app/`.
Quatre n'apparaissaient nulle part ; l'une a été rattachée par cette phase.

| Permission | Statut avant | Décision |
|---|---|---|
| `customers.block` | déclarée, jamais contrôlée | **Rattachée.** Voir plus bas. |
| `dashboard.view` | déclarée, jamais contrôlée | **Conservée**, non contrôlée. |
| `organizations.view` | déclarée, jamais contrôlée | **Conservée**, non contrôlée. |
| `organizations.create` | déclarée, jamais contrôlée | **Conservée**, non contrôlée. |

### `customers.block` — rattachée

Elle était déclarée depuis la Phase 2 mais ne gardait rien : bloquer un client
passait par `customers.update`, au même titre que corriger son téléphone. Or
bloquer un client interrompt ses commandes.

`PATCH /customers/{customer}/status` exige désormais **`customers.block`**
lorsque le statut visé est `blocked`, et `customers.update` pour les autres
transitions. C'est le raisonnement déjà appliqué à `queue`, `cancel` et `retry`
en Phase 9.

### Les trois autres — conservées sans contrôle, et pourquoi

**`organizations.view` et `organizations.create`** ne sont pas contrôlées parce
que `OrganizationPolicy::viewAny()` et `::create()` retournent `true` : tout
utilisateur authentifié peut lister **ses propres** organisations — la requête
filtre sur son appartenance — et en créer une. C'est un parcours d'inscription,
pas une opération d'administration : exiger une permission attribuée par une
organisation pour créer sa première organisation serait circulaire.

**`dashboard.view`** ne garde aucune route parce qu'aucun tableau de bord
n'existe : c'est un écran, donc un sujet frontend. La permission est déjà en
base, prête à être affectée à des rôles.

**Aucune n'est supprimée.** Retirer une permission du seeder ne la retire pas
des `role_permissions` existants ; il faudrait une migration de nettoyage, pour
un risque réel et un gain nul. Le §5 impose la même prudence pour les colonnes.

## 4. Couverture par les tests

Le contrôle des permissions est vérifié par **onze fichiers dédiés**, un par
phase ou domaine :

```text
OrganizationPermissionTest      OrderPermissionTest
CustomerPermissionTest          TourPermissionTest
PackagePermissionTest           TrackingPermissionTest
BillingPermissionTest           StockPermissionTest
IntegrationPermissionTest       CommunicationPermissionTest
ProviderPermissionTest
```

Chacun suit le même schéma : un membre **sans aucune permission** (ni
propriétaire, ni rôle porteur de droits) se voit refuser lecture, création et
actions spécifiques ; puis les permissions sont attachées une à une et l'accès
s'ouvre exactement dans la mesure accordée.

Les codes de permission apparaissent rarement en toutes lettres dans les tests :
la plupart des scénarios s'exécutent sous le compte propriétaire, dont
`is_owner` court-circuite le contrôle. Ce n'est pas un défaut de couverture — ce
sont les tests de permission qui portent la vérification, et ils l'exercent
**par le refus**, ce qui est la seule preuve utile.

Deux vérifications par contraste méritent d'être citées :

- lire un accès API ne permet pas d'en renouveler la clé (Phase 8) ;
- `order_communications.update` ne permet ni `queue`, ni `cancel`, ni `retry`
  (Phase 9).

## 5. Tableau de synthèse

| Permission | Seeder | Policy / Controller | Test | Statut |
|---|:-:|:-:|:-:|---|
| 183 permissions métier | ✓ | ✓ | ✓ | **CONFORME** |
| `customers.block` | ✓ | ✓ *(Phase 10)* | ✓ | **CORRIGÉE** |
| `dashboard.view` | ✓ | — | — | **EN ATTENTE** (écran non livré) |
| `organizations.view` | ✓ | — *(accès ouvert par conception)* | ✓ | **SANS OBJET** |
| `organizations.create` | ✓ | — *(inscription libre)* | ✓ | **SANS OBJET** |
