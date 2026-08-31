# Phase 7 Frontend — Rapport final

Stock client chez le transporteur : articles, emplacements hiérarchiques,
soldes, mouvements, réservations.

> Préalables : [phase-7-gap-analysis.md](phase-7-gap-analysis.md) (comparatif
> avant travaux) et [phase-7-analysis.md](phase-7-analysis.md) (analyse du
> backend réel).

## 1. Branche

```text
feature/frontend-phase-7-customer-stock
```

Créée depuis `feature/frontend-phase-6-billing-exports`, dont le rapport final
conclut `FRONTEND_PHASE_6_READY`. Ni fusion ni poussée automatique.

**À signaler** : la branche de départ portait des modifications non commitées
(module Exports — formats CSV/PDF, transports e-mail et REST, `composer.json`).
Elles n'ont **pas** été commitées ni modifiées : elles restent dans l'arbre de
travail, sous la responsabilité de leur auteur. Seuls les fichiers de cette
phase sont indexés.

## 2. Identité Git

```text
git config user.name   → Badr
git config user.email  → bouaichibadr@gmail.com
GIT_AUTHOR_IDENT       → Badr <bouaichibadr@gmail.com>
GIT_COMMITTER_IDENT    → Badr <bouaichibadr@gmail.com>
```

## 3. Absence de mention Claude / Anthropic

Aucun fichier produit, aucun message de commit et aucune ligne de documentation
ne mentionne Claude ou Anthropic. Aucun `Co-authored-by` ni `Generated-by` n'est
posé : le commit est attribué à son auteur seul.

## 4. Vue d'ensemble du stock

`/stock` — `StockDashboardPage`. Filtre par client, quatre indicateurs
(quantité, réservée, disponible, emplacements occupés) et les cinq accès du
module.

**Les indicateurs portent sur la page chargée, et l'écran le dit.** Aucune route
d'agrégat n'existe : `stock-balances` pagine des lignes sans total. Sommer tout
exigerait de rapatrier chaque page — le N+1 que le §68 interdit. Le choix assumé
est un chiffre **vrai et borné** plutôt qu'un total faux ; la mention sous les
cartes distingue « calculé sur N lignes » de « N affichées sur M ».

## 5. Stock d'un client

Onglet **Stock** sur `/customers/:id` (`CustomerStockTab`), servi par les deux
routes dédiées `customers/{c}/stock-items` et `customers/{c}/stock-balances` —
pas par un filtrage local d'une liste globale.

Mouvements et réservations n'y sont pas repris : ni `ListStockMovementRequest`
ni `ListStockReservationRequest` n'accepte `customerId`. Les afficher aurait
demandé de charger l'ensemble puis de trier en mémoire, ce qui donnerait une
liste fausse dès la deuxième page. Deux liens mènent aux écrans qui savent le
faire.

## 6. StockItem

| Route | Écran |
|---|---|
| `/stock/items` | Liste : recherche, filtre client, filtre statut, tri serveur |
| `/stock/items/create` | Création |
| `/stock/items/:id` | Fiche : identité, soldes, historique |
| `/stock/items/:id/edit` | Modification, client verrouillé |

Le formulaire ne porte **aucun champ de quantité** : elles vivent dans
`stock_balances`, par emplacement. Le lien au catalogue est facultatif et se
choisit par cascade catalogue → article, parce que l'API n'expose les articles
que sous un catalogue.

La liste n'affiche **aucune colonne de total** : `StockItemListResource` n'expose
aucun solde et aucun agrégat n'existe — voir les blockers, §25.

La suppression est refusée en 409 tant qu'un solde, un mouvement ou une
réservation subsiste ; le message du serveur est affiché tel quel.

## 7. StockLocation — liste et arbre

`/stock/locations` propose deux vues, filtre de dépôt commun.

- **Liste** : paginée, cherchée et triée par le serveur ; répond à « où est le
  code A-01-2 ».
- **Arbre** : `StockLocationTree` / `StockLocationTreeNode` sur
  `GET /stock-locations/tree`, jusque-là inutilisé. Non paginé, donc **un dépôt
  est exigé** — l'écran le dit au lieu de charger le parc entier.

Le formulaire porte désormais **`parentLocationId`**, absent avant cette phase :
la hiérarchie était inatteignable depuis l'interface. Les parents proposés sont
restreints au même dépôt et excluent l'emplacement courant ; les cycles plus
longs sont laissés au serveur, seul à pouvoir les détecter sans descendre
l'arbre à chaque frappe.

Fiche `/stock/locations/:id` : parent, enfants, et ce qui y est rangé.
Suppression refusée en 409 par quatre dépendances — enfants, soldes,
réservations, `packages.current_stock_location_id`.

## 8. StockBalance — lecture seule

`/stock/balances`. Colonnes article, emplacement, quantité, réservée,
disponible, mise à jour. Tri serveur sur les quatre colonnes autorisées, filtre
client et « disponible seulement ».

**Aucun bouton de création, de modification ni de suppression**, et aucun hook de
mutation dans le module : `StockBalancePolicy` n'expose que `viewAny` et `view`,
aucune route n'écrit un solde. Aucun champ de recherche non plus —
`StockBalanceListQuery` n'en applique aucune, et un champ inerte laisserait
croire que l'écran cherche.

## 9. StockMovement

| Route | Écran |
|---|---|
| `/stock/movements` | Journal filtrable, tri serveur |
| `/stock/movements/create` | Saisie complète |
| `/stock/movements/:id` | Fiche, sans aucune action |

**Ni route ni bouton de modification ou de suppression** : la route serveur
n'expose que `index`, `store`, `show`. Une correction s'enregistre comme un
mouvement de plus.

Le **sens** n'est pas un champ du modèle : entrée, sortie ou transfert se
déduisent des emplacements, et l'écran ne demande que ce que le sens implique.
Un transfert part en **une seule mutation** — le test le vérifie sur la charge
utile émise.

`sourceEntityType` / `sourceEntityId` ne sont pas proposés à la saisie : voir
les blockers, §25.

## 10. StockReservation

Entité entièrement absente du frontend avant cette phase : ni type, ni appel
API, ni écran.

| Route | Écran |
|---|---|
| `/stock/reservations` | Liste, filtre statut, « en cours seulement » |
| `/stock/reservations/create` | Client → commande → ligne → article → emplacement → quantité |
| `/stock/reservations/:id` | Fiche et libération |

Le parcours suit la contrainte du serveur plutôt que de l'inventer :
`OrderLine.order.customerId` doit valoir `StockItem.customerId`, donc le client
vient en premier et tout en découle.

`AvailabilityHint` propose les emplacements **où il reste du disponible**, tirés
des soldes et non de la liste des emplacements, avec le disponible de chacun. Ce
chiffre est indicatif et le composant le dit : c'est le verrou serveur qui
tranche.

Une `OrderLine` peut porter plusieurs réservations — c'est la granularité même
de l'entité, et c'est pourquoi aucun `StockReservationLine` n'a été créé.

## 11. Libération

`ReleaseReservationDialog` sur `POST /stock-reservations/{id}/release`.

Ce n'est **pas** une suppression : `releasedAt` est renseignée, le statut change,
la quantité repart au disponible, et la ligne reste comme trace de ce qui fut
promis. Le serveur n'accepte qu'un champ, `status` ; ni la date ni la quantité
rendue ne se saisissent — les laisser saisir permettrait d'antidater une
libération ou de rendre plus que ce qui fut pris.

Le bouton disparaît quand `releasedAt` existe. C'est une commodité, pas une
garantie : deux onglets ouverts la contourneraient, et c'est le 409 — contrôlé
avant la transaction **puis** sous verrou — qui interdit réellement la seconde
libération. Deux tests couvrent les deux faces.

## 12. Décimaux

`decimal(12,3)` en base, rendus **en chaînes** par l'API. Les types acceptent
`number | string`, et `formatStockQuantity` ne convertit qu'à l'affichage, en
retirant les zéros inutiles. Aucune valeur convertie ne repart au serveur.
`sumQuantities` centralise la seule addition du module — celle des indicateurs.

Un test vérifie que `"100.000"` se lit `100`, `"2.250"` se lit `2.25`, et qu'une
valeur absente donne `—` plutôt qu'un zéro faux.

## 13. Code-barres

Recherche par code-barres disponible sur les articles et les emplacements : le
champ de recherche de chaque liste le couvre, parce que `StockItemListQuery` et
`StockLocationListQuery` l'incluent dans leurs colonnes cherchées. Aucun module
de scan n'a été créé — un lecteur clavier saisit dans le champ existant.

## 14. Intégration OrderLine

Action **Stock et réservations** sur chaque ligne de `/orders/:id`, ouvrant
`OrderLineStockSheet` : quantité commandée, quantité réservée, puis le détail
des réservations avec leur emplacement et leur état.

En tiroir et non en colonne, délibérément : les réservations se demandent par
`GET /stock-reservations?orderLineId=`, une requête par ligne. Les afficher
toutes ferait autant d'appels que de lignes ; ouvrir une ligne n'en fait qu'un,
au moment où on le demande.

## 15. Package.currentStockLocationId

`PackageStockLocation` résout l'identifiant en code d'emplacement cliquable, là
où l'ULID brut s'affichait. La requête n'est lancée que si l'identifiant existe.

**Aucune écriture** : déplacer un colis demanderait de savoir quel mouvement de
stock l'accompagne et pour quelle quantité — une règle que le backend ne définit
pas. Le §56 l'interdit tant qu'elle n'existe pas.

## 16. Statuts centralisés

Trois sources entrent au référentiel via `StatusSeeder` :

| Source | Codes | Origine |
|---|---|---|
| `stock_item` | `active`, `archived` | fabrique et `StockItemTest` |
| `stock_location` | `active`, `inactive`, `blocked` | fabrique, `StockLocationTest`, `inactive` par symétrie |
| `stock_reservation` | `active`, `confirmed`, `released` | fabrique et `StockReservationTest` |

Aucun code n'est inventé, à l'exception d'`inactive` pour les emplacements, qui
est le pendant employé par toutes les autres ressources du référentiel.

Côté frontend, `ReferentialStatusSelect`, `StatusFilterSelect` et `StatusBadge`
remplacent les `'active'` écrits en dur dans `StockLocationDialog` et
`CatalogItemStockSheet`. Le frontend envoie le **code**, jamais l'identifiant.

`stock_balances` et `stock_movements` **n'ont pas** de colonne `status` et n'en
reçoivent pas : un solde est un état calculé, un mouvement un fait daté.

`docs/backend/statuses-global-audit.md` est mis à jour : les trois lignes passent
de « À sa phase » à « Phase 7 », les deux valeurs orphelines correspondantes
disparaissent, et une section confirme l'absence de `status_id`.

## 17. Permissions

Les 20 codes du §59 existaient déjà ; aucun n'a été ajouté. Chaque route porte
sa permission via `guarded(...)`, et chaque action d'écriture est enveloppée
d'un `PermissionGuard`. `MenuPermissionConsistencyTest` vérifie que chaque
permission citée par le menu et par les gardes existe au référentiel.

## 18. Clés de requête

`stockKeys` couvre les cinq entités, avec pour chacune une clé racine sans
filtre — c'est elle qu'on invalide, pour atteindre toutes les pages et tous les
tris d'un coup :

```text
items / itemList / itemsOfCustomer / item
locations / locationList / locationTree / location
balances / balanceList / balancesOfCustomer
movements / movementList / movement
reservations / reservationList / reservation
```

Invalidations : un mouvement périme soldes, historique et fiches d'articles ; une
écriture de réservation y ajoute `orderKeys.all`, parce que
`OrderLine.reservedQuantity` est calculée par le serveur à partir des
réservations.

## 19. Couche API

Cinq fichiers, un par entité :

```text
stock-items.api.ts        stock-locations.api.ts    stock-balances.api.ts
stock-movements.api.ts    stock-reservations.api.ts
```

`stock-balances.api.ts` n'expose aucune écriture, et `stock-movements.api.ts` ni
`update` ni `remove` : l'absence est le contrat, pas un oubli.

L'ancien `stock.api.ts` monolithique est supprimé.

## 20. Multi-organisation

Portée par l'en-tête `X-Organization-Id` du client HTTP unique, et par le scope
`inOrganization()` de chaque modèle serveur. Toutes les routes de stock sont
`organizationOnly` : un compte plateforme n'a ni dépôt ni client. Un identifiant
d'une autre organisation revient en 404, jamais en 403 — le frontend ne le
présente donc pas comme un problème de droits.

## 21. Concurrence

**Aucune mise à jour optimiste sur les quantités.** Le disponible dépend de ce
que les autres sessions ont réservé entre-temps ; anticiper le résultat
afficherait un chiffre que le serveur va démentir. Le cycle est : mutation →
succès → relecture.

Sur 409, les soldes sont invalidés — la valeur qui avait décidé de la saisie
vient d'être démentie — et le message du serveur est posé sur le formulaire, là
où la saisie fautive est encore visible. Pas de toast en double.

## 22. AuditLog

L'audit existant est réutilisé sans table parallèle :
`stock_item.created/updated/deleted`, `stock_location.*`,
`stock_movement.created`, `stock_reservation.created/updated/released` — tous
écrits par les Actions serveur via `BuildsAuditContext`.

## 23. Tests

8 fichiers, 46 tests dans le module ; 76 fichiers et 521 tests sur l'ensemble du
frontend, tous verts.

| Fichier | Couvre |
|---|---|
| `StockItemListPage.test.tsx` | liste, client, tri serveur, lien catalogue, absence de colonnes de quantité, permission |
| `StockLocationListPage.test.tsx` | coordonnées, recherche serveur, statut du référentiel, arbre exigeant un dépôt, hiérarchie déroulée, permission |
| `StockBalanceListPage.test.tsx` | trois quantités, décimales, lecture seule stricte, `availableOnly=1`, absence de recherche |
| `StockMovementForm.test.tsx` | sens déduit, transfert en une charge utile, quantité > 0, au moins une extrémité |
| `StockReservationForm.test.tsx` | cascade client → commande → ligne, disponibilité, article indisponible, charge utile, quantité laissée au verrou serveur |
| `StockReservationDetailPage.test.tsx` | affichage, absence de suppression, libération n'envoyant que `status`, double libération masquée, permission |
| `stockSources.test.ts` | décimales, formule du solde, sens du mouvement, état de libération |
| `CatalogItemStockSheet.test.tsx` | parcours depuis le catalogue (existant, conservé) |

`testSupport.ts` centralise les jeux de données à la forme réelle des
ressources — y compris leurs **absences** : quantités en chaînes,
`StockItemListResource` sans solde, `StockReservationListResource` sans
emplacement résolu.

Vérifications :

```text
npm run lint       ✔ 0 erreur
npm run typecheck  ✔
npm run test       ✔ 521 / 521
npm run build      ✔
./vendor/bin/pint  ✔
```

## 24. E2E

**Non exécuté : aucun harnais E2E n'est configuré dans ce dépôt.** Il n'existe ni
script `test:e2e` ni dépendance Playwright ou Cypress. Les parcours des §80 à
§82 sont couverts par les tests d'intégration ci-dessus au niveau composant —
notamment le transfert en une mutation, la hiérarchie parent/enfant et le cycle
réservation/libération. Monter un harnais E2E dépasse le périmètre de cette
phase et reste noté pour la suite.

## 25. Différences DB / UML, et blockers

1. **`legacyId` absent.** Les §10 et §18 le mentionnent pour `StockLocation` et
   `StockMovement` ; les colonnes n'existent pas, et la migration le documente :
   « le diagramme n'en mentionne aucun ». Rien n'est typé ni affiché.
2. **`movementType` sans source contrôlée.** Aucune énumération, aucune table,
   aucune constante. Conformément au §20, rien n'est codé en dur : le champ reste
   une saisie libre.
3. **Pas d'agrégat de quantités.** `StockItemListResource` n'expose aucun solde
   et aucune route de total n'existe. Les colonnes du §42 et les KPI du §40 sont
   donc impossibles sans N+1, que le §42 interdit. Les quantités se lisent sur la
   fiche de l'article et dans `/stock/balances` ; le tableau de bord agrège ce
   qu'il charge et le dit.
4. **`sourceEntityType` non proposé à la saisie.** Le §21 exige d'employer la
   whitelist `MorphMap::registered()` — qu'**aucune route n'expose**. Ces deux
   champs sont d'ailleurs renseignés par le serveur : `ConsumeOrderStock` écrit
   `MorphMap::ORDER_LINE` quand une commande consomme du stock. Un mouvement
   saisi à la main n'a pas d'origine à déclarer ; ils restent affichés en
   lecture sur la fiche.
5. **Routes de création d'article.** Le §60 annonce `POST /stock-items` ; le
   projet expose aussi `POST /customers/{c}/stock-items`. Les deux sont
   employées, selon que le client est déjà connu ou choisi au formulaire.

## 26. Éléments exclus

Aucune entité hors scope n'a été créée : ni `Warehouse`, ni `StockZone`, ni
`StockReceipt`, `StockAdjustment`, `StockInventory`, `StockTransfer`,
`StockLot`, `StockBatch`, `PackageLocationHistory`, `StockMovementLine`,
`StockReservationLine`. Aucune allocation automatique FIFO/FEFO n'a été
inventée : l'utilisateur choisit l'emplacement, assisté par le disponible réel.

Le menu ne propose ni réceptions, ni inventaires, ni ajustements, ni zones, ni
entrepôts, ni lots.

## 27. Risques

1. **Les indicateurs du tableau de bord sont bornés à la page chargée.** C'est
   dit à l'écran, mais un utilisateur pressé peut le manquer. Une route
   d'agrégat côté backend lèverait la limite ; elle n'était pas demandée.
2. **`movementType` en saisie libre** produira des variantes orthographiques
   (« reception », « Réception »). Le modèle ne permet pas mieux aujourd'hui ;
   une table de référence serait un ajout backend.
3. **Les listes déroulantes d'emplacements et d'articles sont plafonnées à 100**
   — la limite de `ListRequest`. Les écrans signalent la troncature et invitent
   à filtrer, mais un très grand dépôt rendra la recherche obligatoire.
4. **La ligne de commande n'est pas résolue sur la fiche d'une réservation** :
   `StockReservationDetailResource` n'expose que `orderLineId` et il n'existe pas
   de route `GET /order-lines`. L'identifiant est affiché tel quel.
5. **Aucun E2E.** Voir §24.

## 28. Phase suivante

Ne pas démarrer sans validation. La suite prévue est :

```text
FRONTEND PHASE 8 — INTÉGRATIONS CLIENTS
```

Les éléments d'export de factures introduits en Phase 6 y seront réutilisés
plutôt que recréés.

## Conclusion

```text
FRONTEND_PHASE_7_READY
```
