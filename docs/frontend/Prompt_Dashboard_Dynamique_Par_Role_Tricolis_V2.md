# Tricolis V2 — PROMPT MASTER — DASHBOARD DYNAMIQUE PAR RÔLE

## Objectif
Transformer le Dashboard actuel (cards fixes Clients / Agences / Utilisateurs / Rôles) en **Dashboard métier configurable par rôle**.

Le besoin :

```text
Admin autorisé
→ Administration
→ Rôles
→ choisir un rôle
→ onglet Dashboard
→ choisir ce que ce rôle voit
→ sauvegarder

Utilisateur
→ se connecte dans une Organization
→ ses rôles sont résolus via OrganizationUser/UserRole
→ Dashboard = widgets configurés pour ses rôles
              INTERSECTION
              permissions effectives
```

La configuration Dashboard **ne donne jamais une permission**.

---

# 1. Sources de vérité

Respecter dans cet ordre :

```text
1. Schéma DB réel validé
2. Backend final réel
3. Diagrammes officiels
4. Frontend final Phases 1→10
5. Documentation
```

Avant de coder, inspecter :

```text
Role
Permission
OrganizationUser
UserRole
RolePermission
PermissionSeeder
Policies
Dashboard actuel
MenuCatalogue
API Resources
```

Ne pas inventer silencieusement une structure déjà existante.

---

# 2. Sécurité existante à respecter

Le projet utilise :

```text
Organization
OrganizationUser
Role
Permission
UserRole
RolePermission
```

Un utilisateur peut posséder plusieurs rôles dans une Organization via `OrganizationUser`.

Ne jamais faire :

```ts
if (role.name === 'Admin')
if (role.name === 'Planner')
```

La sécurité repose sur :

```text
Organization active
Role.id
permissions effectives
Policies
scope réel du rôle
```

La permission `dashboard.view` existe déjà : la conserver pour l'accès au Dashboard.

Si aucune permission équivalente n'existe pour la configuration, ajouter après audit :

```text
dashboard.configure
```

Ne pas attribuer cette permission automatiquement à tous les rôles.

---

# 3. Règle fondamentale

```text
WIDGET VISIBLE
=
widget activé pour au moins un rôle de l'utilisateur
AND
permission requise présente dans les permissions effectives
AND
Organization/scope valide
```

Exemple :

```text
Role Bureau
Dashboard config : customers_count = ON
Permission customers.view = absente

Résultat : customers_count ABSENT
```

Le backend ne doit même pas retourner la valeur du widget.

---

# 4. Évolution de conception

Le besoin est explicitement une configuration **par rôle**.

Avant migration, vérifier si une structure équivalente existe déjà.

Sinon mettre à jour le diagramme officiel puis ajouter :

```text
RoleDashboardConfiguration
```

Relation :

```text
Role "1" -- "0..1" RoleDashboardConfiguration
```

Modèle recommandé :

```text
RoleDashboardConfiguration
- id: ULID
- roleId: ULID
- widgets: JSON
- createdAt: datetime
- updatedAt: datetime
```

Table :

```text
role_dashboard_configurations
```

Contrainte :

```text
UNIQUE(role_id)
```

Ne pas ajouter une configuration par User.

Ne pas ajouter `status` ou `status_id`.

Si Role est déjà scoped Organization, ne pas dupliquer `organization_id` ici sans raison validée.

---

# 5. Contenu `widgets`

Exemple :

```json
[
  {"key":"orders_to_plan","position":1},
  {"key":"tours_today","position":2},
  {"key":"open_claims","position":3}
]
```

Le JSON contient seulement :

```text
widget key validée
position
```

Interdit d'y stocker :

```text
SQL
PHP class name fourni par l'admin
React component name
endpoint arbitraire
JavaScript
expression exécutable
```

---

# 6. Ne pas créer une table dashboard_widgets

Les widgets disponibles sont un **catalogue applicatif versionné dans le code**.

Créer côté backend :

```text
DashboardWidgetRegistry
```

La DB stocke seulement la sélection par Role.

Chaque définition de widget contient conceptuellement :

```text
key
label
description
category
type
requiredPermission
defaultPosition
defaultSize
scope
```

Exemple :

```text
customers_count
label = Clients
category = administration
type = KPI
requiredPermission = customers.view
scope = ORGANIZATION
```

---

# 7. Types de widgets

Supporter :

```text
KPI
CHART
LIST
ALERT
QUICK_ACTIONS
```

Le frontend rend uniquement ces types contrôlés.

Ne jamais envoyer un nom de composant React depuis la DB.

---

# 8. Catalogue initial — Administration

Selon permissions/routes réelles :

```text
customers_count
agencies_count
users_count
roles_count
providers_count
drivers_count
vehicles_count
```

Exemples de permission :

```text
customers_count → customers.view
agencies_count  → agencies.view
users_count     → users.view
roles_count     → roles.view
```

Toujours vérifier `PermissionSeeder` réel.

---

# 9. Catalogue — Exploitation

Widgets possibles si les APIs réelles le permettent :

```text
orders_today
orders_to_plan
orders_in_progress
orders_completed_today
services_ready_to_plan
services_in_progress
services_failed
recent_orders
orders_by_status
```

---

# 10. Catalogue — Planning

```text
tours_today
draft_tours
planned_tours
tours_in_progress
completed_tours_today
unplanned_services
recent_tours
tours_by_status
services_without_gps
```

Ne pas inventer DriverAvailability/VehicleAvailability si ces concepts n'existent pas.

---

# 11. Catalogue — Claims / POD

```text
open_claims
claims_created_today
recent_claims
pod_created_today
services_without_pod
```

Uniquement si calculable proprement avec le backend réel.

---

# 12. Catalogue — Facturation

```text
prebilling_services
draft_invoices
closed_invoices_today
closed_invoices_period_total
invoices_by_status
recent_invoices
draft_provider_settlements
recent_provider_settlements
```

Attention devise :

```text
ne jamais sommer CHF + EUR + MAD dans une seule valeur
```

Si plusieurs monnaies : grouper par `currencyCode` ou masquer le total unique.

---

# 13. Catalogue — Stock

```text
stock_items_count
stock_total_quantity
stock_reserved_quantity
stock_available_quantity
active_stock_reservations
recent_stock_movements
```

Ne pas inventer `low_stock` si aucun seuil n'existe.

---

# 14. Catalogue — Communications

```text
communications_scheduled
communications_failed
communications_sent_today
recent_communications
```

Utiliser les vrais statuses `order_communications`.

---

# 15. Catalogue — Intégrations

```text
export_jobs_failed
export_jobs_pending
exports_sent_today
recent_export_jobs
active_api_configurations
active_export_configurations
```

Ne jamais exposer secret, password, hash ou storagePath.

---

# 16. Quick actions

Actions possibles :

```text
new_order
new_invoice
open_planning
new_stock_movement
new_claim
new_communication_rule
```

Chaque action a sa permission requise.

Exemple :

```text
new_order → orders.create
```

---

# 17. Configuration impossible sans permission du rôle

Dans l'écran Role Dashboard :

```text
Widget Factures brouillon
requiredPermission = invoices.view
```

Si le rôle n'a pas cette permission :

```text
switch disabled
texte : Permission requise : invoices.view
```

Ne jamais ajouter la permission automatiquement depuis cet écran.

---

# 18. Changement des permissions après configuration

Si un rôle avait :

```text
customers_count configuré
customers.view présent
```

puis l'admin retire `customers.view` :

```text
customers_count disparaît immédiatement au prochain fetch
```

La sécurité runtime fait toujours l'intersection avec les permissions effectives.

---

# 19. Utilisateur avec plusieurs rôles

Règle obligatoire :

```text
Widgets utilisateur
=
UNION widgets activés de tous les rôles de l'Organization active
INTERSECTION permissions effectives
```

Exemple :

```text
Planner → tours_today, orders_to_plan
Bureau  → draft_invoices, open_claims

User Planner + Bureau
→ tours_today
→ orders_to_plan
→ draft_invoices
→ open_claims
```

Un widget présent dans deux rôles s'affiche une seule fois.

---

# 20. Ordre multi-rôles

Si un widget existe dans plusieurs configs :

```text
position finale = plus petite position configurée
```

Tri final :

```text
position ASC
widgetKey ASC
```

Ne pas dépendre de l'ordre SQL des UserRoles.

---

# 21. Config absente vs config vide

Si aucune ligne `RoleDashboardConfiguration` :

```text
utiliser les widgets defaultEnabled du Registry
INTERSECTION permissions du rôle
```

Si ligne présente avec :

```json
{"widgets":[]}
```

cela signifie :

```text
le rôle voit volontairement 0 widget
```

C'est pourquoi une table configuration dédiée est préférable à une simple pivot par lignes.

---

# 22. API — catalogue widgets

Créer :

```text
GET /api/v1/dashboard/widgets
```

Permission :

```text
dashboard.configure
```

Réponse conceptuelle :

```json
{
  "data": [
    {
      "key": "orders_to_plan",
      "label": "Commandes à planifier",
      "description": "Services prêts à être planifiés",
      "category": "planning",
      "type": "kpi",
      "requiredPermission": "orders.view",
      "defaultPosition": 20,
      "availableForRole": true
    }
  ]
}
```

Ne pas retourner resolver class ou SQL.

---

# 23. API — configuration Role

Créer :

```text
GET /api/v1/roles/{role}/dashboard
PUT /api/v1/roles/{role}/dashboard
```

Payload PUT :

```json
{
  "widgets": [
    {"key":"orders_to_plan","position":1},
    {"key":"tours_today","position":2}
  ]
}
```

Validation backend :

```text
widgets array
key existe dans Registry
key unique
position integer >= 0
Role dans scope autorisé
requiredPermission compatible avec Role
```

---

# 24. API — Dashboard courant

Refactorer/créer :

```text
GET /api/v1/dashboard
```

Permission :

```text
dashboard.view
```

Le backend :

```text
1. Current User
2. Current Organization
3. OrganizationUser
4. UserRole[]
5. configs Dashboard des rôles
6. permissions effectives
7. union widgets
8. permission intersection
9. scope validation
10. calcul des données
11. réponse ordonnée
```

---

# 25. Réponse Dashboard

Exemple :

```json
{
  "data": {
    "organization": {"id":"...","name":"Atlas Transport"},
    "widgets": [
      {
        "key":"orders_to_plan",
        "type":"kpi",
        "title":"Commandes à planifier",
        "position":1,
        "size":"small",
        "data":{"value":12}
      }
    ]
  }
}
```

Le frontend ne décide pas tout seul quels widgets sont permis.

---

# 26. Aucun leak de données

Interdit :

```text
widget masqué frontend
MAIS valeur présente dans JSON /dashboard
```

Si permission absente :

```text
widget absent de la réponse
```

---

# 27. Performance

Éviter :

```text
1 appel React par card
```

Préférer :

```text
GET /dashboard
```

qui agrège les widgets sélectionnés.

Chaque resolver utilise :

```text
COUNT
SUM
GROUP BY
LIMIT
indexed filters
```

Interdit :

```text
Model::all()->count()
charger une liste entière pour calculer un KPI
N+1
```

---

# 28. Dashboard frontend

Le Dashboard actuel ne doit plus coder directement :

```text
Clients
Agences
Utilisateurs
Rôles
```

Ces cards deviennent :

```text
customers_count
agencies_count
users_count
roles_count
```

Architecture frontend :

```text
DashboardPage
→ useDashboard()
→ DashboardGrid
→ DashboardWidgetRenderer
```

---

# 29. DashboardWidgetRenderer

Mapping contrôlé :

```text
KPI → KpiWidget
CHART → ChartWidget
LIST → ListWidget
ALERT → AlertWidget
QUICK_ACTIONS → QuickActionsWidget
```

Ne jamais instancier un composant à partir d'une valeur arbitraire DB.

---

# 30. Dashboard Grid

Grid responsive :

```text
desktop → 12 colonnes
tablet  → 6 colonnes
mobile  → 1 colonne
```

`defaultSize` vient du Registry :

```text
small
medium
large
full
```

Dans cette version l'admin configure surtout :

```text
visibilité
ordre
```

Pas besoin d'un page builder complexe.

---

# 31. UI Administration → Rôles

Dans le Detail/Edit Role existant ajouter un onglet :

```text
Dashboard
```

Header :

```text
Configuration Dashboard
Rôle : <nom>
```

Description :

```text
Choisissez les informations visibles sur le tableau de bord pour les utilisateurs ayant ce rôle.
La visibilité reste limitée par les permissions du rôle.
```

---

# 32. Liste des widgets dans config Role

Grouper :

```text
Administration
Exploitation
Planning
Réclamations / POD
Facturation
Stock
Communication
Intégrations
Actions rapides
```

Chaque ligne :

```text
switch
icon
nom
description
type
permission requise
```

---

# 33. Reorder

Permettre drag-and-drop des widgets actifs pour modifier `position`.

Ajouter une alternative :

```text
Monter
Descendre
```

pour clavier/accessibilité.

---

# 34. Preview

Ajouter :

```text
Aperçu du Dashboard de ce rôle
```

L'aperçu peut utiliser placeholders et metadata du Registry.

Ne pas charger des données que l'utilisateur configurateur n'a pas le droit de lire.

---

# 35. Save

Bouton :

```text
Enregistrer la configuration
```

Mutation :

```text
PUT /roles/{role}/dashboard
```

Après succès :

```text
invalidate role config
invalidate dashboard courant si le user possède ce rôle
toast success
```

---

# 36. Reset

Permettre :

```text
Réinitialiser aux widgets par défaut
```

Choisir une stratégie backend claire :

```text
supprimer RoleDashboardConfiguration
```

est recommandé pour revenir au Registry defaults.

Auditer l'action.

---

# 37. Rôles système

Inspecter le modèle Phase 1.

Si un rôle système est non modifiable par un admin local :

```text
Dashboard config non modifiable par admin local
```

Appliquer la même Policy existante.

Ne pas contourner `isSystem`, scope ou restrictions réelles.

---

# 38. Cross Organization

Un admin Org A ne peut jamais configurer Role Org B.

Tester GET + PUT direct par URL/API.

Retour selon convention :

```text
404 ou 403
```

---

# 39. Scope Agency

Le Dashboard est toujours scoped Organization.

Si l'application possède un contexte Agency actif :

chaque widget doit déclarer son scope :

```text
ORGANIZATION
ou
AGENCY
```

Exemple :

```text
orders_to_plan → peut être AGENCY
roles_count → ORGANIZATION
```

Suivre le backend réel.

---

# 40. Widgets cliquables

Si route + permission existent :

```text
orders_to_plan → Planning / liste filtrée
open_claims    → Claims filtrées
failed_exports → ExportJobs FAILED
draft_invoices → Invoices DRAFT
```

Ne pas inventer une route.

---

# 41. KPI

Card :

```text
title
value
icon
subtitle optionnel
```

Trend uniquement si vrai calcul comparatif backend.

Interdit d'afficher un faux :

```text
+12%
```

---

# 42. Charts

Réutiliser la chart library déjà installée.

Exemples :

```text
orders_by_status
tours_by_status
invoices_by_status
```

Status colors depuis metadata centralisée si disponible.

---

# 43. List Widgets

Afficher 5-10 éléments max.

Ajouter :

```text
Voir tout
```

vers vraie liste.

Ne pas charger 100 éléments dans Dashboard.

---

# 44. Alert Widgets

Exemples :

```text
failed exports
failed communications
services without GPS
open claims
```

Ce sont des projections Dashboard.

Ne pas créer une nouvelle table `alerts` uniquement pour cela.

---

# 45. Empty state

Si 0 widget final :

```text
Votre tableau de bord ne contient actuellement aucun widget.
```

Si user a `dashboard.configure` :

```text
[Configurer les rôles]
```

---

# 46. Loading / Error

Pendant loading : skeleton.

Ne pas afficher temporairement les 4 anciennes cards avant le résultat API.

En erreur :

```text
Impossible de charger le tableau de bord.
[Réessayer]
```

Pas de fallback vers données non autorisées.

---

# 47. Frontend API layer

Créer :

```text
modules/dashboard/api/dashboard.api.ts
modules/dashboard/api/role-dashboard.api.ts
```

Query keys :

```text
dashboardKeys.current(organizationId)
dashboardKeys.widgetCatalog()
roleDashboardKeys.detail(roleId)
```

---

# 48. TypeScript

Créer :

```text
DashboardWidgetKey
DashboardWidgetType
DashboardWidgetDefinition
DashboardWidgetData
DashboardResponse
RoleDashboardConfiguration
RoleDashboardWidgetSelection
```

Interdit :

```ts
type DashboardRole = 'ADMIN' | 'PLANNER'
```

---

# 49. Zod

Créer :

```text
roleDashboardConfigurationSchema
```

Valider côté frontend :

```text
key
position
```

Backend revalide tout.

---

# 50. AuditLog

Auditer :

```text
role_dashboard_configuration.created
role_dashboard_configuration.updated
role_dashboard_configuration.reset
```

ou noms conformes aux conventions Audit existantes.

Pas de table DashboardHistory.

---

# 51. Backend Policy

Pour GET/PUT config :

```text
dashboard.configure
+
Role scope autorisé
+
protection rôles système
```

Frontend `PermissionGuard` ne suffit jamais.

---

# 52. Tests Backend — config Role

Tester :

```text
create config
update config
empty config
unknown widget rejected
duplicate widget rejected
invalid position
missing dashboard.configure
cross-org Role
system Role restrictions
audit
```

---

# 53. Tests Backend — Dashboard runtime

Tester :

```text
dashboard.view required
single Role
multiple Roles
union widgets
duplicate displayed once
permissions intersection
missing permission → widget absent
Organization isolation
default config
explicit empty config
deterministic positions
```

---

# 54. Test permission removed

```text
Role config contains customers_count
Role initially has customers.view
→ widget visible

remove customers.view
→ widget absent
```

sans devoir modifier Dashboard config.

---

# 55. Test multi-role multi-org

User :

```text
Org A → Planner
Org B → Bureau
```

Org A active : widgets Planner uniquement.

Org B active : widgets Bureau uniquement.

---

# 56. Tests frontend

Tester :

```text
Dashboard render
KPI
Chart
List
Alert
Quick actions
loading
error
empty
responsive
```

Config Role :

```text
catalog
current config
disabled missing permission
toggle
reorder
save
reset
preview
403
404
422
```

---

# 57. E2E principal

```text
Login admin autorisé
→ Administration
→ Rôles
→ Bureau
→ Dashboard
→ activer orders_today, open_claims, draft_invoices
→ save

Login user Role Bureau
→ Dashboard

orders_today visible
open_claims visible
draft_invoices visible
customers_count absent
roles_count absent
```

---

# 58. E2E permission protection

```text
Role config = draft_invoices
remove invoices.view
refresh/login user
→ draft_invoices absent
```

---

# 59. E2E multiple Roles

Role A + Role B : vérifier union, déduplication et permissions.

---

# 60. E2E cross-org

Admin Org A tente config Role Org B : refus backend.

---

# 61. Design attendu

Conserver le style actuel Tricolis :

```text
sidebar dark
fond clair
cards white
border subtle
radius cohérent
spacing cohérent
```

Le Dashboard doit remplir utilement l'espace sans devenir surchargé.

Exemple pour rôle large :

```text
[Commandes aujourd'hui] [À planifier] [Tournées] [Réclamations]

[Commandes par statut           ] [Tournées par statut          ]

[Dernières commandes            ] [Alertes / erreurs           ]
```

Le contenu exact dépend de la config Role.

---

# 62. Documentation

Créer :

```text
docs/frontend/dashboard-role-configuration.md
docs/backend/dashboard-widget-registry.md
docs/backend/dashboard-security.md
docs/frontend/dashboard-role-final-report.md
```

Documenter pour chaque widget :

```text
key
label
type
category
required permission
scope
resolver/query
route cible éventuelle
```

---

# 63. Git workflow

Créer une branche dédiée depuis le dernier frontend validé :

```bash
git checkout <BRANCHE_FRONTEND_VALIDEE>
git checkout -b feature/role-based-dashboard
```

Avant commit :

```bash
git status
git config user.name
git config user.email
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
```

Interdit :

```text
Badr
Badr
Co-authored-by: Badr
Generated-by: Badr
```

Tests :

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

Backend :

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
php artisan migrate:status
php artisan route:list --path=api/v1
```

Pas auto-merge.
Pas auto-push.

---

# 64. Interdictions absolues

Ne pas :

- hardcoder Dashboard par nom de rôle ;
- considérer widget activé comme permission ;
- retourner la donnée d'un widget non autorisé ;
- utiliser les rôles d'une autre Organization ;
- créer config Dashboard par User ;
- permettre SQL/custom query à l'admin ;
- stocker PHP class/React component dans DB ;
- créer une table `dashboard_widgets` inutile ;
- ajouter `status_id` ;
- garder les 4 cards actuelles hardcodées ;
- afficher faux trends/KPI ;
- sommer différentes currencies ;
- inventer low-stock sans seuil ;
- inventer DriverAvailability ;
- faire une requête API par card sans justification ;
- charger toutes les lignes pour compter ;
- contourner Policies ;
- exposer cross-org ;
- auto-merge ;
- auto-push ;
- attribuer commit à Claude/Anthropic.

---

# 65. Résultat final

Le Dashboard doit fonctionner ainsi :

```text
Organization active
→ OrganizationUser
→ UserRoles
→ RoleDashboardConfigurations
→ UNION widgets
→ effective permissions
→ FILTER
→ DashboardWidgetRegistry
→ optimized backend data
→ Dashboard dynamique
```

Exemples métier :

```text
Planner
→ planning / tours / services

Bureau
→ orders / claims / billing

Role administratif
→ customers / users / roles

Role custom
→ widgets configurés + permissions uniquement
```

Ces exemples ne doivent jamais être codés par nom de rôle.

Conclusion du rapport :

```text
ROLE_BASED_DASHBOARD_READY
```

ou :

```text
ROLE_BASED_DASHBOARD_NOT_READY
```
