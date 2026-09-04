# Catalogue des widgets

Le catalogue vit **en code**. La base ne porte que la sélection d'un rôle.

C'est la même ligne de partage que pour le menu (`role-menu.md`, §2), et pour
les mêmes raisons.

---

## 1. Pourquoi pas de table `dashboard_widgets`

Une définition de widget porte quatre choses, et les quatre sont couplées au
code :

| Donnée | Ce qu'elle désigne | Ce qu'une valeur fausse produit |
| --- | --- | --- |
| `key` | un résolveur, dans une source de données | une carte vide, indistinguable d'une carte à zéro |
| `type` | un composant React, parmi cinq | rien ne s'affiche, sans erreur |
| `requiredPermission` | une entrée du référentiel | le widget disparaît pour tout le monde, sans erreur |
| `route` | un chemin du routeur React | « Page introuvable », depuis une carte d'apparence normale |

Quatre pannes silencieuses, pour aucun gain : **personne n'ajoute un widget sans
écrire le code qui le calcule**. Une table aurait offert la possibilité de
déclarer un widget que rien ne sait produire — et c'est exactement ce qu'on ne
veut pas pouvoir faire.

Ce qui reste en base est donc la seule chose qui, au pire, est mal rangée : la
sélection d'un rôle, dans `role_dashboard_configurations`.

---

## 2. Les fichiers

| Fichier | Rôle |
| --- | --- |
| `app/Shared/Dashboard/DashboardWidget.php` | une définition |
| `app/Shared/Dashboard/DashboardWidgetType.php` | les cinq formes : `kpi`, `chart`, `list`, `alert`, `quick_action` |
| `app/Shared/Dashboard/DashboardWidgetSize.php` | `small`, `medium`, `large`, `full` |
| `app/Shared/Dashboard/DashboardWidgetCategory.php` | le regroupement de l'écran de réglage |
| `app/Shared/Dashboard/DashboardWidgetRegistry.php` | le catalogue, source unique |
| `app/Shared/Dashboard/Catalogue/*.php` | une définition par catégorie |
| `app/Modules/Dashboard/Sources/*.php` | le calcul, une source par catégorie |
| `app/Modules/Dashboard/Services/DashboardComposer.php` | les trois filtres |
| `app/Modules/Dashboard/Services/DashboardDataSources.php` | l'aiguillage catégorie → source |
| `app/Modules/Dashboard/Services/DashboardPayload.php` | les quatre formes de donnée |
| `app/Modules/Dashboard/Services/DashboardContext.php` | organisation, et jour figé |
| `app/Modules/Identity/Models/RoleDashboardConfiguration.php` | la sélection d'un rôle |
| `app/Modules/Identity/Services/RoleDashboardWidgets.php` | absente ≠ vide |
| `app/Modules/Identity/Services/RoleDashboardCatalogue.php` | ce que l'écran de réglage affiche |
| `app/Modules/Identity/Services/UserDashboardWidgets.php` | union multi-rôles, plus petit rang |
| `app/Modules/Identity/Services/EffectivePermissions.php` | les permissions d'un compte, en une requête |

---

## 3. Ajouter un widget

Trois gestes, et aucune migration :

```
1. la définition, dans app/Shared/Dashboard/Catalogue/<Categorie>Widgets.php
2. le calcul, dans app/Modules/Dashboard/Sources/<Categorie>Data.php
3. les deux clés i18n, dans frontend/src/app/i18n/locales/fr.json
   → dashboardWidgets.<clé>.label  et  .description
```

Puis :

```bash
./vendor/bin/pest tests/Feature/Hardening/DashboardCatalogueConsistencyTest.php
```

qui vérifie les quatre couplages du §1 **et** que le widget est réellement
calculé — il les joue tous sur une base vide, et refuse une donnée `null`.

**Aucune synchronisation à lancer.** L'absence de ligne vaut « les défauts du
catalogue » : un widget ajouté est proposé aussitôt à tous les rôles dans
l'écran de réglage, et ceux qui n'ont rien configuré profitent des nouveaux
`defaultEnabled` sans qu'on écrive une ligne.

---

## 4. `defaultEnabled` : ce que voit un rôle qui n'a rien réglé

Quatre widgets seulement le portent :

```
customers_count  agencies_count  users_count  roles_count
```

Ce sont exactement les quatre cartes que le tableau de bord affichait en dur
avant ce travail. Le choix n'est pas esthétique : un rôle qui n'a rien configuré
doit retrouver ce qu'il voyait, sans qu'on ait à écrire une configuration pour
chacun. Les cinquante-six autres attendent d'être demandés — les activer
d'office aurait rempli l'écran de chiffres que personne n'a réclamés.

L'intersection avec les permissions s'applique aux défauts comme au reste.

---

## 5. Le catalogue livré

Soixante widgets, neuf catégories. Chacun avec sa permission — jamais nulle : un
widget sans permission serait visible de tous, y compris de rôles qui n'ont pas
le droit d'ouvrir l'écran d'où le chiffre vient.

### Exploitation

| Clé | Type | Permission | Route | Ce qu'il compte |
| --- | --- | --- | --- | --- |
| `orders_today` | kpi | `orders.view` | `/orders` | `order_date` dans la journée |
| `orders_to_plan` | kpi | `orders.view` | `/orders` | statuts `ready` et `partially_planned` |
| `orders_in_progress` | kpi | `orders.view` | `/orders` | statut `in_progress` |
| `orders_completed_today` | kpi | `orders.view` | `/orders` | statut `completed`, `updated_at` du jour |
| `services_ready_to_plan` | kpi | `order_services.view` | — | statut `ready_to_plan` |
| `services_in_progress` | kpi | `order_services.view` | — | statut `in_progress` |
| `services_failed` | alert | `order_services.view` | — | statut `failed` |
| `recent_orders` | list | `orders.view` | `/orders` | six dernières par `order_date` |
| `orders_by_status` | chart | `orders.view` | `/orders` | `GROUP BY status` |

Les widgets de service n'ont **pas** de route : `/services` est le catalogue des
prestations vendues, pas la liste des services d'une commande.

### Planning

| Clé | Type | Permission | Route | Ce qu'il compte |
| --- | --- | --- | --- | --- |
| `tours_today` | kpi | `tours.view` | `/tours` | `tour_date` du jour |
| `draft_tours` | kpi | `tours.view` | `/tours` | statut `draft` |
| `planned_tours` | kpi | `tours.view` | `/tours` | statuts `planned` et `confirmed` |
| `tours_in_progress` | kpi | `tours.view` | `/tours` | statut `in_progress` |
| `completed_tours_today` | kpi | `tours.view` | `/tours` | statut `completed`, date du jour |
| `unplanned_services` | kpi | `tours.view` | `/planning` | `PLANNABLE_STATUSES` sans affectation active |
| `services_without_gps` | alert | `tours.view` | `/planning` | idem, adresse sans latitude ou longitude |
| `recent_tours` | list | `tours.view` | `/tours` | six dernières par `tour_date` |
| `tours_by_status` | chart | `tours.view` | `/tours` | `GROUP BY status` |

`unplanned_services` et `services_without_gps` reprennent
`PlanningEligibility::PLANNABLE_STATUSES` — la règle qu'applique la
planification elle-même. Trois définitions du même « à planifier » auraient
divergé au premier statut ajouté.

**Pas de disponibilité chauffeur ou véhicule** : aucune table ne la porte, et la
déduire des tournées du jour donnerait un chiffre faux dès qu'un congé n'y
figure pas.

### Réclamations et preuves de livraison

| Clé | Type | Permission | Route | Ce qu'il compte |
| --- | --- | --- | --- | --- |
| `open_claims` | alert | `claims.view` | `/claims` | `closed_at IS NULL` |
| `claims_created_today` | kpi | `claims.view` | `/claims` | `created_at` du jour |
| `recent_claims` | list | `claims.view` | `/claims` | six dernières |
| `pod_created_today` | kpi | `proofs_of_delivery.view` | — | `delivered_at` du jour |
| `services_without_pod` | alert | `proofs_of_delivery.view` | — | services achevés sans preuve |

« Ouverte » se lit dans `closed_at`, **jamais** dans `claims.status` : cette
colonne est une chaîne libre que chaque organisme remplit à sa façon, et
chercher les valeurs qui ressemblent à « ouvert » aurait donné un compteur juste
ici et faux ailleurs.

### Facturation

| Clé | Type | Permission | Route | Ce qu'il compte |
| --- | --- | --- | --- | --- |
| `prebilling_services` | kpi | `price_lists.view` | `/billing/prebilling` | services achevés sans ligne de facture |
| `draft_invoices` | kpi | `invoices.view` | `/billing/invoices` | statut `draft` |
| `closed_invoices_today` | kpi | `invoices.view` | `/billing/invoices` | statut `closed`, `created_at` du jour |
| `closed_invoices_period_total` | chart | `invoices.view` | `/billing/invoices` | `SUM(total)` du mois, **par devise** |
| `invoices_by_status` | chart | `invoices.view` | `/billing/invoices` | `GROUP BY status` |
| `recent_invoices` | list | `invoices.view` | `/billing/invoices` | six dernières |
| `draft_provider_settlements` | kpi | `provider_settlements.view` | `/billing/settlements` | statut `draft` |
| `recent_provider_settlements` | list | `provider_settlements.view` | `/billing/settlements` | six derniers |

**Le seul montant du catalogue est un graphe, pas un compteur.** Les factures
portent `currency_code`, et un organisme qui facture en CHF, en EUR et en MAD
verrait, dans une tuile, la somme des trois — un nombre sans signification, mais
qui ressemble assez à un chiffre d'affaires pour qu'on le cite sans le vérifier.
Une barre par devise ne laisse pas cette place.

`prebilling_services` est gouverné par `price_lists.view`, la permission que
`/billing/prebilling` exige déjà : en choisir une autre aurait proposé une carte
menant à un refus.

### Stock

| Clé | Type | Permission | Route | Ce qu'il compte |
| --- | --- | --- | --- | --- |
| `stock_items_count` | kpi | `stock_items.view` | `/stock/items` | articles suivis |
| `stock_total_quantity` | kpi | `stock_balances.view` | `/stock/balances` | `SUM(quantity)` |
| `stock_reserved_quantity` | kpi | `stock_balances.view` | `/stock/balances` | `SUM(reserved_quantity)` |
| `stock_available_quantity` | kpi | `stock_balances.view` | `/stock/balances` | `SUM(available_quantity)` |
| `active_stock_reservations` | kpi | `stock_reservations.view` | `/stock/reservations` | `released_at IS NULL` |
| `recent_stock_movements` | list | `stock_movements.view` | `/stock/movements` | six derniers |

**Pas de « stock bas ».** Aucune colonne ne porte de seuil : ni l'article, ni
l'emplacement, ni le solde. Le calculer demanderait d'en inventer un — dix
unités ? un mois de consommation ? — et l'alerte dirait quelque chose sur la
valeur choisie, pas sur le stock.

Ces tables ne portent pas d'`organization_id` : le stock appartient au client, et
le client à l'organisation. Les `scopeInOrganization` existants portent la
jointure.

### Communications

| Clé | Type | Permission | Route | Ce qu'il compte |
| --- | --- | --- | --- | --- |
| `communications_scheduled` | kpi | `order_communications.view` | `/communications/history` | statuts `scheduled` et `queued` |
| `communications_failed` | alert | `order_communications.view` | `/communications/history` | statut `failed` |
| `communications_sent_today` | kpi | `order_communications.view` | `/communications/history` | `sent`, `delivered`, `read`, `sent_at` du jour |
| `recent_communications` | list | `order_communications.view` | `/communications/history` | six dernières |

« Envoyée » couvre les trois états qui suivent le départ : ne compter que `sent`
aurait fait **baisser** le chiffre du jour à mesure que les accusés de réception
arrivaient.

### Intégrations

| Clé | Type | Permission | Route | Ce qu'il compte |
| --- | --- | --- | --- | --- |
| `export_jobs_failed` | alert | `export_jobs.view` | `/integrations/export-jobs` | statut `failed` |
| `export_jobs_pending` | kpi | `export_jobs.view` | `/integrations/export-jobs` | statut `pending` |
| `exports_sent_today` | kpi | `export_jobs.view` | `/integrations/export-jobs` | `sent`, `sent_at` du jour |
| `recent_export_jobs` | list | `export_jobs.view` | `/integrations/export-jobs` | six derniers |
| `active_api_configurations` | kpi | `api_configurations.view` | `/api-configurations` | `is_active` |
| `active_export_configurations` | kpi | `customer_export_configurations.view` | `/billing/export-configurations` | `is_active` |

### Administration

| Clé | Type | Permission | Route |
| --- | --- | --- | --- |
| `customers_count` | kpi | `customers.view` | `/customers` |
| `agencies_count` | kpi | `agencies.view` | `/agencies` |
| `users_count` | kpi | `users.view` | `/users` |
| `roles_count` | kpi | `roles.view` | `/roles` |
| `providers_count` | kpi | `providers.view` | `/providers` |
| `drivers_count` | kpi | `drivers.view` | `/drivers` |
| `vehicles_count` | kpi | `vehicles.view` | `/vehicles` |

`users_count` compte les **appartenances**, pas les comptes : un même compte peut
travailler dans deux organisations, et la carte annonce « les utilisateurs de
cette organisation ».

### Actions rapides

| Clé | Permission | Route |
| --- | --- | --- |
| `new_order` | `orders.create` | `/orders/create` |
| `new_invoice` | `invoices.create` | `/billing/invoices/create` |
| `open_planning` | `tours.view` | `/planning` |
| `new_stock_movement` | `stock_movements.create` | `/stock/movements/create` |
| `new_claim` | `claims.create` | `/claims` |
| `new_communication_rule` | `communication_rules.create` | `/communications/rules` |

Chacune est **un widget**, et non une entrée d'une carte unique : un rôle qui ne
crée jamais de facture décoche `new_invoice` sans toucher au reste, là où une
carte unique aurait demandé un second niveau de configuration à l'intérieur d'un
widget.

La permission est celle de **l'action**, pas de la lecture. Deux actions ouvrent
une liste plutôt qu'un formulaire — réclamations et règles de communication se
créent dans une boîte de dialogue posée sur leur liste ; inventer
`/claims/create` aurait donné une page introuvable.

---

## 6. Les formes de donnée

`DashboardPayload` les produit, `DashboardWidgetData` les relit côté frontend.

```
kpi           { value, unit }
alert         { value }
chart         { source, series: [{ code, value }] }
list          { items: [{ id, title, subtitle, status, statusSource, date, route }] }
quick_action  null
```

Aucune ne transporte de **libellé traduit**. Le serveur rend un code — un
statut, une devise, une clé i18n — et l'interface le nomme dans la langue de qui
regarde. `source` désigne l'entité au référentiel des statuts, où un
administrateur a pu régler le libellé : le frontend l'y lit plutôt que dans une
traduction livrée qui l'ignorerait.

---

## 7. Performance

Un seul appel HTTP — `GET /dashboard` — qui agrège les widgets retenus. Le
tableau de bord précédent demandait une page d'un élément à quatre listes
paginées pour n'en lire que `meta.total` : quatre requêtes, quatre
autorisations, quatre paginations, pour quatre entiers. Avec soixante widgets
configurables, la même méthode aurait fini par ouvrir une requête par carte.

Chaque source reçoit **les clés retenues**, jamais le catalogue entier : quand
un rôle n'a activé qu'un compteur sur neuf, huit requêtes ne sont pas jouées.

Toutes les lectures sont des `COUNT`, `SUM`, `GROUP BY` ou `LIMIT` sur colonnes
indexées. Aucune ne charge de collection pour la compter. Les bornes de journée
partent en `whereBetween` plutôt qu'en `whereDate` : la fonction appliquée à la
colonne écarterait l'index.

Deux agrégats passent par `toBase()`, hors d'Eloquent : hydrater un modèle pour
lire un `GROUP BY status` ferait passer la colonne par sa conversion en
énumération, qui lève sur une valeur qu'elle ne connaît pas. Une commande
portant un statut retiré du code ferait alors échouer le tableau de bord entier,
pour une ligne.
