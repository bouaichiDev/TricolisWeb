# Tricolis V2 — FRONTEND PHASE 5 MASTER FINAL

## Planification & Tournées
### Planning normal + MAPS + Drag & Drop + Tour DRAFT + replanification + historique + regroupement des chargements + départ dépôt + géocodage + routing + distances/temps + statuses

> **Ce fichier remplace toutes les versions précédentes de la Phase 5.**
>
> Il doit être utilisé comme **seule source de travail pour la Phase 5**.

---

# 1. Mission

Tu es un architecte frontend/backend senior spécialisé en :

- React ;
- TypeScript ;
- Vite ;
- TanStack Query ;
- Laravel ;
- API REST ;
- MySQL 8 / schéma DB réel du projet ;
- drag & drop ;
- planification transport ;
- cartographie ;
- géocodage ;
- calcul d’itinéraires ;
- concurrence multi-utilisateur ;
- sécurité multi-organisation.

Tu travailles sur **Tricolis V2**.

Les Frontend Phases 1 à 4 sont terminées ou validées.

Ta mission est d’implémenter uniquement :

# FRONTEND PHASE 5 — PLANIFICATION & TOURNÉES

La phase doit proposer deux modes synchronisés :

```text
1. PLANIFICATION NORMALE
2. MAPS
```

Les deux modes travaillent sur les **mêmes données backend** et les **mêmes `Tour` DRAFT persistées**.

---

# 2. Sources de vérité obligatoires

Utiliser comme références UML :

```text
Conception/diagramme/00-diagramme-classes-partagees.puml
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

Analyser également le backend et la DB réels :

```text
database/migrations/
app/Modules/
Models
Actions
Services
Form Requests
API Resources
Policies
PermissionSeeder
Seeders
routes/
docs/backend/
docs/frontend/
```

Ordre de priorité :

```text
1. Schéma DB réellement validé
2. Backend réellement implémenté
3. Diagrammes UML officiels
4. Documentation de phase
5. Anciennes implémentations / anciens prompts
```

Si un écart existe :

- ne pas le corriger silencieusement ;
- ne pas inventer une colonne ;
- ne pas inventer une table ;
- documenter l’écart ;
- respecter le contrat validé du projet.

---

# 3. Classes exactes de la Phase 5

Le package de planification contient uniquement :

```text
Tour
TourStop
TourStopService
TourPeriod
TourPeriodAssignment
```

Entités existantes utilisées :

```text
Organization
Agency
Depot
Address
EntityAddress
Order
OrderService
Service
Package
Provider
Driver
Vehicle
VehicleType
User
AuditLog
statuses
configs
```

Ne pas créer de nouvelle entité métier si les classes existantes suffisent.

---

# 4. Modèle UML exact — Tour

Respecter :

```text
Tour
- id: ULID
- organizationId: ULID
- tourNumber: string
- tourDate: date
- agencyId: ULID
- depotId: ULID
- providerId: ULID
- vehicleId: ULID
- driverId: ULID
- tourType: string
- instructions: text
- plannedStartAt: datetime
- plannedEndAt: datetime
- actualStartAt: datetime
- actualEndAt: datetime
- totalWeight: decimal
- totalVolume: decimal
- totalPackages: int
- totalCustomers: int
- drivingTimeMinutes: int
- workingTimeMinutes: int
- distanceMeters: bigint
- status: TourStatus
```

Ne pas ajouter sans validation de conception :

```text
createdBy
planningUserId
lockedBy
lockedAt
validatedBy
validatedAt
isVirtual
isDraft
routeGeometry
currentLatitude
currentLongitude
capacityStatus
optimizationScore
assistantDriverId
```

---

# 5. Modèle UML exact — TourStop

Respecter :

```text
TourStop
- id: ULID
- tourId: ULID
- addressId: ULID
- sequence: int
- groupingKey: string
- generationMode: string
- plannedArrivalAt: datetime
- plannedDepartureAt: datetime
- actualArrivalAt: datetime
- actualDepartureAt: datetime
- waitingMinutes: int
- serviceMinutes: int
- status: TourStopStatus
```

Ne pas ajouter :

```text
type
distanceFromPrevious
travelTimeFromPrevious
latitude
longitude
orderId
customerId
```

si ces champs n’existent pas dans le schéma validé.

---

# 6. Modèle UML exact — TourStopService

Respecter :

```text
TourStopService
- id: ULID
- tourStopId: ULID
- orderServiceId: ULID
- sequenceWithinStop: int
- isActiveAssignment: boolean
- status: string
```

Ne pas ajouter :

```text
plannedStartAt
plannedEndAt
actualStartAt
actualEndAt
```

Ils ne font pas partie du modèle officiel actuel.

---

# 7. Modèle UML exact — TourPeriod

Respecter :

```text
TourPeriod
- id: ULID
- tourId: ULID
- tourStopId: ULID
- periodType: string
- sequence: int
- plannedStartAt: datetime
- plannedEndAt: datetime
- actualStartAt: datetime
- actualEndAt: datetime
- breakMinutes: int
- serviceMinutes: int
- waitingMinutes: int
- distanceMeters: bigint
- internalRemark: text
- status: string
```

Important :

```text
internalRemark
```

ne doit pas être remplacé par un ancien champ `communication`.

---

# 8. Modèle UML exact — TourPeriodAssignment

Respecter :

```text
TourPeriodAssignment
- id: ULID
- tourPeriodId: ULID
- tourStopServiceId: ULID
- packageId: ULID
```

`packageId` peut être optionnel selon le contrat réel.

Cette classe ne possède pas de `status` dans l’UML actuel.

---

# 9. Relations officielles importantes

Respecter :

```text
Organization 1 -> 0..* Tour
Agency 1 -> 0..* Tour
Depot 0..1 -> 0..* Tour
Provider 0..1 -> 0..* Tour
Driver 0..1 -> 0..* Tour
Vehicle 0..1 -> 0..* Tour

Tour 1 -> 0..* TourStop
Address 1 -> 0..* TourStop

TourStop 1 -> 1..* TourStopService
OrderService 1 -> 0..* TourStopService

Tour 1 -> 0..* TourPeriod
TourStop 0..1 -> 0..* TourPeriod

TourPeriod 1 -> 0..* TourPeriodAssignment
TourStopService 1 -> 0..* TourPeriodAssignment
Package 0..1 -> 0..* TourPeriodAssignment
```

La relation fondamentale pour la replanification est :

```text
OrderService 1 -> 0..* TourStopService
```

Un même service peut donc conserver plusieurs affectations historiques.

---

# 10. Enums officiels

## TourStatus

```text
DRAFT
PLANNED
CONFIRMED
IN_PROGRESS
COMPLETED
CANCELLED
```

## TourStopStatus

```text
PENDING
ARRIVED
IN_PROGRESS
COMPLETED
SKIPPED
CANCELLED
```

## OrderServiceStatus existant

```text
DRAFT
PENDING
READY_TO_PLAN
PLANNED
IN_PROGRESS
COMPLETED
FAILED
CANCELLED
INVOICED
```

Ne pas inventer de nouvelle valeur sans validation métier.

---

# 11. Règle globale `statuses` — obligatoire

La règle introduite précédemment reste obligatoire.

Toute table métier comportant une colonne :

```text
status
```

conserve cette valeur **sous forme textuelle**.

Ne jamais remplacer par :

```text
status_id
```

Pour cette phase :

```text
tours.status
tour_stops.status
tour_stop_services.status
tour_periods.status
```

restent des chaînes.

Toutes les valeurs réellement utilisées doivent être enregistrées dans :

```text
statuses
```

avec une source logique telle que :

```text
src = tours
src = tour_stops
src = tour_stop_services
src = tour_periods
```

`TourPeriodAssignment` n’a pas de status.

---

# 12. Type DB du status

Si SQL Server :

```text
NVARCHAR
```

Si MySQL 8 :

```text
VARCHAR / string Unicode avec utf8mb4
```

Dans tous les cas :

```text
status = texte
```

Ne jamais convertir en FK numérique.

---

# 13. Frontend status-driven

Réutiliser :

```text
StatusSelect
StatusBadge
useStatuses
statusKeys
```

Exemples :

```text
useStatuses("tours")
useStatuses("tour_stops")
useStatuses("tour_stop_services")
useStatuses("tour_periods")
```

Le frontend envoie :

```text
code
```

et jamais :

```text
statusId
```

Les labels/couleurs/icônes viennent de `statuses`.

---

# 14. Analyse préalable obligatoire

Avant de coder, créer ou mettre à jour :

```text
docs/frontend/phase-5-analysis.md
```

Analyser :

1. migrations ;
2. modèles ;
3. relations ;
4. Resources ;
5. Requests ;
6. Actions ;
7. Policies ;
8. permissions ;
9. routes ;
10. endpoints de planification ;
11. filtres ;
12. tris ;
13. API de statuses ;
14. statut et transitions d’OrderService ;
15. logique de génération des stops ;
16. logique de grouping ;
17. règles d’éligibilité à la planification ;
18. services considérés comme chargement ;
19. route de reorder ;
20. gestion transactionnelle ;
21. audit ;
22. configs ;
23. géocodage ;
24. routing ;
25. structure réelle des TourPeriod ;
26. unités GPS.

Créer :

| Fonction UI | Endpoint réel | Permission | Resource | Statut |
|---|---|---|---|---|

Ne pas coder un endpoint supposé.

---

# 15. Branche Git

Identifier la branche contenant réellement la Phase 4 frontend validée.

Créer :

```bash
git checkout <BRANCHE_PHASE_4_VALIDEE>
git checkout -b feature/frontend-phase-5-planning-tours
```

Si elle existe déjà :

```bash
git checkout feature/frontend-phase-5-planning-tours
```

Ne pas travailler sur `main` si le frontend validé n’y est pas encore intégré.

Ne pas merger automatiquement.

Ne pas pousser automatiquement.

---

# 16. Identité Git obligatoire

Avant commit :

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

Le commit appartient uniquement au propriétaire humain.

Commit recommandé :

```bash
git add .
git commit -m "feat(frontend): implement phase 5 planning and tours"
```

Puis vérifier :

```bash
git show -s --format=fuller HEAD
git log -1 --pretty='%an <%ae>%n%cn <%ce>%n%B'
```

---

# 17. Architecture frontend

Ajouter :

```text
src/modules/
├── planning/
└── tours/
```

Structure :

```text
planning/
├── pages/
├── components/
├── api/
├── hooks/
├── schemas/
├── types/
└── utils/
```

et même principe pour `tours`.

Règles :

- fichiers courts ;
- API séparée ;
- hooks séparés ;
- types séparés ;
- Zod séparé ;
- aucune requête HTTP directement dans JSX ;
- aucun composant monolithique ;
- aucun mock permanent ;
- aucun TODO laissé.

---

# 18. Route principale Planning

Créer :

```text
/planning
```

avec :

```text
PlanningPage
PlanningToolbar
PlanningFilters
PlanningModeSwitcher
```

Modes :

```text
[ Planning ] [ Carte ]
```

Les deux modes utilisent les mêmes Tours DRAFT et les mêmes mutations.

---

# 19. Filtres Planning

Utiliser uniquement les filtres réellement supportés par le backend.

Exemples possibles :

```text
date
agencyId
depotId
providerId
driverId
vehicleId
tourStatus
customerId
search
```

Ne pas inventer de filtre API.

---

# 20. Tour DRAFT = tournée virtuelle

Une tournée en cours de préparation est :

```text
Tour.status = DRAFT
```

Ne pas créer :

```text
VirtualTour
DraftTour
PlanningSession
PlanningLock
PlanningBoard
PlanningSlot
```

en base.

La DRAFT est une vraie ligne `Tour`, persistée.

---

# 21. Persistance de la DRAFT

Chaque modification importante doit être sauvegardée côté backend.

Ne pas conserver la planification uniquement dans :

```text
React state
localStorage
sessionStorage
```

L’utilisateur doit pouvoir :

```text
commencer aujourd’hui
fermer le navigateur
revenir demain
retrouver la même Tour DRAFT
continuer
```

---

# 22. Mes planifications en cours

Créer :

```text
MyDraftTours
```

Afficher :

```text
Tour
Date
Agence
Depot
Stops
Services
Créateur
Statut
Dernière activité si audit/API disponible
```

Action :

```text
Reprendre
```

Ne pas créer une table `PlanningSession`.

---

# 23. Créateur de la DRAFT

`Tour` ne contient pas `createdBy` dans l’UML actuel.

Ne pas ajouter cette colonne juste pour l’interface.

Utiliser :

```text
AuditLog
```

pour déterminer l’utilisateur ayant créé la Tour.

Le backend peut exposer une projection calculée :

```text
creator
```

dans la Resource, sans nouvelle colonne DB.

---

# 24. Ownership de la DRAFT

Règle métier obligatoire :

> tant que `Tour.status = DRAFT`, seul son créateur peut modifier cette tournée.

Pendant la DRAFT :

```text
draftOwner = créateur récupéré depuis AuditLog
current planner = draftOwner
```

Afficher :

```text
Créée par : Sara Amrani
En cours de planification par : Sara Amrani
```

Cela ne signifie pas "online".

C’est la réservation métier du brouillon.

---

# 25. Autre utilisateur ouvre une DRAFT

Si Badr ouvre une DRAFT créée par Sara :

afficher :

```text
Planification en cours par Sara Amrani
Mode lecture seule
```

Interdire :

```text
drag
drop
ajouter service
retirer service
reorder
changer provider
changer driver
changer vehicle
modifier périodes
valider
annuler
```

La protection doit être backend, pas uniquement UI.

---

# 26. Fin de l’exclusivité

L’exclusivité prend fin lorsque le créateur :

```text
valide
ou
annule
```

Après validation :

un autre utilisateur ayant les permissions nécessaires peut modifier la tournée réelle selon les règles métier du backend.

Ne pas conserver un verrou permanent.

---

# 27. Validation d’une DRAFT

Créer :

```text
Valider la planification
```

Le backend applique la transition réelle autorisée.

Typiquement :

```text
DRAFT -> PLANNED
```

si c’est le workflow final.

Important :

```text
même Tour.id
mêmes TourStop
mêmes TourStopService
mêmes TourPeriod
```

Ne pas copier les données vers une nouvelle Tour.

---

# 28. Validation transactionnelle

Pseudo workflow :

```text
BEGIN
lock Tour
check Tour.status == DRAFT
check current user == owner
check active assignments
check Provider / Driver / Vehicle
check Organization
check addresses/geocoding
check stops
recalculate totals
recalculate route
validate statuses
change status
write AuditLog
COMMIT
```

Ne pas faire une simple modification frontend du status.

---

# 29. Annulation DRAFT

Créer :

```text
Annuler la planification
```

Utiliser :

```text
Tour.status = CANCELLED
```

si la transition est autorisée.

Ne jamais supprimer l’historique.

Les affectations actives de cette DRAFT doivent être désactivées selon la logique backend et les services redeviennent planifiables si leur workflow l’autorise.

---

# 30. Replanification — besoin fondamental

Un même `OrderService` peut être planifié plusieurs fois au cours de son histoire.

Exemple :

```text
OrderService DELIVERY
→ Tour TR-OLD
→ client absent
→ ancienne affectation historique
→ service réactivé
→ Tour TR-NEW
```

Ne jamais supprimer `TR-OLD` ni son affectation.

---

# 31. `isActiveAssignment`

Utiliser :

```text
TourStopService.isActiveAssignment
```

pour distinguer :

```text
affectation active
affectation historique
```

Exemple :

```text
TSS-OLD.isActiveAssignment = false
TSS-NEW.isActiveAssignment = true
```

---

# 32. Une seule affectation active par OrderService

Règle obligatoire :

```text
un OrderService
→ plusieurs TourStopService historiques possibles
→ maximum 1 avec isActiveAssignment = true
```

Le backend doit protéger cette règle transactionnellement.

Utiliser un verrou DB adapté, par exemple :

```text
lockForUpdate()
```

si cohérent avec l’architecture Laravel réelle.

En conflit :

```text
409 Conflict
```

---

# 33. Réactivation d’un service

Après :

```text
client absent
client ne répond pas
échec livraison
nouveau rendez-vous
```

le service peut redevenir planifiable si son workflow backend l’autorise.

Ne pas inventer automatiquement une transition.

Le frontend affiche :

```text
Réactiver pour planification
```

uniquement si la transition réelle le permet.

Ne pas créer de nouvel OrderService.

---

# 34. Ne pas replanifier tous les services automatiquement après échec

Exemple :

```text
LOAD déjà effectué
DELIVERY échouée
```

peut nécessiter uniquement :

```text
DELIVERY
```

à replanifier.

Chaque OrderService conserve son propre historique.

Ne pas réactiver toute la commande automatiquement après un échec.

---

# 35. Historique de planification dans la commande

Dans :

```text
/orders/:id
```

onglet Services :

pour chaque service ajouter :

```text
Historique de planification
```

Créer :

```text
OrderServicePlanningHistory
OrderServicePlanningHistoryItem
```

Afficher :

```text
Tour
Date
Stop
Adresse
Séquence
Status
Affectation active / historique
Créneau
Créateur/planificateur si disponible via AuditLog
```

---

# 36. Historique dans Tour Detail

Dans `/tours/:id`, afficher les services ayant appartenu à la Tour, même s’ils ont ensuite été replanifiés ailleurs.

Badge :

```text
Affectation actuelle
Historique / remplacée
```

selon `isActiveAssignment`.

---

# 37. MODE PLANNING NORMAL

Le mode Planning normal doit permettre :

```text
voir les commandes/services à planifier
voir les Tours DRAFT
drag une commande vers une tournée
drag un OrderService individuellement
réordonner les stops
insérer entre deux positions
retirer un service d’une DRAFT
reprendre une DRAFT plus tard
valider
annuler
```

---

# 38. Pool "À planifier"

Créer :

```text
UnplannedOrdersPanel
OrderPlanningCard
OrderServicePlanningItem
```

Les données du pool doivent être dérivées des :

```text
Order
OrderService
```

éligibles.

Ne pas créer `PlanningOrder` en DB.

---

# 39. OrderPlanningCard

Afficher :

```text
N° commande
Client
Nombre de services éligibles
Nombre d’adresses
Nombre de services de chargement
Poids
Volume
Packages
Créneaux
Etat de planification
```

Permettre :

```text
expand
```

pour voir les services.

---

# 40. Drag d’une commande complète — règle obligatoire

> **Lorsque l’utilisateur drag une commande complète vers une Tour DRAFT, TOUS ses OrderService éligibles doivent être planifiés automatiquement.**

Ne pas ouvrir un modal demandant de sélectionner les services.

Workflow :

```text
drag Order
→ backend récupère tous ses OrderService éligibles
→ vérifie les conflits
→ géocode les adresses manquantes si nécessaire
→ groupe/crée les TourStop
→ crée tous les TourStopService
→ recalcule sequence
→ recalcule totals
→ recalcule distance/temps
→ retourne Tour DRAFT actualisée
```

---

# 41. Bulk planning = transaction unique

Le drag d’une commande ne doit pas exécuter un appel HTTP par service.

Utiliser une mutation transactionnelle unique.

Pseudo backend :

```text
BEGIN
lock Tour DRAFT
check owner
lock OrderServices
check eligibility
check active assignments
resolve addresses
generate/reuse stops
create assignments
recalculate sequence
recalculate totals
calculate routing
COMMIT
```

En erreur bloquante :

```text
ROLLBACK
```

---

# 42. Services non éligibles pendant drag Order

Le contrat backend doit définir le comportement.

Deux approches possibles :

```text
A. tout ou rien
B. planifier les éligibles + retourner les refusés
```

Ne pas décider silencieusement.

Documenter la règle finale dans `phase-5-analysis.md`.

---

# 43. Drag d’un seul OrderService

L’utilisateur peut également :

```text
expand Order
→ drag un seul OrderService
```

Le backend applique les mêmes règles :

- éligibilité ;
- active assignment ;
- grouping ;
- géocodage ;
- route recalculation ;
- ownership.

---

# 44. Source de vérité de la position

La position réelle dans la tournée est :

```text
TourStop.sequence
```

Ne pas créer :

```text
Order.position
TourOrder
TourOrderPosition
commandOrder[]
```

comme persistance métier.

---

# 45. Insertion entre deux positions

Exemple :

```text
1 CMD1
2 CMD2
3 CMD3
```

L’utilisateur drag CMD4 entre CMD1 et CMD2.

Résultat visuel :

```text
1 CMD1
2 CMD4
3 CMD2
4 CMD3
```

La persistance réelle met à jour :

```text
TourStop.sequence
```

---

# 46. Reorder transactionnel

Créer/utiliser une mutation bulk de reorder.

Le backend doit :

1. verrouiller la Tour ;
2. vérifier owner DRAFT ;
3. vérifier que tous les stops appartiennent à la même Tour ;
4. vérifier les sequences ;
5. appliquer l’ordre complet ;
6. recalculer les périodes/route si nécessaire ;
7. commit.

Ne pas faire plusieurs PATCH indépendants.

---

# 47. Optimistic UI

Le drag peut être optimiste :

```text
drop local
→ mutation
→ success
```

En cas :

```text
403
409
422
```

faire rollback et refetch.

---

# 48. Une commande peut produire plusieurs TourStop

Exemple :

```text
CMD1
LOAD Depot
DELIVERY Client
MONTAGE Client
```

La commande n’a pas une "position unique" en DB.

Ses services peuvent appartenir à plusieurs stops.

Le drag d’une Order exprime une **intention de position**.

Le backend retourne l’ordre final compatible avec les règles de grouping.

---

# 49. Stops partagés

Un TourStop peut regrouper plusieurs OrderService venant :

- d’une même commande ;
- de plusieurs commandes.

Un stop partagé est **un seul élément de sequence**.

Ne pas permettre de déplacer un seul service comme si le stop partagé appartenait exclusivement à cette commande.

---

# 50. Réordonnancement et stops partagés

Exemple :

```text
Stop 1 = LOAD partagé CMD1 CMD2 CMD3
Stop 2 = DELIVERY CMD1
Stop 3 = DELIVERY CMD2
Stop 4 = DELIVERY CMD3
```

Ajouter CMD4 :

```text
LOAD CMD4 -> rejoint Stop1
DELIVERY CMD4 -> insertion entre Stop2 et Stop3
```

Résultat :

```text
Stop1 = LOAD CMD1 CMD2 CMD3 CMD4
Stop2 = DELIVERY CMD1
Stop3 = DELIVERY CMD4
Stop4 = DELIVERY CMD2
Stop5 = DELIVERY CMD3
```

---

# 51. Identification des services de chargement

Le modèle possède :

```text
Service.code
Service.name
```

Il ne possède pas un enum `LOAD` officiel dans l’UML.

Ne pas créer :

```text
ServiceType
LOAD enum
```

Analyser les services réellement configurés.

Documenter :

```text
Services de chargement reconnus
```

| Service ID | Code | Nom | Loading service ? |
|---|---|---|---|

Le code réel du service de chargement doit venir de la DB/configuration validée.

---

# 52. Une commande peut avoir plusieurs services de chargement

Exemple valide :

```text
CMD100
LOAD 1 -> Warehouse A
LOAD 2 -> Warehouse B
DELIVERY -> Client C
```

Ce sont trois `OrderService` distincts.

Ne pas créer :

```text
Order.loadingAddress
```

---

# 53. Deux LOAD même commande, même adresse

Si :

```text
LOAD1 -> Address A
LOAD2 -> Address A
```

et date/créneaux compatibles :

```text
1 TourStop
2 TourStopService
```

Ne pas fusionner les deux services.

---

# 54. LOAD de plusieurs commandes, même adresse

Si :

```text
CMD1 LOAD -> Address A
CMD2 LOAD -> Address A
CMD3 LOAD -> Address A
```

et :

```text
même Tour
même Address
même date
créneaux compatibles
```

résultat :

```text
1 TourStop
3 TourStopService
```

---

# 55. Même adresse mais créneaux incompatibles

Exemple :

```text
LOAD1 Address A 08:00-09:00
LOAD2 Address A 16:00-17:00
```

Si la règle backend considère ces créneaux incompatibles :

```text
2 TourStop
```

Ne pas grouper uniquement sur `addressId`.

---

# 56. Règle backend de grouping

Le regroupement doit considérer au minimum :

```text
Tour
Address
Date
créneaux compatibles
```

La logique exacte reste backend.

Le frontend ne doit pas reproduire seul l’algorithme métier.

---

# 57. groupingKey

`TourStop` possède :

```text
groupingKey
```

Le backend doit générer/valider cette clé.

Ne pas inventer un format incompatible dans React.

---

# 58. generationMode

`TourStop` possède :

```text
generationMode
```

Utiliser uniquement les codes réellement présents dans le backend/status/configuration.

Ne pas inventer un enum.

---

# 59. Point de départ obligatoire = Depot

Chaque Tour possède :

```text
depotId
```

Le Depot de la Tour est le **point de départ opérationnel**.

Le point initial de route est :

```text
Depot
→ adresse par relation existante
→ latitude/longitude
```

Le diagramme partagé utilise `EntityAddress`.

Ne pas inventer un champ `depot.addressId` si la relation réelle passe par :

```text
EntityAddress
```

Utiliser l’adresse par défaut / type validé du Depot.

---

# 60. START_POINT n’est pas une nouvelle table

Ne pas créer :

```text
TourStartPoint
StartStop
```

Le point de départ est dérivé du Depot.

---

# 61. Affichage du départ

Toujours afficher :

```text
DÉPART
Depot Marrakech
```

avant les TourStops.

Exemple :

```text
Départ — Depot Marrakech

Stop 1
Chargement Depot Marrakech

Stop 2
Client A
```

---

# 62. Chargement à la même adresse que le Depot

Règle obligatoire :

si des services de chargement ont la même adresse que le Depot de départ et des créneaux compatibles :

ils doivent former **le premier TourStop**.

Exemple :

```text
START = Depot A

Stop 1 = Depot A
├── CMD1 LOAD
├── CMD2 LOAD
└── CMD3 LOAD
```

Ne pas supprimer le stop parce qu’il est à la même adresse que START.

Le stop est nécessaire pour porter les `TourStopService`.

---

# 63. Si aucun LOAD au Depot

Exemple :

```text
START = Depot A
LOAD = Warehouse B
DELIVERY = Client C
```

Résultat :

```text
Depot A
↓
Stop1 Warehouse B
↓
Stop2 Client C
```

---

# 64. Drag commande avec LOAD au Depot

Exemple :

```text
CMD4
LOAD -> Depot A
DELIVERY -> Client D
```

Même si l’utilisateur drag CMD4 entre Stop2 et Stop3 :

```text
LOAD -> rejoint Stop1 Depot
DELIVERY -> inséré selon intention/contraintes
```

Le backend retourne l’ordre réel.

---

# 65. Drag commande avec plusieurs LOAD

Exemple :

```text
CMD5
LOAD -> Depot
LOAD -> Warehouse B
DELIVERY -> Client C
```

Après drag :

```text
LOAD Depot -> Stop1 partagé
LOAD Warehouse B -> stop existant ou nouveau
DELIVERY C -> stop existant ou nouveau
```

Une même Order peut donc générer plusieurs stops non contigus.

---

# 66. MODE MAPS

Le mode MAPS doit afficher :

- le Depot de départ ;
- les commandes/services planifiables ;
- les Tours DRAFT ;
- les stops d’une Tour ;
- leur ordre ;
- leurs distances/temps lorsque calculés.

---

# 67. Coordonnées Address

Le modèle partagé `Address` contient :

```text
latitude
longitude
```

Utiliser ces champs.

Ne pas stocker de coordonnées supplémentaires dans :

```text
TourStop
OrderService
Tour
Depot
```

si Address est la source prévue.

---

# 68. Marker Depot

Afficher un marker distinct :

```text
DEPOT / DÉPART
```

Ne pas le confondre avec un client.

---

# 69. Markers des commandes

Le besoin est de voir les commandes sur la carte.

Mais une Order peut avoir plusieurs adresses.

Utiliser une projection logique par :

```text
Order + Address
```

ou une projection backend équivalente.

Ne pas forcer 1 Order = 1 marker si elle possède plusieurs adresses.

---

# 70. Marker partagé par adresse

Si plusieurs commandes ont des services à la même adresse :

le marker peut afficher :

```text
Warehouse A
8 commandes
12 services
```

Puis lister :

```text
CMD1
  LOAD
CMD2
  LOAD
CMD3
  LOAD
```

C’est une projection UI, pas un TourStop avant planification.

---

# 71. Clic marker

Afficher :

```text
Commande
Client
Adresse
Services
Statuts
Créneaux
Poids
Volume
Packages
Instructions
Historique de planification si disponible
```

Actions :

```text
Planifier ce service
Planifier toute la commande
```

---

# 72. Planifier toute la commande depuis MAPS

Même règle que le drag normal :

```text
Planifier toute la commande
= tous les OrderService éligibles
```

Ne pas ouvrir un sélecteur de services.

Utiliser la même mutation backend bulk.

---

# 73. MAPS et Planning utilisent la même DRAFT

Exemple :

```text
Planning
→ drag CMD1 dans TR-001

passage MAPS
→ CMD1 est déjà dans TR-001

MAPS
→ planifier CMD2

retour Planning
→ CMD2 est déjà présent
```

Ne pas maintenir deux brouillons.

---

# 74. MAPS sans coordonnées

Ne jamais afficher une adresse inconnue à :

```text
0,0
```

Avant marker/routing :

si :

```text
latitude IS NULL
OR longitude IS NULL
```

le backend doit tenter le géocodage.

---

# 75. API GPS de géocodage

L’URL ne doit pas être hardcodée.

Elle doit être enregistrée dans :

```text
configs
```

Réutiliser le schéma réel de `configs`.

Clé conceptuelle :

```text
GPS_GEOCODING_URL
```

Valeur actuelle :

```text
https://duperrex.mine.nu:8443/TRC_GPS_API_V2/api/values/getLocation
```

Si le projet utilise une convention de clé différente, respecter la convention.

---

# 76. Appel getLocation

Exemple fourni :

```text
GET https://duperrex.mine.nu:8443/TRC_GPS_API_V2/api/values/getLocation?adress=Paris
```

Attention :

le paramètre réel est :

```text
adress
```

avec cette orthographe.

Encoder correctement la valeur URL.

---

# 77. Réponse getLocation

Réponse XML :

```xml
<Result>
    <Lat>48.857170093</Lat>
    <Lng>2.3413999257</Lng>
</Result>
```

Le backend doit parser :

```text
Lat
Lng
```

Ne pas parser côté React.

---

# 78. GeocodingService backend

Créer/réutiliser un service technique, par exemple :

```text
GeocodingService
```

Responsabilités :

1. recevoir une Address ;
2. construire une chaîne d’adresse exploitable ;
3. lire l’URL depuis `configs` ;
4. appeler le service ;
5. parser le XML ;
6. valider Lat/Lng ;
7. mettre à jour la même Address ;
8. retourner les coordonnées.

Ne pas créer une nouvelle Address.

---

# 79. Construction de la chaîne d’adresse

Utiliser les champs existants lorsque disponibles :

```text
addressNumber
route
addressLine1
addressLine2
postalCode
city
town
country
```

Ne pas envoyer aveuglément `name` si le backend dispose d’une règle plus fiable.

Documenter la construction finale.

---

# 80. Quand géocoder

Géocoder seulement si :

```text
latitude == null
OR longitude == null
```

Une fois les coordonnées enregistrées :

```text
réutiliser la DB
```

Ne pas appeler l’API à chaque affichage.

---

# 81. Géocodage du Depot

Avant routing :

résoudre l’Address du Depot via les relations réelles (`EntityAddress` si applicable).

Si lat/lng manquent :

```text
geocode
→ update Address
```

Le point de départ doit avoir des coordonnées pour le calcul de route.

---

# 82. Erreurs géocodage

Gérer :

```text
timeout
HTTP error
XML invalide
Result absent
Lat absent
Lng absent
valeur invalide
```

Ne jamais inventer des coordonnées.

Retour UI :

```text
Adresse non géolocalisable.
Veuillez vérifier l’adresse ou réessayer.
```

---

# 83. Batch géocodage

MAPS peut contenir beaucoup d’adresses.

Ne pas envoyer 500 appels depuis React.

Le backend doit gérer :

```text
batch
queue
rate limit
lazy geocode
```

selon volume réel.

Pour un drag immédiat :

géocoder uniquement les adresses nécessaires à l’opération si besoin.

---

# 84. URL Routing dans configs

Stocker également :

```text
GPS_ROUTE_CALCULATION_URL
```

Valeur actuelle :

```text
https://duperrex.mine.nu:8443/TRC_GPS_API_V2/api/values/calculateRoute
```

Ne pas hardcoder dans React.

Ne pas dupliquer l’URL dans plusieurs classes PHP.

---

# 85. Profile route

Le profile fourni est :

```text
truckfast
```

Le stocker dans :

```text
configs
```

clé conceptuelle :

```text
GPS_ROUTE_PROFILE
```

Ne pas hardcoder le profile dans React.

---

# 86. Exemple calculateRoute

```text
GET https://duperrex.mine.nu:8443/TRC_GPS_API_V2/api/values/calculateRoute?profile=truckfast&waypoints=wy48.8566~2.3522*wy45.7640~4.8357
```

Construire :

```text
wy{lat}~{lng}*wy{lat}~{lng}...
```

selon l’API réelle.

---

# 87. Réponse calculateRoute

Réponse XML :

```xml
<Result>
    <Distance>465536</Distance>
    <TrafficTime>23611</TrafficTime>
    <BaseTime>23611</BaseTime>
    <TravelTime>23611</TravelTime>
</Result>
```

Parser :

```text
Distance
TrafficTime
BaseTime
TravelTime
```

dans Laravel.

---

# 88. RoutingService backend

Créer/réutiliser :

```text
RoutingService
```

Responsabilités :

1. lire URL depuis configs ;
2. lire profile depuis configs ;
3. recevoir une liste ordonnée de points ;
4. construire `waypoints` ;
5. appeler l’API ;
6. parser XML ;
7. retourner un DTO technique.

Exemple :

```text
distance
trafficTime
baseTime
travelTime
```

Ne pas créer une table `Route`.

---

# 89. Vérifier les unités

Avant de figer la conversion :

vérifier l’unité réelle auprès de l’API/legacy.

L’hypothèse la plus probable d’après les champs est :

```text
Distance -> mètres
Times -> secondes
```

mais le rapport d’analyse doit le confirmer.

Ne pas stocker des kilomètres dans `distanceMeters`.

---

# 90. Liste des points pour la tournée

L’ordre du calcul global est :

```text
Point 0 = Address du Depot
Point 1 = TourStop.sequence 1
Point 2 = TourStop.sequence 2
...
Point N = dernier TourStop
```

Ne pas ajouter automatiquement un retour au dépôt sans besoin métier explicite.

---

# 91. Recalcul route

Recalculer après :

```text
création/affectation Order
affectation OrderService
retrait d’une affectation
reorder
changement Depot
changement d’un point
validation DRAFT
```

Ne pas recalculer à chaque mouvement du curseur pendant le drag.

Déclencher après le `drop` confirmé.

---

# 92. Distance globale Tour

Utiliser le résultat réel pour :

```text
Tour.distanceMeters
```

si le backend/schéma le prévoit.

Ne pas calculer uniquement côté React.

---

# 93. Temps de conduite Tour

Utiliser le résultat réel pour :

```text
Tour.drivingTimeMinutes
```

La conversion dépend de l’unité confirmée.

Si seconds :

```text
TravelTime / 60
```

avec règle d’arrondi documentée.

---

# 94. Working time

Ne pas confondre :

```text
drivingTimeMinutes
workingTimeMinutes
```

`workingTimeMinutes` peut inclure :

- conduite ;
- service ;
- attente ;
- pauses.

Utiliser la logique backend réelle.

Ne pas mettre simplement `TravelTime` dans les deux colonnes.

---

# 95. Distance et temps entre chaque stop

Besoin obligatoire :

afficher entre chaque point :

```text
Distance
Temps
```

Exemple :

```text
DEPOT
  ↓ 12.4 km — 18 min
STOP 1

  ↓ 4.1 km — 8 min
STOP 2

  ↓ 23.7 km — 32 min
STOP 3
```

---

# 96. Calcul segmentaire

Si `calculateRoute` avec plusieurs waypoints ne retourne que le total :

calculer les segments par paire côté backend :

```text
Depot -> Stop1
Stop1 -> Stop2
Stop2 -> Stop3
...
```

Ne pas supposer que le résultat global contient un détail segmentaire.

---

# 97. Total à partir des segments

Si la stratégie pairwise est utilisée :

```text
Tour.distanceMeters = somme Distance segments
Tour.drivingTimeMinutes = somme TravelTime segments convertie
```

Vérifier la cohérence avec l’appel global si disponible.

---

# 98. TourPeriod pour les segments

Le modèle possède :

```text
TourPeriod.distanceMeters
plannedStartAt
plannedEndAt
periodType
```

Avant d’ajouter des colonnes à `TourStop`, analyser comment les périodes sont utilisées.

Approche recommandée si compatible :

```text
TourPeriod représentant déplacement
→ distanceMeters
→ plannedStartAt
→ plannedEndAt
```

Ne pas inventer un nouveau `periodType`.

Utiliser les valeurs backend réelles.

---

# 99. Si TourPeriod ne permet pas le temps segmentaire

Ne pas ajouter silencieusement :

```text
TourStop.travelTimeFromPrevious
```

Documenter le besoin.

Si une extension DB est réellement nécessaire :

arrêter cette partie et documenter la modification de conception avant migration.

---

# 100. Composant RouteSegmentInfo

Créer une projection UI :

```text
RouteSegmentInfo
```

entre les stops.

Afficher :

```text
distance
temps de trajet
éventuellement trafic si utile
```

Ce composant ne correspond pas à une nouvelle entité DB.

---

# 101. MAPS et route geometry

L’API fournie retourne :

```text
Distance
TrafficTime
BaseTime
TravelTime
```

mais pas de polyline.

Ne pas inventer une route routière dessinée.

Afficher :

- markers ;
- ordre ;
- distances ;
- temps.

Si un fournisseur de carte renvoie plus tard une geometry validée, elle pourra être utilisée.

---

# 102. Mode Planning normal — layout

Exemple :

```text
┌──────────────────────────┬──────────────────────────────────────┐
│ À planifier              │ TR-001 — DRAFT                     │
│                          │ Créée par Sara                      │
│ CMD-005                  │                                      │
│ CMD-006                  │ DÉPART : DEPOT A                    │
│ CMD-007                  │                                      │
│                          │ 1. DEPOT A — LOAD partagé           │
│                          │    CMD1, CMD2, CMD3                  │
│                          │                                      │
│                          │    7.6 km — 11 min                  │
│                          │                                      │
│                          │ 2. Client A — DELIVERY              │
└──────────────────────────┴──────────────────────────────────────┘
```

Le panneau Tour affiche les TourStops réels.

---

# 103. Stop de chargement au Depot

Exemple :

```text
START = Depot A

Stop1 Depot A
├── CMD1 LOAD
├── CMD2 LOAD
└── CMD5 LOAD

7.6 km — 11 min

Stop2 Warehouse B
└── CMD5 LOAD

8.4 km — 13 min

Stop3 Client C
└── CMD5 DELIVERY
```

Le segment START -> Stop1 peut être zéro.

---

# 104. Capacité véhicule

Réutiliser :

```text
Vehicle.payloadCapacity
Vehicle.volumeCapacity
Vehicle.palletCapacity
```

et Tour :

```text
totalWeight
totalVolume
totalPackages
```

Créer/réutiliser :

```text
VehicleCapacityBar
TourCapacitySummary
```

Afficher par exemple :

```text
Poids : 1200 / 1500 kg
Volume : 8.4 / 10 m³
Packages : 18
```

Ne pas créer `VehicleCapacity` comme entité.

---

# 105. Dépassement de capacité

Le frontend peut afficher :

```text
⚠ Capacité dépassée
```

à partir des données existantes.

Ne pas créer :

```text
capacityStatus
Conflict
```

en DB.

Le backend décide si le dépassement est bloquant.

---

# 106. TourPeriod / Timeline

Créer/réutiliser :

```text
TourTimeline
TourPeriodBlock
TourPeriodEditor
TourPeriodDetailDrawer
```

Afficher :

```text
periodType
sequence
plannedStartAt
plannedEndAt
actualStartAt
actualEndAt
breakMinutes
serviceMinutes
waitingMinutes
distanceMeters
internalRemark
status
```

La Timeline est une représentation des TourPeriod, pas une nouvelle table.

---

# 107. TourPeriodAssignment

Créer l’UI uniquement si nécessaire.

Afficher/lier :

```text
TourPeriod
TourStopService
Package facultatif
```

Ne pas créer d’entité supplémentaire.

---

# 108. Liste Tours

Créer :

```text
/tours
```

avec :

```text
TourListPage
TourTable
TourFilters
```

Colonnes selon API réelle :

```text
N° tournée
Date
Agence
Depot
Fournisseur
Chauffeur
Véhicule
Début prévu
Fin prévue
Poids
Volume
Packages
Clients
Distance
Temps conduite
Statut
Créateur si projection audit disponible
Actions
```

---

# 109. Fiche Tour

Créer :

```text
/tours/:id
```

Tabs recommandés :

```text
Résumé
Stops
Timeline
Assignments / Packages
Carte
Audit / Historique si UI existante
```

Ne pas créer de tab inutile.

---

# 110. Résumé Tour

Afficher :

```text
tourNumber
tourDate
agency
depot
provider
driver
vehicle
tourType
instructions
plannedStartAt
plannedEndAt
actualStartAt
actualEndAt
totalWeight
totalVolume
totalPackages
totalCustomers
distanceMeters
drivingTimeMinutes
workingTimeMinutes
status
creator
```

selon Resource réelle.

---

# 111. Tour Stops tab

Afficher les stops dans l’ordre :

```text
sequence
address
services
orders
customers
plannedArrivalAt
plannedDepartureAt
waitingMinutes
serviceMinutes
status
distance/temps depuis point précédent
```

---

# 112. Services dans un TourStop

Créer :

```text
TourStopServicesList
TourStopServiceItem
```

Afficher :

```text
Order
Customer
Service
OrderService
sequenceWithinStop
isActiveAssignment
status
packages si pertinent
```

---

# 113. Modification d’une Tour réelle

Pour :

```text
status != DRAFT
```

ne pas appliquer automatiquement les règles libres de DnD.

Les modifications dépendent :

- des permissions ;
- du statut ;
- des règles backend.

Ne pas permettre un drag libre simplement parce que le composant supporte DnD.

---

# 114. Drag entre Tours DRAFT

Autoriser uniquement selon règle validée.

Minimum :

```text
currentUser owner des deux DRAFT
```

Le backend doit déplacer/désactiver/réaffecter transactionnellement.

Ne pas créer deux active assignments.

---

# 115. Retrait d’un service d’une DRAFT

Si un TourStop contient encore d’autres services :

```text
TourStop reste
```

Si le stop devient vide :

le backend applique sa stratégie validée :

- suppression dans une DRAFT si aucun historique nécessaire ;
- ou conservation si audit/métier l’exige.

Puis compacter les sequences.

---

# 116. Recalcul après retrait

Après retrait :

```text
recalculate stops
recalculate totalWeight
recalculate totalVolume
recalculate packages
recalculate customers
recalculate route
recalculate driving time
```

---

# 117. API backend nécessaires

Analyser l’existant et compléter uniquement si nécessaire.

Capacités obligatoires :

```text
GET plannable Orders + OrderServices
GET MAPS projection
GET my DRAFT Tours
GET team DRAFT Tours si autorisé

POST create DRAFT Tour
GET DRAFT Tour detail
PATCH DRAFT Tour
POST assign whole Order = all eligible services
POST assign individual OrderService
POST bulk assign OrderServices
POST reorder TourStops
POST remove/deactivate assignment
POST validate DRAFT
POST cancel DRAFT

POST reactivate OrderService si workflow l’autorise
GET OrderService planning history

POST/GET backend geocode Address
POST/GET backend calculate route
```

Les noms exacts doivent suivre les conventions existantes.

Ne pas créer de nouvelles tables pour fournir ces endpoints.

---

# 118. Projection MAPS

Une Resource/projection technique dédiée est autorisée.

Exemple conceptuel :

```text
Order
OrderService[]
Address
latitude
longitude
planningState
activeAssignment
```

Cette projection n’est pas une entité DB.

---

# 119. Permissions

Analyser `PermissionSeeder`.

Ne pas inventer les codes.

Perms possibles à vérifier :

```text
planning.view
planning.manage
tours.view
tours.create
tours.update
tours.delete
tours.change_status
tour_stops.view
tour_stops.update
tour_stop_services.view
tour_stop_services.create
tour_stop_services.update
tour_periods.view
tour_periods.update
order_services.change_status
```

Utiliser uniquement les permissions réellement présentes.

---

# 120. Ownership > permission générique

Même si un user possède :

```text
planning.manage
```

il ne doit pas modifier la DRAFT d’un autre utilisateur.

DRAFT ownership est une règle métier supplémentaire.

---

# 121. SuperAdmin

Ne pas ajouter automatiquement :

```text
force unlock
take ownership
```

Si ce besoin arrive plus tard, il sera conçu explicitement.

Pour cette phase :

```text
creator valide ou annule
```

---

# 122. Concurrence backend

Avant chaque mutation DRAFT :

revalider :

```text
Tour.status
owner
Organization
OrderService eligibility
active assignment
current stops
```

Même si le frontend est ouvert depuis plusieurs minutes.

---

# 123. Deux users, même OrderService

User A affecte :

```text
OS-001 -> DRAFT A
```

User B essaie :

```text
OS-001 -> DRAFT B
```

Résultat :

```text
409
```

Aucune seconde affectation active.

Si permis, retourner :

```text
tourId
tourNumber
tourStatus
creator
```

pour afficher un message clair.

---

# 124. Multi-organisation

Toutes les données doivent être filtrées par Organization active.

Empêcher :

```text
Tour Organization A
Driver Organization B
Vehicle Organization B
Provider Organization B
OrderService Organization B
```

selon relations métier.

Au changement d’Organization, invalider :

```text
planning
maps
tours
tourStops
tourPeriods
providers
drivers
vehicles
statuses
```

---

# 125. Selects dépendants

Respecter :

```text
Organization
→ Agency
→ Depot
```

et :

```text
Provider
→ Driver
→ Vehicle
```

Ne pas permettre une combinaison incohérente.

---

# 126. API Layer

Créer :

```text
modules/planning/api/planning.api.ts
modules/tours/api/tours.api.ts
modules/tours/api/tour-stops.api.ts
modules/tours/api/tour-periods.api.ts
shared/api/geocoding.api.ts   // uniquement vers backend Tricolis si nécessaire
shared/api/routing.api.ts     // uniquement vers backend Tricolis si nécessaire
```

React n’appelle jamais directement l’API GPS externe.

---

# 127. Query keys

Créer :

```text
planningKeys
tourKeys
tourStopKeys
tourPeriodKeys
orderServicePlanningKeys
```

Exemples :

```text
planningKeys.plannable(filters)
planningKeys.map(filters)
tourKeys.list(filters)
tourKeys.detail(id)
tourKeys.stops(id)
tourKeys.periods(id)
tourKeys.myDrafts()
tourKeys.teamDrafts()
orderServicePlanningKeys.history(id)
```

---

# 128. Invalidation ciblée

Après :

```text
assign
bulk assign
reorder
remove
validate
cancel
reactivate
geocode
route recalc
```

invalider uniquement :

```text
Tour concernée
plannable Orders/Services
MAPS projection
planning history concerné
```

Ne pas invalider tout TanStack Query.

---

# 129. Types TypeScript

Créer selon Resources réelles :

```text
Tour
TourStop
TourStopService
TourPeriod
TourPeriodAssignment
PlanningOrder
PlanningOrderService
PlanningMapPoint
RouteSegment
RouteSummary
StatusDefinition
```

`PlanningOrder`, `PlanningMapPoint`, `RouteSegment` peuvent être des types de projection frontend/API.

Ils ne représentent pas de nouvelles tables.

---

# 130. Zod

Créer uniquement selon les formulaires réels :

```text
tourSchema
tourStopSchema
tourPeriodSchema
planningAssignmentSchema
reorderStopsSchema
```

Ne pas créer un gros schéma unique.

---

# 131. i18n

Ajouter :

```text
planning.*
tours.*
tourStops.*
tourPeriods.*
routing.*
geocoding.*
```

Les labels status viennent de `statuses`.

---

# 132. Design / UX

Respecter les Phases précédentes.

Réutiliser :

```text
AppLayout
DataTable
StatusBadge
StatusSelect
PermissionGuard
ProtectedRoute
AsyncSelect
Drawer
Dialog
Tabs
Cards
Timeline
LoadingSkeleton
EmptyState
ErrorState
```

Ne pas créer un nouveau design system.

---

# 133. Responsive

Desktop = cible principale.

Planning DnD doit rester utilisable sur laptop.

Tablette :

- colonnes simplifiées ;
- DnD ou actions alternatives si besoin ;
- MAPS + sidebar adaptative.

Ne pas sacrifier la fonctionnalité principale pour mobile.

---

# 134. Performance Planning

Ne pas charger :

```text
toutes les commandes historiques
tous les services historiques
toutes les Tours de toutes les dates
```

Utiliser :

```text
date
agency
depot
filters
pagination / infinite query
virtualization si nécessaire
debounced search
```

---

# 135. Performance DnD

Pendant le drag :

ne pas appeler le backend à chaque mouvement.

Mutation uniquement :

```text
au drop
```

Pour route recalculation :

```text
après mutation confirmée
```

---

# 136. Performance GPS

Ne pas géocoder toutes les adresses à chaque ouverture.

Réutiliser les coordonnées persistées.

Pour les adresses manquantes :

- batch contrôlé ;
- lazy processing ;
- queue si nécessaire.

---

# 137. Timeout / retry GPS

Configurer côté backend :

```text
timeout
retry limité
logging
```

Pas de retry infini.

Ne pas exposer de secrets au frontend.

---

# 138. Service GPS indisponible

Ne pas inventer les résultats.

Afficher :

```text
Distance/temps non disponibles
```

La DRAFT peut être sauvegardée si le métier l’autorise.

Documenter si la validation finale exige obligatoirement le routing.

---

# 139. Tests — modèle / statuses

Tester :

```text
Tour status valide via statuses
TourStop status valide
TourStopService status valide
TourPeriod status valide
status autre src refusé
status absent refusé
status_id jamais utilisé
```

---

# 140. Tests — DRAFT ownership

Sara crée DRAFT.

Badr tente :

```text
assign
remove
reorder
validate
cancel
```

Résultat :

```text
403/409
```

selon convention.

---

# 141. Test reprise lendemain

Créer DRAFT le jour J.

Nouvelle session jour J+1.

Vérifier :

```text
même Tour
mêmes Stops
mêmes Services
mêmes sequences
distance/time sauvegardés/recalculables
owner identique
```

---

# 142. Test validation

Après validation :

```text
Tour.id inchangé
TourStop.id inchangés
TourStopService.id inchangés
status != DRAFT
```

Pas de copie.

---

# 143. Test annulation

Après annulation DRAFT :

```text
Tour.status = CANCELLED
historique conservé
active assignments désactivées selon règle
services redeviennent éligibles si workflow le permet
```

---

# 144. Test replanification

Scénario :

```text
OS-001
→ TSS-OLD active
→ tentative échouée
→ réactivation
→ ancienne TSS inactive
→ nouvelle TSS active
```

Vérifier :

```text
2 TSS existent
1 seule active
```

---

# 145. Test drag Order = tous services

Given :

```text
CMD1 possède 4 services éligibles
```

Drag CMD1.

Vérifier :

```text
4 OrderService traités
aucun modal de sélection
TourStops correctement groupés
4 TourStopService actifs
```

---

# 146. Test drag OrderService individuel

Drag un seul service parmi 4.

Vérifier :

```text
seulement ce service
```

sans planifier automatiquement les 3 autres.

---

# 147. Test insertion

Initial :

```text
1 CMD1
2 CMD2
3 CMD3
```

Drag CMD4 entre 1 et 2.

Vérifier les sequences finales réelles.

Aucune duplicate sequence.

---

# 148. Test stop LOAD partagé

```text
CMD1 LOAD A
CMD2 LOAD A
CMD3 LOAD A
```

compatibles.

Résultat :

```text
1 TourStop
3 TourStopService
```

---

# 149. Test même Order deux LOAD même adresse

```text
CMD1
LOAD1 A
LOAD2 A
```

compatibles :

```text
1 TourStop
2 TourStopService
```

---

# 150. Test deux LOAD adresses différentes

```text
LOAD1 A
LOAD2 B
```

Résultat :

```text
2 TourStop
```

---

# 151. Test créneaux incompatibles

Même adresse, créneaux incompatibles.

Vérifier :

```text
2 TourStop
```

---

# 152. Test LOAD au Depot

```text
Tour Depot = A
CMD1 LOAD A
CMD2 LOAD A
```

Vérifier :

```text
START A
Stop1 A
2 TourStopService
sequence = 1
```

---

# 153. Test drag Order avec LOAD au Depot

CMD4 :

```text
LOAD A
DELIVERY D
```

drag en position intermédiaire.

Vérifier :

```text
LOAD rejoint Stop1 A
DELIVERY insérée selon intention
```

---

# 154. Test géocodage succès

Address sans coordonnées.

Mock :

```xml
<Result>
  <Lat>48.857170093</Lat>
  <Lng>2.3413999257</Lng>
</Result>
```

Vérifier :

```text
Address.latitude mis à jour
Address.longitude mis à jour
```

---

# 155. Test géocodage échec

Réponse sans Result.

Vérifier :

```text
aucune coordonnée inventée
pas de 0,0
erreur contrôlée
```

---

# 156. Test route parsing

Mock :

```xml
<Result>
  <Distance>465536</Distance>
  <TrafficTime>23611</TrafficTime>
  <BaseTime>23611</BaseTime>
  <TravelTime>23611</TravelTime>
</Result>
```

Vérifier parsing et unités validées.

---

# 157. Test route globale

Points :

```text
Depot
Stop1
Stop2
Stop3
```

Vérifier :

```text
Tour.distanceMeters
Tour.drivingTimeMinutes
```

selon résultat backend.

---

# 158. Test segments

Vérifier affichage :

```text
Depot -> Stop1
Stop1 -> Stop2
Stop2 -> Stop3
```

avec distance + temps.

---

# 159. Test reorder recalc route

Avant :

```text
Depot -> A -> B -> C
```

Après :

```text
Depot -> A -> C -> B
```

Vérifier recalcul.

Ne pas afficher les anciennes valeurs.

---

# 160. Test Planning ↔ MAPS

Planning :

```text
drag CMD1
```

MAPS :

```text
CMD1 déjà présent dans DRAFT
```

MAPS :

```text
planifier CMD2
```

Planning :

```text
CMD2 déjà présent
```

---

# 161. Test concurrence même service

User A affecte OS-001.

User B essaie le même service.

Résultat :

```text
409
aucune seconde active assignment
```

---

# 162. E2E complet

```text
Login
→ Organization
→ Planning
→ choisir date/agence/depot
→ créer Tour DRAFT
→ choisir Provider/Driver/Vehicle
→ voir Depot comme départ
→ drag CMD1
→ tous ses services sont planifiés
→ vérifier grouping LOAD
→ drag CMD2
→ LOAD même Depot rejoint Stop1
→ drag CMD3
→ reorder
→ distances/temps recalculés
→ passer MAPS
→ vérifier mêmes stops
→ fermer
→ revenir
→ reprendre DRAFT
→ valider
→ même Tour devient PLANNED
```

---

# 163. E2E replanification

```text
OrderService planifié dans TR-OLD
→ échec / client absent via workflow existant
→ service réactivé
→ ancienne affectation reste
→ service revient dans Planning/MAPS
→ nouvelle DRAFT
→ planification
→ validation
→ historique affiche TR-OLD + TR-NEW
```

---

# 164. AuditLog

Réutiliser le système existant.

Auditer selon conventions réelles au minimum :

```text
tour.created
tour.draft_updated
tour.order_assigned
tour.service_assigned
tour.service_removed
tour.stops_reordered
tour.route_recalculated
tour.validated
tour.cancelled
order_service.reactivated
order_service.replanned
address.geocoded
```

Ne pas créer `TourHistory`.

---

# 165. Audit configs GPS

Créer :

```text
docs/backend/gps-config-audit.md
```

Documenter :

```text
clé
valeur
scope
utilisation
```

Au minimum :

```text
GPS_GEOCODING_URL
GPS_ROUTE_CALCULATION_URL
GPS_ROUTE_PROFILE
```

ou les noms réels équivalents.

---

# 166. Audit statuses

Mettre à jour :

```text
docs/backend/statuses-global-audit.md
```

Ajouter :

```text
tours
tour_stops
tour_stop_services
tour_periods
```

Vérifier :

```text
status textuel
codes dans statuses
aucun status_id
```

---

# 167. Rapport d’analyse final avant code

`docs/frontend/phase-5-analysis.md` doit contenir au minimum :

1. champs réels des 5 classes ;
2. relations ;
3. endpoints ;
4. permissions ;
5. status codes ;
6. status sources ;
7. Tour DRAFT ;
8. ownership ;
9. AuditLog creator ;
10. replanification ;
11. isActiveAssignment ;
12. active assignment uniqueness ;
13. drag Order = all services ;
14. drag service individuel ;
15. bulk API ;
16. reorder ;
17. grouping ;
18. services LOAD identifiés ;
19. multi-LOAD ;
20. Depot start ;
21. address resolution Depot ;
22. geocoding configs ;
23. getLocation XML ;
24. routing configs ;
25. calculateRoute XML ;
26. unités ;
27. routing segments ;
28. TourPeriod strategy ;
29. performance ;
30. erreurs ;
31. tests.

---

# 168. Rapport final Phase 5

Créer :

```text
docs/frontend/phase-5-final-report.md
```

Inclure :

1. branche de base ;
2. branche Phase 5 ;
3. Git Author ;
4. Git Committer ;
5. confirmation absence Claude/Anthropic ;
6. Planning normal ;
7. MAPS ;
8. DnD Order ;
9. DnD OrderService ;
10. all-services planning ;
11. Tour DRAFT ;
12. persistence DRAFT ;
13. ownership ;
14. validation ;
15. annulation ;
16. replanification ;
17. historique ;
18. isActiveAssignment ;
19. grouping LOAD ;
20. multi-LOAD ;
21. Depot start ;
22. first loading stop ;
23. geocoding ;
24. configs GPS ;
25. routing ;
26. distance totale ;
27. driving time ;
28. segments inter-stop ;
29. TourPeriod ;
30. capacities ;
31. statuses ;
32. query keys ;
33. API layer ;
34. tests ;
35. E2E ;
36. différences UML/DB ;
37. APIs manquantes ;
38. risques ;
39. prochaine phase.

Conclusion obligatoire :

```text
FRONTEND_PHASE_5_READY
```

ou :

```text
FRONTEND_PHASE_5_NOT_READY
```

Ne pas déclarer READY si les tests échouent.

---

# 169. Interdictions absolues

Ne pas :

- créer VirtualTour ;
- créer DraftTour ;
- créer PlanningSession ;
- créer PlanningLock ;
- créer PlanningBoard en DB ;
- créer PlanningSlot en DB ;
- créer TourOrder ;
- créer Order.position ;
- créer TourOrderPosition ;
- créer Route ;
- créer RouteGeometry ;
- créer Conflict ;
- créer VehicleCapacity ;
- créer ServiceType uniquement pour LOAD ;
- créer un enum LOAD absent du modèle ;
- hardcoder le code LOAD sans vérifier les services réels ;
- planifier seulement une partie des services lors du drag d’une Order complète ;
- ouvrir un sélecteur de services après drag Order ;
- exécuter un POST par service pour le drag d’une Order ;
- supprimer une ancienne TourStopService lors de replanification ;
- dupliquer OrderService pour replanifier ;
- autoriser plusieurs active assignments pour le même service ;
- copier une DRAFT vers une nouvelle Tour à la validation ;
- stocker une DRAFT uniquement dans React/localStorage ;
- permettre à un autre user de modifier la DRAFT ;
- ajouter createdBy/planningUserId/lockedBy à Tour sans validation ;
- créer TourStop.type pour LOAD ;
- créer un stop par commande lorsque les LOAD sont compatibles ;
- fusionner plusieurs OrderService en un seul ;
- considérer une Order comme ayant une seule position DB ;
- déplacer arbitrairement une partie d’un stop partagé ;
- créer des sequences dupliquées ;
- faire plusieurs PATCH non transactionnels pour reorder ;
- oublier le Depot comme point de départ ;
- supprimer le Stop1 LOAD parce qu’il est au Depot ;
- stocker lat/lng sur TourStop si Address est la source ;
- mettre 0,0 pour une adresse non géocodée ;
- appeler les services GPS directement depuis React ;
- hardcoder les URLs GPS dans React ;
- hardcoder les URLs dans plusieurs services Laravel ;
- appeler le routing à chaque pixel du DnD ;
- inventer une polyline ;
- ajouter distanceFromPrevious/travelTimeFromPrevious à TourStop sans conception ;
- mettre TravelTime directement dans workingTimeMinutes sans logique métier ;
- créer status_id ;
- hardcoder labels/couleurs status ;
- inventer des permissions ;
- pousser automatiquement ;
- attribuer le commit à Claude/Anthropic ;
- laisser des TODO.

---

# 170. Vérifications finales

Frontend :

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

E2E si configuré :

```bash
npm run test:e2e
```

Backend :

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
php artisan migrate:status
```

Vérifier les configs :

```text
GPS_GEOCODING_URL
GPS_ROUTE_CALCULATION_URL
GPS_ROUTE_PROFILE
```

Vérifier DB :

```text
Address.latitude
Address.longitude
Tour.distanceMeters
Tour.drivingTimeMinutes
TourStop.sequence
TourStopService.isActiveAssignment
tours.status
tour_stops.status
tour_stop_services.status
tour_periods.status
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

Ne pas commencer la phase suivante sans validation explicite de l’utilisateur.
