# Phase 7 Frontend — Comparatif « existant / à ajouter »

> Rapport préalable, rédigé avant toute écriture de code, à partir du backend
> réellement implémenté (`app/Modules/Stock/`, `routes/api.php`, migrations
> `2026_08_05_1000*`) et du frontend réellement présent
> (`frontend/src/modules/stock/`).
>
> Il compare `Phase7_MASTER_FINAL_Customer_Stock.md` à l'état du dépôt.

## 1. Résumé

| Couche | État |
|---|---|
| Backend Stock | **Terminé et testé** — 5 modèles, 12 Actions, 5 Queries, 5 Policies, 13 Resources, 13 Requests, 20 permissions, 5 suites de tests |
| Frontend Stock | **≈ 20 % du périmètre du prompt** — 14 fichiers, un seul écran (`/stock-locations`), un tiroir greffé sur le catalogue |
| Branche | `feature/frontend-phase-6-billing-exports`, **avec des modifications non commitées** (exports, i18n, `composer.json`) |

La Phase 7 frontend n'est donc **pas** un départ de zéro. Une base réelle existe,
écrite pendant la Phase 2 pour répondre au besoin « combien en reste-t-il de cet
article ». Elle couvre emplacements, soldes et mouvements *vus depuis un article
de catalogue*. Tout le reste manque.

---

## 2. Ce que j'ai déjà — frontend

### 2.1 Fichiers présents (1 700 lignes)

| Fichier | Rôle | Couverture prompt |
|---|---|---|
| `api/stock.api.ts` | 9 appels : items (list, create), balances, movements (list, create), locations (CRUD) | §61 partiel |
| `hooks/stockKeys.ts` | clés all / items / balances / movements / locations | §62 partiel |
| `hooks/useStock.ts` | 4 `useQuery` + 2 `useMutation` | §63 partiel |
| `hooks/useStockLocationMutations.ts` | create / update / delete emplacement | §47, §48 |
| `hooks/useStockScope.ts` | options d'emplacements (plafond 100, `isTruncated`) | — |
| `types/stock.ts` | 4 interfaces + 4 filtres + `movementDirection()` | §64 partiel |
| `pages/StockLocationListPage.tsx` | liste plate paginée, recherche, CRUD | §45 mode « Liste » |
| `components/StockLocationDialog.tsx` | création/édition emplacement (agence → dépôt) | §47 partiel |
| `components/StockBalanceTable.tsx` | soldes par emplacement, lecture seule | §49 partiel |
| `components/StockMovementTable.tsx` | historique, lecture seule | §50 partiel |
| `components/StockMovementDialog.tsx` | création d'un mouvement, transfert atomique | §50, §51 |
| `components/CatalogItemStockSheet.tsx` | tiroir « stock de l'article » depuis le catalogue | hors prompt, à conserver |
| 2 fichiers `.test.tsx` | 394 lignes de tests | §69–§73 partiel |

### 2.2 Routage et menu

- `app/router/routes/stockRoutes.tsx` : **une seule route**, `/stock-locations`,
  gardée par `stock_locations.view` + `organizationOnly`.
- `app/router/navigation.ts` : **aucune section Stock**. L'écran existe mais n'est
  atteignable qu'en tapant l'URL — c'est le manque le plus visible aujourd'hui.
- i18n : la clé `stock` de `fr.json` existe (≈ 45 libellés), orientée
  « emplacements + mouvements ».

### 2.3 Déjà conforme au prompt

- Aucune écriture directe d'un solde : la quantité passe par un mouvement (§5).
- Le transfert A → B est **une seule mutation** (§51).
- `movementType` reste une chaîne libre, non énumérée côté front (§20).
- Aucune entité hors scope (Warehouse, Zone, Lot, Réception…) n'a été créée (§4).
- Aucun `stockBalanceEditSchema`, aucun écran d'édition de solde (§17, §65).

---

## 3. Ce que j'ai déjà — backend (rien à écrire)

### 3.1 Endpoints réels et leur consommation

| Endpoint réel | Consommé par le front ? |
|---|---|
| `GET/POST/PATCH/DELETE /stock-items`, `/{id}` | **list seulement** |
| `GET /customers/{customer}/stock-items` | non |
| `POST /customers/{customer}/stock-items` | oui |
| `GET/POST/PATCH/DELETE /stock-locations`, `/{id}` | oui |
| `GET /stock-locations/tree` | **non** |
| `GET /stock-balances`, `/{id}` | list seulement |
| `GET /customers/{customer}/stock-balances` | **non** |
| `GET/POST /stock-movements`, `GET /{id}` | list + create |
| `GET/POST/PATCH /stock-reservations`, `GET /{id}` | **non** |
| `POST /stock-reservations/{id}/release` | **non** |
| `GET /orders/{order}/stock-plan` | oui (`OrderStockPlanFields`) |

### 3.2 Divergence de routes (§60)

Le prompt annonce `POST /stock-items` avec `customerId` dans le corps. La route
existe, mais le projet expose **aussi** `POST /customers/{customer}/stock-items`,
que le front utilise déjà. Les deux sont valides ; je garde la route imbriquée là
où le client est connu.

### 3.3 Permissions (§59)

Les 20 codes attendus existent tous dans `PermissionSeeder.php` et sont rattachés
à `MenuSection::STOCK`.

---

## 4. Écarts prompt / réalité, à acter

| Prompt | Réalité | Décision |
|---|---|---|
| `legacyId` sur StockLocation et StockMovement (§10, §18) | **Colonnes absentes** — la migration le documente : « le diagramme n'en mentionne aucun » | Ne pas les typer, ne pas les afficher |
| Statuts centralisés (§31, §33, §83) | `MorphMap` déclare les trois sources, mais **`StatusSeeder` n'en sème aucune**, et le front écrit `'active'` en dur (`StockLocationDialog:77`, `CatalogItemStockSheet:63`) | **Écart à corriger** |
| `Warehouse` (§4) | Jamais créé ; `Depot → StockLocation` est le modèle réel | Conforme |
| KPI dashboard (§40) | Aucun endpoint d'agrégat : `stock-balances` pagine des lignes, sans totaux | KPI limités à la page courante, ou blocker documenté |
| Total/Réservé/Disponible par article (§42) | `StockItemListResource` n'expose aucune quantité | Colonnes **impossibles sans N+1** (§42 l'interdit) — blocker à documenter |
| Recherche code-barres (§57) | Supportée : `search` porte sur `barcode` (items et locations) | Conforme, à exposer |

---

## 5. Ce que je vais ajouter

**Navigation — priorité 1.** Section **Stock** dans `navigation.ts` (Vue stock,
Articles, Emplacements, Mouvements, Réservations, §39) et passage de 1 à ~12
routes.

**StockItem — entité absente de l'UI.** `/stock/items` (liste, recherche, filtres
client/catalogue/statut), `create`, détail, `edit`, suppression avec gestion du
409/422 (§44). API manquante : `item(id)`, `updateItem`, `removeItem`,
`itemsByCustomer`.

**StockLocation — compléter l'existant.** Mode **Arbre** sur
`GET /stock-locations/tree`, aujourd'hui inutilisé (§45, §46) ; champ
**`parentLocationId`** dans le dialogue — absent, donc la hiérarchie est
inatteignable depuis l'UI (§12) ; champ **statut** réel au lieu de `'active'` en
dur ; page détail.

**StockBalance.** `/stock/balances` en lecture seule (Client / Article /
Emplacement / Dépôt / Quantité / Réservée / Disponible / MàJ, §49).
`StockBalanceTable` existe déjà et sera réutilisé.

**StockMovement.** Liste globale filtrable (article, type, emplacements, auteur)
— aujourd'hui les mouvements ne se voient que par article ; détail ; page de
création. Aucune route edit/delete (§19).

**StockReservation — totalement absente du frontend.** Le plus gros chantier : ni
type, ni appel API, ni écran. Types, Zod, clés de cache, 5 appels dont `release`,
trois routes, parcours Client → Commande → Ligne → Article → Emplacement →
Quantité (§52), bouton **Libérer** désactivé si `releasedAt != null` (§30),
invalidations croisées (§63).

**Intégrations.** Onglet **Stock** sur `/customers/:id` (§41) — la page n'a
aujourd'hui qu'Informations / Configuration / Capacités ; bloc
**Stock / Réservations** sur les lignes de `/orders/:id` (§55) ;
`Package.currentStockLocationId`, déjà affiché **brut** dans
`OrderPackageFields.tsx:49`, à résoudre en code lisible (§56).

**Statuts.** Semer les trois sources dans `StatusSeeder`, brancher
`ReferentialStatusSelect` / `StatusBadge`, mettre à jour
`docs/backend/statuses-global-audit.md` (lignes 57–59, encore « À sa phase »).

**Qualité.** Schémas Zod (§65) — aucun n'existe, les dialogues valident à la
main ; ~10 fichiers de tests supplémentaires (§69–§79, il en existe 2) ;
`phase-7-analysis.md` et `phase-7-final-report.md` (§35, §84).

---

## 6. Points à trancher avant de commencer

1. **Branche.** Le §36 demande de partir de la Phase 6 validée, mais la branche
   courante porte des modifications non commitées. Les commiter d'abord ?
2. **Convention de routes.** L'existant est `/stock-locations` (plat) ; le prompt
   demande `/stock/locations`. Migrer est plus cohérent mais casse l'URL actuelle.
3. **KPI et totaux par article.** Sans endpoint d'agrégat : les retirer, ou
   ajouter un compteur backend. Je pencherai pour documenter le blocker plutôt
   qu'inventer un endpoint.
4. **Statuts.** Semer les trois sources touche le backend — nécessaire au §33,
   mais déborde du frontend.

---

## 7. Chiffrage indicatif

| Bloc | Fichiers | État |
|---|---|---|
| Navigation + routes | 2 | à modifier |
| StockItem | ~8 | à créer |
| StockLocation (arbre, parent, statut, détail) | ~5 | 2 modifiés, 3 créés |
| StockBalance | ~2 | à créer |
| StockMovement | ~5 | à créer |
| StockReservation | ~9 | **tout à créer** |
| Intégrations Customer / Order / Package | ~4 | à créer/modifier |
| Zod, types, API, clés | ~6 | à étendre |
| Statuts (seeder + audit) | 2 | backend |
| Tests | ~10 | à créer |
| Docs (analyse + rapport) | 2 | à créer |

Environ **50 fichiers**, dont 14 existants à étendre.
