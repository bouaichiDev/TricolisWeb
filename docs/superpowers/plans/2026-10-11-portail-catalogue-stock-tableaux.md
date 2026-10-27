# Catalogue modifiable, demandes de correction, tableaux alignés — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Le client modifie/désactive ses articles (isolation stricte), crée une demande de correction depuis la page des demandes, et retrouve partout le tableau du back-office.

**Architecture:** Backend Laravel : une permission, une action d'écriture passant exclusivement par `CustomerCatalogScope`, trois routes. Portail React : dialogue d'article réutilisé en mode modification, étape « article » dans le dialogue de correction, composant tableau copié du back-office puis 13 écrans migrés.

**Tech Stack:** Laravel 13 / Pest ; React 19, TanStack Query, react-hook-form/zod, Vitest/MSW, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-11-portail-catalogue-stock-tableaux-design.md`

## Global Constraints

- Fichiers de 200 lignes au plus (préférence utilisateur).
- Aucun commit sans demande explicite de l'utilisateur (les étapes « Commit » sont remplacées par « vérifier `git status` »).
- Pas de modification de quantité de stock, pas de suppression d'article, pas de `customer.stock.quantity.update`.
- Toute écriture d'article passe par `CustomerCatalogScope` ; jamais `CustomerCatalogItem::find()` ni liaison implicite dans le portail.
- `catalogId`, `customerId`, `organizationId`, `status`, `id` : `prohibited` (422) à la modification.
- Article étranger et identifiant inexistant : même 404 générique.
- Un filtre sous colonne n'existe que si le serveur l'accepte déjà.
- Base de test partagée : ne jamais lancer Pest et Playwright en même temps ; re-semer la base de test avant Playwright.

## Review Focus

- Article d'un autre client **dans la même organisation** (GETAZ, QOQA) → 404, base inchangée — test `refuse de toucher l'article d'un autre client` (Task 2).
- Article d'un client d'une **autre organisation** → 404 — même jeu de données (Task 2).
- Code article déjà présent chez un **autre** client → accepté (unicité par catalogue, rien de révélé) — Task 2.
- Activer un article déjà actif → 200 sans audit — Task 2.
- Demande de correction alors que le client n'a aucun article de stock → message, pas de bouton mort — Task 4.

---

### Task 1: Permission `customer.articles.update`

**Files:**
- Modify: `app/Modules/CustomerPortal/Support/CustomerPortalPermissions.php` (catalogue), `app/Modules/CustomerPortal/Support/CustomerPortalRoles.php` (ADMIN implicite, STOCK_MANAGER)
- Test: `tests/Feature/Api/V1/CustomerPortal/CustomerPortalSeederTest.php` (étendre)

**Interfaces:** Produces: code `customer.articles.update`, libellé « Modifier ou désactiver un article ».

- [ ] Test : après `CustomerPortalSeeder`, le rôle `CUSTOMER_STOCK_MANAGER` et `CUSTOMER_ADMIN` ont `customer.articles.update`, `CUSTOMER_VIEWER` et `CUSTOMER_ORDER_MANAGER` non ; rejouer le seeder sur une base existante l'ajoute aux rôles déjà créés.
- [ ] Implémenter, lancer le test, puis `php artisan db:seed --class=CustomerPortalSeeder` sur la base de dev.

### Task 2: Modifier / activer / désactiver — backend et isolation

**Files:**
- Create: `app/Modules/CustomerPortal/Actions/UpdateCustomerCatalogItem.php`, `app/Http/Requests/Api/V1/Customer/Catalog/UpdateCustomerCatalogItemRequest.php`, `app/Http/Controllers/Api/V1/Customer/Catalog/CustomerCatalogItemWriteController.php`
- Modify: `routes/customer-catalog.php`, `app/Http/Resources/Api/V1/Customer/Catalog/CustomerCatalogItemResource.php` (`actions.canUpdate`)
- Test: `tests/Feature/Api/V1/CustomerPortal/Catalog/PortalCatalogUpdateTest.php`, `.../Catalog/PortalCatalogIsolationTest.php`; étendre l'instantané de `Security/PortalCrossCustomerIsolationTest.php` à l'article QOQA.

**Interfaces:**
- Produces: `UpdateCustomerCatalogItem::update(string $itemId, array $input, Request $request): CustomerCatalogItem` et `::setStatus(string $itemId, bool $active, Request $request): CustomerCatalogItem` — chacune relit l'article par `$scope->items()->whereKey($id)->lockForUpdate()->firstOrFail()` dans une transaction (404 sinon).
- Routes : `PATCH catalog/items/{item}`, `POST catalog/items/{item}/deactivate`, `POST catalog/items/{item}/activate` ; middleware `customer.permission:customer.catalog.view,customer.articles.update`.
- Audit : `WriteAuditLog` `updated` ; `CustomerPortalAudit` `catalog_item_updated|catalog_item_deactivated|catalog_item_activated`.
- Ressource : `actions: { canUpdate: bool }` (= permission).

- [ ] Tests `PortalCatalogUpdateTest` : modifie nom/poids/code (200, audits) ; code en double dans le même catalogue → 422 `articleCode` ; `status`/`catalogId`/`customerId` → 422 ; désactiver → `status = inactive`, absent de `GET customer/catalog-items?search=` ; réactiver ; activer un article actif → 200, aucun audit ; lecteur → 403 ; module fermé → 403.
- [ ] Tests `PortalCatalogIsolationTest` : IKEA → PATCH/activate/deactivate sur articles GETAZ, QOQA, autre organisation → 404, instantané (`name`, `article_code`, `status`, `updated_at`) inchangé, aucun audit ; corps identique à `PortalRoutes::NOWHERE` ; `catalogId` GETAZ sur son propre article → 422, inchangé ; code d'un article GETAZ sur son article → 200.
- [ ] Lancer : échec attendu (routes absentes).
- [ ] Implémenter ; `vendor/bin/pint`, tests verts, `php artisan customer-portal:route-matrix --check` puis `--output=docs/customer-portal/security-route-matrix.md`.

### Task 3: Modifier / désactiver — écran

**Files:**
- Modify: `customer-portal/src/modules/catalog/components/CatalogItemDialog.tsx` (prop `item?` : mode modification), `.../api/catalogApi.ts` (`update`, `setActive`), `.../hooks/useCatalog.ts`, `.../pages/CatalogItemPage.tsx` (boutons), `.../types.ts` (`actions.canUpdate`)
- Test: `customer-portal/src/modules/catalog/pages/CatalogPages.test.tsx` (étendre), `customer-portal/e2e/catalog.spec.ts` (modifier puis désactiver), `customer-portal/e2e/isolation.spec.ts` (article GETAZ par URL et API → introuvable / 404)

- [ ] Vitest : « Modifier » pré-remplit et envoie `PATCH` sans `status`/`catalogId` ; « Désactiver » demande confirmation puis appelle `deactivate` ; boutons absents sans `customer.articles.update`.
- [ ] Implémenter ; typecheck, lint, Vitest verts.

### Task 4: « Nouvelle demande » de correction + stock de démo en dev

**Files:**
- Modify: `customer-portal/src/modules/stock/components/AdjustmentRequestDialog.tsx` (article facultatif → étape de choix), `.../pages/StockAdjustmentRequestsPage.tsx` (bouton, message stock vide)
- Create (si le dialogue dépasse 200 lignes) : `.../components/StockItemPicker.tsx`
- Modify si nécessaire : `database/seeders/DemoCustomerPortalStockSeeder.php` (rejouable)
- Test: `customer-portal/src/modules/stock/pages/StockAdjustment.test.tsx`, `customer-portal/e2e/stock.spec.ts`

- [ ] Vitest : bouton visible avec `customer.stock.adjustment_requests.create` ; recherche → choix d'article → `POST` avec ce `stockItemId` ; aucun article → message « Aucun article en stock… » sans bouton actif ; bouton absent sans permission.
- [ ] Implémenter ; vérifier que le seeder est rejouable (deux exécutions sur la base de test → mêmes comptes), puis le lancer sur la base de dev.

### Task 5: Composant tableau du back-office dans le portail

**Files:**
- Create/replace: `customer-portal/src/shared/components/data/DataTable.tsx`, `DataTablePagination.tsx`, `DataTableParts.tsx`, `RowActions.tsx` (copies adaptées : textes FR en dur, `errorMessage?: string | null` conservé pour compatibilité, `filter`, `onPerPageChange`, `actionsColumn`)
- Test: `customer-portal/src/shared/components/data/DataTable.test.tsx`, `RowActions.test.tsx` (portés du back-office)

**Interfaces:** Produces: `Column<T>` avec `filter?: ReactNode` ; `DataTable` props `onPerPageChange?`, `actions?` ; `RowActions({ actions: RowAction[] })`, `RowAction { label, icon, to?, onClick?, permission?, tone? }`.

- [ ] Porter les tests, les voir échouer, porter les composants, tests verts ; les écrans existants compilent sans changement.

### Task 6: Migrer les écrans

**Files:** les 13 écrans listés dans la spec (§ Lot 3) et leurs tests ; colonnes longues → `xxxColumns.tsx`.

- [ ] Par écran : filtres de la barre → sous la colonne (seulement ceux du tableau de la spec), `onPerPageChange` branché sur `perPage`, actions → `RowActions` ; mettre à jour le test de l'écran sans retirer d'assertion.
- [ ] Typecheck, lint, Vitest verts ; re-semer la base de test ; Playwright 14+ parcours verts.

### Task 7: Vérification finale et documents

- [ ] Suite Pest complète détachée (Start-Process + JUnit + `done.txt`) : 0 échec.
- [ ] Vitest back-office et portail, lint, typecheck, builds ; `pint --test` ; `route-matrix --check` ; Playwright.
- [ ] Docs : `access-matrix.md`, `phase-8-permission-matrix.md`, `phase-8-feature-inventory.md`, `phase-8-e2e-matrix.md`, `phase-8-final-report.md` (addendum), checklist de production (`db:seed --class=CustomerPortalSeeder --force` pour la nouvelle permission).
