# Analyse Phase 8 — Intégrations clients

Répond au §3. Aucune migration écrite avant que le tableau du §5 soit terminé.

---

## 1. Sources de vérité

Les `.puml` du §1 n'existent pas ; les `.txt` font foi. Classes lignes 160-214,
enums lignes 70-83, relations lignes 851-854.

**Aucun conflit cette fois.** Les quatre classes et les deux enums correspondent
exactement au diagramme, attribut par attribut. C'est la première phase depuis
la Phase 4 sans écart `legacyId`.

## 2. État du code et dépendances

Phases 1 à 7 livrées : **531 tests**, 1689 assertions, 281 routes, 153
permissions.

Une seule dépendance : `customers` (Phase 1). Les quatre classes s'y rattachent
directement.

### Conventions réutilisées

| Besoin | Convention existante | Décision |
|--------|---------------------|----------|
| Empreinte de recherche | `hash('sha256', …)` dans `LoginUser` (`email_hash`) | **réutilisée** pour `api_key_hash` |
| Mot de passe utilisateur | `Hash::make()` (bcrypt) | **non** réutilisée pour la clé API — voir §7 |
| Chiffrement réversible | aucun usage existant | `Crypt` de Laravel, introduit ici |
| Colonnes JSON | `organizations.settings` (`JSON`, cast `array`) | reprise |
| Queues | `QUEUE_CONNECTION=database`, **aucun job dans le projet** | voir §11 |

## 3. Classes et relations

```text
CustomerImportConfiguration   CustomerApiConfiguration
CustomerExportConfiguration   ExportJob
```

```text
Customer                    "1" -- "0..*" CustomerImportConfiguration
Customer                    "1" -- "0..*" CustomerApiConfiguration
Customer                    "1" -- "0..*" CustomerExportConfiguration
CustomerExportConfiguration "1" -- "0..*" ExportJob
```

Que des associations : aucune composition, aucune cascade.

**Deux enums**, exactement ceux du diagramme :

```text
ExportFormat    : XML, CSV, JSON, PDF
ExportTransport : FTP, SFTP, REST_API, EMAIL, MANUAL
```

`exportType`, `sourceType`, `fileFormat`, `frequency`, `encoding` et
`ExportJob.status` restent des `string` : le diagramme ne les énumère pas, et
les §8, §18 et §26 interdisent d'en faire des enums.

### Isolation organisationnelle

Aucune des quatre classes ne porte `organizationId`, et le §2 interdit de
l'ajouter. Le périmètre passe par le client :

```text
CustomerImportConfiguration → customer.organization_id
CustomerApiConfiguration    → customer.organization_id
CustomerExportConfiguration → customer.organization_id
ExportJob                   → customer.organization_id
```

## 4. Tableau de correspondance

### CustomerImportConfiguration → `customer_import_configurations`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `customerId` | ULID | `customer_id` CHAR(26) | non | index + unique composite | FK `customers.id` CASCADE |
| `name` | string | `name` VARCHAR(255) | non | unique `(customer_id, name)` | — |
| `sourceType` | string | `source_type` VARCHAR(64) | non | index | — |
| `fileFormat` | string | `file_format` VARCHAR(32) | non | index | — |
| `mapping` | JSON | `mapping` JSON | **oui** | — | cast `array` |
| `validationRules` | JSON | `validation_rules` JSON | **oui** | — | cast `array` |
| `isActive` | boolean | `is_active` BOOLEAN | non, défaut `true` | index | — |

### CustomerApiConfiguration → `customer_api_configurations`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `customerId` | ULID | `customer_id` CHAR(26) | non | index + unique composite | FK `customers.id` CASCADE |
| `name` | string | `name` VARCHAR(255) | non | unique `(customer_id, name)` | — |
| `apiKeyHash` | string | `api_key_hash` CHAR(64) | non | **unique** | jamais exposé |
| `allowedIps` | JSON | `allowed_ips` JSON | **oui** | — | cast `array` |
| `permissions` | JSON | `permissions` JSON | **oui** | — | cast `array` |
| `isActive` | boolean | `is_active` BOOLEAN | non, défaut `true` | index | — |
| `lastUsedAt` | datetime | `last_used_at` DATETIME | **oui** | index | — |

### CustomerExportConfiguration → `customer_export_configurations`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `customerId` | ULID | `customer_id` CHAR(26) | non | index + unique composite | FK `customers.id` CASCADE |
| `name` | string | `name` VARCHAR(255) | non | unique `(customer_id, name)` | — |
| `exportType` | string | `export_type` VARCHAR(64) | non | index | — |
| `format` | ExportFormat | `format` VARCHAR(16) | non | index | cast enum |
| `transport` | ExportTransport | `transport` VARCHAR(16) | non | index | cast enum |
| `host` | string | `host` VARCHAR(255) | **oui** | — | — |
| `port` | int | `port` INT UNSIGNED | **oui** | — | — |
| `username` | string | `username` VARCHAR(255) | **oui** | — | — |
| `encryptedPassword` | text | `encrypted_password` TEXT | **oui** | — | jamais exposé |
| `remoteDirectory` | string | `remote_directory` VARCHAR(255) | **oui** | — | — |
| `fileNamePattern` | string | `file_name_pattern` VARCHAR(255) | **oui** | — | — |
| `encoding` | string | `encoding` VARCHAR(32) | **oui** | — | — |
| `frequency` | string | `frequency` VARCHAR(64) | **oui** | index | — |
| `settings` | JSON | `settings` JSON | **oui** | — | cast `array` |
| `isActive` | boolean | `is_active` BOOLEAN | non, défaut `true` | index | — |

### ExportJob → `export_jobs`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `customerId` | ULID | `customer_id` CHAR(26) | non | index | FK `customers.id` RESTRICT |
| `configurationId` | ULID | `configuration_id` CHAR(26) | non | index | FK `customer_export_configurations.id` RESTRICT |
| `entityType` | string | `entity_type` VARCHAR(64) | **oui** | index composite | alias morph map, **pas de FK** |
| `entityId` | ULID | `entity_id` CHAR(26) | **oui** | `(entity_type, entity_id)` | **pas de FK** |
| `fileName` | string | `file_name` VARCHAR(255) | **oui** | — | — |
| `storagePath` | string | `storage_path` VARCHAR(255) | **oui** | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |
| `attemptCount` | int | `attempt_count` INT UNSIGNED | non, défaut 0 | — | — |
| `generatedAt` | datetime | `generated_at` DATETIME | **oui** | index | — |
| `sentAt` | datetime | `sent_at` DATETIME | **oui** | index | — |
| `errorMessage` | text | `error_message` TEXT | **oui** | — | — |

`customerId` est **redondant** avec `configuration.customer_id`. Le §24 interdit
de supprimer cette redondance et demande de vérifier la cohérence : elle est
donc **forcée** à celle de la configuration, jamais acceptée en entrée.

## 5. Nullabilité

| Colonne | Choix | Raison |
|---------|-------|--------|
| `mapping`, `validation_rules`, `allowed_ips`, `permissions`, `settings` | **nullable** | Une configuration peut naître vide et se remplir. Un JSON vide et un JSON absent ne veulent pas dire la même chose. |
| `is_active` | non, défaut `true` | Une configuration créée est active — c'est le cas nominal. |
| `last_used_at` | nullable | Une clé jamais utilisée n'a pas de date. |
| Champs de connexion export (`host`…`settings`) | **tous nullables** | Voir §8 : leur nécessité dépend du transport, et le §19 interdit d'ajouter des colonnes par transport. |
| `export_jobs.entity_type`, `entity_id` | **nullable** | Un export périodique porte sur une période, pas sur une entité. |
| `export_jobs.file_name`, `storage_path`, `generated_at`, `sent_at`, `error_message` | nullable | Renseignés par le traitement, absents à la création. |
| `attempt_count` | non, défaut 0 | Un compteur nul est zéro, pas `NULL`. |

## 6. Suppression

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| Les trois `*_configurations.customer_id` | **`CASCADE`** | Une configuration d'intégration n'a aucun sens sans son client. Supprimer le client emporte ses réglages — ce ne sont pas des pièces comptables. |
| `export_jobs.customer_id` | `RESTRICT` | Un job est une **exécution historique** : il documente ce qui a été envoyé. |
| `export_jobs.configuration_id` | `RESTRICT` | Idem : la configuration explique le job. |

L'asymétrie est délibérée : les configurations sont des réglages, les jobs sont
un historique.

Refus applicatifs :

| Ressource | Refus | Code |
|-----------|-------|------|
| `CustomerExportConfiguration` | possède des `ExportJob` | 409 |
| `ExportJob` | **aucune route** `PATCH` ni `DELETE` | 405 |

## 7. Stratégie de la clé API (§12)

Le diagramme stocke `apiKeyHash`. Deux choix se présentaient :

| Option | Retenue | Raison |
|--------|---------|--------|
| `Hash::make()` — bcrypt | **non** | Bcrypt est délibérément lent (~100 ms). Vérifier une clé à **chaque requête** API coûterait plus cher que la requête elle-même, et interdirait la recherche par empreinte : il faudrait comparer contre toutes les clés du client. |
| `hash('sha256', $key)` | **oui** | Déterministe, donc consultable par index unique. Convention **déjà présente** dans le projet (`LoginUser`, `email_hash`) et celle de Laravel Sanctum, déjà utilisé ici. |

Un hash rapide est cryptographiquement suffisant **parce que la clé est
générée**, pas choisie : 64 caractères issus de `Str::random()` — donc à haute
entropie — ne se cassent pas par force brute, contrairement à un mot de passe
humain. C'est précisément le raisonnement de Sanctum.

Cycle de vie :

1. `CreateCustomerApiConfigurationAction` génère la clé avec `Str::random(64)` ;
2. seule son empreinte SHA-256 est écrite en base ;
3. la clé claire est retournée **une seule fois**, dans la réponse de création ;
4. elle n'apparaît ni dans l'audit, ni dans les journaux, ni dans aucune lecture
   ultérieure ;
5. `api_key_hash` n'est exposé par **aucune** Resource.

**Rotation** : `POST /customer-api-configurations/{configuration}/rotate-key`
remplace l'empreinte et retourne la nouvelle clé une fois. L'ancienne est
invalidée à l'instant même — l'unicité de `api_key_hash` le garantit. Aucune
table d'historique.

## 8. Nullabilité conditionnelle de l'export (§19)

Le §19 demande de documenter les règles retenues sans ajouter de colonnes.

| Transport | Exigé |
|-----------|-------|
| `FTP`, `SFTP` | `host` **obligatoire** |
| `REST_API` | `host` **obligatoire** |
| `EMAIL` | rien — les destinataires vivent dans `settings` |
| `MANUAL` | rien — le fichier est récupéré à la main |

Seul `host` est rendu conditionnellement obligatoire. `port`, `username`,
`encryptedPassword` et `remoteDirectory` restent facultatifs même en FTP : une
connexion anonyme sur port par défaut, à la racine, est un cas réel.

Le §19 dit « **peuvent** nécessiter » — en faire des obligations serait plus
strict que le diagramme.

## 9. Chiffrement du mot de passe (§20)

`encrypted_password` utilise `Crypt::encryptString()` — chiffrement **réversible**,
nécessaire puisque le transport doit présenter le mot de passe en clair au
serveur distant.

Trois règles tenues :

- **jamais retourné**, ni déchiffré ni chiffré : la Resource expose un booléen
  `hasPassword` ;
- **jamais journalisé** : l'audit enregistre le fait qu'un mot de passe a changé,
  pas sa valeur ;
- **conservé si absent** : un `PATCH` sans `password` laisse l'ancien en place.
  Pour l'effacer, il faut envoyer `password: null` — un geste explicite.

Aucun chiffrement maison.

## 10. Validations JSON

### `allowedIps` (§14)

Le diagramme ne précise pas la structure. **Retenue : liste plate de chaînes**,
chacune une adresse IP ou un bloc CIDR. Validée par `ip` ou une expression CIDR.
Ni objets imbriqués, ni valeurs non textuelles.

### `permissions` (§15)

**Liste plate de codes de permissions existants**, validés contre la table
`permissions`. Le §15 l'exige : « ne pas créer un second système de
permissions ».

Les permissions d'administration sont refusées : une clé API client ne doit pas
pouvoir gérer des rôles, des utilisateurs ou des organisations. La liste
interdite est dérivée des modules `roles`, `users`, `organizations` et
`permissions` — pas recopiée.

### `mapping`, `validationRules`, `settings` (§9)

Castés en `array`, validés comme tableaux, bornés en taille. **Le schéma métier
n'est pas inventé** : le diagramme ne le définit pas, ces structures restent
librement configurables. Aucune évaluation, aucun appel dynamique — ce ne sont
que des données lues par un futur moteur d'import.

### `fileNamePattern` (§21)

Chaîne validée contre la traversée de chemin (`..`, `/`, `\`) et les caractères
interdits par les systèmes de fichiers. **Aucun moteur de template** n'est créé,
et aucun jeton n'est documenté : le projet n'en définit aucun.

### `encoding` (§22)

Chaîne libre. Le §22 interdit d'inventer une liste ; aucune convention n'existe
dans le projet.

## 11. Traitement des exports — décision explicite

Le §29 autorise un `ProcessExportJob` « **seulement si le projet utilise les
queues** ». `QUEUE_CONNECTION=database` est configuré, mais **aucun job n'existe
dans le projet** : la file n'est utilisée nulle part.

Surtout, le §30 est catégorique :

> Si les règles de contenu ne sont pas définies : créer l'architecture d'export ;
> **ne pas inventer un schéma métier** ; implémenter uniquement les types dont le
> mapping est connu dans le projet. **Ne pas produire de faux export métier.**

**Aucune règle de contenu n'est définie** : ni le diagramme, ni les Phases 1 à 7
ne disent ce que contient un export XML de commandes, ni comment nommer ses
balises. Écrire un générateur reviendrait à inventer un format que le client
rejetterait.

**Décision : la génération de fichier et les transports ne sont pas
implémentés.** Sont livrés :

- les trois configurations, complètes et validées ;
- `ExportJob` comme **enregistrement d'exécution** : création, lecture, et
  `retry` qui incrémente `attemptCount` et efface `errorMessage` ;
- aucun `ProcessExportJob`, aucun `FtpExportTransporter` — des classes vides ou
  fabriquées seraient pires que leur absence, et le §5 interdit les classes
  vides.

Ce qui manque est donc **une seule chose, clairement délimitée** : le moteur qui
lit un `ExportJob`, produit le fichier et le dépose. Il pourra être ajouté sans
migration ni changement d'API le jour où les formats seront spécifiés.

## 12. Référence générique d'`ExportJob` (§25)

`entityType` / `entityId` réutilisent la **morph map** existante, comme
`StockMovement.sourceEntityType` en Phase 7. La liste des types autorisés est
dérivée de `MorphMap::registered()`, jamais recopiée.

Aucune clé étrangère sur `entityId` : la colonne peut désigner plusieurs tables.

## 13. Permissions et endpoints

16 permissions :

```text
customer_import_configurations.view / create / update / delete
customer_api_configurations.view / create / update / delete / rotate_key
customer_export_configurations.view / create / update / delete
export_jobs.view / create / retry
```

`export_jobs` n'a ni `update` ni `delete` : les routes n'existent pas.
`rotate_key` est distincte d'`update` — remplacer une clé coupe l'accès des
intégrations qui l'utilisent.

26 routes :

```text
GET|POST          /customer-import-configurations
GET|PATCH|DELETE  /customer-import-configurations/{configuration}
GET|POST          /customers/{customer}/import-configurations
GET|POST          /customer-api-configurations
GET|PATCH|DELETE  /customer-api-configurations/{configuration}
POST              /customer-api-configurations/{configuration}/rotate-key
GET|POST          /customers/{customer}/api-configurations
GET|POST          /customer-export-configurations
GET|PATCH|DELETE  /customer-export-configurations/{configuration}
GET|POST          /customers/{customer}/export-configurations
GET|POST          /export-jobs
GET               /export-jobs/{exportJob}
POST              /export-jobs/{exportJob}/retry
```

## 14. Ordre des migrations

```text
1. customer_import_configurations
2. customer_api_configurations
3. customer_export_configurations
4. export_jobs
```

**Aucun timestamp, aucun soft delete.** Le §2 les range parmi les ajouts
interdits, et aucune des quatre classes n'en déclare. `ExportJob` porte
`generatedAt` et `sentAt`, qui sont des dates métier.

## 15. Éléments exclus

```text
Import  ImportFile  ImportRow  ImportError  ImportMapping  ImportResult
ImportTemplate  ExportTemplate  ExportBatch  ExportResult  ExportError
ApiRequestLog  ApiUsageLog  ApiToken  ApiClient  Webhook  WebhookDelivery
ScheduledExport  ExportHistory  FileTransferLog  NotificationJob
ApiKeyHistory  ExportEntity
```

Attributs non ajoutés : `organization_id`, `created_by`, `updated_by`,
`created_at`, `updated_at`, `deleted_at`, `softDeletes`, `description`,
`api_secret`, `api_key`, `token`, `token_expires_at`, `rate_limit`,
`last_error_at`, `next_run_at`, `started_at`, `completed_at`, `payload`,
`response_body`, `metadata`. `settings` n'existe que sur
`CustomerExportConfiguration`, où le diagramme le déclare.
