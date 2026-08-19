# Référentiel des statuts

Table `statuses`, commune à toute la plateforme.

---

## 1. Pourquoi

Les colonnes `status` du domaine portent des codes bruts — `draft`,
`confirmed`, `pending`. Rien ne disait jusqu'ici ce qu'un code signifie, quelle
icône lui va, dans quel ordre les présenter, ni si l'atteindre doit prévenir le
client. Ce référentiel le dit, et donne à l'administrateur plateforme un écran
pour le régler.

---

## 2. Portée : la plateforme, pas l'organisation

La table ne porte **pas** d'`organization_id`.

Un statut décrit le cycle de vie du domaine. Deux organismes qui nommeraient
différemment l'état « confirmée » rendraient leurs commandes incomparables, et
casseraient exports comme échanges. Chaque organisme lit le référentiel ; seule
la plateforme l'écrit.

| Permission | Portée |
| --- | --- |
| `statuses.view` | organisationnelle — tout membre lit |
| `statuses.create` | **plateforme** |
| `statuses.update` | **plateforme** |
| `statuses.delete` | **plateforme** |

Les trois écritures figurent dans `PlatformAccess::PLATFORM_PERMISSIONS` : elles
ne peuvent donc pas être déléguées à un rôle local, et `RoleSeeder` les retire
des rôles `admin` existants.

---

## 3. Colonnes

| Colonne | Type | Rôle |
| --- | --- | --- |
| `id` | `CHAR(26)` | ULID |
| `source` | `VARCHAR(64)` | entité concernée — alias de `MorphMap` |
| `status` | `INT UNSIGNED` | identifiant numérique du statut |
| `code` | `VARCHAR(64)` | **valeur stockée dans les colonnes `status` du domaine** |
| `label` | `VARCHAR(255)` | libellé affiché |
| `icon` | `VARCHAR(64)` | nom d'icône Lucide, celui qu'utilise déjà le frontend |
| `active` | `BOOLEAN` | un statut inactif reste lisible mais n'est plus proposé |
| `is_to_send` | `BOOLEAN` | atteindre ce statut déclenche un envoi au client |
| `allows_content_changes` | `BOOLEAN` | le contenu reste-t-il modifiable ? |
| `requires_reason` | `BOOLEAN` | atteindre ce statut exige-t-il un motif ? |
| `position` | `SMALLINT UNSIGNED` | ordre d'affichage |

Deux unicités : `(source, code)` et `(source, status)`. Le même code existe pour
plusieurs entités — « draft » vaut pour une commande comme pour un colis — et
c'est `source` qui les sépare.

MySQL : aucun type propriétaire, aucune énumération SQL, aucun index partiel.

---

## 4. Le lien avec le reste

**C'est `code`.** Les colonnes `orders.status`, `packages.status`,
`order_services.status` restent des chaînes, **sans clé étrangère** :

- elles portent déjà des données ;
- elles appartiennent à trente-neuf tables différentes ;
- une même valeur y désigne des statuts différents selon l'entité.

L'intégrité est donc vérifiée à l'application, au moment où elle compte :
`StatusUsage` compte les enregistrements qui portent encore un code avant
d'autoriser sa suppression, et le refus dit combien.

---

## 5. `source` est dérivé, jamais recopié

`StatusSources::all()` parcourt la morph map et retient les entités dont la
table porte réellement une colonne `status` — 39 aujourd'hui. Une liste écrite à
la main divergerait au premier module ajouté.

Le référentiel s'exclut lui-même : `statuses.status` est l'identifiant numérique
d'un statut, pas l'état d'un statut.

Un test parcourt la liste et vérifie, pour chaque entrée, que l'alias existe
dans la morph map et que sa table porte la colonne.

---

## 6. Ce qui est semé

`StatusSeeder` ne sème **que** les entités dont le statut est gouverné par une
énumération PHP — les seules dont la liste exacte soit connue :

| Entité | Énumération | Statuts |
| --- | --- | --- |
| `order` | `OrderStatus` | 10 |
| `order_service` | `OrderServiceStatus` | 9 |
| `order_communication` | `CommunicationStatus` | 9 |
| `tour` | `TourStatus` | 6 |
| `tour_stop` | `TourStopStatus` | 6 |
| `subscription` | `SubscriptionStatus` | 5 |
| `organization` | `OrganizationStatus` | 4 |
| `user` | `UserStatus` | 4 |
| `customer` | `CustomerStatus` | 3 |

Soit 56 lignes. Les autres colonnes `status` sont des chaînes libres : deviner
leurs valeurs produirait un référentiel faux. L'administrateur les complète
depuis l'écran — c'est précisément ce que ce référentiel lui donne.

Le seeder est rejouable, et **ne réécrit jamais une ligne existante** : libellé,
icône, rang et comportement sont réglables depuis l'écran, et rejouer le semis
ne doit pas effacer ce qu'un administrateur a décidé.

---

## 7. Routes

| Route | Permission |
| --- | --- |
| `GET /statuses` | `statuses.view` |
| `GET /statuses/sources` | `statuses.view` |
| `POST /statuses` | `statuses.create` — plateforme |
| `GET /statuses/{status}` | `statuses.view` |
| `PATCH /statuses/{status}` | `statuses.update` — plateforme |
| `DELETE /statuses/{status}` | `statuses.delete` — plateforme |

Ces routes vivent **hors** du groupe `organization` : un compte plateforme n'a
pas d'organisation active, et exiger l'en-tête lui fermerait l'écran. C'est
`StatusPolicy` qui tranche.

`source` n'est pas modifiable après création : les enregistrements qui portent
déjà le code suivraient sinon en silence vers un autre domaine.

---

## 8. Écran

`/statuses`, entrée de menu **Statuts** dans la section Plateforme, à côté des
Organisations. Route `platformOnly` : masquer le bouton ne suffit pas, saisir
l'URL doit aussi être refusé.

---

## 9. Le cycle de vie

Table `status_transitions` : `from_status_id → to_status_id`, plus `is_manual`.

Jusqu'à cette phase, la machine à états vivait dans
`OrderStatus::allowedTransitions()`. Un référentiel géré par l'administrateur et
des règles figées dans le code ne pouvaient pas coexister : un statut créé à
l'écran n'était atteignable par aucune transition, donc inutile. La règle a
rejoint la donnée.

**`is_manual` est porté par la transition, pas par le statut.** Passer une
commande en « planifiée » est une transition légitime — c'est la planification
qui la pose — mais un opérateur ne doit pas pouvoir la déclarer. L'ancien
`manuallyAssignable()`, défini par statut, interdisait la transition à tout le
monde ou à personne.

Deux colonnes de plus sur `statuses` portent le reste du comportement, qui
vivait lui aussi dans l'énumération :

| Colonne | Ce qu'elle remplace |
| --- | --- |
| `allows_content_changes` | `OrderStatus::allowsContentChanges()` |
| `requires_reason` | `OrderStatus::requiresReason()` |

`StatusMachine` lit l'ensemble en une requête par entité, mémorisée pour le
processus. `OrderStatus` subsiste pour nommer les statuts que le code désigne
explicitement — `DRAFT` à la création d'une commande — mais ne décide plus.

**20 transitions semées** pour les commandes, dérivées une fois de
l'énumération. Passé ce point, c'est la table qui fait foi. Aucune autre entité
n'a de cycle de vie : leur en inventer produirait des règles que personne n'a
décidées.

| Route | Permission |
| --- | --- |
| `GET /statuses/{status}/transitions` | `statuses.view` |
| `PUT /statuses/{status}/transitions` | `statuses.update` — plateforme |

Le `PUT` remplace l'ensemble d'un bloc, dans une transaction : une mise à jour
arête par arête laisserait, le temps de la séquence, un graphe que personne n'a
voulu — et des commandes bloquées entre-temps.

`tricolis:check-status-machine` signale les statuts sans issue ni entrée, et les
codes portés en base mais absents du référentiel. Elle ne corrige rien : ce sont
des décisions d'administration.

---

## 10. Ce qui reste ouvert

**`is_to_send`** est stocké, réglable et documenté, mais **rien ne le consomme
encore** : le déclenchement d'un envoi appartient au module Communications, dont
les tables existent en base sans écran ni orchestration à ce stade. Le drapeau
est prêt ; son effet viendra avec ce module.

**Les autres entités n'ont pas de cycle de vie.** Trente des trente-neuf portent
des valeurs de statut sans aucune entrée au référentiel — `agencies.status`
vaut `active` sans que rien ne le déclare. `tricolis:check-status-machine` les
liste. Les semer demanderait d'inventer leur signification ; c'est à
l'administrateur de les déclarer, entité par entité.
