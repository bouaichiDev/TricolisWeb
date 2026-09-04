# Menu par rôle — carte des fichiers

Compagnon de `role-menu.md`, qui porte le raisonnement. Celui-ci dit **où** les
choses vivent, ce qui les empêche de dériver, et ce qu'il faut faire à chaque
nouvelle phase.

---

## 1. Les routes

```
GET   /api/v1/menu                            menu servi à l'appelant
GET   /api/v1/roles/{role}/menu               réglage du rôle          roles.update
PATCH /api/v1/roles/{role}/menu               visibilité, ordre, nom,
                                              icône, rattachement      roles.update
POST  /api/v1/roles/{role}/menu/groups        créer un groupe          roles.update
DEL   /api/v1/roles/{role}/menu/groups/{code} supprimer un groupe créé roles.update
```

`GET /menu` est hors du middleware `organization` : un compte plateforme n'agit
dans aucune organisation, et exiger l'en-tête lui interdirait l'accès.

L'écran unique est sur la **fiche du rôle**.

### Qui a le droit

`RolePolicy::update` refuse le rôle `admin` : il porte toutes les permissions, et
les toucher ouvrirait une voie d'élévation. **Son menu, lui, se règle.** Il ne
porte rien de tel — il range des écrans, il n'en ouvre aucun — et l'interdire
privait l'administrateur du seul menu qu'il voit lui-même.

D'où deux autorisations distinctes :

| Ability | Ce qu'elle garde | Rôle système |
| --- | --- | --- |
| `update` | Le jeu de permissions | refusé |
| `updateMenu` | Rien de sensible : l'appartenance à l'organisation et la portée | **accepté** |
| `view` | La lecture, y compris celle du menu | accepté |

Lire le menu demande `view` : exiger `update` le rendait inaffichable sur le
rôle `admin`. Reste exclu le rôle de portée **plateforme** — il n'appartient pas
à l'organisation, et son menu se consulte sans se régler.

---

## 2. Backend

| Fichier | Rôle |
| --- | --- |
| `app/Shared/Menu/MenuEntry.php` | Une entrée du catalogue |
| `app/Shared/Menu/MenuCatalogue.php` | Le catalogue, source unique |
| `app/Shared/Menu/MenuIcons.php` | Icônes offertes au choix, miroir de `menuIcons.ts` |
| `app/Shared/Menu/MenuCodes.php` | Réunion des codes livrés et des codes créés |
| `app/Modules/Identity/Models/RoleMenuItem.php` | Réglage d'un rôle sur une entrée |
| `app/Modules/Identity/Models/RoleMenuGroup.php` | Un groupe créé, et son code préfixé |
| `app/Modules/Identity/Services/RoleMenuOverrides.php` | Les réglages d'un rôle et leurs replis |
| `app/Modules/Identity/Services/RoleMenuGroups.php` | Les groupes créés, vus comme des entrées |
| `app/Modules/Identity/Services/RoleMenuCatalogue.php` | Ce que l'écran de réglage affiche |
| `app/Modules/Identity/Services/UserRoleMenus.php` | Rôle principal et union de visibilité |
| `app/Modules/Identity/Actions/SaveRoleMenuGroupSettings.php` | Réglages d'un groupe créé |
| `app/Modules/Organizations/Services/MenuResolver.php` | Compose le menu servi |
| `app/Http/Controllers/Api/V1/Organizations/MenuController.php` | `GET /menu`, en lecture seule |
| `app/Http/Controllers/Api/V1/Identity/RoleMenuController.php` | Lecture et réglage du menu d'un rôle |
| `app/Http/Controllers/Api/V1/Identity/RoleMenuGroupController.php` | Naissance et mort d'un groupe |
| `app/Http/Requests/Api/V1/Identity/UpdateRoleMenuRequest.php` | Ce qu'on accepte de régler |
| `app/Http/Requests/Api/V1/Identity/StoreRoleMenuGroupRequest.php` | Un nom, une icône |

---

## 3. Frontend

| Fichier | Rôle |
| --- | --- |
| `src/modules/menu/types/menu.ts` | Type et reconstruction de l'arbre |
| `src/modules/menu/types/menuOrder.ts` | Déplacement parmi les frères, rattachement, renumérotation |
| `src/modules/menu/hooks/useMenu.ts` | Le menu servi |
| `src/modules/menu/hooks/useMenuDraft.ts` | Brouillon : un seul enregistrement pour tous les gestes |
| `src/modules/menu/components/menuIcons.ts` | Nom d'icône → composant, et liste offerte au choix |
| `src/modules/menu/components/MenuSettingsPanel.tsx` | **L'écran de réglage, unique** |
| `src/modules/menu/components/MenuSettingsRow.tsx` | Une entrée : flèches, personnalisation, interrupteur |
| `src/modules/menu/components/MenuEntryDialog.tsx` | Renommer, réillustrer, rattacher |
| `src/modules/menu/components/MenuGroupDialog.tsx` | Création d'un groupe |
| `src/modules/menu/components/MenuIconGrid.tsx` | Choix de l'icône, liste fermée |
| `src/modules/menu/components/MenuParentSelect.tsx` | Groupe d'accueil, ou premier niveau |
| `src/modules/roles/api/roleMenu.api.ts` | Les quatre appels |
| `src/modules/roles/hooks/useRoleMenu.ts` | Lecture et écritures |
| `src/modules/roles/pages/RoleDetailPage.tsx` | Porte l'écran de réglage |
| `src/app/layouts/AppSidebar.tsx` | Rend ce que l'API renvoie |
| `src/app/layouts/NavGroup.tsx` | Groupe repliable, extrait |
| `src/app/layouts/Breadcrumbs.tsx` | Nomme le premier segment d'après le menu servi |

Le panneau vit dans `modules/menu` bien qu'il règle un rôle : il assemble les
composants du menu, et les déplacer dans `modules/roles` séparerait la liste de
ses pièces.

---

## 4. Ce qui empêche le catalogue de dériver

| Test | Ce qu'il empêche |
| --- | --- |
| `names only permissions that exist` | Une entrée qui disparaît en silence — le bug `audit_logs.view` |
| `names only routes declared in the React router` | Une entrée qui mène à « Page introuvable » |
| `gives every child a parent that exists` | Un enfant orphelin |
| `offers exactly the icons the frontend can render` | Une icône acceptée qui retomberait sur l'icône neutre |
| `only names icons the frontend can render in the catalogue` | Une entrée livrée déjà sans son icône |
| `keeps the setting inside its role` | Un réglage qui déborde sur un autre rôle |
| `takes the naming from the role whose code comes first` | Un départage instable entre rôles cumulés |
| `keeps an entry a second role still shows` | Un ajout de rôle qui retirerait un écran |
| `lets the system role menu be set, though the role itself cannot` | Un administrateur privé du seul menu qu'il voit |
| `locks only my-organization, whatever the role` | Un verrou qui gênerait au lieu de protéger |
| `lets an ordinary role hide the administration` | Le même, vu du rôle ordinaire |
| `still offers an entry the role has hidden` | Un masquage irréversible |
| `drops a group left without any visible child` | Un groupe au titre vide |
| `refuses to nest a group inside another group` | Un troisième niveau que rien n'affiche |
| `stops overriding when the catalogue parent is chosen again` | Un rattachement figé qui ne suivrait plus le catalogue |
| `falls back to the catalogue parent when the chosen group is gone` | Une promotion que personne n'a demandée |
| `names the group itself, outside the catalogue namespace` | Un code créé qui heurterait un code livré |
| `stays out of the sidebar while it is empty` | Un titre repliable qui n'ouvre rien |
| `clears the parent of the entries it held` | Une référence vers un groupe supprimé |
| `never reaches a group of another role` | Un groupe supprimé chez le voisin |
| `keeps its name when the field arrives empty` | Un groupe au titre vide, introuvable pour le corriger |

Les tests d'icônes et de routes lisent `menuIcons.ts` et
`frontend/src/app/router/routes/*.tsx` plutôt qu'une liste recopiée : une liste
recopiée aurait le défaut qu'elle prétend vérifier. Ils se mettent en attente
lorsque le frontend est absent de la copie de travail.

**Où sont les tests.** Le menu servi : `Organizations/MenuTest.php`. Le réglage,
sous `Identity/` : `RoleMenuTest` (visibilité), `RoleMenuNamingTest` (nom et
icône), `RoleMenuNestingTest` (rattachement), `RoleMenuGroupTest` et
`RoleMenuGroupBehaviourTest` (groupes créés), `RoleMenuResolutionTest` (cumul de
rôles).

---

## 5. Procédure à chaque nouvelle phase

Quand une phase ajoute des écrans, le menu suit en deux gestes :

```bash
# 1. déclarer les entrées dans app/Shared/Menu/MenuCatalogue.php
#    (route, icône, permission, section, position, parent)
#    Une icône nouvelle s'ajoute AUX DEUX listes : menuIcons.ts et MenuIcons.php
#    Une section nouvelle s'ajoute AUX TROIS match de MenuSection (sans défaut)

# 2. vérifier que route, permission et icône existent réellement
./vendor/bin/pest tests/Feature/Hardening/MenuPermissionConsistencyTest.php
./vendor/bin/pest tests/Feature/Hardening/MenuIconConsistencyTest.php
```

**Aucune synchronisation à lancer.** L'absence de ligne vaut « valeurs par
défaut » : une entrée ajoutée au catalogue apparaît chez tous les rôles sans
migration de données, et l'écran de réglage la propose aussitôt. C'est ce qui a
permis de retirer `SyncOrganizationMenu` et sa commande — elles n'existaient que
pour peupler un écran qui lisait la base plutôt que le catalogue.

Le catalogue ne doit contenir que des entrées **réellement atteignables**. Y
inscrire un module à venir proposerait un écran qui n'existe pas ; le test des
routes l'interdit.

---

## 6. Migrations

```
2026_08_13_100000_add_menu_section_to_permissions.php
2026_08_13_110000_create_organization_menu_items_table.php
2026_09_02_130000_add_label_and_icon_to_organization_menu_items.php
2026_09_03_100000_add_parent_override_to_organization_menu_items.php
2026_09_03_110000_create_role_menu_items_table.php
2026_09_03_120000_create_organization_menu_groups_table.php
2026_09_04_100000_move_menu_settings_to_roles.php
```

La dernière descend le réglage de l'organisation vers le rôle et **copie ce qui
existait** : chaque rôle reçoit le menu que son organisation avait composé, à
l'identique, puis diverge à mesure qu'on le règle. Son `down()` rend les tables,
pas leur contenu — répartir sur une organisation ce que plusieurs rôles ont réglé
différemment demanderait de choisir lequel a raison.
