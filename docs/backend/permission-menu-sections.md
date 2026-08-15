# Sections de menu des permissions

Branche : `fix/phase-1-organization-roles-permissions`.

---

## 1. Le problème

Le référentiel compte **188 permissions réparties sur 48 modules**. Le
formulaire de rôle les groupait sur `module` : il présentait donc 48 blocs, dont
`tour_stop_services`, `provider_settlement_lines` et
`customer_export_configurations`. Composer un rôle là-dedans n'était pas
praticable.

`module` est une découpe **technique** — une table, un contrôleur. Un menu est
une découpe **métier**. Les deux ne se recouvrent pas : le menu compte une
dizaine d'entrées quand les modules en comptent 48.

---

## 2. La section, portée par la permission

`permissions.menu_section` porte la découpe métier. Dix valeurs, décrites par
`App\Shared\Enums\MenuSection` :

| Section | Contenu | Permissions |
| --- | --- | --- |
| `dashboard` | Tableau de bord | 1 |
| `customers` | Clients, sites, adresses, contacts, documents, catalogues | 24 |
| `resources` | Agences, dépôts, véhicules, chauffeurs, prestataires | 24 |
| `operations` | Commandes, services, colis, tournées, suivi, réclamations | 55 |
| `stock` | Articles, emplacements, mouvements, réservations | 15 |
| `billing` | Factures, règlements prestataires | 16 |
| `communications` | Modèles, règles, envois | 18 |
| `integrations` | API client, imports, exports | 16 |
| `administration` | Utilisateurs, rôles, audit, abonnement, mon organisation | 17 |
| `platform` | Créer et supprimer une organisation | 2 |

### Pourquoi sur la permission et non sur le module

Un module tombe en général tout entier dans une section — mais pas toujours.

```
organizations.view    → administration   consulter SON organisation
organizations.update  → administration
organizations.create  → platform         administrer la plateforme
organizations.delete  → platform
```

Même module, deux sections. Une colonne sur `permissions` capture cette
distinction ; une table `module → section` ne l'aurait pas pu.

Les rattacher au module aurait par ailleurs présenté « Créer une organisation »
à côté de « Modifier mon organisation », dont elle n'a ni la portée ni les
conséquences.

---

## 3. Où c'est décidé

`Database\Seeders\PermissionMenuMap` — deux niveaux :

- `BY_MODULE` couvre les 48 modules ;
- `BY_CODE` déclare les exceptions, et l'emporte.

Le référentiel de `PermissionSeeder` ne porte pas la section : elle est déduite.
La répéter sur 188 lignes inviterait à l'incohérence.

`PermissionSeeder` utilise désormais `updateOrCreate` et non `firstOrCreate` :
sur une base déjà semée, une ligne existante n'aurait pas reçu sa section, et le
formulaire n'aurait rien eu pour grouper. Reclasser une permission d'une section
à l'autre doit aussi pouvoir se rejouer.

---

## 4. Ce qui l'empêche de dériver

| Test | Ce qu'il empêche |
| --- | --- |
| `assigns a section to every permission` | Une permission sans section, invisible dans le formulaire |
| `assigns only known sections` | Une valeur hors énumération |
| `maps every module explicitly` | Un module oublié retombant **silencieusement** dans « Administration » |
| `separates platform permissions` | Le retour d'une permission plateforme parmi celles de l'organisme |
| `reapplies the section on an already seeded database` | Une base semée avant la migration restant sans sections |
| `keeps the sections few enough to be usable` | Le retour à un découpage trop fin |

La colonne est `nullable` — elle devait l'être pour que la migration passe sur
les lignes existantes. **C'est le test qui tient l'invariant**, pas le schéma.

---

## 5. Cohérence entre le menu et le référentiel

`tests/Feature/Hardening/MenuPermissionConsistencyTest.php` lit
`frontend/src/app/router/navigation.ts` et les gardes de
`frontend/src/app/router/routes/`, et vérifie que chaque permission citée existe
dans le référentiel.

Ce test répond à un défaut réel : le menu exigeait `audit_logs.view` quand le
code est `audit.view`. `has()` renvoyait `false`, l'entrée n'était jamais rendue,
et **aucune erreur n'était levée** — le journal d'audit était invisible pour
tout le monde, administrateurs compris.

Le test lit les fichiers plutôt qu'une liste recopiée : une liste recopiée
aurait le même défaut que ce qu'elle prétend vérifier. Il se met en attente
lorsque le frontend est absent de la copie de travail.

---

## 6. Frontend

`groupPermissionsByModule` devient `groupPermissionsBySection`. L'ordre des
sections suit `MENU_SECTION_ORDER`, calqué sur `MenuSection::position()` :
l'ordre alphabétique placerait « Administration » en tête, devant « Clients ».

À l'intérieur d'une section, l'ordre suit le module puis le libellé, pour que
les permissions d'un même sujet restent voisines.

Une permission sans section tombe dans « Autres » plutôt que de disparaître :
une permission invisible serait impossible à accorder, et personne ne saurait
pourquoi.

---

## 7. Étape suivante : le menu en base

Cette étape prépare la seconde sans la préjuger. Les sections définies ici
deviendraient les entrées racines d'une table `menu_items` :

```
menu_items
  id, parent_id, code, label_key, route, icon, position
  scope          platform | organization
  permission_id  contrôle la visibilité
```

Deux points à garder en tête avant de s'y engager :

- **le couplage au code ne disparaît pas, il se déplace.** Une icône est un
  composant React : la table stockerait un nom, résolu par une correspondance
  côté code. Une route doit exister dans le routeur — une route en base qui n'y
  correspond à rien donne « Page introuvable ». Un libellé doit rester une clé
  i18n, sinon la traduction est perdue ;
- **le gain réel est l'activation par organisation.** Un transporteur qui
  n'utilise pas le stock n'aurait pas à voir la section Stock. C'est le seul
  argument qui justifie la table ; l'ordre et le renommage se font aussi bien
  dans le code.

---

## 8. Résultats

```
Backend   780 tests, 2586 assertions   — passent
Frontend  118 tests, 20 fichiers        — passent
```

Migration : `2026_08_13_100000_add_menu_section_to_permissions.php`.
