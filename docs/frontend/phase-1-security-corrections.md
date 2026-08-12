# Correctifs de sécurité — Phase 1

## Séparation SuperAdmin plateforme / Administrateur d'organisme

Branche : `fix/phase-1-organization-roles-permissions`, partie de
`feature/frontend-phase-1-administration-customers`.

Aucune fusion vers `main`, aucun push.

---

## 1. Ce qui était réellement ouvert

Le prompt décrivait un bouton mal masqué. L'audit du code a montré plus large :
la protection n'existait pas du tout côté serveur.

| Constat | Fichier | Conséquence |
| --- | --- | --- |
| `create()` renvoyait `true` sans condition | `OrganizationPolicy` | **Tout compte authentifié** pouvait créer une organisation |
| `viewAny()` renvoyait `true` | `OrganizationPolicy` | Aucune borne de portée sur la liste |
| `delete()` reposait sur `is_owner` | `OrganizationPolicy` | Un organisme pouvait se supprimer, avec ses commandes, ses factures et son audit |
| Le rôle `admin` recevait `Permission::pluck('id')` | `RoleSeeder` | Chaque propriétaire détenait `organizations.create` |
| L'inscription publique aussi | `RegisterTransporter` | **S'inscrire suffisait** à obtenir le droit de créer des organisations |
| `scope` était accepté en entrée et écrit tel quel | `UpdateRoleRequest`, `RoleController` | Un administrateur pouvait poster `scope: platform` |
| `permissionIds` était accepté sans confrontation | `RoleController` | Un administrateur pouvait se fabriquer un rôle plus puissant que le sien |
| L'affectation ne vérifiait que l'organisation | `OrganizationUserController`, `CreateOrganizationMember` | Un rôle **système** — donc porteur de toutes les permissions — était attribuable |
| Aucune notion de SuperAdmin n'existait | — | Il n'y avait pas de niveau plateforme à protéger |

Le point 5 est le plus grave et n'était pas dans le périmètre décrit : il ne
demandait aucun accès préalable.

---

## 2. Le mécanisme retenu

Deux décisions, prises avec l'accord de l'utilisateur.

**`roles.organization_id` devient nullable.** Un rôle de portée plateforme
n'appartient à aucune organisation. Ce n'est pas un assouplissement de
contrainte : c'est la traduction exacte de la différence entre les deux niveaux,
et c'est ce qui rend un rôle plateforme structurellement inatteignable depuis
l'administration d'un organisme, où l'organisation active est toujours imposée
par l'en-tête `X-Organization-Id`.

Migration : `2026_08_12_100000_allow_platform_scoped_roles.php`. MySQL admet
plusieurs `NULL` dans un index unique, donc `unique(organization_id, code)`
continue d'interdire deux rôles de même code dans une organisation.

**`organizations.delete` rejoint le référentiel.** La permission était citée par
le prompt mais n'existait pas ; la suppression était gouvernée par `is_owner`.
Elle est ajoutée et classée réservée plateforme, ce qui ferme le trou avec le
même mécanisme que le reste plutôt qu'un cas particulier.

L'autorité plateforme se lit sur **un seul fait** : l'utilisateur détient-il un
rôle dont `scope = platform` ? Ni le code, ni le nom, ni `is_owner` n'entrent en
compte. C'est ce qui rend vaine la tentative de créer un rôle nommé
`SUPER_ADMIN`.

---

## 3. Fichiers modifiés — backend

### Créés

| Fichier | Rôle |
| --- | --- |
| `app/Shared/Enums/RoleScope.php` | `PLATFORM` / `ORGANIZATION`, sur la colonne `roles.scope` prévue par le diagramme |
| `app/Modules/Identity/Services/PlatformAccess.php` | Autorité plateforme et plafond de délégation |
| `app/Modules/Identity/Services/RoleAssignmentGuard.php` | Rôles attribuables à un membre |
| `database/migrations/2026_08_12_100000_allow_platform_scoped_roles.php` | `organization_id` nullable |
| `tests/Feature/Hardening/PrivilegeEscalationTest.php` | 20 tests d'attaque |

### Modifiés

| Fichier | Correction |
| --- | --- |
| `app/Policies/OrganizationPolicy.php` | `create` et `delete` plateforme uniquement ; `update` bornée à son organisation |
| `app/Policies/RolePolicy.php` | Trois conditions cumulatives ; `assign` distinct d'`update` |
| `app/Http/Controllers/Api/V1/Identity/RoleController.php` | Portée et drapeau système imposés ; plafond de délégation |
| `app/Http/Requests/Api/V1/Identity/StoreRoleRequest.php` | `scope`, `isSystem`, `organizationId` retirés des règles |
| `app/Http/Requests/Api/V1/Identity/UpdateRoleRequest.php` | Idem, plus `code` |
| `app/Http/Controllers/Api/V1/Identity/OrganizationUserController.php` | Garde d'affectation |
| `app/Http/Controllers/Api/V1/Identity/UserController.php` | Garde d'affectation |
| `app/Http/Controllers/Api/V1/Identity/PermissionController.php` | Réponse bornée à l'ensemble délégable |
| `app/Http/Controllers/Api/V1/Organizations/OrganizationController.php` | Liste globale réservée à la plateforme |
| `app/Http/Resources/Api/V1/Auth/UserResource.php` | `scope` et `isSystem` exposés sur chaque rôle |
| `app/Modules/Identity/Actions/CreateOrganizationMember.php` | Seconde barrière : local, non système |
| `app/Modules/Identity/Actions/RegisterTransporter.php` | Permissions plateforme écartées à l'inscription |
| `database/seeders/PermissionSeeder.php` | `organizations.delete` |
| `database/seeders/RoleSeeder.php` | Rôle plateforme ; retrait des permissions plateforme du rôle `admin` |

---

## 4. OrganizationPolicy

| Action | SuperAdmin plateforme | Admin / Propriétaire d'organisme |
| --- | --- | --- |
| `viewAny` | oui — toutes | oui — le contrôleur borne à ses rattachements |
| `view` | n'importe laquelle | la sienne |
| `create` | avec `organizations.create` | **non** |
| `update` | n'importe laquelle | la sienne, avec la permission ou la qualité de propriétaire |
| `delete` | avec `organizations.delete` | **non** |

`viewAny` reste autorisé pour tous, délibérément : cette liste sert à choisir son
organisation active. Le bornage est fait dans le contrôleur, pas dans la policy.

---

## 5. RolePolicy

Un administrateur d'organisme n'agit que sur un rôle remplissant les trois
conditions **à la fois** :

```
role.organization_id == organisation active
role.scope           == organization
role.is_system       == false
```

Chacune ferme une voie distincte : la première interdit d'agir sur
l'organisation d'un tiers, la deuxième d'atteindre un rôle plateforme, la
troisième de modifier un rôle livré avec l'application — dont `admin`, qui porte
toutes les permissions de l'organisation.

L'ordre des refus compte : l'appartenance est vérifiée en premier, et un rôle
d'une autre organisation se présente comme **absent** (404) et non comme
interdit (403). La différence entre les deux réponses confirmerait son
existence.

`assign` est une capacité distincte d'`update` : attribuer ne modifie pas le
rôle, mais transmet ses permissions.

---

## 6. Logique de permissions et de délégation

**Permissions réservées à la plateforme** — `PlatformAccess::PLATFORM_PERMISSIONS` :

```
organizations.create
organizations.delete
```

Ce sont les seuls codes existants qui confèrent un pouvoir dépassant un
organisme. Aucune permission n'a été inventée ; `platform.*`, `superadmin.*` et
`system.*` cités par le prompt n'existent pas dans le référentiel.

**Plafond de délégation** — `delegablePermissionCodes()` :

1. le référentiel, moins les permissions réservées à la plateforme ;
2. intersecté avec ce que l'utilisateur détient dans l'organisation active ;
3. un propriétaire obtient l'ensemble organisationnel — il détient déjà tous les
   droits de son organisation, mais jamais ceux de la plateforme ;
4. un administrateur plateforme échappe au retrait du point 1.

Ce plafond est appliqué en deux endroits :

- `GET /permissions` ne renvoie que cet ensemble — le formulaire ne peut donc
  pas proposer ce qui est interdit, et « Tout cocher » ne peut pas le
  sélectionner ;
- `POST`/`PATCH /roles` confronte chaque `permissionIds` à cet ensemble et
  répond 422 sur la clé `permissionIds` en cas d'écart.

Le second contrôle n'est pas redondant : le premier borne l'affichage, le second
borne l'écriture. Une requête forgée n'a pas besoin du premier.

---

## 7. Rôles système protégés

`is_system = true` interdit, pour un administrateur d'organisme :

```
modifier le code, le nom, la portée, le drapeau système, les permissions
supprimer le rôle
attribuer le rôle — à soi-même ou à un tiers
```

L'attribution est traitée à part parce qu'elle contourne autrement le plafond de
délégation : le rôle `admin` porte toutes les permissions de l'organisation, et
l'attribuer transmettrait des droits que l'attribuant ne détient pas
nécessairement.

Côté interface, un rôle système porte deux marques — « Système » et « Lecture
seule » — et n'offre ni lien de modification, ni bouton de suppression.

---

## 8. Routes protégées

`ProtectedRoute` et `PermissionGuard` reçoivent `platformOnly`. Le drapeau est
distinct de la permission, et il le fallait : `organizations.view` est légitime
pour un administrateur d'organisme, qui ne doit pourtant pas atteindre
l'annuaire global.

```
/organizations              organizations.view    + plateforme
/organizations/create       organizations.create  + plateforme
/organizations/:id          organizations.view    + plateforme
/organizations/:id/edit     organizations.update  + plateforme
/my-organization            organizations.view
/roles, /roles/:id/edit     inchangées — la protection est dans la page et l'API
```

Sans `platformOnly`, masquer le bouton laissait croire la route protégée : saisir
`/organizations/create` dans la barre d'adresse ouvrait le formulaire. Le backend
refusait déjà l'envoi, mais l'utilisateur atteignait un écran voué à l'échec.

`/my-organization` réutilise `GET /organizations/{id}` avec l'identifiant de
l'appartenance active — **aucun endpoint nouveau**. L'identifiant ne vient jamais
de l'URL, ce qui interdit d'atteindre l'organisation d'un tiers en modifiant
l'adresse.

---

## 9. Menu corrigé

| SuperAdmin | Admin / Propriétaire |
| --- | --- |
| Organisations | Mon organisation |
| Utilisateurs | Utilisateurs |
| Rôles | Rôles |
| Journal d'audit | Journal d'audit |

Les deux entrées sont mutuellement exclusives, portées par `platformOnly` et
`organizationOnly` dans `navigation.ts`. Un groupe dont aucune entrée n'est
visible disparaît entièrement, titre compris.

`OrganizationSwitcher` est inchangé : il n'affichait déjà que les appartenances
réelles, et n'a jamais permis de saisir un identifiant. Avec une seule
appartenance il redevient un simple libellé.

---

## 10. Cache TanStack Query

Au changement d'organisation, le cache est **retiré**, plus seulement invalidé :

```ts
queryClient.removeQueries({ predicate: (q) => q.queryKey[0] !== 'auth' })
void queryClient.invalidateQueries({ queryKey: ['auth', 'me'] })
```

Une invalidation marque les données périmées mais les laisse affichées pendant
le rechargement — et `placeholderData: (previous) => previous`, que portent
toutes les listes, les y maintiendrait activement. L'utilisateur voyait les
clients de l'organisation qu'il venait de quitter.

L'identité est conservée puis invalidée : la vider déconnecterait l'utilisateur
le temps du rechargement. Les rôles et les permissions arrivent avec la réponse
de `/auth/me`, puisqu'ils sont portés par l'appartenance.

---

## 11. Fichiers modifiés — frontend

| Fichier | Correction |
| --- | --- |
| `src/shared/types/auth.ts` | `scope` et `isSystem` sur `AuthRole` ; `isPlatformAdmin` |
| `src/app/providers/AuthProvider.tsx` | Calcul de `isPlatformAdmin` ; retrait du cache au changement |
| `src/shared/hooks/usePermission.ts` | `isPlatformAdmin` exposé |
| `src/app/guards/ProtectedRoute.tsx` | `platformOnly` |
| `src/app/guards/PermissionGuard.tsx` | `platformOnly` ; `permission` devient facultative |
| `src/app/router/navigation.ts` | Entrées Organisations / Mon organisation |
| `src/app/layouts/AppSidebar.tsx` | Filtrage par portée |
| `src/app/router/routes/guarded.tsx` | Option `platformOnly` |
| `src/app/router/routes/adminRoutes.tsx` | Routes organisations réservées ; `/my-organization` |
| `src/modules/organizations/pages/MyOrganizationPage.tsx` | **créé** |
| `src/modules/organizations/pages/OrganizationDetailPage.tsx` | Réutilisable ; suppression réservée |
| `src/modules/roles/components/RoleForm.tsx` | Portée retirée de la saisie, affichée en lecture |
| `src/modules/roles/schemas/roleSchema.ts` | `scope` retiré |
| `src/modules/roles/api/roles.api.ts` | `scope` et `isSystem` retirés des charges utiles |
| `src/modules/roles/types/role.ts` | `scope` typé ; `isEditableRole()` |
| `src/modules/roles/pages/RoleCreatePage.tsx` | N'envoie plus portée ni drapeau système |
| `src/modules/roles/pages/RoleEditPage.tsx` | Rôle système en lecture seule |
| `src/modules/roles/pages/RoleDetailPage.tsx` | Ni modification ni suppression sur un rôle verrouillé |
| `src/modules/roles/pages/RoleListPage.tsx` | Marques « Système », « Lecture seule », « Plateforme » |
| `src/modules/users/components/RoleAssignment.tsx` | Rôles système et plateforme écartés |
| `src/app/i18n/locales/fr.json` | Libellés ; compteur de permissions corrigé |

---

## 12. Tests frontend

23 tests ajoutés, **87 au total**, 16 fichiers.

| Fichier | Couverture |
| --- | --- |
| `src/app/guards/PlatformScope.test.tsx` | SuperAdmin voit l'action ; admin local ne la voit pas malgré la permission ; un rôle nommé « SuperAdmin » n'accorde rien ; `/organizations/create` refusée en accès direct |
| `src/app/layouts/AppSidebarScope.test.tsx` | Organisations vs Mon organisation ; cible `/my-organization` ; aucune sans la permission |
| `src/modules/roles/components/RoleForm.test.tsx` | Aucune portée saisissable ; aucun champ système ; portée et organisation en lecture ; charge utile sans portée ; permissions bornées à la réponse de l'API ; « Tout cocher » ; compteur |
| `src/modules/roles/pages/SystemRoleLock.test.tsx` | Rôle local modifiable ; rôle système sans action ; page d'édition refusée et expliquée |
| `src/modules/users/components/RoleAssignment.test.tsx` | Rôles système et plateforme écartés ; message quand il n'en reste aucun |

---

## 13. Tests backend

20 tests dans `tests/Feature/Hardening/PrivilegeEscalationTest.php`. Ils forgent
la requête HTTP, comme le ferait quelqu'un ayant lu le code du frontend.

L'utilisateur de test est **propriétaire** de son organisation — le profil le
plus favorable côté local, puisque `hasPermission()` accorde tout à un
propriétaire. S'il ne peut pas s'élever, personne d'un rang inférieur ne le peut.

**Organisations** — création refusée ; modification d'une organisation étrangère
refusée ; liste bornée pour un local, complète pour la plateforme.

**§30, requête forgée** — `{"code": "SUPER_ADMIN", "name": "SuperAdmin",
"scope": "PLATFORM", "isSystem": true}` crée un rôle **local et non système** :
`scope` et `isSystem` sont absents des règles de validation, donc de
`validated()`, donc de la base. Le rôle ainsi obtenu ne confère aucune autorité
plateforme — vérifié par un second appel qui échoue.

**§31, permission plateforme** — refusée à la création comme à la modification
d'un rôle local ; absente de `GET /permissions` pour un local, présente pour la
plateforme ; une permission que l'auteur ne détient pas est refusée elle aussi.

**§32, rôle d'une autre organisation** — refusé. De même pour le rôle plateforme,
y compris attribué à soi-même, et pour un rôle système.

**Rôles semés** — le rôle `admin` ne porte plus les permissions plateforme ; il
existe exactement un rôle plateforme, sans organisation, **attaché à personne**.

Ce dernier point est délibéré : désigner un administrateur de plateforme est une
décision d'exploitation. Le rattacher automatiquement au premier compte venu
recréerait exactement le problème corrigé.

### Tests existants corrigés

Trois tests affirmaient le comportement désormais interdit. Ils ont été
**réécrits**, jamais désactivés :

- `OrganizationTest` — « crée une organisation » exige maintenant l'autorité
  plateforme ; « supprime une organisation » aussi, et un test affirme qu'un
  propriétaire ne peut pas supprimer la sienne ;
- `IdentityManagementTest` — l'attribution utilise un rôle local ordinaire, plus
  le rôle `admin` système ; la clé de validation est `roleIds` et non
  `roleIds.0`, la vérification étant faite en bloc ;
- `RegisterTest` et `PermissionTest` — décomptes ajustés, et une assertion
  supplémentaire vérifie qu'un compte inscrit ne reçoit aucune permission
  plateforme.

---

## 14. Résultats

```
Backend   758 tests, 2527 assertions   — passent
Frontend   87 tests, 16 fichiers       — passent
```

`pint` : conforme. `npm run typecheck` : aucune erreur. `npm run lint` : aucune
erreur, 5 avertissements `only-export-components` sur des fichiers `shadcn/ui`
non modifiés. `npm run build` : succès.

Aucun fichier ne dépasse 200 lignes.

**Échec intermittent sans rapport** : `DocumentLinkTest` peut échouer sur
`Incorrect datetime value` — une date aléatoire de `faker` tombant dans un trou
de changement d'heure, que MySQL refuse. Antérieur à ces correctifs, dans un
module non touché, et il repasse à la relance. À corriger dans la fabrique de
`Document`, hors périmètre de cette phase.

---

## 15. Critères du §35

| Critère | État |
| --- | --- |
| SuperAdmin administre la plateforme | oui |
| Admin local administre seulement son organisation | oui |
| Admin local ne peut pas créer d'organisation | oui — 403 |
| Admin local ne voit pas « Nouvelle organisation » | oui |
| Admin local ne peut pas créer de rôle plateforme | oui — la portée est imposée |
| Admin local ne peut pas créer de rôle système | oui — le drapeau est imposé |
| Admin local ne peut pas modifier SuperAdmin | oui — 404, le rôle lui est invisible |
| Admin local ne peut pas s'affecter SuperAdmin | oui — 422 |
| Admin local ne peut pas déléguer une permission plateforme | oui — 422 |
| Admin local ne peut pas accéder aux rôles d'une autre organisation | oui — 404 |
| Protections côté frontend **et** backend | oui |
| Tous les tests passent | oui |

---

## 16. Ce qui reste à décider

**Aucun administrateur de plateforme n'existe.** Le rôle `superadmin` est semé
mais n'est attribué à personne. C'est volontaire — voir §13 — mais cela signifie
qu'aujourd'hui **plus personne ne peut créer d'organisation** par l'API, hors
inscription publique. Deux voies :

1. attacher le rôle à un compte par une commande Artisan dédiée ;
2. l'attacher directement en base pour le compte d'exploitation.

La première est préférable si plusieurs environnements sont concernés. Dites-moi
laquelle vous voulez et je la livre.

**L'inscription publique reste ouverte.** `POST /auth/register` crée toujours une
organisation et son propriétaire, sans autorité plateforme. C'est cohérent avec
un produit en libre-service, mais si Tricolis doit valider chaque transporteur,
cette route relève d'une décision produit qui n'est pas dans le périmètre de ces
correctifs.
