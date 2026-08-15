# Menu par organisation

Branche : `fix/phase-1-organization-roles-permissions`.

---

## 1. Le besoin

Chaque organisme veut son menu. Un transporteur qui n'utilise pas une fonction
n'a pas à en voir l'entrée — et ce choix ne peut pas vivre dans un fichier livré
à tout le monde.

---

## 2. La ligne de partage

**Le catalogue reste en code. Seules la visibilité et l'ordre sont en base.**

Ce n'est pas une demi-mesure, c'est ce que la nature des données impose :

| Donnée | Où | Pourquoi |
| --- | --- | --- |
| `route` | code | Doit exister dans le routeur React. Une route en base qui n'y correspond à rien donne « Page introuvable ». |
| `icon` | code | Une icône est un composant React. La base en stocke le **nom**, `menuIcons.ts` le résout. |
| `labelKey` | code | Une clé i18n. Stocker du texte perdrait la traduction. |
| `permission` | code | Doit exister dans le référentiel — c'est le bug `audit_logs.view`. |
| `isVisible` | **base** | Le choix de l'organisation. |
| `position` | **base** | Le choix de l'organisation. |

Laisser saisir une route ou une clé i18n ne rendrait pas le menu plus
configurable : cela rendrait possible d'en fabriquer un cassé.

---

## 3. Ce qui a été ajouté

### Backend

| Fichier | Rôle |
| --- | --- |
| `app/Shared/Menu/MenuEntry.php` | Une entrée du catalogue |
| `app/Shared/Menu/MenuCatalogue.php` | Le catalogue, source unique |
| `app/Modules/Organizations/Models/OrganizationMenuItem.php` | Réglage d'une organisation |
| `app/Modules/Organizations/Services/MenuResolver.php` | Compose le menu effectif |
| `app/Http/Controllers/Api/V1/Organizations/MenuController.php` | Les trois routes |
| `app/Http/Requests/Api/V1/Organizations/UpdateMenuRequest.php` | Visibilité et position seules |
| `app/Modules/Organizations/Actions/SyncOrganizationMenu.php` | Donne le menu de base à une organisation |
| `app/Console/Commands/SyncOrganizationMenus.php` | Met à jour les organisations existantes |
| `database/migrations/2026_08_13_110000_create_organization_menu_items_table.php` | La table |

```
GET   /api/v1/menu             menu effectif de l'appelant
GET   /api/v1/menu/catalogue   catalogue configurable      organizations.update
PATCH /api/v1/menu             visibilité et ordre         organizations.update
```

`GET /menu` est hors du middleware `organization` : un compte plateforme n'agit
dans aucune organisation, et exiger l'en-tête lui interdirait l'accès. Le
résolveur choisit le catalogue d'après la portée du compte.

### Chaque organisation reçoit le menu de base

`SyncOrganizationMenu` donne à une organisation une ligne pour chaque entrée du
catalogue. Il est appelé aux deux endroits où une organisation naît —
`POST /organizations` et l'inscription publique — dans la même transaction :
une organisation créée sans lui n'aurait rien à montrer dans son écran de
réglage, et son administrateur ne saurait pas quelles entrées existent.

**La règle qui rend l'ensemble tenable : créer les lignes manquantes, ne jamais
toucher aux existantes.** Deux exigences tirent en sens contraire —
l'administrateur doit voir le menu de base, ce qui suppose des lignes ; et une
entrée ajoutée à une phase suivante doit parvenir aux organisations déjà
créées, ce qu'un instantané figé empêcherait. L'action est donc rejouable, et
ce qu'une organisation a choisi de masquer le reste.

Le **repli du résolveur est conservé** : une entrée sans ligne reste visible.
C'est le filet de sécurité si la synchronisation est oubliée après une phase —
mieux vaut une entrée de trop qu'un écran devenu inatteignable.

### Frontend

| Fichier | Rôle |
| --- | --- |
| `src/modules/menu/types/menu.ts` | Type et reconstruction de l'arbre |
| `src/modules/menu/api/menu.api.ts` | Les trois appels |
| `src/modules/menu/hooks/useMenu.ts` | Lecture et réglage |
| `src/modules/menu/components/menuIcons.ts` | Nom d'icône → composant |
| `src/modules/menu/components/MenuSettingsPanel.tsx` | Écran de réglage |
| `src/app/layouts/NavGroup.tsx` | Groupe repliable, extrait |
| `src/app/layouts/AppSidebar.tsx` | Rend ce que l'API renvoie |

L'écran de réglage est sous **Mon organisation**, gardé par
`organizations.update` : régler le menu relève de l'administration de
l'organisation.

---

## 4. Les trois filtres du menu effectif

`MenuResolver::resolve()` applique, **dans cet ordre** :

1. **la portée du compte** — un compte plateforme reçoit le menu plateforme, pas
   le menu d'organisme expurgé : clients et agences appartiennent aux
   organismes ;
2. **les réglages de l'organisation** — ce qu'elle a choisi de masquer ;
3. **les permissions de l'utilisateur** — une entrée qu'il n'a pas le droit
   d'ouvrir ne lui est pas proposée.

L'ordre compte : masquer une entrée au niveau de l'organisation la retire pour
tout le monde, propriétaire compris, alors qu'une permission manquante ne
concerne qu'un utilisateur.

Un groupe dont plus aucun enfant ne subsiste est retiré : il afficherait un
titre vide, ce que le §10 interdit.

---

## 5. Les entrées qu'on ne peut pas masquer

`alwaysVisible` protège l'administration et « Mon organisation ». Sans cela, un
administrateur pourrait masquer les écrans qui permettent de revenir en
arrière — et n'aurait plus aucun moyen de se corriger depuis l'interface.

La demande est **ignorée**, pas refusée : la requête reste valide, c'est la
contrainte qui l'emporte. L'interrupteur est désactivé côté écran, et la raison
affichée plutôt que laissée à deviner.

---

## 6. Ce qui empêche le catalogue de dériver

| Test | Ce qu'il empêche |
| --- | --- |
| `names only permissions that exist` | Une entrée qui disparaît en silence — le bug `audit_logs.view` |
| `names only routes declared in the React router` | Une entrée qui mène à « Page introuvable » |
| `gives every child a parent that exists` | Un enfant orphelin |
| `keeps the setting inside its organization` | Un réglage qui déborde sur une autre organisation |
| `refuses to hide an entry the organization must keep` | Un organisme qui se coupe l'accès à son administration |
| `rejects a code that is not in the catalogue` | Une ligne orpheline en base |
| `drops a group left without any visible child` | Un groupe au titre vide |
| `leaves an existing choice untouched` | Une phase qui réinitialiserait les réglages d'une organisation |
| `adds an entry that appeared after the organization was created` | Une organisation ancienne privée des entrées d'une nouvelle phase |
| `never gives an organization a platform entry` | Une organisation croyant pouvoir régler une entrée plateforme |

Le test des routes lit `frontend/src/app/router/routes/*.tsx` plutôt qu'une
liste recopiée : une liste recopiée aurait le même défaut que ce qu'elle
prétend vérifier. Il se met en attente lorsque le frontend est absent de la
copie de travail.

---

## 7. Procédure à chaque nouvelle phase

Quand une phase ajoute des écrans, le menu suit en trois gestes :

```bash
# 1. déclarer les entrées dans app/Shared/Menu/MenuCatalogue.php
#    (route, icône, permission, section, position, parent)

# 2. vérifier que route et permission existent réellement
./vendor/bin/pest tests/Feature/Hardening/MenuPermissionConsistencyTest.php

# 3. donner les nouvelles entrées aux organisations déjà créées
php artisan tricolis:sync-organization-menus
```

La commande accepte `--organization=<code>` pour se limiter à une seule.

Elle est **rejouable** : elle ne crée que ce qui manque et ne réinitialise
jamais les choix d'une organisation. Une organisation qui a masqué « Dépôts »
la retrouve masquée après chaque phase.

Le catalogue ne doit contenir que des entrées **réellement atteignables**. Y
inscrire un module à venir proposerait un écran qui n'existe pas : le test des
routes l'interdit, et c'est pourquoi l'étape 2 précède l'étape 3.

---

## 8. Résultats

```
Backend   805 tests, 2639 assertions   — passent
Frontend  123 tests, 20 fichiers        — passent
```

Migrations : `2026_08_13_100000_add_menu_section_to_permissions.php`,
`2026_08_13_110000_create_organization_menu_items_table.php`.
