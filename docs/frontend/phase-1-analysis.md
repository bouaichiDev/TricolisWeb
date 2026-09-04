# Frontend Phase 1 — Analyse préalable

Document exigé par le §3. Aucune page n'a été créée avant sa production.

---

## 1. État de départ

**Aucun projet frontend n'existait.** Le dossier `Project/Front/` ne contenait
que le prompt de phase. Les seize points d'analyse du §3 — `package.json`,
structure `src/`, router, auth, client API, TanStack Query, Tailwind,
shadcn/ui, i18next, composants partagés — portaient donc sur du vide.

La phase commence par la création du projet, puis par la construction du socle
que les points suivants supposaient déjà présent.

### Emplacement retenu

```text
TricolisWeb/frontend/
```

Dans le dépôt du backend, et non dans un dépôt séparé. Trois raisons :

- `docs/frontend/backend-api-contract.md` y est déjà, et le §41 y attend
  `phase-1-final-report.md` — les deux chemins désignent ce dépôt ;
- le §0 décrit un flux de branches sur un dépôt existant avec un remote ;
  `TricolisWeb` est le seul à en avoir un ;
- le contrat d'API et le code qui le consomme évoluent ensemble. Séparés, ils
  divergent au premier champ ajouté.

## 2. Stack installée

Celle imposée par le §2, sans ajout :

| Paquet | Version | Rôle |
|---|---|---|
| `react` | 19.2 | — |
| `typescript` | 6.0 | — |
| `vite` | 8.2 | build et serveur de développement |
| `react-router-dom` | 7.18 | routage |
| `@tanstack/react-query` | 5.101 | données serveur |
| `tailwindcss` | 4.3 | styles |
| shadcn/ui | — | 24 composants copiés dans `src/shared/components/ui` |
| `react-hook-form` | 7.85 | formulaires |
| `zod` | 3.25 | validation |
| `i18next` / `react-i18next` | 26.3 | traductions |
| `lucide-react` | — | icônes |
| `vitest` + Testing Library + `msw` | — | tests |

**Aucun client HTTP tiers.** Le §2 dit « Ne pas utiliser Axios si le projet
possède déjà un client HTTP stable ». Le projet n'en avait aucun ; `fetch` natif
suffit, et une dépendance de moins est une dépendance de moins à suivre.

**Aucun Redux.** Le §2 l'écarte si TanStack Query et le contexte suffisent. Ils
suffisent : l'état de cette application est du cache serveur, plus une session.

## 3. Architecture

```text
src/
├── app/
│   ├── guards/       PermissionGuard · ProtectedRoute
│   ├── i18n/         configuration + locales/fr.json
│   ├── layouts/      AppLayout · AppSidebar · OrganizationSwitcher · UserMenu
│   ├── providers/    AuthProvider · QueryProvider
│   └── router/       AppRouter · navigation
│
├── modules/          un dossier par domaine métier
│   └── <module>/     api · hooks · pages · components · schemas · types
│
└── shared/
    ├── api/          client · errors · session · types
    ├── components/   ui (shadcn) · layout · data · feedback · form
    ├── hooks/        useAuth · usePermission
    ├── types/        auth
    └── utils/        cn
```

## 4. Le point structurant — les habilitations viennent de l'appartenance

C'est la découverte de l'analyse, et elle conditionne tout le reste.

`GET /api/v1/auth/me` renvoie :

```jsonc
{
  "data": {
    "user": {
      "id", "firstName", "lastName", "fullName", "email", "phone",
      "preferredLanguage", "status", "emailVerifiedAt", "lastLoginAt",
      "organizations": [
        {
          "id", "code", "name", "isOwner", "isPrimary",
          "roles":       [{ "id", "code", "name" }],
          "permissions": [{ "id", "code" }],
          "agencies":    [{ "id", "code", "name" }]
        }
      ]
    }
  }
}
```

**Les permissions sont portées par l'appartenance, pas par le compte.** Un même
utilisateur peut être propriétaire d'une organisation et simple lecteur dans une
autre. Trois conséquences directes :

1. `usePermissions` lit les permissions de **l'appartenance active**, jamais une
   liste globale ;
2. changer d'organisation change les droits **en même temps** que les données —
   d'où l'invalidation complète du cache dans `switchOrganization` ;
3. le propriétaire contourne le contrôle, exactement comme
   `BaseOrganizationPolicy::hasPermission()` le fait côté serveur. Les deux
   doivent dire la même chose, sinon l'interface masque un bouton que l'API
   accepterait.

### Correction apportée au contrat d'API

`docs/frontend/backend-api-contract.md` affirmait :

> « Le backend ne renvoie pas la liste des permissions de l'utilisateur sur
> `/auth/me` : l'interface doit tenter l'action et traiter le 403. »

**C'est faux**, et cela a été corrigé. Le contrat avait été rédigé en Phase 10
sans relire `Auth\UserResource`. Le repli par 403 reste valable comme filet,
mais l'interface peut — et doit — masquer en amont ce qui n'est pas permis.

## 5. Endpoints de la phase

Relevés dans `routes/api.php` et vérifiés Resource par Resource.

| Domaine | Endpoints | Permissions |
|---|---|---|
| Authentification | `POST /auth/login` · `GET /auth/me` · `POST /auth/logout` · `POST /auth/logout-all` | — |
| Organisations | `GET|POST /organizations` · `GET|PATCH|DELETE /organizations/{id}` | `organizations.view` `.create` `.update` |
| Utilisateurs | `GET|POST /users` · `GET|PATCH|DELETE /users/{id}` | `users.view` `.create` `.update` `.disable` `.assign_roles` |
| Appartenances | `GET|POST /organization-users` · `GET|PATCH|DELETE /organization-users/{id}` | `users.assign_roles` |
| Rôles | `GET|POST /roles` · `GET|PATCH|DELETE /roles/{id}` | `roles.view` `.create` `.update` `.delete` `.assign_permissions` |
| Permissions | `GET /permissions` · `GET /permissions/{id}` | `permissions.view` |
| Agences | `GET|POST /agencies` · `GET|PATCH|DELETE /agencies/{id}` | `agencies.view` `.create` `.update` `.delete` |
| Dépôts | `GET|POST /agencies/{agency}/depots` · `GET|PATCH|DELETE /agencies/{agency}/depots/{depot}` | `depots.view` `.create` `.update` `.delete` |
| Clients | `GET|POST /customers` · `GET|PATCH|DELETE /customers/{id}` · `PATCH /customers/{id}/status` | `customers.view` `.create` `.update` `.delete` `.block` |
| Sites client | `GET|POST /customers/{customer}/sites` · `GET|PATCH|DELETE .../sites/{site}` | `customer_sites.view` `.create` `.update` `.delete` |
| Adresses | `GET|POST /addresses` · `GET|PATCH|DELETE /addresses/{id}` · liens | `addresses.view` `.create` `.update` `.delete` |
| Contacts | `GET|POST /contacts` · `GET|PATCH|DELETE /contacts/{id}` · liens | `contacts.view` `.create` `.update` `.delete` |
| Documents | `GET|POST /documents` · `GET|DELETE /documents/{id}` · `GET /documents/{id}/download` · liens | `documents.view` `.upload` `.delete` |
| Audit | `GET /audit-logs` | `audit_logs.view` |

### Deux contraintes de route à connaître

**Les dépôts sont imbriqués sous une agence.** Il n'existe pas de
`GET /depots` : le backend n'expose que `/agencies/{agency}/depots`. Le §16
demande une page `/depots` avec un filtre par agence — elle exigera donc de
choisir une agence d'abord, ce qui correspond d'ailleurs à la dépendance
`Organization → Agency → Depot` décrite au même paragraphe.

**Les organisations ne sont pas filtrées par l'organisation active.** Ces cinq
routes sont hors du middleware de contexte : elles retournent les organisations
dont l'utilisateur est membre. C'est cohérent — filtrer les organisations par
l'organisation active serait circulaire.

## 6. Champs réels par entité

Relevés sur les API Resources, pas déduits des diagrammes.

| Entité | Champs exposés |
|---|---|
| `Organization` | `id` `code` `name` `legalName` `registrationNumber` `taxNumber` `email` `phone` `preferredLanguage` `timezone` `currencyCode` `status` `settings` `createdAt` `updatedAt` |
| `User` | `id` `firstName` `lastName` `fullName` `email` `phone` `preferredLanguage` `status` `emailVerifiedAt` `lastLoginAt` `memberships[]` |
| `Role` | `id` `organizationId` `code` `name` `scope` `isSystem` `status` `permissions[]` |
| `Permission` | `id` `code` `name` `module` `action` |
| `Agency` | `id` `organizationId` `code` `name` `shortName` `email` `phone` `color` `loadingPoint` `status` |
| `Depot` | `id` `agencyId` `code` `name` `status` |
| `Customer` | `id` `organizationId` `code` `name` `legalName` `email` `phone` `paymentMode` `communicationMode` `catalogEnabled` `stockEnabled` `packageEnabled` `appointmentEnabled` `trackingEnabled` `status` |
| `AuditLog` | `id` `organizationId` `userId` `action` `entityType` `entityId` `oldValues` `newValues` `ipAddress` `createdAt` |

### Capacités client — les cinq réelles

Le §18 en cite cinq à titre d'exemple ; ce sont exactement celles que l'API
expose :

```text
catalogEnabled · stockEnabled · packageEnabled · appointmentEnabled · trackingEnabled
```

Aucune n'est inventée, aucune n'est omise.

## 7. Listes — ce que le serveur sait faire

Chaque liste tient sa propre liste blanche. Une colonne hors liste renvoie
**422** : le tri client est donc exclu, comme le §26 le demande.

| Liste | Tri autorisé | Recherche sur | Filtres |
|---|---|---|---|
| Clients | `name` `code` `created_at` | `name` `code` `legal_name` `email` | `status` |
| Agences | `name` `code` `created_at` | `name` `code` | `status` |
| Utilisateurs | `last_name` `email` `created_at` | `first_name` `last_name` `email` | `status` |
| Audit | `created_at` | — | `entityType` `entityId` `action` `userId` `createdFrom` `createdTo` |

Paramètres communs : `page`, `perPage` (défaut 25, maximum 100), `search`,
`sort`, `direction`, `createdFrom`, `createdTo`.

## 8. Ce qui sera créé

**Pages** — 30 : organisations (4), utilisateurs (4), rôles (4), agences (4),
dépôts (4), clients (4), sites client (4), audit (1), tableau de bord (1),
plus connexion, accès refusé et introuvable.

**Composants partagés** — `DataTable` et ses satellites, `PageHeader`,
`StatusBadge`, `ConfirmDialog`, `SearchInput`, `EmptyState`, `ErrorState`,
`LoadingSkeleton`, `FormErrorSummary`, `SectionCard`, `DetailField`,
`AsyncSelect`.

**Composants métier réutilisables** — `AddressForm`, `AddressCard`,
`ContactForm`, `ContactCard`, `DocumentList`, `DocumentUploader`. Le §22 et le
§23 insistent : ils ne doivent pas être couplés à `Customer`, puisque les
commandes et les tournées les réutiliseront.

**Hooks** — un jeu `useXList` / `useX` / `useCreateX` / `useUpdateX` /
`useDeleteX` par module, adossé à une fabrique de clés de requête.

**Schémas Zod** — un par formulaire, limité à ce que le §30 autorise : requis,
format, longueur, e-mail, nombre, date. Les règles métier restent au backend.

## 9. Ce qui manque côté API

| Manque | Conséquence | Contournement retenu |
|---|---|---|
| **Aucun endpoint d'agrégation** pour le tableau de bord | Pas de compteurs sans requête dédiée | Lire `meta.total` des listes existantes. Le §11 interdit les valeurs fictives ; aucune n'est affichée. |
| **Pas de `GET /depots` global** | La page dépôts exige de choisir une agence | Sélecteur d'agence obligatoire, conforme à la dépendance du §16 |
| **Pas de `DELETE /users/{id}`** exposé comme suppression | Un utilisateur se désactive, ne se supprime pas | Action « désactiver » sous `users.disable` |
| **Permissions de l'utilisateur non filtrables par module côté API** | Le regroupement par domaine du §14 se fait côté client | `GET /permissions` expose `module` et `action` : le regroupement s'appuie dessus, il n'invente rien |

Aucun de ces manques n'est comblé par de la logique métier côté React, ni par un
mock. Le §1 l'interdit.

## 10. Exclusions volontaires

Conformément aux §17 et §42, rien n'est développé pour : catalogues, commandes,
lignes, colis, services de commande, fournisseurs, chauffeurs, véhicules,
planning, tournées, suivi, preuves de livraison, réclamations, facturation,
stock, imports, exports, communications, portails client, fournisseur et
chauffeur.

Les onglets `Catalogs`, `Orders`, `Stock`, `Invoices`, `Claims` et
`Integrations` de la fiche client ne sont pas créés — le §20 les réserve aux
phases suivantes.
