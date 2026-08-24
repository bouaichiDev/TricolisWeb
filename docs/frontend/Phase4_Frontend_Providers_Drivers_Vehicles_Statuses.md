# Tricolis V2 — Frontend Phase 4

## Fournisseurs, chauffeurs, types de véhicules et véhicules
## + normalisation obligatoire des statuts via `statuses`

Tu es un architecte frontend/backend senior spécialisé en React, TypeScript, Laravel, API REST, MySQL/SQL Server, sécurité multi-organisation et plateformes de transport/logistique.

Tu travailles sur **Tricolis V2**.

Les Frontend Phases 1, 2 et 3 sont terminées ou en cours de validation.

Ta mission consiste à développer uniquement :

# FRONTEND PHASE 4 — FOURNISSEURS ET RESSOURCES

Périmètre fonctionnel :

```text
Provider
Driver
VehicleType
Vehicle
```

Cette phase doit également appliquer une règle transversale obligatoire concernant les statuts :

> Toutes les valeurs de statut utilisées dans les tables métier doivent être référencées dans la table centrale `statuses`, tout en conservant dans chaque table source une colonne `status` textuelle.

Cette règle est une exigence projet explicite et doit être respectée.

---

# 0. Sources de vérité et ordre de priorité

Avant toute modification, analyser :

```text
Conception/diagramme/00-diagramme-classes-partagees.puml
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

Mais cette phase doit également respecter **le schéma réel de la base de données et le backend réellement implémenté**.

Analyser obligatoirement :

```text
database/migrations/
app/Modules/
routes/
API Resources
Form Requests
Policies
PermissionSeeder
Seeders
Models
docs/backend/
docs/frontend/
```

Ordre de priorité pour l’implémentation :

```text
1. Schéma réel de la base de données validé
2. Backend réel final
3. Diagrammes UML officiels
4. Documentation de phase
5. Ancien prompt
```

Si le schéma réel et le diagramme ne correspondent pas :

- ne pas inventer une correction silencieuse ;
- ne pas ajouter automatiquement des colonnes ;
- documenter l’écart ;
- utiliser le contrat réellement validé du projet ;
- signaler l’écart dans le rapport final.

Créer avant développement :

```text
docs/frontend/phase-4-analysis.md
```

---

# 1. Branche Git obligatoire

Avant toute modification :

```bash
git status
git branch --show-current
git log --oneline --decorate -10
```

Identifier la branche contenant réellement la Frontend Phase 3 validée.

Créer :

```bash
git checkout <BRANCHE_PHASE_3_VALIDEE>
git checkout -b feature/frontend-phase-4-providers-resources
```

Si elle existe déjà :

```bash
git checkout feature/frontend-phase-4-providers-resources
```

Ne jamais :

- travailler directement sur une mauvaise branche de base ;
- merger automatiquement ;
- pousser automatiquement.

---

# 2. Identité Git — aucune attribution Claude

Avant tout commit :

```bash
git config user.name
git config user.email
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
```

Interdiction absolue :

```text
Badr
Badr
Co-authored-by: Badr
Co-authored-by: Badr
Generated-by: Badr
Generated-by: Badr
```

Le commit doit être attribué uniquement au propriétaire humain du repository.

Si nécessaire :

```bash
git log --all --format='%an <%ae>' | sort -u
```

pour retrouver l’identité humaine déjà utilisée.

Ne jamais inventer l’e-mail Git.

Commit final recommandé :

```bash
git add .
git commit -m "feat(frontend): implement phase 4 providers drivers and vehicles"
```

Puis vérifier :

```bash
git show -s --format=fuller HEAD
git log -1 --pretty='%an <%ae>%n%cn <%ce>%n%B'
```

Ne pas pousser automatiquement.

---

# 3. Périmètre métier strict

Implémenter uniquement :

```text
Provider
Driver
VehicleType
Vehicle
```

Ne pas développer :

```text
ProviderContract
ProviderContractVersion
ProviderPriceList
DriverAvailability
VehicleAvailability
VehicleCapacity
DriverSkill
VehicleSkill
VehicleMaintenance
GPS
RouteOptimization
Planning
Tour
TourStop
```

Ces éléments appartiennent à d’autres versions/portails/phases ou ne font pas partie du périmètre validé actuel.

---

# 4. Modèles attendus — vérifier contre la DB réelle

Le diagramme officiel décrit le domaine Fournisseurs/Ressources.

Avant de créer les types TypeScript ou formulaires, comparer ces champs avec les migrations, modèles et Resources réellement présents.

## Provider attendu

```text
id
legacyId
organizationId
code
name
providerType
status
```

## Driver attendu

```text
id
legacyId
providerId
userId
code
firstName
lastName
phone
email
status
```

## VehicleType attendu

```text
id
organizationId
code
name
status
```

## Vehicle attendu

```text
id
legacyId
providerId
vehicleTypeId
code
registrationNumber
payloadCapacity
volumeCapacity
palletCapacity
status
```

IMPORTANT :

Si la base réellement validée utilise une variante différente, par exemple :

```text
addressId
contactId
name
organizationId
```

sur Provider/Driver, ne pas forcer les anciens champs simplement parce qu’ils apparaissent dans une ancienne version UML.

Documenter la différence puis suivre le schéma réellement validé.

---

# 5. Relations métier

Relations à vérifier :

```text
Organization 1 -> 0..* Provider
Provider 1 -> 0..* Driver
Provider 1 -> 0..* Vehicle
VehicleType 1 -> 0..* Vehicle
```

Selon le schéma réel, Provider et Driver peuvent également utiliser les mécanismes partagés :

```text
Address
Contact
User
```

Ne créer aucune relation inexistante.

---

# 6. RÈGLE GLOBALE DES STATUTS — OBLIGATOIRE

Cette règle s’applique à **toutes les tables métier comportant un champ `status`**, pas uniquement aux quatre tables de cette phase.

Le principe est :

```text
TABLE SOURCE
status = code texte

+

TABLE CENTRALE
statuses = définition/configuration des codes
```

Exemple :

```text
providers.status = "ACTIVE"
```

et dans :

```text
statuses
```

une entrée correspondant à :

```text
src = "providers"
code = "ACTIVE"
```

avec les autres métadonnées disponibles dans le schéma réel de `statuses`.

---

# 7. Ne jamais remplacer `status` par `status_id`

Ne pas transformer :

```text
providers.status
drivers.status
vehicle_types.status
vehicles.status
```

en :

```text
status_id
```

La table source doit conserver son statut sous forme de chaîne.

Exemple attendu :

```text
providers.status = "ACTIVE"
drivers.status = "AVAILABLE"
vehicles.status = "ACTIVE"
```

Le but est de :

- conserver une lecture directe du statut dans la table métier ;
- utiliser `statuses` comme référentiel central ;
- gérer label/couleur/icône/configuration depuis le référentiel ;
- éviter de dupliquer ces métadonnées dans toutes les tables.

---

# 8. Type SQL du champ `status`

Exigence fonctionnelle :

```text
status = colonne texte Unicode
```

Si la base réelle est SQL Server :

```sql
status NVARCHAR(...)
```

Si le projet utilise MySQL 8 avec Laravel :

- conserver le champ texte existant équivalent (`VARCHAR` / `string`) avec charset `utf8mb4` ;
- ne pas convertir le statut en entier ;
- ne pas créer `status_id` uniquement pour simuler NVARCHAR.

Le moteur de base réellement utilisé doit être respecté.

Ne pas changer de moteur de base pendant cette phase.

---

# 9. Table `statuses` — réutiliser le schéma réel

Avant toute migration ou API, inspecter la table existante :

```text
statuses
```

Ne pas inventer ses colonnes si elle existe déjà.

Réutiliser exactement son schéma réel.

Elle peut notamment contenir selon l’implémentation existante des informations telles que :

```text
id
code
label
src
color
backgroundColor
icon
active
organization/store scope
```

Mais n’ajouter aucun champ sans inspection préalable.

Créer un document :

```text
docs/backend/statuses-schema-audit.md
```

avec :

| Colonne | Type DB | Nullable | Usage |
|---|---|---|---|

---

# 10. `src` identifie la table/domaine

Le référentiel doit permettre de distinguer les statuts de chaque source.

Exemples :

```text
src = providers
src = drivers
src = vehicle_types
src = vehicles
src = orders
src = order_services
src = claims
src = invoices
...
```

Ne pas considérer qu’un code :

```text
ACTIVE
```

signifie nécessairement la même chose dans tous les domaines.

La clé logique de recherche doit utiliser au minimum :

```text
src + code
```

et le scope Organization si le schéma réel le prévoit.

---

# 11. Audit global de toutes les colonnes status

Avant de développer les selects Phase 4, rechercher dans toutes les migrations/tables :

```text
status
```

Créer :

```text
docs/backend/statuses-global-audit.md
```

Tableau :

| Table source | Colonne | Type actuel | Codes utilisés | Présents dans statuses ? | Action |
|---|---|---|---|---|---|

L’objectif est d’identifier toutes les tables ayant un statut.

Exemples potentiels :

```text
organizations
users
roles
customers
catalogs
catalog_items
orders
order_lines
packages
order_services
providers
drivers
vehicle_types
vehicles
claims
invoices
provider_settlements
stock_items
stock_locations
stock_reservations
communication_templates
order_communications
...
```

Ne pas supposer cette liste complète.

Scanner la base réelle.

---

# 12. Synchronisation des statuses

Pour toute table métier ayant un `status` :

1. conserver la colonne texte dans la table source ;
2. identifier tous les codes autorisés/utilisés ;
3. créer les entrées manquantes dans `statuses` ;
4. rendre le seeding idempotent ;
5. ne pas supprimer un status encore utilisé ;
6. ne pas renommer silencieusement un code déjà enregistré dans les données.

Créer ou compléter un seeder central, par exemple selon architecture existante :

```text
StatusSeeder
```

mais réutiliser le nom existant si le projet en possède déjà un.

---

# 13. Validation backend d’un status

Lors de :

```text
POST
PATCH
```

sur une ressource métier :

le backend doit valider que le code reçu existe dans :

```text
statuses
```

avec le bon :

```text
src
active
organization scope si applicable
```

Exemple logique :

```text
Provider.status = ACTIVE
```

doit être accepté uniquement si :

```text
statuses.src = providers
statuses.code = ACTIVE
statuses.active = true
```

Ne pas accepter une valeur arbitraire.

---

# 14. L’API doit retourner le code source

Les Resources doivent continuer à retourner :

```json
{
  "status": "ACTIVE"
}
```

Ne pas remplacer par :

```json
{
  "statusId": 12
}
```

Le frontend travaille principalement avec le code texte.

L’API peut également retourner les métadonnées :

```json
{
  "status": "ACTIVE",
  "statusMeta": {
    "code": "ACTIVE",
    "label": "Actif",
    "color": "...",
    "backgroundColor": "...",
    "icon": "..."
  }
}
```

uniquement si cette convention est déjà retenue/ajoutée proprement.

---

# 15. API de référentiel statuses

Vérifier si une API existe déjà.

Réutiliser par exemple une route du type :

```text
GET /api/v1/statuses
```

avec un filtre :

```text
src
```

Exemples frontend :

```text
GET /api/v1/statuses?src=providers
GET /api/v1/statuses?src=drivers
GET /api/v1/statuses?src=vehicle_types
GET /api/v1/statuses?src=vehicles
```

Si la route réelle utilise une autre convention, utiliser cette convention.

Si aucune API n’existe :

- ajouter une API backend minimale de lecture du référentiel ;
- sécurisée ;
- filtrée par `src` ;
- filtrée sur les statuts actifs ;
- sans inventer un nouveau modèle de statut.

---

# 16. Frontend : aucun status métier hardcodé

Interdiction de faire :

```ts
const PROVIDER_STATUSES = [
  'ACTIVE',
  'INACTIVE',
]
```

si les statuts sont maintenant gérés par la table `statuses`.

Le frontend doit charger :

```text
statuses?src=providers
```

et utiliser la réponse pour :

```text
select
filter
badge
label
color
icon
```

Même principe pour :

```text
drivers
vehicle_types
vehicles
```

---

# 17. Composants status partagés

Créer/réutiliser :

```text
StatusSelect
StatusBadge
useStatuses
statusKeys
```

API :

```text
useStatuses("providers")
useStatuses("drivers")
useStatuses("vehicle_types")
useStatuses("vehicles")
```

`StatusSelect` doit :

- charger les statuts actifs ;
- afficher le label ;
- envoyer `code` ;
- ne jamais envoyer `id` comme valeur métier principale.

---

# 18. Architecture frontend Phase 4

Ajouter :

```text
src/modules/
├── providers/
├── drivers/
├── vehicle-types/
└── vehicles/
```

Chaque module :

```text
pages/
components/
api/
hooks/
schemas/
types/
utils/
```

Réutiliser les composants Phase 1–3.

Ne pas créer un deuxième design system.

---

# 19. Menu

Dans le menu existant :

```text
Ressources
├── Fournisseurs
├── Chauffeurs
├── Types de véhicules
└── Véhicules
```

Permissions obligatoires.

Ne pas afficher les éléments non autorisés.

---

# 20. Providers — routes frontend

Créer :

```text
/resources/providers
/resources/providers/create
/resources/providers/:id
/resources/providers/:id/edit
```

ou réutiliser le préfixe de routes existant si différent.

Créer :

```text
ProviderListPage
ProviderCreatePage
ProviderDetailPage
ProviderEditPage
ProviderForm
ProviderFilters
```

---

# 21. Provider List

Afficher uniquement les champs existants dans l’API.

Minimum :

```text
Code
Nom
Type fournisseur si présent
Statut
Actions
```

Si Address/Contact sont réellement dans le schéma/API :

ajouter éventuellement :

```text
Ville
Contact principal
Téléphone
```

Ne pas inventer ces colonnes si elles n’existent pas.

Filtres :

```text
search
status
providerType si présent
```

et uniquement ceux supportés par l’API.

---

# 22. Provider Create/Edit

Formulaire conforme au schéma réel.

Exemple selon version du modèle :

```text
Code
Nom
Type fournisseur
Statut
```

ou, si le schéma réel validé contient :

```text
Address
Contact
```

réutiliser :

```text
AddressSelector
ContactSelector
```

Ne pas ajouter des champs absents.

Le champ `status` utilise :

```text
StatusSelect src="providers"
```

---

# 23. Provider Detail

Créer une fiche avec tabs :

```text
Informations
Chauffeurs
Véhicules
```

Ajouter :

```text
Adresse
Contact
```

uniquement si réellement présents.

Ne pas créer :

```text
Contrats
Disponibilités
Liste de prix
```

---

# 24. Drivers — routes

Créer :

```text
/resources/drivers
/resources/drivers/create
/resources/drivers/:id
/resources/drivers/:id/edit
```

Créer :

```text
DriverListPage
DriverCreatePage
DriverDetailPage
DriverEditPage
DriverForm
DriverFilters
```

---

# 25. Driver List

Selon schéma réel afficher :

Version avec identité détaillée :

```text
Code
Prénom
Nom
Téléphone
Email
Fournisseur
Statut
```

ou version schéma avec nom unique :

```text
Code
Nom
Fournisseur
Statut
```

Ne pas créer des champs pour faire correspondre artificiellement une ancienne version du diagramme.

Filtrer selon API :

```text
providerId
status
search
```

---

# 26. Driver Create/Edit

Le Provider doit appartenir à l’Organization active.

Ne pas permettre :

```text
Driver Organization A
→ Provider Organization B
```

Si Driver est lié à User :

- sélectionner uniquement un User accessible ;
- respecter les règles backend.

Si Driver utilise Address/Contact :

- réutiliser les composants partagés existants.

Status :

```text
StatusSelect src="drivers"
```

---

# 27. VehicleType — routes

Créer :

```text
/resources/vehicle-types
/resources/vehicle-types/create
/resources/vehicle-types/:id
/resources/vehicle-types/:id/edit
```

Créer :

```text
VehicleTypeListPage
VehicleTypeCreatePage
VehicleTypeDetailPage
VehicleTypeEditPage
VehicleTypeForm
```

Champs attendus :

```text
code
name
status
```

Status :

```text
StatusSelect src="vehicle_types"
```

---

# 28. Vehicle — routes

Créer :

```text
/resources/vehicles
/resources/vehicles/create
/resources/vehicles/:id
/resources/vehicles/:id/edit
```

Créer :

```text
VehicleListPage
VehicleCreatePage
VehicleDetailPage
VehicleEditPage
VehicleForm
VehicleFilters
VehicleCapacitySummary
```

---

# 29. Vehicle List

Colonnes :

```text
Code
Immatriculation
Fournisseur
Type de véhicule
Charge utile
Volume
Palettes
Statut
Actions
```

Filtres uniquement selon API réelle :

```text
providerId
vehicleTypeId
status
search
payloadCapacityMin
volumeCapacityMin
palletCapacityMin
```

---

# 30. Vehicle Create/Edit

Champs :

```text
providerId
vehicleTypeId
code
registrationNumber
payloadCapacity
volumeCapacity
palletCapacity
status
```

Si `legacyId` est exposé uniquement pour migration :

- ne pas nécessairement le rendre éditable ;
- l’afficher en lecture seule si utile à l’administration.

Status :

```text
StatusSelect src="vehicles"
```

---

# 31. Cohérence Provider / VehicleType

Lorsqu’un Vehicle est créé :

```text
Provider
```

et :

```text
VehicleType
```

doivent appartenir à la même Organization.

Le frontend filtre les listes pour améliorer l’UX.

Le backend doit vérifier la règle.

Ne jamais se reposer uniquement sur React.

---

# 32. Provider Detail — Chauffeurs

Dans :

```text
ProviderDetailPage
```

onglet :

```text
Chauffeurs
```

afficher les Drivers liés.

Actions :

```text
Ajouter
Voir
Modifier
```

uniquement selon permissions.

Préremplir `providerId` lors de la création depuis la fiche Provider.

---

# 33. Provider Detail — Véhicules

Onglet :

```text
Véhicules
```

afficher les Vehicles liés.

Lors de :

```text
+ Ajouter véhicule
```

préremplir Provider.

Ne pas ajouter Planning/Tours dans cette phase.

---

# 34. Permissions

Analyser le `PermissionSeeder` réel.

Ne pas inventer les codes.

Attendus possibles :

```text
providers.view
providers.create
providers.update
providers.delete

drivers.view
drivers.create
drivers.update
drivers.delete

vehicle_types.view
vehicle_types.create
vehicle_types.update
vehicle_types.delete

vehicles.view
vehicles.create
vehicles.update
vehicles.delete
```

Mais utiliser **uniquement les noms réellement présents dans le backend**.

---

# 35. API Layer

Créer :

```text
modules/providers/api/providers.api.ts
modules/drivers/api/drivers.api.ts
modules/vehicle-types/api/vehicle-types.api.ts
modules/vehicles/api/vehicles.api.ts
shared/api/statuses.api.ts
```

Ne jamais appeler `fetch` directement dans JSX.

---

# 36. TanStack Query keys

Créer :

```text
providerKeys
driverKeys
vehicleTypeKeys
vehicleKeys
statusKeys
```

Exemple :

```text
statusKeys.list("providers")
statusKeys.list("drivers")
statusKeys.list("vehicle_types")
statusKeys.list("vehicles")
```

Au changement d’Organization, invalider :

```text
providers
drivers
vehicleTypes
vehicles
statuses si scope organisationnel
```

---

# 37. Types TypeScript

Créer les types à partir des Resources API réelles :

```text
Provider
Driver
VehicleType
Vehicle
StatusDefinition
```

`StatusDefinition` doit représenter exactement la réponse de l’API statuses.

Ne pas inventer des propriétés absentes.

---

# 38. Zod

Créer :

```text
providerSchema
driverSchema
vehicleTypeSchema
vehicleSchema
```

Le champ status valide :

- une chaîne ;
- issue de la liste de statuses chargée ;
- mais la sécurité finale reste backend.

Ne pas hardcoder une union TypeScript fixe si les valeurs viennent de la table `statuses`.

---

# 39. StatusBadge global

Modifier si nécessaire le `StatusBadge` existant pour utiliser la définition venant de `statuses`.

Exemple :

```text
code
label
color
backgroundColor
icon
```

Ne pas avoir :

```ts
switch(status) {
 case 'ACTIVE': ...
 case 'INACTIVE': ...
}
```

répété dans chaque module.

---

# 40. Gestion des couleurs/status UI

Le frontend ne doit pas inventer les couleurs.

Si `statuses` contient :

```text
color
background_color
icon
```

utiliser ces informations.

Sinon utiliser le style neutre global existant.

Ne pas stocker les couleurs dans Provider/Driver/Vehicle.

---

# 41. Statuts inactifs

Lors de la création :

- afficher uniquement les statuses actifs.

Lors de l’édition d’une ancienne donnée :

si son status est devenu inactif :

- afficher le statut actuel pour ne pas perdre l’information ;
- ne pas proposer ce statut comme nouvelle sélection pour les nouvelles données, selon règle backend.

---

# 42. Sécurité multi-organisation

Le frontend et backend doivent empêcher :

```text
Provider Organization A visible depuis B
Driver Provider A affecté à B
VehicleType A affecté à Vehicle B
Vehicle Provider A modifié depuis B
```

Utiliser :

```text
404
```

lorsque révéler l’existence constituerait une fuite.

---

# 43. Suppression

Respecter les contraintes backend.

Exemples :

```text
Provider avec Driver -> suppression potentiellement refusée
Provider avec Vehicle -> suppression potentiellement refusée
VehicleType utilisé -> suppression refusée
Vehicle utilisé par Tour -> suppression refusée
Driver utilisé par Tour -> suppression refusée
```

Afficher correctement les erreurs :

```text
409
422
403
404
```

Ne pas masquer les conflits métier.

---

# 44. Shared UI

Réutiliser :

```text
DataTable
PageHeader
EntityHeader
StatusBadge
StatusSelect
ConfirmDialog
AsyncSelect
AddressSelector
ContactSelector
LoadingSkeleton
EmptyState
ErrorState
FormErrorSummary
PermissionGuard
ProtectedRoute
```

Ne pas créer de doublons.

---

# 45. Responsive

Desktop-first.

Sur tablette/mobile :

- tables simplifiées ;
- actions dans menu contextuel ;
- formulaire sur une colonne ;
- capacités véhicule regroupées dans une card.

---

# 46. i18n

Ajouter :

```text
providers.*
drivers.*
vehicleTypes.*
vehicles.*
statuses.*
```

Les labels de statut viennent de `statuses`.

Les clés i18n servent au reste de l’interface.

---

# 47. Tests backend — statuses

Ajouter des tests transversaux.

## Provider

```text
status présent dans statuses/providers -> accepté
status absent -> 422
status d’un autre src -> 422
status inactif -> refus selon règle
```

Même tests pour :

```text
drivers
vehicle_types
vehicles
```

---

# 48. Test global de cohérence statuses

Créer un test ou une commande de vérification qui scanne toutes les données métier :

Pour chaque :

```text
table.status
```

vérifier qu’il existe une définition compatible dans :

```text
statuses
```

Produire une erreur claire pour les valeurs orphelines.

Ne pas supprimer automatiquement les données orphelines.

---

# 49. Tests frontend Providers

Tester :

```text
liste
pagination
recherche
status filter depuis statuses API
création
édition
detail
permissions
Organization isolation
status label/color
```

---

# 50. Tests frontend Drivers

Tester :

```text
liste
provider filter
status filter
création
Provider hors Organization refusé
édition
detail
permissions
status dynamique
```

---

# 51. Tests frontend VehicleType

Tester :

```text
liste
création
édition
suppression protégée
status dynamique
permissions
Organization isolation
```

---

# 52. Tests frontend Vehicles

Tester :

```text
liste
provider
vehicleType
capacités
status dynamique
création
édition
immatriculation
Provider/VehicleType Organization mismatch
permissions
```

---

# 53. E2E principal

Si Playwright/Cypress existe :

```text
Login
→ sélectionner Organization
→ Ressources
→ créer Provider
→ ouvrir Provider
→ créer Driver
→ créer VehicleType
→ créer Vehicle
→ vérifier Driver dans Provider
→ vérifier Vehicle dans Provider
→ modifier les statuts
→ vérifier les badges provenant du référentiel statuses
```

---

# 54. E2E Status

Créer un scénario :

```text
Backend statuses:
src=providers
code=ACTIVE
label=Actif
```

Puis :

```text
Create Provider
→ StatusSelect charge ACTIVE
→ sélection ACTIVE
→ POST envoie "status": "ACTIVE"
→ providers.status stocke "ACTIVE"
→ détail affiche label "Actif"
```

Important :

```text
providers.status
```

reste textuel.

Ne pas envoyer un `statusId`.

---

# 55. Vérification DB obligatoire

Avant de déclarer la phase terminée, inspecter le schéma réel.

Vérifier :

```text
providers.status
drivers.status
vehicle_types.status
vehicles.status
```

Les colonnes doivent rester textuelles.

Vérifier la table :

```text
statuses
```

et les entrées pour :

```text
providers
drivers
vehicle_types
vehicles
```

Créer :

```text
docs/backend/phase-4-statuses-report.md
```

avec :

| src | code | label | active | utilisé dans données |
|---|---|---|---|---|

---

# 56. Règle globale à conserver pour les prochaines phases

À partir de cette phase, toute nouvelle table comportant :

```text
status
```

doit respecter automatiquement :

```text
1. status textuel dans la table métier
2. code enregistré/configuré dans statuses
3. validation backend via statuses
4. dropdown frontend via statuses API
5. badge frontend via metadata statuses
```

Cette règle devra être appliquée également aux phases futures :

```text
Planning
Tours
Stock
Billing
Integrations
Communications
```

---

# 57. Ne pas transformer les enums métier en FK

Même lorsqu’un statut est décrit comme enum dans l’UML :

```text
OrderStatus
OrderServiceStatus
TourStatus
TourStopStatus
CommunicationStatus
```

la persistance doit respecter la convention projet demandée :

```text
table_source.status = code textuel
```

et la définition disponible doit être synchronisée dans :

```text
statuses
```

Ne pas créer :

```text
status_id
```

dans la table source.

Le code texte reste la valeur métier persistée.

---

# 58. Analyse finale

Créer :

```text
docs/frontend/phase-4-analysis.md
```

incluant :

1. schéma réel Provider ;
2. schéma réel Driver ;
3. schéma réel VehicleType ;
4. schéma réel Vehicle ;
5. différences UML/DB éventuelles ;
6. endpoints réels ;
7. permissions ;
8. filtres ;
9. tris ;
10. schéma statuses ;
11. API statuses ;
12. status src utilisés ;
13. composants réutilisés ;
14. éléments hors scope.

---

# 59. Rapport final

Créer :

```text
docs/frontend/phase-4-final-report.md
```

Inclure :

1. branche de base ;
2. branche Phase 4 ;
3. identité Git Author ;
4. identité Git Committer ;
5. confirmation absence Claude/Anthropic ;
6. Providers ;
7. Drivers ;
8. VehicleTypes ;
9. Vehicles ;
10. routes frontend ;
11. endpoints backend ;
12. permissions ;
13. schéma DB réellement utilisé ;
14. différences UML/DB ;
15. table statuses auditée ;
16. `status` textuel confirmé dans tables source ;
17. entries statuses créées/synchronisées ;
18. API statuses ;
19. StatusSelect ;
20. StatusBadge ;
21. query keys ;
22. Zod ;
23. tests ;
24. E2E ;
25. résultats build ;
26. fichiers modifiés ;
27. risques ;
28. prochaine phase.

Conclusion obligatoire :

```text
FRONTEND_PHASE_4_READY
```

ou :

```text
FRONTEND_PHASE_4_NOT_READY
```

Ne pas déclarer READY si les tests ou la cohérence statuses échouent.

---

# 60. Vérifications finales

Exécuter les scripts réellement présents dans le projet :

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

Backend si modifications status nécessaires :

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
```

Vérifier les migrations :

```bash
php artisan migrate:status
```

Vérifier Git :

```bash
git status
git diff --check
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
git log -1 --pretty=fuller
```

Ne pas pousser automatiquement.

---

# 61. Interdictions finales

Ne pas :

- développer Planning ;
- développer Tours ;
- créer ProviderContract ;
- créer DriverAvailability ;
- créer VehicleAvailability ;
- créer VehicleCapacity séparée ;
- créer GPS ;
- créer Maintenance ;
- inventer des champs Provider/Driver/Vehicle ;
- ignorer le schéma DB réel ;
- hardcoder les statuses dans React ;
- créer `status_id` dans les tables source ;
- remplacer la colonne `status` par une FK ;
- stocker seulement l’ID du status ;
- utiliser un status absent du référentiel `statuses` ;
- dupliquer label/couleur/icône dans chaque table métier ;
- inventer les couleurs frontend ;
- travailler sur une mauvaise branche ;
- merger automatiquement ;
- pousser automatiquement ;
- attribuer un commit à Claude/Anthropic ;
- laisser des TODO.

Le résultat doit être :

- conforme au schéma réel de base de données ;
- conforme au domaine UML ;
- multi-organisation ;
- connecté à l’API réelle ;
- status-driven via la table `statuses` ;
- avec `status` conservé comme chaîne dans chaque table source ;
- prêt pour la phase suivante : **Planning & Tournées**.
