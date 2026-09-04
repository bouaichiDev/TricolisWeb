# Configuration de la plateforme

Les réglages de l'**installation**, par opposition à ceux d'un organisme. Un
seul aujourd'hui — le logo par défaut — et la page existe autant pour le
suivant.

---

## 1. Pourquoi une page, et pas un champ de plus

Les réglages de l'installation n'ont aucun autre endroit où aller. Les glisser
dans la fiche d'une organisation les rendrait introuvables : ils ne concernent
aucune organisation en particulier, et celui qui les cherche ne pense pas à
ouvrir un organisme au hasard pour les trouver.

D'où une entrée de menu de portée plateforme, à côté des organisations, des
statuts et des variables tarifaires — les trois autres référentiels qui
appartiennent à l'installation plutôt qu'à ses locataires.

---

## 2. Une ligne, des colonnes nommées

```
platform_settings
- id: ULID
- singleton: BOOLEAN, UNIQUE
- default_logo_path
- default_logo_mime_type
- createdAt / updatedAt
```

**`singleton` vaut toujours `true` et porte l'unicité.** Un booléen constant
paraît étrange ; c'est le seul moyen d'exprimer « au plus une ligne » dans une
contrainte, et une contrainte vaut mieux qu'une convention. Sans elle, deux
enregistrements concurrents en laisseraient deux en base, et la lecture prendrait
« le premier » — celui que l'ordre SQL veut bien rendre.

**Des colonnes nommées, pas un sac clé-valeur.** La tentation est réelle — « on
ajoutera des réglages sans migration » — et c'est précisément ce qu'on ne veut
pas : un JSON libre ne dit pas ce qu'il contient, ne se contraint pas, et se
découvre en lisant le code qui l'écrit. Un réglage de plus est une colonne de
plus et un champ de plus sur l'écran ; c'est le même raisonnement que pour le
logo d'une organisation (`organization-logo.md`, §3).

`PlatformSetting::current()` crée la ligne si elle manque. Elle n'est pas semée :
la créer d'avance obligerait à la recréer sur chaque base existante, pour un
enregistrement que la première lecture fabrique.

---

## 3. Lire est ouvert, écrire ne l'est pas

| Route | Qui |
| --- | --- |
| `GET /api/v1/configuration` | tout compte authentifié |
| `GET /api/v1/configuration/logo` | tout compte authentifié |
| `POST /api/v1/configuration/logo` | `platform_settings.update` |
| `DELETE /api/v1/configuration/logo` | `platform_settings.update` |

L'asymétrie a une raison précise : **la barre latérale de chacun** demande s'il
existe un logo par défaut. Protéger cette question obligerait à distribuer une
permission plateforme pour afficher une image de marque.

Les routes vivent **hors du middleware `organization`**, comme `GET /menu` : la
plateforme n'agit dans aucune organisation, et exiger l'en-tête interdirait
l'accès à un compte qui n'en a pas.

`PlatformSettingPolicy::update` passe par `hasPlatformPermission` et non
`hasPermission` : la seconde est bornée à une organisation. Un propriétaire
d'organisme, qui détient pourtant tout chez lui, n'a rien à décider ici — et
`platform_settings.update` figure dans `PlatformAccess::PLATFORM_PERMISSIONS`,
donc ne peut pas être déléguée à un rôle local.

Il n'y a **pas** de `platform_settings.view` : ce serait une permission que tout
le monde devrait avoir.

---

## 4. Le logo par défaut

C'est l'identité que pose un intégrateur sur l'outil qu'il revend, et que voient
les organisations qui n'ont pas encore la leur.

**Trois niveaux de repli**, dans la barre latérale :

```
le logo de l'organisation active
→ le logo par défaut de l'installation
→ l'icône livrée avec l'application
```

Deux endroits où il ne descend **pas**, et chacun pour une raison :

| Où | Pourquoi pas |
| --- | --- |
| Les factures PDF | `organization.logo` reste celui de l'émetteur. Y substituer celui de la plateforme mettrait la marque de l'éditeur sur la facture d'un transporteur — que personne ne verrait avant l'envoi au client |
| La liste des organisations | il rendrait toutes les lignes identiques, ce qui est le contraire de ce que la colonne sert à faire. Sans logo : l'icône neutre |

Le fichier vit sur le disque **`local`**, sous `platform-logo/`, et se sert par
une route authentifiée. Ce n'est pas un secret — c'est une image de marque —
mais deux façons de servir la même chose en feraient une à protéger et une à
oublier.

Les formats acceptés sont ceux du logo d'une organisation : PNG, JPEG, GIF, un
mégaoctet. Ce logo ne descend pourtant sur aucune facture, donc la contrainte du
moteur PDF ne le vise pas directement — elle le vise **indirectement** : un
intégrateur qui dépose ici son image la déposera demain sur ses organisations, et
lui laisser passer un SVG ici pour le lui refuser là serait la meilleure façon de
faire croire à un bug.

Le chemin ne sort jamais par l'API : `hasDefaultLogo`, un booléen. Le publier
révélerait la disposition du disque, et l'écran n'en a pas besoin.

---

## 5. Les fichiers

| Fichier | Rôle |
| --- | --- |
| `database/migrations/2026_09_05_110000_create_platform_settings_table.php` | la table, et sa ligne unique |
| `app/Modules/Platform/Models/PlatformSetting.php` | la ligne, créée à la première lecture |
| `app/Modules/Platform/Services/PlatformDefaultLogo.php` | poser, retirer, vérifier |
| `app/Http/Controllers/Api/V1/Platform/ConfigurationController.php` | les quatre routes |
| `app/Http/Requests/Api/V1/Platform/StorePlatformLogoRequest.php` | ce qu'on accepte de déposer |
| `app/Policies/PlatformSettingPolicy.php` | qui écrit |
| `frontend/src/modules/configuration/pages/ConfigurationPage.tsx` | l'écran |
| `frontend/src/shared/components/form/LogoField.tsx` | le geste, partagé avec l'organisation |
| `frontend/src/app/layouts/SidebarBrand.tsx` | les trois niveaux de repli |

---

## 6. Ajouter un réglage

```
1. une colonne dans une migration
2. le champ sur `PlatformSetting`
3. sa lecture dans `ConfigurationController::show()`
4. son champ sur `ConfigurationPage`
```

Aucune permission nouvelle : `platform_settings.update` couvre la page entière.
Découper plus finement demanderait une permission par réglage, pour un écran que
seul un administrateur plateforme atteint.

---

## 7. Ce que les tests tiennent

| Test | Ce qu'il empêche |
| --- | --- |
| `is open to any authenticated account` | une permission plateforme exigée pour afficher une image de marque |
| `answers without an organization header` | un compte plateforme enfermé dehors |
| `refuses an organization owner, however powerful` | un propriétaire d'organisme réglant l'outil des autres |
| `keeps the file off the public disk` | un logo servi de deux façons, dont une oubliée |
| `replaces the previous file rather than piling them up` | un disque qui grossit à chaque remplacement |
| `refuses a format the PDF engine cannot render` | un format accepté ici et refusé là |
| `never carries the file path` | la disposition du disque, publiée |
| `keeps a single row, whatever the number of writes` | deux configurations, dont une lue au hasard |
| `se replie sur le logo de l'installation quand l'organisation n'en a pas` | un repli qui ne se déclenche jamais |
