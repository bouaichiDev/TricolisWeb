# Tricolis V2 — FRONTEND PHASE 10 MASTER FINAL

## Consolidation finale du Backoffice + Hardening + Cohérence globale + Sécurité + Performance + E2E + Release Readiness

> Source unique de travail pour la Frontend Phase 10.
>
> Les Phases 1 à 9 sont supposées implémentées sur leurs branches validées.
> Cette phase ne crée **aucune nouvelle entité métier**. Elle audite, corrige, unifie, sécurise, optimise, teste et documente l'ensemble du Backoffice.

# 0. Règle absolue

```text
PHASE 10
=
AUDITER
+ CORRIGER
+ UNIFIER
+ SÉCURISER
+ OPTIMISER
+ TESTER
+ DOCUMENTER
```

Interdit :

```text
nouvelle table métier pour résoudre un problème d'UI
nouvelle entité métier
nouveau workflow métier non validé
```

# 1. Sources de vérité

Ordre obligatoire :

```text
1. Schéma DB réellement validé
2. Backend final réellement implémenté
3. Diagrammes officiels finaux
4. Frontend réellement livré Phases 1→9
5. Documentation des phases
6. Legacy uniquement pour analyse
```

Diagrammes :

```text
Conception/diagramme/00-diagramme-classes-partagees.puml
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

# 2. Audit initial

Avant code :

```bash
git status
git branch --show-current
git log --oneline --decorate -30
```

Créer :

```text
docs/frontend/phase-10-initial-audit.md
```

Matrice obligatoire :

| Phase | Domaine                                 | Backend | Frontend | Permissions | Tests | E2E | Statut |
| ----- | --------------------------------------- | ------- | -------- | ----------- | ----- | --- | ------ |
| 1     | Administration / Customers              |         |          |             |       |     |        |
| 2     | Orders / Catalogs / Packages / Services |         |          |             |       |     |        |
| 3     | Tracking / POD / Claims                 |         |          |             |       |     |        |
| 4     | Providers / Drivers / Vehicles          |         |          |             |       |     |        |
| 5     | Planning / Tours / Maps                 |         |          |             |       |     |        |
| 6     | Pricing / Billing / Settlements         |         |          |             |       |     |        |
| 7     | Stock                                   |         |          |             |       |     |        |
| 8     | Integrations                            |         |          |             |       |     |        |
| 9     | Templates / Communications              |         |          |             |       |     |        |

# 3. Git

Base :

```bash
git checkout <BRANCHE_PHASE_9_V2_VALIDEE>
git checkout -b feature/frontend-phase-10-final-consolidation
```

Ne pas partir de `main` si les phases frontend n'y sont pas encore fusionnées.

Avant commit :

```bash
git config user.name
git config user.email
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
```

Interdit dans l'identité/message :

```text
Badr
Badr
Co-authored-by: Badr
Generated-by: Badr
```

Si identité inconnue :

```bash
git log --all --format='%an <%ae>' | sort -u
```

Ne jamais inventer l'email.

Pas de merge automatique. Pas de push automatique.

---

# 4. Audit Phase 1 — Administration

Vérifier :

```text
Organizations
Users
Roles
Permissions
Agencies
Depots
Customers
Customer Sites
Addresses
Contacts
Documents
Audit
```

Sécurité :

```text
PLATFORM → SuperAdmin

ORGANIZATION
├── Owner
├── Admin
├── Planner
├── Bureau
└── rôles custom
```

L'autorisation ne dépend jamais du nom du rôle.

Interdit :

```ts
role.name === "Admin";
```

Utiliser :

```text
permissions
scope
isSystem
organization context
```

Un seul contexte cohérent pour :

```text
Organization active
Agency active
Depot active
```

Au changement d'Organization :

```text
reset selections
invalidate queries scoped
reload permissions
reload menus
reload statuses contextualisés
```

Aucune donnée de l'ancienne Organization ne doit rester visible.

# 5. Audit Phase 2 — Orders

Vérifier :

```text
Catalogs
CustomerCatalogItems
Orders
OrderLines
Packages
OrderServices
OrderServiceContacts
Documents
History
Services
Package Types
Grouping Types
```

Règle finale :

```text
PAS de OrderStop
```

Architecture :

```text
Order
└── OrderService
     ├── Address
     ├── Contacts
     └── Packages
```

Planning :

```text
Tour
└── TourStop
     └── TourStopService
          └── OrderService
```

Supprimer tout runtime `OrderStop`.

Wizard final :

```text
1. Informations
2. Lignes / Articles
3. Colis
4. Services
5. Vérification
```

# 6. Audit Phase 3

Vérifier :

```text
TrackingEvent
ProofOfDelivery
Claim
OrderCommunication manuel
CommunicationAttachment
Documents
```

Mais migrer tout ancien code :

```text
CommunicationTemplate
communication_templates
CommunicationTemplateType
communicationTemplateKeys
/communication-templates
```

vers :

```text
Template
templates
TemplateType
templateKeys
/templates
```

Aucun module parallèle durable.

---

# 7. Audit Phase 4 — Ressources

Vérifier :

```text
Provider
Driver
VehicleType
Vehicle
```

Ne pas inventer :

```text
ProviderContract
DriverAvailability
VehicleAvailability
VehicleMaintenance
```

si absents du backend final.

Relations selon DB réellement validée :

```text
Organization → Provider
Provider → Driver
Provider → Vehicle
VehicleType → Vehicle
```

# 8. Audit Phase 5 — Planning

Entités strictes :

```text
Tour
TourStop
TourStopService
TourPeriod
TourPeriodAssignment
```

Confirmer :

```text
Tour.status = DRAFT
```

pour planning virtuel persisté.

Pas de :

```text
VirtualTour
DraftTour
PlanningSession
```

Ownership :

```text
créateur DRAFT → modifiable
autres → read-only selon permissions
```

backend enforcement obligatoire.

Replanification :

```text
TSS ancien.isActiveAssignment = false
TSS nouveau.isActiveAssignment = true
```

Un `OrderService` possède au maximum un assignment actif.

Test concurrence → un seul gagne.

Drag Order :

```text
→ tous les OrderServices éligibles
```

sans modal de choix.

LOAD grouping :

```text
même Tour
+ même Address
+ date compatible
+ créneau compatible
→ même TourStop
```

Ne pas créer `TourStop.type`.

Route :

```text
Point 0 = Depot
Stop1...
```

Si LOAD même Address que Depot :

```text
Stop1 = chargement Depot
```

Geocoding :

```text
Address.latitude/longitude null
→ backend geocode
→ update même Address
```

Routing externe appelé uniquement par backend.

Distance/temps total et segments visibles.

# 9. Audit Phase 6 — Pricing

Vérifier le modèle réellement validé :

```text
PriceList
PriceRule
PriceRuleCondition
PriceMatrix
PriceMatrixRow
CustomerPriceList
PricingCalculation
```

Si le diagramme officiel n'est pas encore aligné, corriger la documentation/conception avant READY.

Fallback obligatoire :

```text
Customer rule/matrix compatible
        ↓ absent
GLOBAL compatible
        ↓ absent
Tarif non configuré
```

Jamais `0` silencieux.

Formule obligatoire. Matrice optionnelle.

Exemple :

```text
({P:poids}/{V:100})*{V:25}
```

Parser sécurisé uniquement :

```text
pas eval
pas PHP
pas SQL
pas JS
pas shell
```

Tester :

```text
division zéro
variable inconnue
overflow
syntaxe invalide
injection
```

`PricingCalculation` doit expliquer l'origine du prix.

Préfacturation :

```text
/billing/prebilling
```

Afficher :

```text
Order
Customer
Service
variables
formule
scope CUSTOMER/GLOBAL
prix
état
```

---

# 10. Audit Phase 6 — Billing

Vérifier :

```text
Invoice
InvoiceLine
InvoiceLineAddressSnapshot
ProviderSettlement
ProviderSettlementLine
```

Règles :

```text
OrderService → max 1 InvoiceLine
OrderService → max 1 ProviderSettlementLine
```

Liste facture :

```text
Actions
├── Voir
├── Modifier
└── Clôturer
```

`Modifier` directement dans la liste si facture modifiable.

Invoice :

```text
DRAFT → CLOSED
```

selon workflow final.

Après `CLOSED` :

```text
immutable
```

Aucun recalcul rétroactif.

# 11. MODIFICATION PHASE 9 APPLIQUÉE À LA PHASE 6 — Templates facture

La Phase 10 doit vérifier que la décision Phase 9 V2 est réellement appliquée.

Une seule entité :

```text
Template
```

Une seule table :

```text
templates
```

Interdit :

```text
InvoiceTemplate
invoice_templates
CustomerInvoiceTemplate
InvoiceTemplateRenderer
```

Modèle final à vérifier :

```text
Template
- id
- organizationId
- customerId nullable
- serviceId nullable
- code
- name
- channel nullable
- templateType
- subjectTemplate nullable
- bodyTemplate
- language
- availableVariables
- isDefault
- isActive
- timestamps
```

`TemplateType` contient :

```text
INVOICE
```

Pour `INVOICE` :

```text
serviceId = null
channel = null
subjectTemplate = null
```

selon conception finale.

Résolution :

```text
Customer INVOICE Template
        ↓ absent
Global INVOICE Template
```

Jamais template d'un autre Customer.

Un seul :

```text
TemplateRenderer
```

pour communications + factures.

PDF :

```text
Template INVOICE
```

JSON/XML :

```text
Export DTO + mapping
```

Ne jamais utiliser HTML facture comme mapping JSON/XML.

Test historique obligatoire :

```text
Invoice A CLOSED avec Template V1
→ rendu A

modifier Template vers V2

Invoice A
→ toujours rendu A

nouvelle Invoice B
→ V2
```

# 12. Invoice Export

Conserver :

```text
Invoice CLOSED
→ CustomerExportConfiguration
→ ExportJob
```

Si réseau externe échoue :

```text
Invoice reste CLOSED
ExportJob FAILED
```

Retry uniquement du job concerné.

---

# 13. Audit Phase 7 — Stock

Entités :

```text
StockItem
StockLocation
StockBalance
StockMovement
StockReservation
```

`StockBalance` :

```text
availableQuantity = quantity - reservedQuantity
```

Pas de CRUD direct.

`StockMovement` :

```text
immutable
transactionnel
```

Pas Edit/Delete.

Réservation :

```text
reserve
release
double release refused
concurrency
```

Isolation :

```text
OrderLine Customer == StockItem Customer
```

Tester :

```text
aucune quantité négative
reserved <= quantity
```

# 14. Audit Phase 8 — Integrations

Vérifier :

```text
CustomerImportConfiguration
CustomerApiConfiguration
CustomerExportConfiguration
ExportJob
```

API Key :

```text
clé claire affichée une seule fois
hash jamais exposé
rotation
ancienne clé invalidée
allowedIps
permissions
```

Secrets jamais exposés :

```text
encryptedPassword
storagePath
Authorization
API secret
```

Formats exacts :

```text
XML
CSV
JSON
PDF
```

Transports exacts :

```text
FTP
SFTP
REST_API
EMAIL
MANUAL
```

# 15. Audit Phase 9 — Templates & Communications

Modèle final :

```text
Template
CommunicationRule
OrderCommunication
CommunicationAttachment
```

Nettoyage global :

```bash
grep -R "CommunicationTemplate" .
grep -R "communication_templates" .
grep -R "communication-templates" .
grep -R "communicationTemplateKeys" .
grep -R "CommunicationTemplateType" .
```

Les occurrences restantes doivent être uniquement :

```text
migration historique
documentation historique explicitement marquée
```

Pas de runtime ancien.

API finale :

```text
GET    /api/v1/templates
POST   /api/v1/templates
GET    /api/v1/templates/{template}
PATCH  /api/v1/templates/{template}
DELETE /api/v1/templates/{template}
```

Un seul module UI :

```text
/templates
```

Navigation :

```text
Communication → Templates
Facturation → Templates de facture
```

mais même page/API/table avec filtres différents.

CommunicationRule :

```text
eventType
recipientRole
delayValue
delayUnit
conditions
isAutomatic
isActive
```

Events exacts :

```text
ORDER_CREATED
ORDER_CONFIRMED
ORDER_CANCELLED
SERVICE_PLANNED
APPOINTMENT_REQUESTED
APPOINTMENT_CONFIRMED
DRIVER_ASSIGNED
TOUR_STOP_APPROACHING
SERVICE_COMPLETED
POD_CREATED
CLAIM_CREATED
```

Channels :

```text
EMAIL
SMS
WHATSAPP
PUSH_NOTIFICATION
INTERNAL_NOTIFICATION
```

Recipient roles :

```text
CUSTOMER
LOAD_CONTACT
DELIVERY_CONTACT
BILLING_CONTACT
INTERNAL_USER
CUSTOM
```

Communication status :

```text
DRAFT
SCHEDULED
QUEUED
SENDING
SENT
DELIVERED
READ
FAILED
CANCELLED
```

Automation = backend Events/Listeners uniquement.

Jamais React.

---

# 16. Audit global statuses

Créer :

```text
docs/frontend/phase-10-statuses-audit.md
```

Pour toutes les tables ayant `status` :

| Source | Colonne textuelle | src statuses | Codes | API dynamique | Front dynamique |
| ------ | ----------------- | ------------ | ----- | ------------- | --------------- |

Règle :

```text
source.status = code texte
statuses = metadata/allowed codes
```

Interdit :

```text
status_id
statusId
```

si le domaine V2 validé utilise le status textuel.

Recherche :

```bash
grep -R "status_id" app database frontend src
grep -R "statusId" frontend src
```

Ne pas supprimer aveuglément les occurrences legacy : analyser.

Frontend :

```text
StatusBadge
StatusSelect
useStatuses(src)
```

Pas de tableaux hardcodés pour les status centralisés.

Ne pas mettre automatiquement dans `statuses` :

```text
CommunicationChannel
TemplateType
CommunicationEventType
RecipientRole
ExportFormat
ExportTransport
```

car ce ne sont pas des champs `status`.

# 17. Menus & Navigation

Créer :

```text
docs/frontend/phase-10-menu-audit.md
```

Pour chaque entrée :

```text
route existe
page existe
permission existe
backend API existe
```

Menu cible indicatif :

```text
Dashboard

Exploitation
├── Commandes
├── Services
├── Types de colis
└── Types de regroupement

Planning
├── Planification
└── Tournées

Clients
├── Clients
└── Catalogues

Ressources
├── Fournisseurs
├── Chauffeurs
├── Types de véhicules
└── Véhicules

Stock
├── Vue stock
├── Articles
├── Emplacements
├── Mouvements
└── Réservations

Facturation
├── Préfacturation
├── Factures
├── Tarification
├── Templates de facture
├── Décomptes fournisseurs
└── Exports

Communication
├── Templates
├── Règles automatiques
└── Historique

Intégrations
├── Imports
├── Accès API
├── Exports
└── Historique

Administration
├── Organisations
├── Agences
├── Dépôts
├── Utilisateurs
├── Rôles
├── Permissions
└── Audit
```

Adapter uniquement aux routes réelles.

Templates de facture et Templates communication utilisent le même module `/templates`.

# 18. Layout & UX globale

Un seul :

```text
AppLayout
AppSidebar
AppHeader
```

Uniformiser :

```text
breadcrumbs
PageHeader
EntityHeader
DataTable
loading
error
empty states
confirm dialogs
forms
```

Toutes les listes importantes :

```text
pagination serveur
search serveur
filters serveur
sort serveur
```

Éviter `fetch all → paginate browser` sur :

```text
Orders
Tours
Invoices
StockMovements
OrderCommunications
ExportJobs
AuditLogs
```

Filtres URL state quand pertinent.

Boutons sensibles désactivés pendant mutation pour éviter double submit.

---

# 19. Query Keys & API Layer

Créer :

```text
docs/frontend/phase-10-query-keys-audit.md
```

Identifier :

```text
duplicate query keys
keys non scoped
invalidations trop globales
objets instables dans key
double modules API
```

Un seul client HTTP.

Supprimer :

```text
fetch direct dans JSX
axios instances multiples
baseURL répétée
auth headers manuels
```

Après switch Organization : aucune query de l'ancienne Organization ne doit ressortir.

Invalidations ciblées uniquement.

# 20. Error handling

Uniformiser :

```text
401
403
404
409
422
500
network
```

401 :

```text
auth invalid → flow signin/refresh réel
```

403 :

```text
Accès refusé
```

404 :

utilisé aussi pour IDOR si convention backend.

409 :

```text
planning concurrent
stock concurrent
resource already changed
```

Refetch + message clair.

422 :

```text
field errors
nested errors
FormErrorSummary
```

500 :

pas de stack trace.

# 21. Permissions

Créer :

```text
docs/frontend/phase-10-permissions-audit.md
```

| Module | Action | Permission backend | Guard front | Policy test |
| ------ | ------ | ------------------ | ----------- | ----------- |

Aucune autorisation par nom de rôle.

Recherche :

```bash
grep -R "role.*Admin" frontend src app
grep -R "SuperAdmin" frontend src
grep -R "Planner" frontend src
```

Les labels sont acceptables ; les conditions d'autorisation basées sur le nom ne le sont pas.

Si Resource expose :

```text
canEdit
canDelete
canRetry
allowedTransitions
allowsContentChanges
```

préférer ces capabilities.

# 22. IDOR / Multi-org

Tests globaux :

```text
Org A → Customer B
Org A → Order B
Org A → Tour B
Org A → Invoice B
Org A → Stock B
Org A → Template B
Org A → ExportJob B
Org A → Document B
```

Tester aussi les FK étrangères dans payload :

```text
customerId
providerId
driverId
vehicleId
agencyId
depotId
serviceId
templateId
documentId
stockLocationId
```

Customer isolation :

```text
Catalog
Stock
Pricing
Invoice
Export config
API config
Customer Template
```

Provider isolation :

```text
Driver
Vehicle
Tour
Settlement
```

---

# 23. Sécurité

Créer :

```text
docs/security/phase-10-security-audit.md
```

Couvrir :

```text
auth
authorization
IDOR
XSS
SSRF
secrets
uploads
API keys
TemplateRenderer
pricing parser
multi-org
```

Secrets à rechercher dans frontend/logs :

```text
apiKey
password
encryptedPassword
Authorization
token
storagePath
providerResponse
```

Interdit :

```text
console.log(secret)
localStorage API key
sessionStorage API key
VITE_* backend secret
```

Toute variable Vite = publique.

XSS audit :

```text
Template preview
Invoice preview
Communication body
Audit diff
Claim description
Instructions
```

Ne pas utiliser `dangerouslySetInnerHTML` sans sanitizer/stratégie contrôlée.

Template HTML preview doit empêcher scripts arbitraires.

Pricing parser et TemplateRenderer : aucun `eval`.

SSRF backend :

```text
REST export
FTP
SFTP
GPS proxy
```

Uploads :

```text
mime
extension
size
Organization
permission
```

# 24. Responsive & Accessibility

Créer :

```text
docs/frontend/phase-10-responsive-audit.md
docs/frontend/phase-10-accessibility-audit.md
```

Tester :

```text
1366px
1920px
tablet
mobile narrow
```

Backoffice peut être desktop-first, mais consultation utilisable sur tablette.

Accessibilité :

```text
keyboard
focus
labels
aria
dialogs
tables
form errors
contrast
icons names
```

Planning DnD : si possible alternative action menu pour utilisateurs sans souris.

# 25. Performance

Créer :

```text
docs/frontend/phase-10-performance-audit.md
```

Auditer :

```text
OrderList
OrderDetail
Planning
TourDetail
InvoiceList
Prebilling
StockBalances
StockMovements
CommunicationHistory
AuditLogs
```

Pour chaque :

```text
request count
payload
pagination
N+1 frontend
N+1 backend
bundle cost
```

Lazy load :

```text
Planning
Maps
Billing
Stock
Integrations
Communications
```

Éviter que Maps/PDF/DnD/editor chargent sur Login/Dashboard.

Search debounce raisonnable.

GPS/Route recalculation uniquement après mutation validée, jamais mousemove/render.

---

# 26. i18n / Dates / Currency / Decimal

Auditer textes hardcodés.

Statuses → metadata centrale.

Enums → labels i18n possibles.

Dates :

```text
API canonical
→ affichage timezone locale
```

Currency :

```text
Invoice.currencyCode
```

Ne jamais hardcoder MAD/CHF/EUR.

Decimals :

```text
prix
poids
volume
quantités
```

ne doivent pas perdre précision.

Séparer :

```text
API value
UI formatted value
```

# 27. TypeScript & API Contract

Exécuter :

```bash
npm run typecheck
```

Zéro erreur.

Éviter :

```ts
as any
unknown as ...
```

comme cache-misère.

Créer :

```text
docs/frontend/phase-10-api-type-audit.md
```

Comparer Types frontend et API Resources.

Rechercher champs fantômes :

```text
priority
billingStatus
OrderStop
legacy speculative fields
```

Lister toutes les routes frontend et comparer avec :

```bash
php artisan route:list --path=api/v1
```

Aucun endpoint fantôme.

Réutiliser/compléter :

```text
docs/frontend/backend-api-contract.md
```

avec :

```text
auth
Organization
pagination
errors
permissions
routes
enums
statuses
uploads
dates
filters
sorts
Template API finale
```

# 28. Template migration audit

Créer :

```text
docs/backend/phase-10-template-migration-audit.md
```

Vérifier :

```text
communication_templates → templates
aucune perte de données
IDs conservés
FK communication_rules.template_id conservées
FK order_communications.template_id conservées
indexes adaptés
permissions migrées
routes migrées
frontend migré
```

Recherche finale :

```text
0 runtime Model CommunicationTemplate
0 runtime table communication_templates
0 InvoiceTemplate
```

Sauf migration historique/documentation explicitement marquée.

Tester DB vide :

```bash
php artisan migrate:fresh
```

UNIQUEMENT DB locale/test.

Tester si possible upgrade :

```text
DB avant unification
→ migrations Phase 9
→ données intactes
```

---

# 29. Seeders / Factories / Indexes

Seeders :

```text
idempotents
no secrets
valid statuses
valid permissions
valid menus
```

Factories cohérentes :

```text
Organization
Customer
Provider
Order
Tour
Invoice
Stock
Template
Communication
Export
```

Aucune vraie donnée client/secrète.

Indexes : ajouter uniquement après analyse réelle des requêtes.

Vérifier au minimum les recherches fréquentes :

```text
organization scopes
customer FK
status
dates
order number
invoice number
tour date
stock item/location uniqueness
active planning assignment
template resolution
communications
export jobs
```

Pour Template, analyser index autour de :

```text
organization_id
customer_id
service_id
template_type
channel
language
is_default
is_active
```

Pas d'index géant arbitraire.

# 30. Tests Frontend

Compléter composants critiques :

```text
PermissionGuard
StatusSelect
Template forms
Template invoice preview
Pricing formula editor
Invoice actions
Stock forms
Communication actions
API key one-time dialog
```

Mocks doivent respecter Resources backend.

# 31. Tests Backend

Tous les tests Phases 1→10 passent.

Interdit :

```text
skip
only
todo
disable suite
modifier assertion juste pour faire vert
```

# 32. E2E — scénario principal Order → Invoice

```text
Login
→ Organization
→ Customer
→ Catalog
→ Order
→ OrderLine
→ Package
→ OrderService
→ Planning
→ Tour
→ Tracking
→ POD
→ Service Completed
→ Pricing
→ Prebilling
→ Invoice DRAFT
→ Resolve Template INVOICE
→ Preview
→ Close Invoice
→ immutable rendered output
→ ExportJob
```

# 33. E2E — exports

REST :

```text
Customer REST JSON config
→ Invoice CLOSED
→ ExportJob
→ SENT
```

SFTP/FTP avec fake/staging :

```text
Invoice CLOSED
→ generated
→ sent
```

# 34. E2E — Invoice Template

```text
Global Template INVOICE
Customer A no override
→ GLOBAL

Customer A creates override
→ CUSTOMER

Customer B
→ GLOBAL
```

Immutabilité :

```text
Invoice A CLOSED on V1
edit Template to V2
Invoice A remains V1
Invoice B uses V2
```

---

# 35. E2E — Provider

```text
Provider
→ Driver
→ Vehicle
→ Tour
→ completed service
→ ProviderSettlement
```

# 36. E2E — Stock

```text
Customer
→ CatalogItem
→ StockItem
→ StockLocation
→ StockMovement
→ StockBalance
→ StockReservation
→ OrderLine
→ Release
```

Concurrence :

```text
2 users reserve same available stock
→ one fails cleanly
→ no negative quantity
```

# 37. E2E — Planning

```text
2 users assign same OrderService
→ one active assignment only
```

Resume :

```text
create DRAFT Tour
leave
reconnect
resume same Tour
```

# 38. E2E — Communications

```text
Template
→ CommunicationRule
→ business Event
→ OrderCommunication
→ Queue
→ fake Sender
→ SENT
```

Failure :

```text
FAILED
→ Retry
→ SENT
```

# 39. E2E — API Key

```text
create Customer API config
→ key shown once
→ close
→ cannot view again
→ rotate
→ new key shown once
```

# 40. E2E — Permissions / IDOR

Restricted user :

```text
menus
routes
buttons
backend enforcement
```

Cross-org resources :

```text
Customer
Order
Tour
Invoice
Stock
Template
ExportJob
```

must be denied.

# 41. Route coverage

Créer :

```text
docs/frontend/phase-10-route-coverage.md
```

| Front route | Page | Backend API | Permission | E2E |
| ----------- | ---- | ----------- | ---------- | --- |

Tester :

```text
menu click
direct URL
refresh
deep link
back/forward
```

Aucun 404 interne.

---

# 42. Build / Quality

Frontend :

```bash
npm ci
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
composer install
php artisan optimize:clear
php artisan migrate:status
php artisan test
./vendor/bin/pint --test
php artisan route:list --path=api/v1
```

N'appeler que les scripts réellement présents.

Retirer :

```text
console.log debug
React warnings
unused code
unused imports
dead API hooks
old Template code
```

Objectif fichiers raisonnables, éviter God Components/God Services.

# 43. Dependency audit

Exécuter si disponible :

```bash
npm audit
composer audit
```

Ne pas appliquer automatiquement une upgrade majeure destructive.

Documenter vulnérabilités restantes.

Supprimer dépendances réellement inutilisées.

# 44. CI

Si pipeline existe, vérifier au minimum :

```text
frontend lint
frontend typecheck
frontend tests
frontend build
backend tests
pint --test
```

E2E critique si infrastructure le permet.

# 45. Aucun auto-déploiement

Phase 10 ne déploie pas en production sans demande explicite.

# 46. Release documentation

Créer :

```text
docs/frontend/phase-10-regression-report.md
docs/frontend/phase-10-final-report.md
docs/release/phase-10-release-checklist.md
```

Checklist DB :

```text
[ ] migrations from zero
[ ] upgrade path
[ ] constraints
[ ] indexes
[ ] statuses
[ ] permissions
[ ] menus
[ ] seeders
[ ] Template migration
```

Checklist frontend :

```text
[ ] lint
[ ] typecheck
[ ] tests
[ ] build
[ ] E2E
[ ] routes
[ ] menus
[ ] permissions
[ ] responsive
[ ] accessibility
[ ] bundle
```

Checklist sécurité :

```text
[ ] IDOR
[ ] XSS
[ ] SSRF
[ ] API keys
[ ] secrets
[ ] uploads
[ ] TemplateRenderer
[ ] pricing parser
```

Checklist intégrité :

```text
[ ] no double InvoiceLine/OrderService
[ ] no double ProviderSettlementLine
[ ] no double active Tour assignment
[ ] no negative stock
[ ] Invoice CLOSED immutable
[ ] communication snapshots immutable
[ ] ExportJob history intact
```

---

# 47. Rapport final obligatoire

`docs/frontend/phase-10-final-report.md` doit contenir :

1. branche de base ;
2. branche Phase 10 ;
3. Git author/committer ;
4. commits ;
5. diagrammes finaux ;
6. audit DB ;
7. audit API ;
8. audit frontend ;
9. Phase 1 ;
10. Phase 2 ;
11. Phase 3 ;
12. Phase 4 ;
13. Phase 5 ;
14. Phase 6 ;
15. Phase 7 ;
16. Phase 8 ;
17. Phase 9 ;
18. Template unification ;
19. Invoice Template ;
20. Pricing ;
21. Planning ;
22. Stock ;
23. Communications ;
24. Integrations ;
25. statuses ;
26. permissions ;
27. menus ;
28. security ;
29. performance ;
30. responsive ;
31. accessibility ;
32. i18n ;
33. query keys ;
34. API layer ;
35. migrations ;
36. tests ;
37. E2E ;
38. dependency audit ;
39. regressions ;
40. limitations ;
41. risks ;
42. exclusions ;
43. release checklist ;
44. prochaine phase.

Conclusion exacte :

```text
FRONTEND_PHASE_10_READY
```

ou :

```text
FRONTEND_PHASE_10_NOT_READY
```

# 48. Conditions READY

READY seulement si :

```text
lint passe
typecheck passe
tests passent
build passe
backend tests passent
Pint passe
migrations cohérentes
aucun endpoint fantôme
permissions critiques cohérentes
IDOR principaux testés
statuses audités
Template unification terminée
Invoice Template testé
Pricing testé
Stock concurrency testé
Planning concurrency testé
Exports testés
Communications testées
E2E critiques passent
```

# 49. NOT READY automatique

Si :

```text
migration destructive non résolue
communication_templates + templates runtime en parallèle
Invoice CLOSED non immutable
cross-org possible
API key exposée
negative stock possible
double active assignment possible
double invoice possible
secret frontend
test critique rouge
build rouge
```

alors :

```text
FRONTEND_PHASE_10_NOT_READY
```

# 50. Documentation finale

Créer/mettre à jour un index frontend indiquant clairement :

```text
Phase 1 Administration
Phase 2 Orders
Phase 3 Tracking/POD/Claims
Phase 4 Resources
Phase 5 Planning
Phase 6 Pricing/Billing
Phase 7 Stock
Phase 8 Integrations
Phase 9 Templates/Communication Automation
Phase 10 Consolidation
```

Mention obligatoire :

```text
Phase 9 V2 supersedes former CommunicationTemplate design.

Final entity: Template
Final table: templates
```

Pour Phase 6 :

```text
Phase 6 requirements
+
Phase 9 amendments for generic invoice templates
=
final billing contract
```

# 51. Interdictions finales

Ne pas :

- créer nouvelle entité métier ;
- créer table pour UI ;
- créer InvoiceTemplate ;
- recréer CommunicationTemplate runtime ;
- garder deux tables Template actives ;
- créer status_id ;
- hardcoder status ;
- hardcoder rôle pour authorization ;
- contourner Policy ;
- cacher un test rouge ;
- utiliser eval ;
- exposer API keys/password/storagePath ;
- appeler API externe directement depuis React ;
- modifier StockBalance directement ;
- supprimer StockMovement ;
- supprimer historique ;
- recalculer Invoice CLOSED avec Template actuel ;
- supprimer anciens planning assignments ;
- avoir 2 active assignments ;
- déclencher communication automatique dans React ;
- inventer endpoint ;
- inventer champ Resource ;
- laisser TODO/FIXME/HACK non résolu ;
- auto-merge ;
- auto-push ;
- attribuer commit à Claude/Anthropic.

# 52. Définition de Done

```text
BACKOFFICE TRICOLIS V2
=
fonctionnel
+ cohérent
+ sécurisé
+ multi-organisation
+ testé
+ documenté
+ performant
+ maintenable
+ prêt pour validation release
```

Ne déclarer `FRONTEND_PHASE_10_READY` que si cette définition est réellement satisfaite.

# 53. Après Phase 10

Ne pas commencer automatiquement :

```text
Customer Portal
Provider Portal
Driver App
```

Roadmap après validation :

```text
Portal Phase 1 — Customer Portal
puis Provider Portal
puis Driver PWA/Mobile
```
