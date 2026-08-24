# Frontend Phase 2 — Rapport final

Catalogues, commandes, colis et services.

Branche : `feature/frontend-phase-2-orders-catalogs`, empilée sur
`fix/phase-1-organization-roles-permissions`.

---

## 1. Périmètre livré

| Module | Écrans | État |
| --- | --- | --- |
| Services (référentiel) | liste, création, détail, modification | complet |
| Types de colis | liste + dialogue | complet |
| Types de regroupement | liste + dialogue | complet |
| Catalogues clients | onglet client, création, détail, modification, articles | complet |
| Commandes | liste, création (5 étapes), détail (6 onglets), modification | complet |
| Documents de commande | onglet liste + téléversement | complet |

87 fichiers dans les quatre modules concernés.

---

## 2. Le constat qui a commandé la phase

**Il n'existe pas d'`OrderStop` dans le backend** — ni modèle, ni ressource, ni
route, ni permission. `OrderService` porte l'adresse, la séquence, le créneau,
les instructions, les contacts et les colis : c'est lui, l'arrêt.

Conséquences tenues partout :

- le parcours de création compte **cinq étapes**, pas six ;
- la fiche commande compte **six onglets**, sans onglet « Arrêts » ;
- deux tests l'attestent explicitement (`OrderDetailPage`, `OrderCreatePage`).

Le §28 du prompt anticipait ce cas : ne jamais supporter deux modèles
incompatibles à la fois.

---

## 3. Création transactionnelle

`POST /orders` crée en une transaction la commande, ses lignes, ses colis, les
affectations ligne ↔ colis, ses services, leurs contacts et leurs colis. Rien
n'est envoyé avant la dernière étape : créer les sous-ressources au fil de la
saisie laisserait des commandes à moitié écrites derrière chaque abandon.

`serializeOrder` est le **seul** endroit qui traduit le brouillon en charge utile.

---

## 4. Clés temporaires : l'asymétrie du backend

Le backend traite les deux collections différemment, et il fallait le savoir :

| Collection | Indexation côté serveur | Conséquence |
| --- | --- | --- |
| `packages` | par `$package->key` **et** par index | `parentKey` / `packageKey` acceptent une clé libre |
| `lines` | par `(string) $index` uniquement | `lineKey` doit valoir la **position** dans le tableau envoyé |

Le §23 interdit l'index comme identité temporaire — à raison : retirer une ligne
décalerait toutes les suivantes et casserait les affectations déjà faites.

**Conciliation retenue.** Chaque élément du formulaire porte un identifiant
stable (`crypto.randomUUID()`) qui sert d'identité en mémoire ; la position
n'est calculée qu'au moment de sérialiser, sur le tableau définitif. L'index ne
sert jamais d'identité. Trois tests le couvrent, dont un qui retire une ligne et
vérifie que l'affectation suit.

**Ordre des colis.** `CreateOrderPackages` construit son index au fil de la
boucle : un parent déclaré après son enfant serait introuvable. `orderedPackages`
parcourt donc l'arbre en profondeur depuis les racines. Un colis dont le parent a
disparu remonte à la racine plutôt que d'être retiré en silence.

---

## 5. Erreurs 422 imbriquées (§34)

`mapOrderErrors` traduit un chemin serveur — `services.0.contacts.0.email`,
`packages.2.lines.1.quantity` — en :

- l'**étape** concernée, signalée dans le fil de navigation ;
- la **clé stable** de l'élément visé, retrouvée via les tableaux de clés
  produits par `serializeOrderWithKeys` (indispensable : les colis sont
  réordonnés à l'envoi, les positions du serveur ne sont pas celles de la saisie) ;
- le **champ** fautif, posé sous le bon champ de saisie.

La saisie n'est jamais réinitialisée après un refus. Un chemin non reconnu n'est
pas jeté : il remonte sans clé, sur l'étape Général, pour rester visible.

Le contrôle local (`validateDraft`) reprend les règles de `StoreOrderRequest` et
produit **le même format** : l'écran ne distingue pas les deux sources.

---

## 6. Montants des services

Les quatre montants — `customerUnitPrice`, `customerTotalPrice`,
`providerUnitCost`, `providerTotalCost` — sont `required` côté serveur. Le §29
interdit d'y poser `0` en douce : ce sont des valeurs métier, elles sont saisies.
Le brouillon les laisse vides et le formulaire les réclame.

Correction assumée par rapport à l'analyse préalable, qui proposait `0` par
défaut : c'était une erreur, le §29 la nomme.

---

## 7. Catalogue facultatif

`catalogEnabled` est une capacité du client. Quand elle est active, les lignes
peuvent reprendre un article ; sinon l'étape le dit et la saisie reste libre.
Les deux cas produisent une commande valide — le backend accepte une ligne sans
article, avec son libellé.

`useCatalogList` ne déclenche aucun appel quand la capacité est désactivée : la
réponse serait vide, l'appel n'aurait servi qu'à faire patienter.

Reprendre un article recopie ses champs dans la ligne : le backend accepte des
valeurs explicites qui priment sur le catalogue, et une commande doit rester
lisible même si l'article évolue plus tard.

---

## 8. Affectation ligne ↔ colis

La relation `PackageOrderLine` se gère **dans l'assistant et dans la fiche**.
Elle manquait d'abord à la fiche : lignes et colis y vivaient côte à côte sans
qu'on puisse les relier une fois la commande enregistrée. Elle vit sous le repli
de chaque colis, avec les trois quantités.


Chaque ligne affiche **commandé / affecté / reste**. `PackageLineAllocator`
refuse le dépassement côté serveur, sous verrou ; l'écran le montre pendant la
saisie plutôt qu'au retour du serveur. C'est le serveur qui tranche.

Supprimer une ligne retire les affectations qui la visaient ; supprimer un colis
retire sa descendance et les renvois des services. Une référence orpheline
produirait un 422 sur un chemin devenu invisible.

---

## 9. Adresse d'un service

**Le client n'est pas redemandé au service** : c'est celui de la commande,
choisi à l'étape Général. Une même commande porte souvent un chargement chez le
donneur d'ordre et une livraison chez son destinataire. Le destinataire
s'exprime par l'adresse, pas par un second client.

L'API expose les adresses **par entité** : celles du client et celles de chacun
de ses sites sont des listes distinctes. L'écran fait donc choisir la source
avant l'adresse. Trois sources :

| Source | Entité interrogée | Contenu |
| --- | --- | --- |
| Adresses du client | `customer` | le carnet du donneur d'ordre |
| *nom du site* | `customer_site` | le carnet de ce site |
| Autres adresses (hors carnet client) | `organization` | les adresses libres de l'organisation |

**Une adresse absente se crée sans quitter la commande.** `StoreOrderRequest`
exige un `addressId` existant — une adresse ne peut pas voyager dans la charge
utile — elle est donc créée par sa propre route, puis désignée par le service.

**Elle n'est pas rattachée au donneur d'ordre.** Le lien avec la commande est
déjà dans le diagramme de classes : `Address 1 → 0..* OrderService`, porté par
`order_services.address_id`, clé étrangère directe vers `addresses`. Il n'y a
rien à ajouter — et surtout rien à faire passer par `entity_addresses`.

Rattacher l'adresse d'un client final au donneur d'ordre ferait grossir son
carnet d'une ligne par livraison : des milliers d'adresses qui ne sont pas les
siennes, dans le sélecteur de toutes ses commandes suivantes.

`AddressController::store()` crée toujours **une** ligne `entity_addresses` —
`EntityLinkResolver` retombe sur l'organisation quand `entityType` est absent.
Une adresse a donc toujours exactement un carnet. Pour une adresse de
destination ce carnet est celui de l'**organisation** : `Address` est une entité
`«shared»` au diagramme, elle appartient à l'organisation, et c'est
`OrderService.address_id` qui la rattache à la commande.

Après création, la source bascule sur *Autres adresses* : c'est la seule liste
qui contient la nouvelle adresse. Sans cela le sélecteur afficherait un
identifiant sans libellé.

**Le contact est saisi dans la même fenêtre**, et versé aussitôt dans les
contacts du service. Sur un point de livraison, l'adresse sans le nom de qui
reçoit ne sert à rien ; et le demander en deux temps obligerait à le taper deux
fois — une fois dans la fenêtre, une fois dans la section Contacts juste en
dessous. Il est rattaché à l'adresse *et* inscrit dans le brouillon, en un seul
appel à `onChange` : deux appels successifs feraient écraser le premier par un
service périmé. Comme l'adresse, le contact appartient à l'organisation.

La section Contacts du service n'est pas masquée pour autant : `services[].contacts`
est recopié dans la commande à la création, et la masquer ferait partir la
commande sans contact. Elle est simplement **déjà remplie**.

L'adresse subsiste si la commande est abandonnée. C'est voulu : c'est une
adresse de l'organisation, réutilisable, pas un brouillon.

Les contacts sont portés par l'adresse, pas par le client : le destinataire
dépend du lieu. Un contact enregistré ou un contact ponctuel sont tous deux
acceptés ; dans les deux cas les coordonnées sont figées dans la commande.

---

## 10. Transitions de statut

Les statuts proposés sont exactement ceux d'`allowedTransitions`, calculés par
`OrderDetailResource`. La machine à états vit dans le backend ; la reproduire
ici la ferait diverger au premier statut ajouté. Quand la liste est vide,
l'écran le dit.

Un 409 porte un message rédigé pour être lu : il est affiché tel quel.

---

## 11. `allowsContentChanges`

Le drapeau vient du backend. Quand il est faux, la fiche retire la modification
et la suppression, et affiche pourquoi. Proposer une action que l'API refusera
serait pire que ne rien proposer.

---

## 12. Documents

Les commandes sont la seule entité à disposer d'une route imbriquée —
`GET|POST /orders/{order}/documents`. L'onglet liste et téléverse réellement.

`document_type` et `status` sont des **chaînes libres** en base (`varchar(64)`
et `varchar(20)`), sans énumération côté serveur : l'interface les fait saisir
plutôt que de proposer une liste fermée qui inventerait un vocabulaire.

Aucun lien de téléchargement : `DocumentResource` n'expose ni URL ni chemin de
stockage, et il n'existe pas de route de téléchargement.

---

## 13. Historique

`GET /orders/{order}/history` renvoie le journal d'audit filtré sur la commande.
Chaque entrée n'affiche que les champs réellement modifiés : le journal
enregistre deux instantanés complets, les montrer entiers noierait la
modification sous les colonnes inchangées.

---

## 14. Duplication

Les cinq options sont exactement celles de `DuplicateOrderRequest` : `lines`,
`packages`, `services`, `contacts`, `documents`. Les documents sont décochés par
défaut, comme côté serveur — recopier une pièce justificative demande une
décision explicite.

---

## 15. Modification d'une commande

`UpdateOrderRequest` n'accepte ni `customerId` ni `agencyId` : une commande ne
change pas de périmètre après création. Les deux champs sont affichés en lecture
seule avec l'explication. Le dépôt reste modifiable, mais dans l'agence de la
commande.

---

## 16. Devise par défaut

`CreateFullOrder` retient `MAD` quand rien n'est envoyé. Le formulaire propose la
même valeur ; proposer `EUR` aurait créé un écart silencieux entre l'écran et la
base.

---

## 17. Permissions

| Écran / action | Permission |
| --- | --- |
| Liste, fiche | `orders.view` |
| Création | `orders.create` |
| Modification | `orders.update` |
| Changement de statut | `orders.change_status` |
| Duplication | `orders.duplicate` |
| Suppression | `orders.delete` |
| Catalogues | `catalogs.view` / `catalogs.create` |
| Services | `services.view` / `services.create` / `services.update` |
| Types de colis et de regroupement | `packages.*` |
| Documents | `documents.view` / `documents.upload` |

Aucun code inventé. Les deux référentiels de colis n'ont pas de permission
propre : `PermissionSeeder` ne leur en donne aucune.

Toutes les routes de la phase portent `organizationOnly: true` : ces données
appartiennent aux organismes, pas à la plateforme.

---

## 18. Isolation multi-organisation

Aucun composant n'appelle `fetch` directement. Le client HTTP porte
`X-Organization-Id` sur chaque requête : aucune liste de la phase ne peut
déborder sur une autre organisation.

---

## 19. Tri, filtres, pagination

Tout est délégué au serveur. Le tri des commandes est borné à ce
qu'`OrderListQuery` accepte — `order_number`, `order_date`, `status`,
`created_at` — toute autre colonne renvoyant 422. Les filtres sont exactement
ceux de `ListOrderRequest`.

---

## 20. Ce que l'API ne permet pas encore

Consigné, jamais contourné :

| Manque | Conséquence |
| --- | --- |
| Pas de `GET /catalogs` global | les catalogues ne sont accessibles que depuis un client ; aucune entrée de menu globale |
| Pas de `GET /documents?entityType=` | seules les commandes ont un onglet Documents réel |
| Pas de `priority` sur `Order` | absent des filtres et de la fiche |
| Pas de filtre par plage de dates sur `/orders` | absent de la barre de filtres |
| Pas de route de réordonnancement des services | la séquence se saisit |
| Pas de `billingStatus` | absent |
| Pas de `lines[].key` | d'où la position calculée à la sérialisation |
| Pas de route de téléchargement de document | aucun lien de téléchargement |

---

## 20 bis. Lecture des fiches

Chaque ligne, colis et service montre **une poignée de valeurs** — ce qui
l'identifie et ce qui le mesure : code-barres, quantité, poids, volume, statut.
Le reste, une quinzaine à une vingtaine de champs, s'ouvre sous « Plus de
détails ».

Le contenu replié n'est pas monté tant qu'il est fermé : sur une commande de
vingt lignes, cela évite de construire vingt tableaux que personne ne regarde.

---

## 21. Fichiers courts

Aucun fichier de production de la phase ne dépasse 200 lignes. `OrderServiceCard`
a été scindé (`OrderServiceMeasures`), `orderDraft` aussi
(`orderDraftFactories`), et les actions du test du parcours vivent dans
`wizardActions`.

---

## 22. Composants partagés ajoutés

| Composant | Raison |
| --- | --- |
| `AsyncSelect` | sélection dépendante ; l'état inerte **dit pourquoi** il l'est |
| `ControlledField` | champ piloté par un état extérieur — le brouillon imbriqué ne se décrit pas en chemins React Hook Form sans faire de l'index une identité |
| `ControlledCheckbox` | pendant du précédent |

`AsyncSelect` associe désormais son libellé à son déclencheur (`id` / `htmlFor`) :
cliquer le libellé donne le focus, et la liste est atteignable par son nom.

---

## 23. Environnement de test

Deux manques de jsdom ont été comblés dans `src/test/setup.ts` :
`hasPointerCapture` / `setPointerCapture` / `releasePointerCapture` et
`scrollIntoView`, dont Radix se sert pour ses listes déroulantes. Ce sont des
manques de l'environnement, pas des composants — aucun test n'a été affaibli.

`renderWithProviders` accepte un `routePath` : sans motif de route, une page qui
lit `useParams` ne recevrait jamais son identifiant.

---

## 24. Tests

**199 tests, 30 fichiers, tous verts.**

Ajoutés dans cette phase :

| Fichier | Ce qu'il couvre |
| --- | --- |
| `serializeOrder.test.ts` | position calculée, retrait de ligne, ordre parent-enfant, colis orphelin, zéro colis, mesures omises |
| `validateDraft.test.ts` | au moins une ligne, au moins un service, colis facultatifs, quatre montants obligatoires, libellé selon l'article, contact ponctuel |
| `orderErrors.test.ts` | 422 imbriqué par étape et par clé, réordonnancement des colis, chemin inconnu, 409 métier |
| `allocations.test.ts` | commandé / affecté / reste, dépassement, arbre des colis, descendance |
| `useOrderDraft.test.ts` | clés stables, nettoyage des renvois, séquences |
| `OrderListPage.test.tsx` | pagination, recherche, tri serveur, permissions |
| `OrderDetailPage.test.tsx` | six onglets sans « Arrêts », arbre des colis, ligne ↔ colis, adresse / contacts / colis d'un service, `allowedTransitions`, `allowsContentChanges`, 409, options de duplication, permissions |
| `OrderCreatePage.test.tsx` | cinq étapes, colis facultatifs, catalogue selon la capacité, refus local, envoi complet libre et catalogue, 422 imbriqué avec saisie conservée |
| `OrderPackagesStep.test.tsx` | trois quantités, dépassement, imbrication, suppression en cascade |
| `CustomerCatalogsTab.test.tsx` | catalogues par client, capacité désactivée sans appel, permissions |

**Aucun test d'`OrderStop` :** l'entité n'existe pas.

---

## 25. Vérifications

```
npm run typecheck   ✓
npm run lint        ✓  (9 avertissements, tous antérieurs à la phase)
npm run test        ✓  199 tests, 30 fichiers
npm run build       ✓  881 kB / 247 kB gzip
git diff --check    ✓
git var GIT_AUTHOR_IDENT     Badr <bouaichibadr@gmail.com>
git var GIT_COMMITTER_IDENT  Badr <bouaichibadr@gmail.com>
```

Commits attribués au seul propriétaire du dépôt, sans co-auteur.

Le menu n'a pas eu besoin d'être resynchronisé : les entrées Phase 2
(`operations`, `orders`, `services`, `package-types`, `grouping-types`) avaient
été ajoutées au catalogue et propagées à l'étape précédente, et cette phase n'en
crée aucune — les catalogues restant accessibles depuis la fiche client.

---

## 25 bis. Temps de montage, et où vit la quantité en stock

### Temps de montage

`CustomerCatalogItem.assemblyTimeMinutes` — ce que le diagramme n'avait pas, et
qui y est désormais. Certains articles se posent, d'autres se montent : un
canapé modulaire coûte un quart d'heure qu'un carton ne coûte pas, et ce temps
est une propriété du **produit**, connue avant qu'aucune commande n'existe.

`nullable`, jamais `default(0)` : « pas de montage » et « montage non
renseigné » ne sont pas la même chose, et le second ne doit pas se faire passer
pour le premier dans une somme. Le tableau affiche « — », pas « 0 min ».

`unsignedSmallInteger` — 65 535 minutes, quarante-cinq jours : tout montage réel
sans réserver quatre octets par ligne.

**Non recopié dans `OrderLine`.** `toOrderLineSnapshot()` recopie les mesures de
l'article dans la ligne pour la rendre autonome ; `order_lines` n'a pas de
colonne de montage, et en ajouter une sans savoir ce qui doit la consommer
serait inventer. Le jour où le montage doit alimenter le
`requiredTimeMinutes` d'un service, c'est une décision à prendre — pas un effet
de bord de cette colonne.

### La quantité en stock n'est pas sur l'article de catalogue

Le diagramme est explicite, et c'est la bonne conception :

```
CustomerCatalogItem  →  StockItem  →  StockBalance
   (description)        (référence      (quantité par
                         physique)       emplacement)
```

Un `CustomerCatalogItem` **décrit** une référence : code, nom, poids, volume,
dimensions, montage. Il ne porte aucune quantité, et ne doit pas en porter — la
même référence peut être posée dans trois emplacements de deux dépôts, avec des
quantités différentes et des réservations différentes dans chacun.

La quantité vit dans `StockBalance(stockItemId, stockLocationId, quantity,
reservedQuantity, availableQuantity)`, avec `unique(stock_item_id,
stock_location_id)`. `StockItem(customerId, catalogItemId, articleCode)` est le
pont : c'est la référence *physique* du client chez le transporteur, rattachée à
l'article de catalogue par `catalog_item_id`, qui est `nullable` — un article
peut arriver en dépôt sans figurer au catalogue.

La quantité totale d'un article est donc une **somme**, pas une colonne :

```sql
SELECT SUM(b.quantity), SUM(b.available_quantity)
FROM stock_balances b
JOIN stock_items i ON i.id = b.stock_item_id
WHERE i.catalog_item_id = ?
```

### Les écrans, ajoutés après coup

Le module `src/modules/stock/` n'existait pas quand ce rapport a été écrit —
la Phase 2 couvrait « catalogues, commandes, colis et services ». Il a été
ajouté ensuite, sans toucher au backend : **aucune route, aucune ressource,
aucune permission n'a été créée**, tout existait déjà.

| Écran | Route consommée | Permission |
| --- | --- | --- |
| Emplacements (`/stock-locations`) | `GET/POST/PATCH/DELETE stock-locations` | `stock_locations.*` |
| Tiroir Stock d'un article | `GET stock-items?catalogItemId=` | `stock_balances.view` |
| Mise sous suivi | `POST customers/{c}/stock-items` | `stock_items.create` |
| Quantités par emplacement | `GET stock-balances?stockItemId=` | `stock_balances.view` |
| Historique | `GET stock-movements?stockItemId=` | `stock_movements.view` |
| Enregistrer un mouvement | `POST stock-movements` | `stock_movements.create` |

**Le stock s'atteint depuis l'article**, par une icône dans sa ligne, et non par
une liste globale : la question qu'on se pose est « combien reste-t-il de cet
article, et qui l'a bougé », pas « montre-moi tous les soldes ».

Deux cas dans le tiroir. Sans `StockItem` — `catalogItemId` est `nullable`, un
article catalogué n'est pas forcément suivi en dépôt — il propose de mettre
l'article sous suivi. Avec, il montre les soldes puis l'historique.

#### Aucune quantité ne se saisit

Il n'existe **pas** de route qui écrive un solde, et l'écran n'en invente pas.
`StockBalance` est dérivé : `CreateStockMovementAction` verrouille les soldes
concernés dans un ordre déterministe, contrôle la disponibilité, écrit le
mouvement puis recalcule — le tout en transaction. Une quantité corrigée à la
main n'aurait aucune histoire, et deux corrections concurrentes s'écraseraient.

Le **sens** d'un mouvement n'est pas un champ du modèle : il se déduit des
emplacements, exactement comme le fait l'Action.

| Sens | Source | Destination |
| --- | --- | --- |
| Entrée | — | requise |
| Sortie | requise | — |
| Transfert | requise | requise, **même dépôt** |

`movementType` reste une chaîne libre. `StoreStockMovementRequest` le dit :
« le diagramme n'en énumère aucune valeur ». En dresser une liste serait décider
à la place du métier ; le champ est proposé, et le sens sert de valeur par
défaut.

#### Cent emplacements au plus, et l'écran le dit

`ListRequest` plafonne `perPage` à 100. Le sélecteur en demandait 200 : le
serveur répondait 422, la liste restait vide, et le dialogue proposait
« Sélectionner » sans un seul emplacement — sans rien dire. Une valeur devinée
au lieu d'être lue dans `app/Shared/Http/Requests/ListRequest.php`.

Corrigé, et deux garde-fous plutôt qu'un :

- un test vérifie que **toute** requête d'emplacements reste sous le plafond ;
- un champ **Filtrer les emplacements** délègue la recherche au serveur —
  `StockLocationListQuery` couvre zone, allée, travée, niveau, code et
  code-barres ;
- et quand le plafond cache des lignes, l'écran l'annonce : « 100 emplacements
  affichés sur 340 — filtrez pour atteindre les autres. » Un entrepôt en compte
  plus de cent ; laisser croire que la liste est complète serait pire que la
  tronquer.

#### Les booléens en paramètre d'URL

La règle `boolean` de Laravel accepte `1`, `0`, `"1"` et `"0"` — **pas**
`"true"` ni `"false"`. Or `buildUrl` sérialisait avec `String(value)`, qui
produit exactement `"true"`. Tout filtre booléen en query repartait donc en 422.

Visible sur `GET /statuses?active=true`, appelé par `ChangeEntityStatusDialog` :
le dialogue affichait « Aucun statut n'est décrit pour ce type d'élément.
Demandez à un administrateur plateforme de les définir. » Un message faux — les
statuts existaient, la requête était refusée.

Corrigé **dans le client**, `src/shared/api/client.ts`, et pas chez l'appelant :
la conversion faite une fois ne peut pas être oubliée par le suivant. `false`
continue de partir — « les inactifs » est un filtre, pas une absence.

Trois tests sur `buildUrl` (`true` → `1`, `false` → `0`, vide omis) et un test
backend qui fige la forme acceptée des deux côtés à la fois.

#### La liste plate plutôt que l'arbre

`stock-locations/tree` existe et n'est pas utilisé. L'arbre entier remonterait
des milliers d'emplacements d'un coup, alors que ce dont on a besoin pour un
mouvement est de retrouver un code. La liste paginée avec recherche le fait sans
charger un dépôt complet.

#### Ce qui reste hors écran

`StockReservation` — routes `stock-reservations` et `release` comprises. Une
réservation naît d'une ligne de commande (`orderLineId`), pas d'une saisie
manuelle : lui donner un écran de création avant que la commande sache la
demander produirait des réservations que rien ne libère. `reservedQuantity` est
en revanche affichée dans les soldes, à côté du disponible.

`stock-locations/tree` et `GET /customers/{customer}/stock-balances`, non
consommées à ce jour.

---

## 25 ter. Pourquoi le contenu d'un colis ne se lie pas tout seul

`PackageOrderLine` porte une **quantité**. Ce n'est pas une simple appartenance :
une ligne de dix chaises peut se répartir sur trois palettes, et `packages[].lines`
est facultatif à la création — un colis peut légitimement ne rien contenir
encore. Lier d'office écrirait une répartition que personne n'a décidée, et
qu'il faudrait ensuite défaire.

Reste que le cas courant — une ligne, un colis, tout dedans — n'a aucune raison
de se saisir à la main. Il a désormais un bouton **« Tout affecter à ce colis »**,
dans l'assistant comme dans la fiche, qui verse le reste en un clic.

**Un préremplissage aurait été pire.** Première tentative : proposer le reste
dans le champ. Un test existant l'a rattrapée — sur un champ affichant « 6 »,
cliquer puis taper « 3 » donne « 63 », et `select()` au focus n'est pas fiable
sur un `input type="number"`. Le bouton explicite ne tend pas ce piège.

---

## 25 quater. La confirmation sort la marchandise du stock

Jusqu'ici, **rien ne reliait les commandes au stock** : `grep -rn "Stock"
app/Modules/Orders/` ne renvoyait rien, et il n'existait ni `Events`, ni
`Listeners`, ni `Observers`. `StockReservation` acceptait bien un `orderLineId`,
mais n'était créée que par un `POST` explicite. Confirmer une commande ne
touchait donc à aucun solde.

### Ce qui déclenche, et ce qui est ignoré

Le passage à **`confirmed`** écrit un `StockMovement` de sortie par ligne
suivie. C'est le seul effet de bord d'un changement de statut, et il vit **dans
la transaction de `ChangeOrderStatus`** : une commande confirmée dont le stock
n'aurait pas bougé — ou l'inverse — laisserait un dépôt qui ment.

| État de la ligne | Effet |
| --- | --- |
| `resolved` | un mouvement de sortie |
| `ambiguous` | **refus** : plusieurs emplacements, il faut choisir |
| `insufficient` | **refus** : le disponible ne couvre pas la quantité |
| `untracked` | ignorée — ligne hors catalogue ou article non entreposé |
| `consumed` | ignorée — déjà sortie |

`untracked` n'est pas une erreur : une commande peut porter des lignes saisies à
la main, et un article catalogué n'est pas forcément entreposé.

### L'idempotence sans colonne nouvelle

Le mouvement porte `sourceEntityType = order_line` et `sourceEntityId =
orderLine.id`. Cela suffit à reconnaître une ligne déjà sortie : un aller-retour
brouillon → confirmée ne prélève pas deux fois, et **aucune colonne n'a été
ajoutée** pour cela.

### Demander avant, plutôt que refuser après

Une ligne de commande ne dit pas où sa marchandise se trouve. `GET
/orders/{order}/stock-plan` — nouvelle route, en lecture seule — annonce ce que
la confirmation sortirait, ligne par ligne. Le dialogue de statut l'interroge
dès que « Confirmée » est visé et ne demande l'emplacement **que** des lignes
ambiguës ; il bloque l'envoi tant qu'il en manque un, ou si le stock ne suffit
pas. Sans cela, la confirmation partirait pour revenir en 422.

Pas de panachage entre emplacements : `StockMovement` porte **une** source, et
rien au diagramme ne décrit un prélèvement réparti. Un emplacement doit couvrir
la ligne à lui seul.

### Par quels services un colis passe

`OrderServicePackage` se lisait dans un seul sens : la fiche d'un service disait
quels colis il transporte, et rien ne disait l'inverse. Or un même colis est
souvent **chargé par un service et livré par un autre**, et c'est cet
acheminement qu'on vient vérifier en ouvrant un colis.

La fiche d'un colis porte donc maintenant, **avant** son contenu, la liste des
services qui le prennent en charge, avec l'ajout et le retrait. Un colis peut
être relié à plusieurs services — le sélecteur propose tous ceux de la commande
qui ne le prennent pas déjà.

On ne peut y relier que des services **déjà dans la commande** : un
`OrderService` exige une adresse, une séquence, une durée et des montants, et
les demander depuis la fiche d'un colis dupliquerait le formulaire de l'onglet
Services. Quand ils sont tous liés, l'écran le dit — il retirait le sélecteur
en silence, ce qui laissait croire que la liaison était impossible.

Aucune route n'expose les liaisons d'un colis — elles sont imbriquées sous le
service. La liste est **dérivée** de `services[].packages[]`, que le détail de
la commande porte déjà : inventer une route pour retrouver ce qui est là serait
un appel de plus pour la même donnée. Le retrait passe en revanche par le
service propriétaire du lien, seul chemin que l'API accepte.

Les hooks existants figent le service à la construction — juste depuis la fiche
d'un service, qui n'en connaît qu'un. Depuis un colis, le service se choisit à
l'écran : `useAttachPackageToService` et `useDetachPackageFromService` le
prennent en variable de mutation, sans quoi il faudrait un hook par service.

### La suite de tests s'étranglait toute seule

Deux tests du parcours de commande ont commencé à dépasser les 20 secondes,
alors qu'ils tiennent en trois isolés. Ce n'était pas leur code : Vitest lance
un processus par cœur, et sur vingt cœurs, vingt environnements jsdom se
disputaient mémoire et temps CPU.

| Processus | Durée | Temps CPU des tests | Échecs |
| --- | --- | --- | --- |
| 20 (sans plafond) | 91 s | **832 s** | 2 |
| 8 | 34 s | 116 s | 0 |
| 6 | 42 s | 109 s | 0 |
| 4 | 56 s | 99 s | 0 |

`maxWorkers: Math.min(8, availableParallelism())`. Au-delà de huit, le
parallélisme coûte plus qu'il ne rapporte. Le plafond est relatif, pour ne pas
imposer huit processus à une machine qui en a deux.

Relever encore `testTimeout` aurait masqué le problème en laissant la suite
lente et au bord de l'échec.

### Le contenu d'un colis ne voyageait pas avec la commande

`GET /orders/{order}` chargeait `'packages'`, mais pas
`'packages.packageOrderLines'`. Or `PackageResource` n'expose `lines` que
`whenLoaded('packageOrderLines')` : le tableau était **toujours absent** de la
réponse.

Conséquence : tout colis paraissait vide. « Commandé 2 · Affecté 0 · Reste à
affecter 2 » restait affiché après un rattachement réussi, et le bouton ne
disparaissait jamais — la ligne était pourtant bien en base. Même classe de bug
que `orderServices.address`, et invisible aux tests parce que les fixtures du
frontend étaient plus généreuses que l'API réelle.

**Et une troisième fois, au même endroit.** `orderServices.servicePackages`
n'était pas chargé non plus : `OrderServiceResource` n'expose `packages` que
`whenLoaded`, donc aucun service ne semblait transporter quoi que ce soit — ce
qui aurait vidé la nouvelle liste avant même qu'elle serve.

Deux tests backend figent désormais la présence de `data.packages.0.lines` et de
`data.services.0.packages`. La leçon vaut au-delà : **toute relation exposée
`whenLoaded` doit avoir un test qui la voit dans la réponse**, sinon elle
disparaît sans bruit et les fixtures du frontend masquent le trou.

**Second défaut, au même endroit.** Le colis ouvert dans le tiroir était gardé
en état local, figé au clic. La commande était bien rechargée après une
affectation, mais le tiroir continuait d'afficher l'instantané d'avant. Il est
maintenant **relu dans la liste fraîche** par son identifiant.

### Où naît le contenu d'un colis, et où il se corrige

Vérification faite dans le code, le lien `PackageOrderLine` a **deux** sources
légitimes, et la fiche web n'en est pas une :

| Moment | Ce qui écrit | Existe |
| --- | --- | --- |
| Création de la commande | `CreateOrderPackages::attachLines()`, depuis `packages[].lines[]` | ✅ |
| Constat au dépôt | `POST/PATCH/DELETE /orders/{order}/packages/{package}/lines` | ✅ routes prêtes |
| Duplication | `DuplicateOrder` recopie les liens | ✅ |
| Application mobile | — | ❌ **n'existe pas** : aucune route dédiée, aucune mention dans les phases 1 à 10 |

La fiche web sert donc à **voir** ce contenu et à l'**amender** — ajouter un
article que le dépôt avait oublié, corriger une quantité, détacher une ligne.
Elle ne l'invente pas.

**Un rattachement automatique à l'ouverture du panneau a été retiré.** Il
écrivait le lien dès qu'une seule réponse était possible : c'était transformer
un geste de lecture en écriture, et affirmer depuis un bureau ce qui se constate
dans un entrepôt. Symptôme visible : une notification « Création effectuée » à
chaque ouverture.

**L'automatisme est resté, à sa place.** L'assistant met la ligne dans le colis
**dès sa création**, là où l'utilisateur déclare justement le contenu — et
seulement quand la commande n'a qu'une ligne et que c'est le premier colis. Le
second n'hérite de rien : la répartition est un choix. C'est une valeur de
brouillon, modifiable et détachable avant l'envoi.

**Et le champ « Quantité affectée » disparaît quand elle ne peut pas varier.**
Avec un seul colis, une ligne y va tout entière ou n'y va pas : le champ posait
une question dont la seule réponse était « tout », et il n'était pas compris.
Il ne reste alors que la quantité, en clair. Le champ revient dès qu'il y a
plusieurs colis, où dix chaises peuvent réellement se répartir sur trois
palettes — et une phrase sous le titre dit laquelle des deux situations
s'applique.

---

## 26. Verdict

**FRONTEND_PHASE_2_READY**
