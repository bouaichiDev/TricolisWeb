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

## 26. Verdict

**FRONTEND_PHASE_2_READY**
