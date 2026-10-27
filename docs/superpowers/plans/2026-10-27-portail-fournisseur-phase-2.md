# Portail fournisseur — Phase 2 : chauffeurs et véhicules — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Le fournisseur liste, crée, modifie, désactive et réactive **ses** chauffeurs et **ses** véhicules, sans jamais voir ni toucher ceux d'un autre fournisseur ou du transporteur.

**Architecture:** Un `ProviderResourceScope` (fournisseur + organisation du contexte, verrou en écriture) sert toutes les lectures et écritures. Les écritures passent par les Actions existantes (`CreateDriverAction`, `UpdateDriverAction`, `CreateVehicleAction`, `UpdateVehicleAction`) avec `providerId` imposé par le contexte. Routes sous `api/v1/provider`. Pages React sur le `DataTable` / `RowActions` du socle.

**Tech Stack:** Laravel 13 / Pest ; React 19, TanStack Query, react-hook-form/zod, Vitest/MSW, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-27-portail-fournisseur-design.md` §5.2

## Global Constraints

- Fichiers ≤ 200 lignes. Aucune migration, aucun ALTER.
- `providerId`, `provider_id`, `organizationId`, `organization_id`, `status`, `id`, `userId`, `addressId`, `contactId` : `prohibited` (422) sur toutes les écritures du portail — le carnet d'adresses et les contacts de l'organisation ne sont jamais exposés au fournisseur.
- Chauffeur ou véhicule d'un autre fournisseur, du transporteur ou inexistant : **même 404**.
- Aucune suppression. Statuts écrits : `active` à la création ; `inactive` / `active` par les routes dédiées.
- Désactivation refusée (422) si la ressource est affectée à une tournée `planned|confirmed|in_progress`.
- E-mail de chauffeur ou immatriculation déjà pris : message **neutre** (« Cette adresse e-mail ne peut pas être utilisée. » / « Cette immatriculation ne peut pas être utilisée. »), limiteur `provider-portal-write` (30/min par compte) sur les POST.
- Disponibilités : `BLOCKED_DESIGN_DECISION` (aucun modèle).
- Commit à la fin de la phase (règle utilisateur), pas de push.

## Review Focus

- Code de chauffeur déjà utilisé par **un autre fournisseur** de la même organisation (unicité par organisation) : 422 neutre sur `code`, sans dire à qui il appartient — Task 3.
- Type de véhicule d'une **autre organisation** ou d'un autre référentiel que `vehicle` : 422 — Task 4.
- Capacités négatives, non numériques ou démesurées : 422 — Task 4.
- Chauffeur/véhicule devenu inactif puis réactivé : le dashboard (Phase 1) suit — Task 3/4.
- Permissions retirées en cours de session : actions masquées côté UI, 403 côté API — Task 5.

---

### Task 1: Permissions et rôles

**Files:** Modify `ProviderPortalPermissions.php`, `ProviderPortalRoles.php`, `ProviderNavigation.php` ; Test `tests/Feature/ProviderPortal/ProviderPortalSeederTest.php`, `ProviderPortalProfileTest.php` (navigation).

**Interfaces:** Nouvelles permissions `provider.drivers.view|create|update` (« Voir / Ajouter / Modifier les chauffeurs »), `provider.vehicles.view|create|update`. Rôles : ADMIN tout ; FLEET_MANAGER base + drivers.* + vehicles.* ; DISPATCHER base + drivers.view + vehicles.view ; VIEWER base + *.view. Navigation : `drivers` → `/drivers` (`provider.drivers.view`), `vehicles` → `/vehicles` (`provider.vehicles.view`), ordre dashboard, drivers, vehicles, profile.

- [ ] Tests : jeux exacts par rôle après rejeu ; un rôle existant reçoit les nouvelles permissions au rejeu (base semée avant la phase 2) ; navigation de l'admin = `[dashboard, drivers, vehicles, profile]`.
- [ ] FAIL → implémenter → PASS.

### Task 2: `ProviderResourceScope`

**Files:** Create `app/Modules/ProviderPortal/Services/ProviderResourceScope.php` ; Test `tests/Feature/Api/V1/ProviderPortal/Resources/ProviderResourceScopeTest.php`.

**Interfaces:** `drivers(): Builder<Driver>`, `vehicles(): Builder<Vehicle>` (filtre `provider_id` + `organization_id` du `CurrentProviderContext`) ; `driver(string $id): Driver`, `vehicle(string $id): Vehicle`, `lockedDriver(string $id)`, `lockedVehicle(string $id)` → `NotFoundHttpException` (message « Ressource introuvable. ») pour étranger, transporteur ou inexistant ; `isEngaged(Driver|Vehicle): bool` (tournée `planned|confirmed|in_progress` sur `driver_id` / `vehicle_id`).

- [ ] Tests unitaires du scope : chauffeur de B, chauffeur du transporteur (`provider_id` nul), chauffeur d'une autre organisation portant l'id de A → 404 ; `isEngaged` selon statut de tournée.
- [ ] FAIL → implémenter → PASS.

### Task 3: API chauffeurs

**Files:** Create `app/Http/Controllers/Api/V1/Provider/Resources/ProviderDriverController.php` (index, show, store, update), `ProviderDriverStatusController.php` (deactivate, activate), `app/Http/Requests/Api/V1/Provider/Resources/{ListProviderDriversRequest,StoreProviderDriverRequest,UpdateProviderDriverRequest}.php`, `app/Http/Resources/Api/V1/Provider/ProviderDriverResource.php`, `app/Modules/ProviderPortal/Actions/ChangeProviderResourceStatus.php` ; Modify `routes/provider.php`, `PortalRateLimits.php` (`provider-portal-write`) ; Test `tests/Feature/Api/V1/ProviderPortal/Resources/ProviderDriversTest.php`.

**Interfaces:** `GET drivers` (`search` sur code/nom/e-mail, `status`, `perPage` 10..100, tri par code), `GET drivers/{id}`, `POST drivers` {code, firstName, lastName, email, phone?}, `PATCH drivers/{id}` {code?, firstName?, lastName?, phone?} (nom recomposé, téléphone sur le compte), `POST drivers/{id}/deactivate|activate`. Ressource : `id, code, name, firstName, lastName, email, phone, status, statusLabel, actions{canUpdate, canDeactivate, canActivate}`. Création via `CreateDriverAction` avec `providerId` = contexte, `status = active` ; modification via `UpdateDriverAction` sous `lockedDriver`. Audit des Actions existantes, contexte `AuditContext(organizationId, user, ip)`.

- [ ] Tests : liste et recherche limitées à A ; détail/modif/désactivation d'un chauffeur de B → 404, base inchangée ; champs interdits → 422 ; e-mail déjà pris (compte d'un autre fournisseur) → 422 neutre ; code pris par B → 422 neutre ; désactivation d'un chauffeur affecté à une tournée `confirmed` → 422, permise si `completed` ; sans `provider.drivers.create` → 403 ; compte chauffeur créé `invited` avec rôle `driver`, aucune permission portail.
- [ ] FAIL → implémenter → PASS.

### Task 4: API véhicules et types

**Files:** Create `ProviderVehicleController.php`, `ProviderVehicleStatusController.php`, `ProviderVehicleTypeController.php`, requêtes `{ListProviderVehiclesRequest,StoreProviderVehicleRequest,UpdateProviderVehicleRequest}.php`, `ProviderVehicleResource.php` ; Modify `routes/provider.php` ; Test `tests/Feature/Api/V1/ProviderPortal/Resources/ProviderVehiclesTest.php`.

**Interfaces:** `GET vehicle-types` (`provider.vehicles.view`) → types `vehicle` actifs de l'organisation `{id, code, name}` ; `GET/POST vehicles`, `GET/PATCH vehicles/{id}`, `POST vehicles/{id}/deactivate|activate`. Champs : `code, registrationNumber, vehicleTypeId, payloadCapacity (0..999999999.999), volumeCapacity (0..99999999.9999), palletCapacity (0..10000)`. Création via `CreateVehicleAction` (`providerId` = contexte, `status = active`), modification via `UpdateVehicleAction` sous `lockedVehicle`. Ressource : `id, code, registrationNumber, vehicleType{id,name}, payloadCapacity, volumeCapacity, palletCapacity, status, statusLabel, actions{…}`.

- [ ] Tests : isolation A/B/transporteur (404) ; type d'une autre organisation ou d'un autre référentiel → 422 ; capacité négative/démesurée → 422 ; immatriculation déjà prise → 422 neutre ; désactivation d'un véhicule engagé → 422 ; liste des types limitée à l'organisation.
- [ ] FAIL → implémenter → PASS.

### Task 5: Pages React

**Files:** Create `provider-portal/src/modules/drivers/{api/driversApi.ts,types.ts,hooks/useDrivers.ts,pages/DriversPage.tsx,pages/DriverDetailPage.tsx,components/DriverDialog.tsx,components/DriverRowActions.tsx}`, idem `modules/vehicles/…` ; Modify `app/router/{AppRouter,lazyPages,paths}.ts(x)`, `app/layouts/navigation.ts` (icônes `drivers` → `IdCard`, `vehicles` → `Truck`), `test/server.ts` ; Tests `modules/drivers/pages/Drivers.test.tsx`, `modules/vehicles/pages/Vehicles.test.tsx`.

**Interfaces:** listes = `DataTable` avec ligne de filtres (recherche différée 350 ms, statut), 10..100 lignes, actions en icônes (voir, modifier, désactiver/réactiver selon `actions.*` du serveur) ; ajout/modification en dialogue ; 422 rendu sous les champs.

- [ ] Tests : liste et filtres envoient `search/status/perPage` ; actions affichées selon `actions.*` et permissions ; dialogue d'ajout envoie le corps exact sans `providerId` ; 422 neutre affiché ; sans `provider.drivers.create` pas de bouton « Ajouter ».
- [ ] FAIL → implémenter → PASS ; lint, typecheck, build.

### Task 6: Démo, E2E, docs, vérification, commit

**Files:** Modify `DemoProviderPortalSeeder.php` (un chauffeur et un véhicule par fournisseur, `local|testing`), `provider-portal/e2e/` (`resources.spec.ts`), `docs/provider-portal/README.md`, `access-matrix.md`.

- [ ] E2E : Alpha ajoute un chauffeur et un véhicule, les modifie, désactive le véhicule ; l'API refuse le chauffeur de Beta (404).
- [ ] Suite Pest complète détachée, Vitest (3 apps), Playwright (2 portails, re-semis), pint, route-matrix.
- [ ] Rapport de phase ; commit « feat(provider-portal): phase 2 — chauffeurs et véhicules ».
