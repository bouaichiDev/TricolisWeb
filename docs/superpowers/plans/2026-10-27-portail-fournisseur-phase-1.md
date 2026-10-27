# Portail fournisseur — Phase 1 : fondations — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un compte rattaché à un fournisseur (géré au back-office) se connecte au portail fournisseur, voit son tableau de bord réel et son profil, sans jamais atteindre l'interne, le portail client ni un autre fournisseur.

**Architecture:** Module Laravel `app/Modules/ProviderPortal` calqué sur `CustomerPortal` (rattachement, jeton Sanctum à capacité propre, contexte résolu à chaque requête, RBAC partagé de portée `provider`), routes `routes/provider.php` sous `api/v1/provider`. Onglet « Accès portail » dans la fiche fournisseur du back-office. Nouvelle app `provider-portal/` construite sur le socle recopié de `customer-portal/`.

**Tech Stack:** Laravel 13 / Sanctum / Pest ; React 19, Vite, TanStack Query, react-hook-form/zod, Tailwind/shadcn, Vitest/MSW, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-27-portail-fournisseur-design.md` (§3, §4, §5.1, §6)

## Global Constraints

- Fichiers de 200 lignes au plus. Commentaires en français, densité du code voisin.
- Aucun commit sans demande explicite : chaque étape « Commit » devient « vérifier `git status` ».
- Seules migrations permises : `provider_users`, `provider_user_roles`, `provider_user_invitations`. Aucun ALTER.
- Fournisseur ouvert = `providers.status === 'active'` (référentiel `statuses`, source `provider`) ; tout autre code ferme le portail.
- `providerId`, `provider_id`, `organizationId`, `organization_id`, `status`, `id` : `prohibited` sur toute écriture du portail.
- Refus de contexte : 403 `{"code":"provider_access_revoked"}` avec message générique ; ressource étrangère = 404 identique à inexistante.
- Le portail n'expose aucune route ni écran de gestion d'utilisateurs ou de rôles.
- Jeton portail : capacités `['provider-portal', 'provider:<id>']`, expiration `provider_portal.token_ttl_minutes` (720).
- Front : jeton en `sessionStorage` clé `tricolis.provider-portal.token` ; port dev 5190 ; build impose `VITE_API_URL`.
- Base de test partagée : jamais deux `php artisan test` en parallèle ; suite complète détachée (Start-Process + JUnit + done.txt) ; re-semer la base de test avant Playwright.
- Seeders de démo : garde `app()->environment('local', 'testing')`.

## Review Focus

- Compte membre **à la fois** d'un client et d'un fournisseur : chaque jeton n'ouvre que son portail ; l'interne refuse les deux — Task 3.
- Fournisseur passé à `blocked` ou organisation suspendue **pendant** une session : la requête suivante répond 403 `provider_access_revoked` — Task 3.
- Rôle de portée `provider` d'une **autre organisation** attaché par erreur : ne donne aucune permission — Task 2.
- Invitation d'un e-mail déjà rattaché au même fournisseur : 422 neutre, aucun second rattachement — Task 5.
- Mot de passe changé : les autres jetons portail de ce compte sont révoqués, la session courante survit — Task 6.

---

### Task 1: Tables et modèles de rattachement

**Files:**
- Create: `database/migrations/2026_10_27_100000_create_provider_users_table.php`, `…100001_create_provider_user_roles_table.php`, `…100002_create_provider_user_invitations_table.php`
- Create: `app/Modules/ProviderPortal/Models/ProviderUser.php`, `ProviderUserRole.php`, `ProviderUserInvitation.php`
- Modify: `app/Shared/Enums/RoleScope.php` (case `PROVIDER = 'provider'`), `app/Shared/Enums/MenuSection.php` (case `PROVIDER_PORTAL = 'provider_portal'`), morph map si les modèles portail y figurent
- Test: `tests/Feature/ProviderPortal/ProviderUserModelTest.php`

**Interfaces:** Produces: `ProviderUser` (`provider()`, `user()`, `inviter()`, `roles(): BelongsToMany` via `provider_user_roles`, `invitations()`, `scopeForProvider(Builder, string)`, `isActive(): bool`), `ProviderUserInvitation::hashToken(string): string`, `isExpired(): bool`. Colonnes : copie de `customer_users` sans `is_owner` ; invitations = colonnes de `customer_user_invitations` avec `provider_id`/`provider_user_id`. FK `provider_id` → `providers` **restrictOnDelete** (un fournisseur n'est jamais supprimé en cascade avec ses accès).

- [ ] Test : unicité `(provider_id, user_id)` (QueryException), `isActive()` vrai seulement pour `active`, `roles()` rend le rôle attaché, `hashToken` = sha256.
- [ ] Lancer `php artisan test --filter=ProviderUserModelTest` → FAIL (classe absente).
- [ ] Écrire migrations, modèles, cases d'enum.
- [ ] Relancer → PASS ; `php artisan migrate --env=testing` propre.

### Task 2: Permissions, rôles par défaut, seeder rejouable

**Files:**
- Create: `app/Modules/ProviderPortal/Support/ProviderPortalPermissions.php`, `ProviderPortalRoles.php`, `app/Modules/ProviderPortal/Actions/ProvisionProviderPortalRoles.php`, `app/Modules/ProviderPortal/Services/ProviderPermissions.php`, `database/seeders/ProviderPortalSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (appel après `CustomerPortalSeeder`)
- Test: `tests/Feature/ProviderPortal/ProviderPortalSeederTest.php`

**Interfaces:** Produces: `ProviderPortalPermissions::{PREFIX='provider.', codes(), definitions(), isProviderPermission(string)}` ; catalogue Phase 1 : `provider.portal.access` « Accéder au portail fournisseur », `provider.dashboard.view` « Voir le tableau de bord », `provider.profile.view` « Voir le profil du fournisseur ». `ProviderPortalRoles::{ADMIN='PROVIDER_ADMIN', DISPATCHER='PROVIDER_DISPATCHER', FLEET_MANAGER='PROVIDER_FLEET_MANAGER', VIEWER='PROVIDER_VIEWER', defaults()}` — libellés « Administrateur fournisseur », « Exploitant », « Gestionnaire flotte », « Lecture » ; en Phase 1 les quatre portent les trois permissions. `ProviderPermissions::codesFor(ProviderUser): list<string>` (triés ; ne compte que les rôles `scope=provider`, `status=active`, même `organization_id` que le fournisseur). Même logique que `ProvisionCustomerPortalRoles` (rôle interne de même code jamais détourné ; permissions nouvelles ajoutées au rejeu). `menu_section = provider_portal`.

- [ ] Test : après `ProviderPortalSeeder` deux fois, chaque organisation a exactement 4 rôles `PROVIDER_*` de portée `provider` avec leurs permissions exactes ; aucun rôle interne ni `CUSTOMER_*` ne reçoit de `provider.*` ; un rôle `PROVIDER_ADMIN` d'une autre organisation attaché à un `ProviderUser` → `codesFor` vide.
- [ ] FAIL → implémenter → PASS.

### Task 3: Jeton, contexte, middlewares, routes

**Files:**
- Create: `app/Modules/ProviderPortal/Support/ProviderPortalToken.php`, `app/Modules/ProviderPortal/Services/CurrentProviderContext.php`, `app/Http/Middleware/EnsureProviderPortalContext.php`, `app/Http/Middleware/RequireProviderPermission.php`, `config/provider_portal.php`, `routes/provider.php`
- Modify: `bootstrap/app.php` (groupe `api/v1/provider` nommé `provider.`, alias `provider.portal`, `provider.permission`), `app/Http/Middleware/RejectCustomerPortalToken.php` (refuse aussi `ProviderPortalToken::isPortalToken` — message « Ce jeton est réservé à un portail externe. »), `app/Modules/CustomerPortal/Support/PortalOrganizationGate.php` (ajout `allowsProvider(Provider): bool`), `app/Shared/Http/RateLimits/PortalRateLimits.php` (`provider-portal-login`, `provider-portal`), `.env.example` (`PROVIDER_PORTAL_URL=http://localhost:5190`), `config/cors.php` (`http://localhost:5190`)
- Test: `tests/Feature/Api/V1/ProviderPortal/ProviderPortalAccessTest.php`, `tests/Support/ProviderPortal.php` (helpers `provider()`, `member()`, `token()`, `headers()`)

**Interfaces:** Produces: `ProviderPortalToken::{ABILITY='provider-portal', PROVIDER_PREFIX='provider:', NAME='provider-portal', abilitiesFor(string), expiresAt(), isPortalToken(mixed), providerIdOf(mixed)}` ; `CurrentProviderContext::{set(ProviderUser, array), membership(), provider(), providerId(), organizationId(), user(), can(string), permissions()}` (singleton scoped) ; middleware `EnsureProviderPortalContext::REVOKED='provider_access_revoked'`, chaîne : jeton portail → `allowsLogin()` → rattachement actif → fournisseur `active` → organisation ouverte → `provider.portal.access` détenu. `RequireProviderPermission` : 403 si aucune des permissions listées.

- [ ] Tests (route sonde déclarée **dans le test** sous `api/v1/provider/__probe` avec `auth:sanctum, provider.portal` — aucune route de production en double) : jeton fournisseur → 200 ; jeton fournisseur sur `api/v1/providers` (interne) → 403 ; sur `api/v1/customer/context` → 403 ; jeton client et jeton interne sur la sonde → 403 ; un même compte membre d'un client **et** d'un fournisseur : chaque jeton n'ouvre que son portail ; utilisateur suspendu, rattachement `disabled`, fournisseur `blocked`, organisation suspendue, rôle sans `provider.portal.access` → 403 `provider_access_revoked` ; jeton échu → 401.
- [ ] FAIL → implémenter → PASS ; relancer `CustomerPortalIsolationTest` → PASS.

### Task 4: Connexion, déconnexion, `me`

**Files:**
- Create: `app/Modules/ProviderPortal/Actions/LoginProviderUser.php`, `app/Modules/ProviderPortal/Exceptions/ProviderSelectionRequired.php`, `app/Modules/ProviderPortal/Services/ProviderPortalAudit.php` (préfixe `provider_portal.`), `app/Http/Controllers/Api/V1/Provider/ProviderAuthController.php`, `app/Http/Requests/Api/V1/Provider/ProviderLoginRequest.php`, `app/Http/Resources/Api/V1/Provider/ProviderContextResource.php`
- Test: `tests/Feature/Api/V1/ProviderPortal/ProviderPortalAuthTest.php`

**Interfaces:** Routes `POST auth/login` (throttle `provider-portal-login`), `POST auth/logout`, `GET auth/me`. `LoginProviderUser::execute(Request, string $email, string $password, ?string $providerId): ProviderUser` — même structure que `LoginCustomerUser` (clé limiteur `provider-login:<ip>|<email>`, 5 essais, log avec `email_hash`). `ProviderSelectionRequired` → 409 `provider_selection_required` + `providers: [{id,name}]`, seulement après mot de passe vérifié. `ProviderContextResource` : `user{id,firstName,lastName,email}`, `provider{id,code,name}`, `roles[{code,name}]`, `permissions[]`.

- [ ] Tests : succès (jeton aux capacités exactes, `last_login_at`, audit `provider_portal.login`) ; mauvais mot de passe → 422 ; 6ᵉ essai → 422 « Trop de tentatives » ; compte sans rattachement et collaborateur interne seul → 422 « Ce compte n’a pas d’accès actif au portail fournisseur. » ; fournisseur `inactive` → même 422 ; deux fournisseurs sans choix → 409 ; `providerId` d'un fournisseur non rattaché → 422 ; logout révoque seulement le jeton courant.
- [ ] FAIL → implémenter → PASS.

### Task 5: Gestion des accès au back-office (API interne)

**Files:**
- Create: `app/Modules/ProviderPortal/Actions/InviteProviderUser.php`, `ChangeProviderUserStatus.php`, `AssignProviderUserRole.php`, `ResendProviderInvitation.php`, `app/Modules/ProviderPortal/Services/IssueProviderInvitation.php`, `app/Modules/ProviderPortal/Mail/ProviderPortalMail.php` (+ vues `resources/views/mail/provider-portal/*.blade.php`), `app/Http/Controllers/Api/V1/Providers/ProviderPortalUserController.php`, `app/Http/Requests/Api/V1/Providers/InviteProviderUserRequest.php`, `UpdateProviderUserRequest.php`, `app/Http/Resources/Api/V1/Providers/ProviderPortalUserResource.php`
- Modify: `routes/api.php` (avant `apiResource('providers')`)
- Test: `tests/Feature/Api/V1/Providers/ProviderPortalUserManagementTest.php`

**Interfaces:** Routes internes `GET providers/{provider}/portal-users` (`authorize('view')`), `POST …/portal-users/invite`, `PATCH …/portal-users/{providerUser}` (`roleId`), `POST …/{providerUser}/disable|enable|resend-invitation` (`authorize('update')`). `{providerUser}` résolu **dans** `$provider` (sinon 404). `roleId` doit être un rôle `scope=provider` de l'organisation du fournisseur. Lien d'invitation : `config('provider_portal.url').'/activate?token=…'`, expiration `provider_portal.invitation_expires_hours` (72). Un utilisateur existant est rattaché sans changer son mot de passe. Audit `provider_portal.user_invited|role_changed|disabled|enabled`.

- [ ] Tests : invitation crée `ProviderUser(invited)` + rôle + courriel (Mail::fake) ; e-mail déjà rattaché au même fournisseur → 422 neutre ; `roleId` interne ou d'une autre organisation → 422 ; `{providerUser}` d'un autre fournisseur → 404 ; sans `providers.update` → 403 ; jeton portail fournisseur sur ces routes → 403 ; désactiver coupe l'accès à la requête suivante.
- [ ] FAIL → implémenter → PASS.

### Task 6: Activation, mot de passe, profil, navigation

**Files:**
- Create: `app/Modules/ProviderPortal/Actions/ActivateProviderInvitation.php`, `SendProviderPasswordReset.php`, `ChangeProviderPassword.php`, `app/Modules/ProviderPortal/Services/ProviderInvitationLookup.php`, `ProviderNavigation.php`, `app/Http/Controllers/Api/V1/Provider/ProviderActivationController.php`, `ProviderPasswordController.php`, `ProviderContextController.php`, requêtes et `ProviderProfileResource.php`
- Test: `tests/Feature/Api/V1/ProviderPortal/ProviderPortalActivationTest.php`, `ProviderPortalProfileTest.php`

**Interfaces:** Routes publiques `GET auth/invitation`, `POST auth/activate`, `POST auth/forgot-password`, `POST auth/reset-password` (mêmes limiteurs que le portail client ; lien de réinitialisation vers `provider_portal.url`). Authentifiées : `GET navigation` → `[{key,label,path}]` filtré par permission (Phase 1 : `dashboard`, `profile`) ; `GET profile` (`provider.profile.view`) → fournisseur `code,name,status`, adresse et contact du fournisseur, utilisateur ; `PUT profile` → `UpdateUserProfile` existant ; `PUT profile/password` (`currentPassword`, `password`, `password_confirmation`) → révoque les **autres** jetons `provider-portal` du compte.

- [ ] Tests : jeton d'invitation invalide, expiré ou déjà utilisé → 422 générique ; activation → compte et rattachement `active` ; forgot-password répond 200 identique pour un e-mail inconnu ; navigation sans `provider.profile.view` n'a pas `profile` et ne contient jamais `users`/`roles` ; changement de mot de passe : ancien faux → 422, autres jetons supprimés, jeton courant valide.
- [ ] FAIL → implémenter → PASS.

### Task 7: Tableau de bord

**Files:**
- Create: `app/Modules/ProviderPortal/Services/ProviderDashboardComposer.php`, `app/Http/Controllers/Api/V1/Provider/ProviderDashboardController.php`
- Test: `tests/Feature/Api/V1/ProviderPortal/ProviderDashboardTest.php`

**Interfaces:** `GET dashboard` (`provider.dashboard.view`) → `{toursToday, toursInProgress, toursCompletedToday, activeDrivers, activeVehicles}`. Toutes les requêtes filtrent `provider_id` **et** `organization_id` du contexte ; `toursToday` = `tour_date` = aujourd'hui (fuseau de l'organisation si disponible, sinon `app.timezone`) et statut ≠ `draft`, `cancelled` ; `activeDrivers`/`activeVehicles` = statut `active`.

- [ ] Tests : compte exact avec tournées du fournisseur A, d'un fournisseur B de la même organisation et d'une autre organisation (seules celles de A comptent) ; brouillon et annulée exclus ; sans permission → 403.
- [ ] FAIL → implémenter → PASS.

### Task 8: Onglet « Accès portail » du back-office

**Files:**
- Create: `frontend/src/modules/providers/components/ProviderPortalAccessTab.tsx`, `ProviderPortalInviteDialog.tsx`, `frontend/src/modules/providers/api/providerPortalUsers.api.ts`, `frontend/src/modules/providers/hooks/useProviderPortalUsers.ts`
- Modify: `frontend/src/modules/providers/pages/ProviderDetailPage.tsx` (onglet `portal`, visible avec `providers.view`, actions avec `providers.update`), fichiers i18n `providers.portal.*`
- Test: `frontend/src/modules/providers/components/ProviderPortalAccessTab.test.tsx`

- [ ] Tests Vitest : liste (nom, e-mail, rôle, statut) ; invitation envoie `{email, firstName, lastName, roleId}` ; actions désactiver/réactiver/renvoyer selon statut ; sans `providers.update` aucune action.
- [ ] FAIL → implémenter → PASS ; `npm run lint && npm run typecheck` dans `frontend/`.

### Task 9: Socle de l'app `provider-portal/`

**Files:**
- Create: `provider-portal/` — `package.json` (nom `provider-portal`, mêmes dépendances que `customer-portal` **sans** FullCalendar ni Leaflet pour l'instant), `vite.config.ts` (port 5190, alias `@`, erreur si `VITE_API_URL` manque au build), `tsconfig*.json`, `index.html`, `src/main.tsx`, `src/index.css`, `src/shared/**` recopié de `customer-portal/src/shared` **sauf** `components/map`, `api/documentsApi.ts`, `components/data/ListsStructure.test.tsx`, `RowActionsScreens.test.tsx`; `src/test/{setup,server,renderApp,renderWithAuth,fixtures}.ts(x)` adaptés ; `README.md`
- Modify: `provider-portal/src/shared/api/session.ts` → `sessionStorage`, clé `tricolis.provider-portal.token`
- Test: tests copiés du socle + `src/shared/api/session.test.ts`

- [ ] Test session : `setToken` écrit en `sessionStorage` (jamais `localStorage`) ; `clear()` efface et notifie les abonnés.
- [ ] `npm install`, `npm run test`, `npm run lint`, `npm run typecheck`, `VITE_API_URL=http://localhost:8000/api/v1/provider npm run build` → tout vert.

### Task 10: Authentification, gardes, tableau de bord et profil (React)

**Files:**
- Create: `provider-portal/src/app/{App.tsx,queryClient.ts}`, `app/auth/{AuthContext.ts,AuthProvider.tsx,useAuth.ts}`, `app/guards/{ProtectedRoute.tsx,Can.tsx}`, `app/layouts/{PortalLayout,PortalSidebar,PortalHeader,UserMenu,Breadcrumbs}.tsx`, `app/layouts/navigation.ts`, `app/router/{AppRouter.tsx,lazyPages.ts,paths.ts}`, `modules/auth/**` (login avec choix du fournisseur sur 409, activation, mot de passe oublié/réinitialisation), `modules/dashboard/{api,pages,components}`, `modules/profile/pages/ProfilePage.tsx` (+ changement de mot de passe), `modules/errors/pages/{ForbiddenPage,NotFoundPage}.tsx`
- Test: `src/app/guards/guards.test.tsx`, `src/app/auth/sessionExpiry.test.tsx`, `src/modules/auth/pages/LoginPage.test.tsx`, `src/modules/dashboard/pages/DashboardPage.test.tsx`, `src/app/layouts/navigation.test.tsx`

**Interfaces:** la navigation affichée = entrées de `GET navigation` (le serveur décide) ; `Can` lit `permissions` de `me`.

- [ ] Tests : non connecté → `/login` ; sans permission → page 403 ; 401 ou `provider_access_revoked` → session et cache TanStack vidés, retour `/login` ; 409 au login → liste des fournisseurs puis reconnexion avec `providerId` ; tableau de bord affiche les 5 KPI ; la barre latérale ne contient jamais « Utilisateurs » ni « Rôles » ; profil en lecture seule sauf mot de passe.
- [ ] FAIL → implémenter → PASS ; lint, typecheck, build verts.

### Task 11: Démo, E2E, documentation, vérification complète

**Files:**
- Create: `database/seeders/DemoProviderPortalSeeder.php` (garde local/testing ; deux fournisseurs actifs de l'organisation de démo, un compte actif chacun `admin@transports-alpha.test` / `admin@transports-beta.test`, mot de passe de démo identique au portail client), `provider-portal/playwright.config.ts`, `provider-portal/e2e/{support.ts,auth.spec.ts,isolation.spec.ts}`, `docs/provider-portal/README.md`, `docs/provider-portal/access-matrix.md`
- Modify: `database/seeders/DatabaseSeeder.php` (bloc local), `tests/Feature/Seeders/DemoSeedersProductionGuardTest.php` (ajout du seeder + tables `provider_users`, `provider_user_roles`)
- Test: E2E + garde de production

- [ ] E2E : connexion A → tableau de bord → profil → déconnexion ; jeton A contre route interne et route client → 403 ; aucun lien Utilisateurs/Rôles.
- [ ] Suite Pest complète détachée → 0 échec ; Vitest `provider-portal`, `customer-portal`, `frontend` ; Playwright `provider-portal` puis `customer-portal` (re-semer avant chacun) ; `vendor/bin/pint --test` ; `php artisan customer-portal:route-matrix --check`.
- [ ] Rapport de phase en français : fichiers, routes, migrations, tests et résultats réels, risques, `BLOCKED_DESIGN_DECISION`, phase suivante. Vérifier `git status` (pas de commit).
