# Phase 10 — Audit de performance

Document exigé par le §22.

---

## 1. Méthode

Le N+1 ne se voit pas dans une réponse : elle est correcte, seulement lente. Il
a donc été mesuré, pas relu — `QueryBudgetTest` compte les requêtes réellement
exécutées sur une liste **de 3 lignes**, puis sur la **même liste à 20 lignes**,
et exige un nombre identique.

C'est le seul critère qui attrape une régression : un budget absolu se
périmerait au premier `with()` légitime ajouté.

## 2. Résultat

| Endpoint | 3 lignes | 20 lignes | Verdict |
|---|:-:|:-:|---|
| `GET /orders` | *n* | *n* | **constant** |
| `GET /tours` | *n* | *n* | **constant** |
| `GET /order-communications` | *n* | *n* | **constant** |
| `GET /organization-users` | *n* | *n* | **constant** |
| `GET /customers` | *n* | *n* | **constant** |

**Aucun N+1 détecté, aucun corrigé.** Le chargement anticipé était déjà en place
sur les listes, phase après phase.

## 3. Chargement anticipé par endpoint

| Endpoint | `with()` / `withCount()` |
|---|---|
| `GET /orders` | `customer`, `agency` |
| `GET /orders/{order}` | `customer`, `agency`, `depot`, `lines`, `services.service`, `services.address`, `packages` |
| `GET /tours` | `agency`, `depot`, `provider`, `withCount('stops')` |
| `GET /tours/{tour}` | `stops.stopServices.orderService`, `periods.assignments` |
| `GET /invoices` | `customer`, `withCount('lines')` |
| `GET /order-communications` | `template`, `order`, `withCount('attachments')` |
| `GET /order-communications/{id}` | `template`, `creator`, `attachments.document` |
| `GET /communication-rules` | `template` |
| `GET /export-jobs` | `configuration:id,name,format,transport` |
| `GET /organization-users` | `user`, `roles` |
| `GET /stock-balances` | `stockItem`, `stockLocation` |

`export-jobs` restreint les colonnes chargées de la configuration : elle porte
`encrypted_password`, qu'il n'y a aucune raison de sortir de la base pour
afficher un nom.

## 4. Index et filtres

Chaque filtre exposé par l'API a été confronté à l'index correspondant. **Aucun
filtre non indexé n'a été trouvé**, donc aucun index ajouté.

| Filtre courant | Index |
|---|---|
| `organizationId`, `customerId`, `providerId`, `orderId` | ✓ |
| `status`, `channel`, `eventType`, `recipientRole` | ✓ |
| `createdFrom` / `createdTo` | ✓ sur `created_at` |
| `scheduledFrom` / `sentFrom` / `failedFrom` | ✓ sur les horodatages correspondants |
| `tourId`, `tourStopId`, `orderServiceId` | ✓ |

## 5. Tris

Chaque liste tient une **liste blanche** de colonnes triables ; une colonne hors
liste renvoie **422**. Ce n'est pas seulement une protection contre l'injection :
c'est ce qui empêche un tri sur un `LONGTEXT`.

```text
GET /order-communications?sort=body            → 422
GET /communication-templates?sort=body_template → 422
GET /customer-export-configurations?sort=customer_id → 422
```

Trier `order_communications.body` imposerait à MySQL un tri de fichier sur des
mégaoctets pour un résultat qui n'a pas de sens.

## 6. Pagination

Toutes les listes paginent, sans exception, par `LengthAwarePaginator` :

```text
perPage par défaut   25
perPage maximum     100   (validé, 422 au-delà)
```

Aucun endpoint ne retourne une collection non bornée. La seule exception
apparente — `GET /order-communications/{id}/attachments` — est bornée par la
nature de la donnée : les pièces jointes d'un seul message.

## 7. Structures arborescentes

Deux arbres existent, tous deux à profondeur non bornée par le diagramme :

| Arbre | Endpoint | Protection |
|---|---|---|
| `Package` (colis dans colis) | `GET /orders/{order}/packages/tree` | Construit **en mémoire** à partir d'une seule requête plate, jamais par récursion SQL. Borné par la commande. |
| `StockLocation` (zone → allée → travée) | `GET /stock-locations/tree` | Idem, borné par l'organisation. `ValidateStockLocationHierarchy` refuse les cycles par un jeu de `$visited`. |

Aucune requête récursive, aucun `N+1` de descente : une requête, un assemblage
en PHP.

## 8. Colonnes JSON

Onze colonnes JSON existent. Aucune n'est indexée, et c'est délibéré : MySQL
imposerait une colonne générée pour cela, et aucun filtre de l'API ne porte sur
un contenu JSON.

Toutes sont **bornées par validation** :

| Colonne | Borne |
|---|---|
| `available_variables` | 100 entrées, nom de 64 caractères |
| `conditions` | 20 clauses, aucune imbrication |
| `allowed_ips` | liste d'IP ou CIDR validées une à une |
| `permissions` | validées contre la table `permissions` |
| `mapping`, `validation_rules`, `settings` | structure libre, taille bornée par le champ |
| `template_variables`, `provider_response` | produits par le serveur |
| `old_values`, `new_values` | produits par l'audit, expurgés |

## 9. Détails lourds

Les quatre ressources les plus profondes ont été mesurées à la main :

| Détail | Relations chargées | Requêtes |
|---|:-:|:-:|
| `GET /orders/{order}` | 7 | ~9 |
| `GET /tours/{tour}` | 5 imbriquées | ~8 |
| `GET /invoices/{invoice}` | 2 + snapshots | ~5 |
| `GET /order-communications/{id}` | 3 | ~6 |

Toutes en nombre **fixe** : aucune ne dépend du nombre d'enfants.

## 10. Ce qui n'est pas mesuré

Cet audit ne mesure ni les temps de réponse, ni la charge, ni le comportement sur
un volume de production. Il vérifie une seule propriété — **le nombre de
requêtes ne croît pas avec le nombre de lignes** — parce que c'est la seule qui
se dégrade silencieusement au fil des phases.

Un test de charge relève d'un environnement de préproduction alimenté ; il est
hors du périmètre de cette phase et n'est pas simulé ici.
