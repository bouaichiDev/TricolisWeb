# Tricolis V2 — FRONTEND PHASE 8 MASTER FINAL

## Intégrations clients

### Configurations d’import + accès REST API + clés API + exports génériques + FTP/SFTP/REST API/EMAIL/MANUAL + ExportJobs + consolidation des exports factures de Phase 6

> **Ce fichier est la source unique de travail pour la Frontend Phase 8.**
>
> Les Frontend Phases 1 à 7 sont terminées ou validées.
>
> La Phase 6 a déjà introduit l’utilisation de :
>
> ```text
> CustomerExportConfiguration
> ExportJob
> ```
>
> pour transmettre les factures `CLOSED` vers les systèmes externes des Customers.
>
> **La Phase 8 ne doit pas recréer ce moteur.**
>
> Elle doit le réutiliser, le consolider et exposer l’ensemble des intégrations Customer prévues par le modèle officiel.

---

# 1. Mission

Tu es un architecte frontend/backend senior spécialisé en React, TypeScript, Vite, TanStack Query, React Hook Form, Zod, Laravel, MySQL 8, API REST, API Keys, hash cryptographique, IP/CIDR, JSON mapping, FTP, SFTP, REST API, Laravel Filesystem, queues, sécurité SSRF, gestion sécurisée des secrets, intégrations B2B, multi-organisation et audit.

Tu travailles sur **Tricolis V2**.

Ta mission est d’implémenter :

# FRONTEND PHASE 8 — INTÉGRATIONS CLIENTS

---

# 2. Sources de vérité obligatoires

Utiliser dans cet ordre :

```text
1. Schéma DB réellement validé
2. Backend réellement implémenté
3. Conception/diagramme/00-diagramme-classes-partagees.puml
4. Conception/diagramme/01-diagramme-plateforme-interne.puml
5. Documentation de Phase 8
6. Phase 6 déjà développée pour les exports de facture
7. Ancienne documentation / legacy
```

Analyser avant toute modification :

```text
database/migrations/
app/Modules/
Models
Actions
Services
Jobs
DTOs
Form Requests
API Resources
Policies
PermissionSeeder
routes/
tests/
docs/backend/
docs/frontend/
frontend/src/
```

Si une capacité existe déjà grâce à la Phase 6 :

```text
RÉUTILISER
```

Ne pas recréer une deuxième implémentation.

---

# 3. Scope métier exact

Implémenter/réutiliser uniquement :

```text
CustomerImportConfiguration
CustomerApiConfiguration
CustomerExportConfiguration
ExportJob

ExportFormat
ExportTransport
```

Relations avec :

```text
Customer
Organization
AuditLog
```

et les entités exportables réellement supportées par `entityType`.

---

# 4. Hors scope absolu

Ne pas créer :

```text
Import
ImportFile
ImportRow
ImportError
ImportMapping
ImportResult
ImportTemplate

ExportTemplate
ExportBatch
ExportResult
ExportError
ExportHistory
ScheduledExport

ApiRequestLog
ApiUsageLog
ApiToken
ApiClient

Webhook
WebhookDelivery
FileTransferLog
NotificationJob
```

Ne pas ajouter de table simplement pour rendre l’interface plus riche.

---

# 5. Pas de moteur d’import complet dans cette phase

Le modèle officiel contient :

```text
CustomerImportConfiguration
```

mais ne contient pas :

```text
Import
ImportRow
ImportError
ImportResult
```

Donc la Phase 8 gère :

```text
configuration d’import
mapping
règles de validation
activation/désactivation
```

mais **pas un historique complet d’exécution d’import**.

Ne pas créer un écran :

```text
Historique imports
Erreurs lignes importées
Résultat import
```

sans évolution de conception.

---

# 6. Modèle exact — CustomerImportConfiguration

Respecter :

```text
CustomerImportConfiguration
- id: ULID
- customerId: ULID
- name: string
- sourceType: string
- fileFormat: string
- mapping: JSON
- validationRules: JSON
- isActive: boolean
```

Table :

```text
customer_import_configurations
```

Ne pas ajouter :

```text
host
port
username
password
directory
schedule
lastImportedAt
status
errorCount
```

si absents du modèle réel.

---

# 7. Relation CustomerImportConfiguration

Respecter :

```text
Customer "1" -- "0..*" CustomerImportConfiguration
```

Chaque configuration appartient à un seul Customer.

Aucune configuration Customer A ne doit être visible/utilisable par Customer B.

---

# 8. sourceType reste un string

Ne pas créer d’enum `ImportSourceType`.

Analyser les valeurs réellement utilisées/configurées par le backend.

Ne pas hardcoder une liste arbitraire.

Si aucune liste contrôlée n’existe :

- utiliser une saisie/configuration cohérente avec le backend ;
- documenter les codes attendus ;
- ne pas inventer une vérité métier.

---

# 9. fileFormat reste un string

Ne pas confondre avec :

```text
ExportFormat
```

Le diagramme de l’import définit :

```text
fileFormat: string
```

Donc ne pas forcer l’enum ExportFormat sur ce champ si le backend ne le fait pas.

---

# 10. mapping JSON

`mapping` est un JSON configurable.

Il doit être :

- JSON valide ;
- sérialisable ;
- de taille raisonnable ;
- exempt de code exécutable.

Interdit :

```text
PHP
SQL
JavaScript exécutable
eval
class name arbitraire
fonction dynamique
```

---

# 11. validationRules JSON

Même règle :

```text
JSON configurable
```

Ne pas convertir les règles en code exécutable.

Ne pas inventer un DSL si le backend n’en possède pas.

---

# 12. Ne pas inventer le schéma du mapping

Le modèle officiel ne fixe pas précisément la structure interne de :

```text
mapping
validationRules
```

Avant l’UI :

inspecter le backend réel.

Si une structure validée existe :

→ créer un éditeur UX adapté.

Sinon :

→ utiliser un éditeur JSON contrôlé avec validation syntaxique et documentation.

---

# 13. Page Import Configurations

Créer :

```text
/integrations/imports
```

Composants :

```text
CustomerImportConfigurationListPage
CustomerImportConfigurationTable
CustomerImportConfigurationFilters
```

Colonnes :

```text
Customer
Name
Source type
File format
Active
Actions
```

---

# 14. Import Configuration Create/Edit

Routes :

```text
/integrations/imports/create
/integrations/imports/:id/edit
/integrations/imports/:id
```

Formulaire :

```text
Customer
Name
SourceType
FileFormat
Mapping
ValidationRules
Active
```

---

# 15. JSON Editor

Créer/réutiliser :

```text
JsonConfigurationEditor
```

Fonctions :

```text
format JSON
validate JSON
show line/field error
reset
```

Ne pas exécuter le JSON.

---

# 16. Customer Detail — Import section

Dans :

```text
/customers/:id
```

onglet :

```text
Intégrations
```

sous-section :

```text
Imports
```

Afficher uniquement les configurations du Customer courant.

---

# 17. API CustomerImportConfiguration

Backend prévu :

```text
GET    /api/v1/customer-import-configurations
POST   /api/v1/customer-import-configurations
GET    /api/v1/customer-import-configurations/{configuration}
PATCH  /api/v1/customer-import-configurations/{configuration}
DELETE /api/v1/customer-import-configurations/{configuration}
```

Nested éventuel :

```text
GET  /api/v1/customers/{customer}/import-configurations
POST /api/v1/customers/{customer}/import-configurations
```

Ne pas créer une route d’exécution d’import inexistante.

---

# 18. Modèle exact — CustomerApiConfiguration

Respecter :

```text
CustomerApiConfiguration
- id: ULID
- customerId: ULID
- name: string
- apiKeyHash: string
- allowedIps: JSON
- permissions: JSON
- isActive: boolean
- lastUsedAt: datetime
```

Table :

```text
customer_api_configurations
```

---

# 19. Rôle de CustomerApiConfiguration

Cette configuration permet au Customer d’accéder aux APIs Tricolis selon le contrat backend.

Concept :

```text
Customer externe
→ API Tricolis
```

Ne pas confondre avec :

```text
CustomerExportConfiguration REST_API
```

qui correspond à :

```text
Tricolis
→ API externe Customer
```

---

# 20. API Key jamais stockée en clair

Le backend stocke :

```text
apiKeyHash
```

La clé réelle :

- générée cryptographiquement ;
- retournée une seule fois ;
- jamais stockée en clair ;
- jamais journalisée ;
- jamais retournée ensuite.

---

# 21. Création API Key UX

Lors du POST de création :

Créer :

```text
ApiKeyCreatedDialog
```

Afficher :

```text
Votre clé API
[**********************]
[Copier]
```

Message :

```text
Cette clé ne sera plus affichée.
Copiez-la et conservez-la dans un endroit sûr.
```

---

# 22. Après fermeture de la clé

La clé ne doit plus rester :

```text
React global state
localStorage
sessionStorage
URL
logs
analytics
```

---

# 23. API Key dans liste

Ne jamais afficher :

```text
apiKeyHash
API Key
```

Afficher seulement un état tel que :

```text
Clé configurée
```

si utile.

---

# 24. Rotation clé API

Si route disponible :

```text
POST /api/v1/customer-api-configurations/{configuration}/rotate-key
```

Créer action :

```text
Renouveler la clé
```

avec confirmation :

```text
L’ancienne clé sera immédiatement invalidée.
```

Afficher la nouvelle clé une seule fois.

---

# 25. Pas d’historique des clés

Ne pas créer :

```text
ApiKeyHistory
PreviousKeys
ApiToken
```

---

# 26. allowedIps

`allowedIps` est un JSON.

Le diagramme ne définit pas exactement sa structure.

Analyser l’implémentation backend.

Si le backend utilise une liste d’IP/CIDR :

créer un `AllowedIpEditor`.

Sinon ne pas imposer cette structure.

---

# 27. permissions JSON API Customer

`permissions` est un JSON.

Il ne s’agit pas automatiquement des permissions RBAC internes.

Inspecter le contrat backend.

Si le backend fournit une whitelist :

créer `CustomerApiPermissionsEditor`.

Sinon utiliser un éditeur JSON contrôlé.

---

# 28. lastUsedAt

Afficher :

```text
Dernière utilisation
```

en lecture seule.

Ne jamais permettre sa modification frontend.

---

# 29. API Config list

Créer :

```text
/integrations/api-access
```

Colonnes :

```text
Customer
Name
Allowed IPs summary
Permissions summary
Active
Last used
Actions
```

---

# 30. API Config Create/Edit

Routes :

```text
/integrations/api-access/create
/integrations/api-access/:id
/integrations/api-access/:id/edit
```

Formulaire :

```text
Customer
Name
AllowedIps
Permissions
Active
```

Ne jamais demander `apiKeyHash`.

---

# 31. Customer Detail — API access

Dans :

```text
Customer → Intégrations → Accès API
```

Afficher toutes les configurations du Customer.

Actions :

```text
Créer
Modifier
Activer/Désactiver
Renouveler clé
Supprimer
```

selon permissions.

---

# 32. API CustomerApiConfiguration

Backend prévu :

```text
GET    /api/v1/customer-api-configurations
POST   /api/v1/customer-api-configurations
GET    /api/v1/customer-api-configurations/{configuration}
PATCH  /api/v1/customer-api-configurations/{configuration}
DELETE /api/v1/customer-api-configurations/{configuration}
```

Rotation éventuelle :

```text
POST /api/v1/customer-api-configurations/{configuration}/rotate-key
```

---

# 33. Modèle exact — CustomerExportConfiguration

Respecter :

```text
CustomerExportConfiguration
- id: ULID
- customerId: ULID
- name: string
- exportType: string
- format: ExportFormat
- transport: ExportTransport
- host: string
- port: int
- username: string
- encryptedPassword: text
- remoteDirectory: string
- fileNamePattern: string
- encoding: string
- frequency: string
- settings: JSON
- isActive: boolean
```

Table :

```text
customer_export_configurations
```

---

# 34. Consolidation Phase 6 obligatoire

La Phase 6 utilise déjà cette entité pour l’export de factures clôturées.

Ne pas créer :

```text
CustomerInvoiceExportConfiguration
```

comme table séparée.

Le frontend Phase 8 doit réutiliser les configurations existantes.

---

# 35. ExportType reste un string

Ne pas créer d’enum `ExportType`.

Réutiliser les valeurs réellement supportées par le backend.

La valeur Invoice introduite en Phase 6 doit rester identique.

---

# 36. frequency reste un string

Ne pas créer d’enum `ExportFrequency`.

Réutiliser les codes réels de Phase 6/backend.

---

# 37. ExportFormat exact

Utiliser uniquement :

```text
XML
CSV
JSON
PDF
```

Ne pas ajouter :

```text
XLS
XLSX
TXT
ZIP
EDI
```

---

# 38. ExportTransport exact

Utiliser uniquement :

```text
FTP
SFTP
REST_API
EMAIL
MANUAL
```

Ne pas ajouter :

```text
FTPS
HTTP
HTTPS
S3
WebDAV
```

---

# 39. Formulaire conditionnel par transport

Le même modèle DB est utilisé.

## FTP/SFTP

Afficher selon nullabilité réelle :

```text
host
port
username
password
remoteDirectory
fileNamePattern
encoding
format
settings
```

## REST_API

Afficher :

```text
host
format
encoding
settings
```

et secret selon stratégie backend existante.

## EMAIL

Utiliser `settings` uniquement si sa structure est documentée.

## MANUAL

Pas de connexion distante.

---

# 40. encryptedPassword

Secret chiffré backend.

Ne jamais :

```text
retourner
afficher
logger
mettre dans AuditLog
```

Le formulaire Edit ne préremplit jamais le secret réel.

---

# 41. settings JSON

Configurable et validé.

Ne jamais exécuter du code provenant de `settings`.

Ne jamais y stocker un secret en clair si le backend possède un champ chiffré prévu.

---

# 42. fileNamePattern

Utiliser le moteur backend sécurisé.

Protéger contre :

```text
../
expression arbitraire
shell
PHP
```

---

# 43. remoteDirectory

Valider côté backend.

Pas de path traversal.

---

# 44. SSRF

Pour :

```text
REST_API
FTP
SFTP
```

la sécurité réelle est côté backend.

Valider host/port/redirects/timeouts selon les règles Phase 8.

---

# 45. Export Config global page

Créer :

```text
/integrations/exports
```

Colonnes :

```text
Customer
Name
Export type
Format
Transport
Host
Frequency
Active
Actions
```

Ne pas afficher password.

---

# 46. Export Config Create/Edit/Detail

Routes :

```text
/integrations/exports/create
/integrations/exports/:id
/integrations/exports/:id/edit
```

Formulaire dynamique selon transport.

---

# 47. Customer Detail — Exports

Dans :

```text
Customer → Intégrations → Exports
```

réutiliser les mêmes composants avec `customerId` fixé.

Les configs Invoice de Phase 6 apparaissent ici.

---

# 48. Ne pas casser Facturation Phase 6

La vue Facturation peut conserver :

```text
Configuration export de facture
```

mais elle manipule les mêmes `CustomerExportConfiguration`.

Aucune duplication.

---

# 49. Modèle exact — ExportJob

Respecter :

```text
ExportJob
- id: ULID
- customerId: ULID
- configurationId: ULID
- entityType: string
- entityId: ULID
- fileName: string
- storagePath: string
- status: string
- attemptCount: int
- generatedAt: datetime
- sentAt: datetime
- errorMessage: text
```

Table :

```text
export_jobs
```

---

# 50. ExportJob est historique

Après création :

```text
lecture
retry contrôlé
download contrôlé
```

Pas :

```text
Edit
Delete
```

---

# 51. entityType / entityId

Utiliser uniquement les aliases contrôlés backend.

Ne jamais laisser l’utilisateur saisir un nom de classe PHP.

Invoice doit réutiliser l’alias Phase 6.

---

# 52. ExportJob status — statuses centralisés

`ExportJob.status` reste textuel.

Référentiel :

```text
statuses
src = export_jobs
```

Ne jamais créer `status_id`.

---

# 53. Pas de status artificiel sur configs

Les configurations utilisent :

```text
isActive
```

Ne pas ajouter `status`.

---

# 54. ExportJob list

Créer :

```text
/integrations/export-jobs
```

Colonnes :

```text
Customer
Configuration
Entity type
Entity reference
File name
Status
Attempts
Generated at
Sent at
Error
Actions
```

---

# 55. storagePath reste interne

Ne jamais afficher `storagePath`.

Download via endpoint sécurisé seulement.

---

# 56. ExportJob detail

Route :

```text
/integrations/export-jobs/:id
```

Afficher :

```text
Customer
Configuration
Entity
FileName
Status
AttemptCount
GeneratedAt
SentAt
ErrorMessage
Transport
Format
```

Actions :

```text
Retry
Download
Open source entity
```

si disponibles.

---

# 57. Retry

Backend éventuel :

```text
POST /api/v1/export-jobs/{exportJob}/retry
```

Réutiliser le même job.

Ne pas dupliquer Invoice ni les jobs déjà réussis.

---

# 58. Download

Utiliser endpoint sécurisé backend.

Ne jamais construire une URL avec `storagePath`.

---

# 59. ProcessExportJob

Réutiliser :

```text
ProcessExportJob
```

Le frontend ne génère jamais les fichiers B2B.

---

# 60. Génération par format backend

Formats exacts :

```text
XML
CSV
JSON
PDF
```

Implémenter seulement les `exportType` dont le mapping métier est réellement défini.

Ne pas générer de faux contenu.

---

# 61. Transporters backend

Réutiliser les services :

```text
FtpExportTransporter
SftpExportTransporter
RestApiExportTransporter
EmailExportTransporter
ManualExportTransporter
```

ou noms réels.

React n’appelle jamais FTP/SFTP directement.

---

# 62. Manual ExportJob

`POST /api/v1/export-jobs` peut être exposé uniquement pour les entités/configurations réellement autorisées à être déclenchées manuellement.

Créer éventuellement :

```text
GenerateExportDialog
```

mais avec whitelist `entityType`.

---

# 63. Invoice export règle inchangée

Ne jamais permettre :

```text
Invoice DRAFT
→ export manuel
```

si cela contourne Phase 6.

Règle :

```text
Invoice CLOSED
→ export
```

reste obligatoire.

---

# 64. API CustomerExportConfiguration

Backend prévu :

```text
GET    /api/v1/customer-export-configurations
POST   /api/v1/customer-export-configurations
GET    /api/v1/customer-export-configurations/{configuration}
PATCH  /api/v1/customer-export-configurations/{configuration}
DELETE /api/v1/customer-export-configurations/{configuration}
```

Nested :

```text
GET  /api/v1/customers/{customer}/export-configurations
POST /api/v1/customers/{customer}/export-configurations
```

---

# 65. API ExportJob

Backend prévu :

```text
GET  /api/v1/export-jobs
POST /api/v1/export-jobs
GET  /api/v1/export-jobs/{exportJob}
```

Éventuellement :

```text
POST /api/v1/export-jobs/{exportJob}/retry
GET  /api/v1/export-jobs/{exportJob}/download
```

Pas de PATCH/DELETE.

---

# 66. Customer Integrations main page

Créer :

```text
/integrations
```

Sections :

```text
Imports
Accès API
Exports
Historique des exports
```

---

# 67. Menu Backoffice

Ajouter :

```text
Intégrations
├── Configurations import
├── Accès API clients
├── Configurations export
└── Exports / Historique
```

Ne pas créer Webhooks, Scheduled exports, API logs ou Import history.

---

# 68. Customer Detail — onglet Intégrations

Sous-tabs :

```text
Imports
API
Exports
Historique
```

`Historique` = ExportJob uniquement.

---

# 69. Multi-organisation

Toujours vérifier :

```text
Customer appartient à Organization active
Configuration.customerId == Customer.id
ExportJob.customerId == Customer.id
ExportJob.configuration.customerId == Customer.id
```

---

# 70. IDOR

Tester :

```text
cross-org config read
cross-org update
cross-org rotate-key
cross-org retry
cross-org download
```

Refus obligatoire.

---

# 71. Suppression config avec historique

Ne pas cascade-delete `ExportJob`.

Si le backend refuse la suppression :

afficher le conflit.

---

# 72. Active toggle

`isActive` est boolean.

Utiliser un switch si PATCH le permet.

Pas de status central pour ces booléens.

---

# 73. Audit

Réutiliser `AuditLog` pour :

```text
customer_import_configuration.created/updated/deleted
customer_api_configuration.created/updated/deleted/key_rotated
customer_export_configuration.created/updated/deleted
export_job.created/retried/generated/sent/failed
```

selon conventions réelles.

---

# 74. Secrets exclus de l’audit

Ne jamais inclure :

```text
API key
apiKeyHash
password
encryptedPassword
Authorization
secret settings
```

---

# 75. API Resources — sécurité

Frontend doit vérifier que les GET ne retournent jamais :

```text
apiKeyHash
encryptedPassword
storagePath
```

Si cela arrive :

corriger le backend.

---

# 76. API Layer frontend

Créer/refactorer :

```text
modules/integrations/api/customer-import-configurations.api.ts
modules/integrations/api/customer-api-configurations.api.ts
modules/integrations/api/customer-export-configurations.api.ts
modules/integrations/api/export-jobs.api.ts
```

Réutiliser les appels Phase 6.

---

# 77. Migration frontend Phase 6 → Phase 8

Ne pas conserver deux implémentations concurrentes ExportJob.

Refactorer Facturation pour utiliser le module partagé Integration.

---

# 78. Query Keys

Créer/consolider :

```text
customerImportConfigurationKeys
customerApiConfigurationKeys
customerExportConfigurationKeys
exportJobKeys
```

Avec variantes :

```text
list
detail
byCustomer
byConfiguration
byEntity
```

---

# 79. Types TypeScript

Créer :

```text
CustomerImportConfiguration
CustomerApiConfiguration
CustomerApiKeyCreated
CustomerExportConfiguration
ExportJob
ExportFormat
ExportTransport
```

---

# 80. ExportFormat frontend exact

```text
XML
CSV
JSON
PDF
```

---

# 81. ExportTransport frontend exact

```text
FTP
SFTP
REST_API
EMAIL
MANUAL
```

---

# 82. Strings non enum

Restent `string` :

```text
sourceType
fileFormat
exportType
frequency
entityType
ExportJob.status
```

---

# 83. Zod

Créer :

```text
customerImportConfigurationSchema
customerApiConfigurationSchema
customerExportConfigurationSchema
```

Validation conditionnelle export selon transport.

---

# 84. JsonConfigurationEditor partagé

Réutiliser pour :

```text
mapping
validationRules
permissions si non typé
settings
```

Sans secrets.

---

# 85. StatusBadge ExportJob

Utiliser :

```text
useStatuses("export_jobs")
StatusBadge
```

Pas de couleurs hardcodées.

---

# 86. Permissions Phase 8

Vérifier les codes réels :

```text
customer_import_configurations.view
customer_import_configurations.create
customer_import_configurations.update
customer_import_configurations.delete

customer_api_configurations.view
customer_api_configurations.create
customer_api_configurations.update
customer_api_configurations.delete
customer_api_configurations.rotate_key

customer_export_configurations.view
customer_export_configurations.create
customer_export_configurations.update
customer_export_configurations.delete

export_jobs.view
export_jobs.create
export_jobs.retry
export_jobs.download
```

---

# 87. PermissionGuard

Appliquer aux actions CRUD, rotate, retry et download.

Backend reste autorité finale.

---

# 88. Pagination / filtres

Toutes les listes globales utilisent :

```text
pagination serveur
search serveur
filters serveur
sort serveur
```

selon Requests réelles.

---

# 89. Polling ExportJob

Après create/retry :

faire un polling raisonnable seulement si nécessaire.

Stopper sur status terminal réel.

Pas de polling infini.

---

# 90. Customer API key tests

Tester :

```text
key returned once
copy dialog
key absent list/detail
apiKeyHash absent
rotation
old key invalid backend
no localStorage
no logs
```

---

# 91. Export secret tests

Tester :

```text
encryptedPassword absent
storagePath absent
AuditLog sanitized
download authorized
cross-customer refused
```

---

# 92. Tests CustomerImportConfiguration

Tester :

```text
list
create
edit
detail
delete
Customer scope
mapping JSON
validationRules JSON
invalid JSON
isActive
permissions
IDOR
audit
```

Ne pas tester un moteur Import absent.

---

# 93. Tests CustomerApiConfiguration

Tester :

```text
create
key one-time
allowedIps
permissions
edit
active
rotate
delete
lastUsedAt read-only
permissions
IDOR
audit
```

---

# 94. Tests CustomerExportConfiguration

Tester :

```text
list
create
edit
delete
format exact
transport exact
conditional fields
password masked
settings JSON
Customer scope
permissions
IDOR
audit
```

---

# 95. Tests ExportJob

Tester :

```text
list
detail
manual create when allowed
no edit/delete
retry
download
status dynamic
attemptCount
generatedAt
sentAt
errorMessage
Customer/config consistency
permissions
IDOR
```

---

# 96. Regression Phase 6 obligatoire

Tester :

```text
Invoice CLOSED
→ ExportJob créé automatiquement
→ visible Invoice Detail
→ visible Integrations
→ retry fonctionne
→ download fonctionne

Invoice DRAFT
→ aucun export
```

---

# 97. E2E API Key

```text
Login
→ Customer
→ Intégrations
→ API
→ Nouvelle configuration
→ save
→ clé une seule fois
→ Copier
→ fermer
→ rouvrir
→ clé absente
→ Rotate
→ nouvelle clé une seule fois
```

---

# 98. E2E Import Configuration

```text
Customer
→ Intégrations
→ Imports
→ create
→ mapping JSON
→ validationRules JSON
→ save
→ edit
```

Pas d’exécution Import.

---

# 99. E2E Export

Avec infrastructure fake/test :

```text
Customer
→ Export Config
→ SFTP/XML
→ save
→ générer export manuel si entityType supporté
→ queue
→ generated
→ sent
→ history
```

---

# 100. Documentation analyse

Créer :

```text
docs/frontend/phase-8-analysis.md
```

Inclure au minimum :

1. branche source ;
2. backend Phase 8 réel ;
3. code Phase 6 réutilisé ;
4. Import Configuration ;
5. mapping ;
6. validationRules ;
7. sourceType codes ;
8. fileFormat codes ;
9. API Configuration ;
10. hash strategy ;
11. one-time key ;
12. rotate ;
13. allowedIps semantics ;
14. permissions semantics ;
15. Export Configuration ;
16. Phase 6 integration ;
17. exportType codes ;
18. frequency codes ;
19. ExportFormat ;
20. ExportTransport ;
21. password strategy ;
22. settings ;
23. SSRF ;
24. ExportJob ;
25. status source ;
26. entityType aliases ;
27. retry ;
28. download ;
29. queue ;
30. permissions ;
31. multi-org ;
32. tests ;
33. exclusions.

---

# 101. Audit statuses

Mettre à jour :

```text
docs/backend/statuses-global-audit.md
```

Ajouter/confirmer :

```text
export_jobs
```

Aucun `status_id`.

Ne pas ajouter les configs booléennes dans `statuses`.

---

# 102. Rapport final

Créer :

```text
docs/frontend/phase-8-final-report.md
```

Inclure :

1. branche ;
2. Git identity ;
3. absence Claude/Anthropic ;
4. Imports UI ;
5. JSON config ;
6. API access ;
7. API key one-time ;
8. rotation ;
9. allowedIps ;
10. API permissions ;
11. Export Config UI ;
12. Phase 6 consolidation ;
13. FTP ;
14. SFTP ;
15. REST_API ;
16. EMAIL ;
17. MANUAL ;
18. formats ;
19. ExportJob ;
20. retry ;
21. download ;
22. statuses ;
23. secrets ;
24. SSRF ;
25. multi-org ;
26. API Layer ;
27. Query Keys ;
28. Types ;
29. Zod ;
30. tests ;
31. E2E ;
32. regression Phase 6 ;
33. différences DB/UML ;
34. exclusions ;
35. risques ;
36. prochaine phase.

Conclusion :

```text
FRONTEND_PHASE_8_READY
```

ou :

```text
FRONTEND_PHASE_8_NOT_READY
```

---

# 103. Branche Git

Créer depuis Phase 7 validée :

```bash
git checkout <BRANCHE_PHASE_7_VALIDEE>
git checkout -b feature/frontend-phase-8-customer-integrations
```

Pas de merge automatique.

Pas de push automatique.

---

# 104. Git Identity

Avant commit :

```bash
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

Commit recommandé :

```bash
git add .
git commit -m "feat(frontend): implement phase 8 customer integrations"
```

---

# 105. Interdictions absolues

Ne pas :

- créer Import/ImportFile/ImportRow/ImportError ;
- créer moteur d’import complet ;
- créer ExportTemplate/ExportBatch/ExportHistory ;
- créer ScheduledExport ;
- créer Webhook ;
- créer ApiToken/ApiClient/ApiRequestLog/ApiUsageLog ;
- créer API key history ;
- stocker API key en clair ;
- retourner apiKeyHash ;
- retourner encryptedPassword ;
- retourner storagePath ;
- mettre clé API en localStorage/sessionStorage/URL ;
- logguer un secret ;
- utiliser apiKeyHash comme secret sortant ;
- mélanger API entrante et REST export sortant ;
- inventer sourceType/fileFormat/exportType/frequency ;
- ajouter format autre que XML/CSV/JSON/PDF ;
- ajouter transport autre que FTP/SFTP/REST_API/EMAIL/MANUAL ;
- créer enum ExportJobStatus ;
- créer status_id ;
- ajouter status aux configs ;
- générer fichiers B2B dans React ;
- appeler FTP/SFTP depuis React ;
- contourner Invoice CLOSED ;
- dupliquer moteur Phase 6 ;
- créer deux modules ExportJob concurrents ;
- Edit/Delete ExportJob ;
- download via storagePath ;
- exécuter code depuis mapping/settings ;
- inventer historique API ;
- croiser Organizations ;
- pousser automatiquement ;
- attribuer commit à Claude/Anthropic ;
- laisser TODO.

---

# 106. Vérifications finales

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

Backend si correction nécessaire :

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
php artisan migrate:status
php artisan route:list --path=api/v1
```

Git :

```bash
git status
git diff --check
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
git log -1 --pretty=fuller
```

---

# 107. Critères READY

La Phase 8 est READY uniquement si :

```text
Import configurations fonctionnelles
pas de faux moteur Import
API configurations fonctionnelles
clé API visible une seule fois
rotation sûre
secrets jamais exposés
Export configurations fonctionnelles
Phase 6 réutilisée
ExportJobs fonctionnels
retry/download contrôlés
formats exacts
transports exacts
ExportJob statuses centralisés
multi-org protégé
tests passent
build passe
aucune régression Phase 6
```

---

# 108. Suite

Ne pas commencer automatiquement la phase suivante.

Après validation utilisateur :

```text
FRONTEND PHASE 9 — COMMUNICATION RULES & AUTOMATISATION
```

Réutiliser les communications manuelles déjà développées en Phase 3 et ajouter uniquement l’automatisation autour de `CommunicationRule`.
