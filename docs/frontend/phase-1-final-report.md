# Frontend — Phase 1 : Administration & Customers

Rapport de fin de phase, conforme au §41.

---

## 1. Branche utilisée

`feature/frontend-phase-1-administration-customers`, partie de `main`.

Aucune fusion vers `main`, aucun push : le §0 les réserve à une validation
explicite.

Commits de la phase :

| Commit | Objet |
| --- | --- |
| `6089316` | Socle : client HTTP, session, authentification, garde de route |
| `8ee6b58` | Analyse de la phase, correction du contrat d'API |
| `6c5d32b` | `import.meta.dirname` au lieu de `__dirname` (Vite 8) |
| `a3adf8f` | Layout conforme aux maquettes du dossier `screenShot` |
| `7cdb4d9` | Module Customers |
| `1d22305` | Agences, dépôts, adresses, sites client |
| `6e9bb64` | Organisations, utilisateurs, rôles, audit, dashboard |
| `4fc625a` | Tests du §37 |

---

## 2. Pages créées

32 pages, hors composants.

| Module | Pages |
| --- | --- |
| Auth | `LoginPage` |
| Dashboard | `DashboardPage` |
| Customers | `List`, `Create`, `Detail`, `Edit` |
| Customer sites | `Create`, `Detail`, `Edit` (+ onglet dans la fiche client) |
| Agencies | `List`, `Create`, `Detail`, `Edit` |
| Depots | `List`, `Create`, `Detail`, `Edit` |
| Organizations | `List`, `Create`, `Detail`, `Edit` |
| Users | `List`, `Create`, `Detail`, `Edit` |
| Roles | `List`, `Create`, `Detail`, `Edit` |
| Audit | `AuditLogPage` |
| Système | `NotFoundPage`, `ForbiddenPage` |

---

## 3. Routes créées

La table est découpée par domaine dans `src/app/router/routes/` : une table
unique dépassait la taille relisible.

```
/login                                    (publique)
/forbidden                                (publique)
/dashboard                                dashboard.view
/customers                                customers.view
/customers/create                         customers.create
/customers/:id                            customers.view
/customers/:id/edit                       customers.update
/customers/:customerId/sites/create       customer_sites.create
/customers/:customerId/sites/:siteId      customer_sites.view
/customers/:customerId/sites/:siteId/edit customer_sites.update
/agencies                                 agencies.view
/agencies/create                          agencies.create
/agencies/:id                             agencies.view
/agencies/:id/edit                        agencies.update
/depots                                   depots.view
/agencies/:agencyId/depots/create         depots.create
/agencies/:agencyId/depots/:depotId       depots.view
/agencies/:agencyId/depots/:depotId/edit  depots.update
/users                                    users.view
/users/create                             users.create
/users/:id                                users.view
/users/:id/edit                           users.update
/roles                                    roles.view
/roles/create                             roles.create
/roles/:id                                roles.view
/roles/:id/edit                           roles.update
/organizations                            organizations.view
/organizations/create                     organizations.create
/organizations/:id                        organizations.view
/organizations/:id/edit                   organizations.update
/audit                                    audit.view
```

Deux choix d'URL méritent une justification.

**Les dépôts vivent sous l'agence** (`/agencies/:agencyId/depots/...`) parce que
l'API les y place : il n'existe pas de `GET /depots` global, seulement
`/agencies/{agency}/depots`. Garder la même forme d'URL évite d'avoir à
retrouver l'agence à partir du dépôt. `/depots` reste un point d'entrée de
navigation, qui demande d'abord de choisir une agence.

**`/users/:id` porte l'identifiant du rattachement**, pas celui du compte. C'est
la ressource que manipule `/organization-users`, et c'est elle qui porte les
rôles.

---

## 4. Composants partagés

| Fichier | Rôle |
| --- | --- |
| `data/DataTable.tsx` | Table adossée à la pagination serveur |
| `data/DataTableParts.tsx` | Tri, squelette, état vide, état d'erreur |
| `data/DataTablePagination.tsx` | Pagination |
| `data/SearchInput.tsx` | Recherche à propagation différée (350 ms) |
| `data/StatusBadge.tsx` | Statut coloré |
| `form/TextField.tsx` | Champ texte relié à React Hook Form |
| `form/SelectField.tsx` | Liste déroulante |
| `form/StatusSelect.tsx` | Statut, valeurs fournies par l'appelant |
| `form/CheckboxField.tsx` | Case à cocher booléenne |
| `form/FormActions.tsx` | Boutons annuler / valider |
| `form/FormErrorSummary.tsx` | Message d'erreur global |
| `layout/PageHeader.tsx` | Titre de page et actions |
| `layout/EntityHeader.tsx` | En-tête de fiche, mutualisé |
| `layout/SectionCard.tsx` | Bloc de contenu titré |
| `layout/DetailField.tsx` | Couple libellé / valeur |
| `feedback/ConfirmDialog.tsx` | Confirmation (§33 : jamais `window.confirm`) |
| `feedback/EmptyState.tsx` | État vide |
| `feedback/ErrorState.tsx` | État d'erreur avec reprise |
| `feedback/LoadingSkeleton.tsx` | `DetailSkeleton`, `ListSkeleton` |
| `feedback/FullPageLoader.tsx` | Chargement de session |
| `utils/format.ts` | Dates, dans la locale i18n active |

---

## 5. Modules créés

`addresses`, `agencies`, `audit`, `auth`, `contacts`, `customers`,
`customerSites`, `dashboard`, `depots`, `documents`, `organizations`, `roles`,
`system`, `users`.

Chaque module suit la même découpe : `api/`, `hooks/`, `schemas/`,
`components/`, `pages/`, `types/`. Aucun fichier ne dépasse 200 lignes ; le plus
long du code applicatif est `CustomerForm.tsx` à 163 lignes.

---

## 6. Hooks créés

| Hook | Rôle |
| --- | --- |
| `useAuth` | Identité et appartenance active |
| `usePermissions` / `usePermission` | Habilitations, avec contournement propriétaire |
| `useApiFormError` | Traduction d'une erreur d'API en erreurs de champs |
| `useCustomers` | Liste, fiche, création, modification, statut, suppression |
| `useCustomerSites` | CRUD des sites, routes imbriquées |
| `useCustomerSiteMutations` | Enchaînement adresse → site |
| `useAddresses` | CRUD des adresses partagées |
| `useAgencies`, `useDepots` | CRUD agences et dépôts |
| `useOrganizations`, `useMembers`, `useRoles` | CRUD administration |
| `useAuditLogs` | Journal, lecture seule |
| `useCounters` | Compteurs du tableau de bord |

---

## 7. Appels API utilisés

Toutes les routes ci-dessous ont été relevées dans `routes/api.php` ; aucune n'a
été inventée.

```
POST   /auth/login                              GET  /auth/me           POST /auth/logout
GET    /customers                               POST /customers
GET    /customers/{id}                          PATCH /customers/{id}   DELETE /customers/{id}
PATCH  /customers/{id}/status
GET    /customers/{customer}/sites              POST /customers/{customer}/sites
GET    /customers/{customer}/sites/{site}       PATCH /…/{site}         DELETE /…/{site}
GET    /addresses                               POST /addresses
GET    /addresses/{id}                          PATCH /addresses/{id}
GET    /agencies                                POST /agencies
GET    /agencies/{id}                           PATCH /agencies/{id}    DELETE /agencies/{id}
GET    /agencies/{agency}/depots                POST /agencies/{agency}/depots
GET    /agencies/{agency}/depots/{depot}        PATCH /…/{depot}        DELETE /…/{depot}
GET    /organizations                           POST /organizations
GET    /organizations/{id}                      PATCH /…/{id}           DELETE /…/{id}
GET    /organization-users                      POST /organization-users
GET    /organization-users/{id}                 PATCH /…/{id}           DELETE /…/{id}
GET    /roles                                   POST /roles
GET    /roles/{id}                              PATCH /roles/{id}       DELETE /roles/{id}
GET    /permissions
GET    /audit-logs
```

Un seul client HTTP, `src/shared/api/client.ts`, pose `Accept`,
`Authorization: Bearer` et `X-Organization-Id`, et vide la session sur 401.

---

## 8. Permissions utilisées

```
dashboard.view
customers.view      customers.create      customers.update    customers.delete    customers.block
customer_sites.view customer_sites.create customer_sites.update customer_sites.delete
agencies.view       agencies.create       agencies.update     agencies.delete
depots.view         depots.create         depots.update       depots.delete
organizations.view  organizations.create  organizations.update organizations.delete
users.view          users.create          users.update        users.disable       users.assign_roles
roles.view          roles.create          roles.update        roles.delete        roles.assign_permissions
audit.view
```

Une correction est intervenue en cours de phase : la navigation exigeait
`audit_logs.view`, permission qui n'existe pas. Le code réel est `audit.view`
(`PermissionSeeder`, `AuditLogPolicy`) ; l'entrée de menu était donc masquée pour
tout le monde, y compris pour un administrateur.

Deux permissions sont traitées comme distinctes de la modification, parce
qu'elles le sont côté backend : `customers.block` (interrompre les commandes
d'un client n'est pas une correction de fiche), `roles.assign_permissions` et
`users.assign_roles` (attribuer des droits n'est pas modifier un libellé).

---

## 9. Schémas Zod

`addressSchema`, `agencySchema`, `customerSchema`, `customerSiteSchema`,
`depotSchema`, `organizationSchema`, `roleSchema`, `memberSchema`
(`memberCreateSchema` + `memberUpdateSchema`).

Les messages sont des **clés i18n**, traduites par `TextField` avec repli sur le
texte brut : un message venant du serveur arrive déjà rédigé et passe tel quel.

Les longueurs reproduisent celles des `FormRequest` Laravel. Les règles métier —
unicité d'un code dans l'organisation, transitions de statut — restent au
backend, seul à pouvoir les vérifier.

---

## 10. Query keys

Une fabrique par module, jamais de tableau écrit à la main dans un composant :

```ts
customerKeys.all | .lists() | .list(filters) | .detail(id)
agencyKeys, organizationKeys, memberKeys, roleKeys, depotKeys,
customerSiteKeys, addressKeys, auditKeys
```

`placeholderData: (previous) => previous` sur toutes les listes : sans lui, la
table se vide à chaque changement de page ou de filtre.

---

## 11. Tests ajoutés

11 fichiers, 64 tests.

| Fichier | Sujet du §37 couvert |
| --- | --- |
| `PermissionGuard.test.tsx` | PermissionGuard, actions non autorisées |
| `ProtectedRoute.test.tsx` | ProtectedRoute |
| `OrganizationSwitcher.test.tsx` | OrganizationSwitcher |
| `AppSidebar.test.tsx` | Menu dynamique |
| `DataTable.test.tsx` | Pagination, tri, états, colonnes responsive |
| `useApiForm.test.tsx` | Erreurs 422, 409, 403 |
| `CustomerForm.test.tsx` | Formulaire Customer |
| `AgencyForm.test.tsx` | Formulaire Agency |
| `DepotForm.test.tsx` | Formulaire Depot |
| `CustomerSitesTab.test.tsx` | Customer Sites, actions non autorisées |
| `CustomerListPage.test.tsx` | Filtres, recherche, tri envoyés à l'API |

Aucune nouvelle stack : Vitest, Testing Library et MSW étaient déjà installés.

MSW démarre avec `onUnhandledRequest: 'error'` — un appel non prévu fait échouer
le test au lieu de passer inaperçu.

---

## 12. Résultats des tests

```
Test Files  11 passed (11)
     Tests  64 passed (64)
```

`npm run typecheck` : aucune erreur.
`npm run lint` : aucune erreur ; 5 avertissements `only-export-components`, tous
sur des fichiers `shadcn/ui` non modifiés et sur `AuthProvider`.
`npm run build` : succès, 746 kB (218 kB gzip).

---

## 13. Responsive

Le layout suit les maquettes : barre latérale sombre fixe au-dessus de `lg`,
tiroir sur mobile. Les tables utilisent `overflow-x-auto` et les colonnes
secondaires portent `hidden md:table-cell` — vérifié par test. Les formulaires
passent de trois colonnes à une par `grid gap-5 sm:grid-cols-2`.

**Réserve à lever** : les maquettes ne sont pas homogènes. Trois des quatre
écrans échantillonnés utilisent une barre latérale sombre ; le quatrième
(`21_57_26 (2)`, écran de planning) utilise une navigation horizontale haute.
J'ai suivi la majorité. Cette divergence relève d'un arbitrage à trancher.

---

## 14. i18n

Une seule langue livrée, le français, dans `src/app/i18n/locales/fr.json`.
Ajouter une traduction vide aurait été un mensonge : l'utilisateur verrait des
clés brutes. La structure est en place — une seconde langue s'ajoute par un
fichier et une ligne.

Aucun libellé n'est écrit en dur dans un composant. Les dates sont formatées
dans la locale active, pas celle du navigateur, pour que changer de langue
change aussi le format.

---

## 15. Fichiers créés

164 fichiers TypeScript hors composants `shadcn/ui` — dont 11 fichiers de test —
plus 24 composants `shadcn/ui` générés. Le détail exact est donné par :

```bash
git diff --stat main..feature/frontend-phase-1-administration-customers -- frontend
```

soit 205 fichiers et 19 141 insertions, échafaudage Vite et configuration
compris. Le seul code applicatif écrit après le socle représente 142 fichiers et
8 745 insertions (`git diff --stat 6089316..HEAD -- frontend`).

---

## 16. Fichiers modifiés

Hors création : `src/app/router/AppRouter.tsx` (découpage par domaine),
`src/app/router/navigation.ts` (correction de `audit_logs.view` →
`audit.view`), `src/app/i18n/locales/fr.json`, `src/index.css` (jetons de
couleur de la barre latérale), `src/test/setup.ts` (MSW et i18n),
`vite.config.ts` (`import.meta.dirname`).

Aucun fichier backend n'a été modifié.

---

## 17. API manquantes

Trois manques ont été rencontrés. Aucun n'a été contourné par une simulation :
l'interface le dit.

**1. Aucun endpoint d'agrégation.** Les compteurs du tableau de bord viennent du
`meta.total` d'une page d'un seul élément par module — la requête la plus légère
donnant un total exact. Un endpoint dédié éviterait quatre requêtes.

**2. Documents d'une entité — toujours ouvert.** `GET /documents` n'accepte pas
`entityType` / `entityId`, et les routes de liaison partent du document, pas de
l'entité. L'onglet correspondant de la fiche client affiche donc un message
explicite plutôt qu'une liste vide qui laisserait croire à une absence de
données.

> **Fermé depuis, pour les adresses et les contacts.** `GET /addresses` et
> `GET /contacts` acceptent désormais `entityType` / `entityId`, et renvoient
> les liaisons qui portent le type — livraison, facturation — et le rôle du
> contact. L'onglet « Contacts » de la fiche client est devenu « Adresses » :
> le modèle rattache les contacts à une adresse, pas au client.
> Voir `docs/frontend/phase-1-entity-addresses.md`.

**3. L'email d'un membre n'est pas modifiable via `/organization-users`.**
`UpdateOrganizationUserRequest` ne l'accepte pas ; il relève de
`PATCH /users/{id}`. L'email est affiché en lecture dans le formulaire de
modification, pour qu'on sache de quel compte il s'agit.

---

## 18. Éléments exclus

- **Onglets de la fiche client réservés aux phases suivantes** : catalogues,
  commandes, stock, factures, réclamations, intégrations. Les afficher vides
  annoncerait des fonctionnalités absentes.
- **Tests E2E (§38)** : ni Playwright ni Cypress n'est configuré, et le §38 les
  conditionne à une installation préexistante.
- **Portails client, fournisseur et chauffeur** : hors du périmètre interne.

---

## 19. Risques

**Le bundle atteint 746 kB** (218 kB gzip) en un seul morceau. C'est acceptable
pour un back-office interne, mais le découpage par route deviendra nécessaire
quand les modules métier s'ajouteront.

**Le filtre d'audit sur `entityType` est un champ libre.** L'API attend un alias
du `MorphMap` (`customer`, `agency`, …) ; une saisie approximative ne renvoie
rien sans expliquer pourquoi. Une liste déroulante alimentée par l'API serait
préférable, mais aucune route ne publie les alias.

**La création d'un site client n'est pas transactionnelle.** L'adresse est créée
d'abord, le site ensuite ; si le second appel échoue, l'adresse subsiste,
rattachée au client mais sans site. C'est visible et corrigeable, contrairement
à un site sans adresse qui serait invalide — mais un endpoint créant les deux
d'un coup serait plus sûr.

**Une divergence entre permission frontend et backend est silencieuse.** Le cas
`audit_logs.view` l'a montré : l'entrée disparaissait sans erreur. Rien ne
vérifie automatiquement que les codes utilisés existent côté serveur.

---

## 20. Prochaine phase

1. Trancher la divergence de maquettes signalée au §13.
2. Décider des manques d'API restants du §17 — le filtre
   `entityType` / `entityId` sur `/documents`, qui débloque le dernier onglet
   construit sur un message d'indisponibilité.
3. Tests E2E si Playwright est installé.
4. Découpage du bundle par route avant l'arrivée des modules métier.

---

FRONTEND_PHASE_1_READY
