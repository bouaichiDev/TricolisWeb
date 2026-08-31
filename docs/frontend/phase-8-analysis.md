# Phase 8 Frontend — Analyse préalable

> Analyse conduite avant écriture de code, sur le backend réellement implémenté
> et sur le frontend Phase 6 déjà livré.

## 1. Branche source

```text
feature/frontend-phase-7-customer-stock   (commit 3143427, FRONTEND_PHASE_7_READY)
→ feature/frontend-phase-8-customer-integrations
```

**À signaler** : l'arbre de travail porte 28 fichiers non commités du module
Exports (formats CSV/PDF, transports e-mail et REST, `composer.json`). Ils ne
sont pas de cette phase et ne seront pas commités ici — mais la Phase 8
**s'appuie dessus**, puisque ce sont eux qui rendent les cinq transports réels.

## 2. Backend Phase 8 — état réel

**Entièrement implémenté.** Rien à écrire côté serveur.

| Module | Contenu |
|---|---|
| `app/Modules/Integrations` | 3 modèles, 5 Actions, 4 DTOs, 1 Query, 3 Services |
| `app/Modules/Exports` | 2 modèles, 3 Actions, 4 DTOs, 2 Enums, 1 Job, 5 formatteurs, 5 transporteurs, `RemoteTargetGuard` |

Services notables : `ApiKeyGenerator` (clé cryptographique + hash),
`ApiPermissionValidator` (liste blanche des permissions), `RemoteTargetGuard`
(SSRF), `ExportFileName` (motif de nom sécurisé), `SafeFileNamePattern` et
`ExportSettings` (règles de validation dédiées).

### Endpoints réels

| Endpoint | Existe |
|---|---|
| `GET/POST/PATCH/DELETE /customer-import-configurations` | oui |
| `GET/POST /customers/{c}/import-configurations` | oui |
| `GET/POST/PATCH/DELETE /customer-api-configurations` | oui |
| `POST /customer-api-configurations/{c}/rotate-key` | oui |
| `GET/POST /customers/{c}/api-configurations` | oui |
| `GET/POST/PATCH/DELETE /customer-export-configurations` | oui |
| `GET/POST /customers/{c}/export-configurations` | oui |
| `GET/POST /export-jobs`, `GET /export-jobs/{id}` | oui |
| `POST /export-jobs/{id}/retry` | oui |
| **`GET /export-jobs/{id}/download`** | **non — voir blockers** |

### Sécurité des ressources (§75)

Vérifié ligne à ligne : **aucune** ressource n'expose `apiKeyHash`,
`encryptedPassword` ni `storagePath`. Le serveur rend à la place deux booléens,
`hasPassword` et `hasFile`. `ApiKeyIssuedResource` est la seule à porter une
clé en clair, et uniquement en réponse à la création ou à la rotation.

## 3. Code Phase 6 réutilisé

| Élément Phase 6 | Décision Phase 8 |
|---|---|
| `modules/exports/api/exports.api.ts` | scindé en `customer-export-configurations.api.ts` et `export-jobs.api.ts` sous `integrations` |
| `ExportConfigurationDialog` + ses 3 panneaux de champs | **réutilisés tels quels**, déplacés, non réécrits |
| `ExportConfigurationListPage`, `ExportJobListPage` | conservés, enrichis (filtres, fiche) |
| `types/export.ts`, `utils/exportPayload.ts` | conservés, étendus |
| Déclenchement automatique `Invoice CLOSED` | intact, aucune modification |

Aucun second moteur d'export n'est créé (§34, §77, §105).

### Collision de nommage à connaître

`modules/integrations` **existe déjà** et désigne les
`OrganizationApiConfiguration` : les API **que l'organisme appelle** (télématique
GPS). La Phase 8 y ajoute les `CustomerApiConfiguration` : les clés **avec
lesquelles un client nous appelle**. Le §19 distingue explicitement les deux
sens.

Les fichiers Phase 8 portent donc tous le préfixe `customer-`, comme le §76 le
prescrit. Les fichiers Phase 5 restent inchangés : les renommer toucherait du
code validé sans rien apporter.

## 4–8. CustomerImportConfiguration

Champs exposés : `id`, `customerId`, `name`, `sourceType`, `fileFormat`,
`mapping`, `validationRules`, `isActive`. Rien d'autre — ni `host`, ni
`schedule`, ni `lastImportedAt` (§6 les nomme comme à ne pas ajouter).

Validation : `sourceType` `string|max:64`, `fileFormat` `string|max:32`,
`mapping` et `validationRules` `array|max:500`, `name` unique par client.

**`sourceType` et `fileFormat` n'ont aucune liste contrôlée** — ni enum, ni
table, ni constante. Conformément aux §8 et §9, rien n'est codé en dur : saisie
libre. Le filtre de liste les accepte tels quels.

`mapping` et `validationRules` sont validés comme **tableaux**, jamais
interprétés : aucune Action ne les exécute. Le §5 est respecté — il n'existe
aucun moteur d'import, et aucun écran d'historique ne sera créé.

## 9–12. mapping / validationRules

Le backend n'impose **aucune structure interne** : `array|max:500`, sans schéma.
Le §12 tranche ce cas : « sinon, utiliser un éditeur JSON contrôlé avec
validation syntaxique ». C'est ce qui sera fait — `JsonConfigurationEditor`,
avec formatage, validation syntaxique, message d'erreur et réinitialisation.
Aucun DSL n'est inventé.

## 9–14. CustomerApiConfiguration

Champs exposés : `id`, `customerId`, `name`, `allowedIps`, `permissions`,
`isActive`, `lastUsedAt`. **`apiKeyHash` n'est jamais rendu.**

### Stratégie de hachage et clé unique

`ApiKeyGenerator` produit la clé, le serveur en stocke le hash.
`ApiKeyIssuedResource` renvoie `{ configuration, apiKey, warning }` — la seule
réponse portant la clé en clair, et seulement à la création et à la rotation.
Aucun `GET` ne la renvoie ensuite.

Côté frontend : la clé vit dans l'état local du dialogue, est effacée à la
fermeture, et n'entre ni dans le cache TanStack Query, ni dans `localStorage`,
ni dans une URL, ni dans un journal (§22, §105).

### allowedIps — structure réelle

`allowedIps` est un tableau, chaque entrée validée par la règle `IpOrCidr`.
La sémantique est donc établie : **liste d'IP ou de CIDR**. Le §26 autorise
alors un `AllowedIpEditor` dédié plutôt qu'un éditeur JSON brut.

### permissions — liste blanche réelle

`ApiPermissionValidator::allowedCodes()` dérive la liste des **permissions RBAC
existantes**, moins cinq modules interdits à une clé client (`organizations`,
`users`, `roles`, `permissions`, `subscriptions`). Le §27 autorise donc un
`CustomerApiPermissionsEditor` plutôt qu'un éditeur JSON.

**Nuance importante** : la liste des modules interdits n'est pas exposée par une
route. Le frontend proposera donc les permissions de `GET /permissions`, et
c'est le serveur qui refusera les codes interdits en 422 — le message est
affiché tel quel. Recopier la liste des cinq modules côté frontend créerait une
seconde vérité qui divergerait au premier changement.

`lastUsedAt` est en lecture seule : aucun formulaire ne l'envoie.

## 15–23. CustomerExportConfiguration

Champs exposés conformes au §33, à ceci près que `encryptedPassword` est
remplacé par le booléen `hasPassword` — c'est exactement ce que le §40 exige.

`exportType` et `frequency` restent des chaînes libres (§35, §36). La valeur
Invoice de la Phase 6 est conservée à l'identique.

### Formats et transports — exacts

```text
ExportFormat    : xml, csv, json, pdf
ExportTransport : ftp, sftp, rest_api, email, manual
```

Les enums PHP ne contiennent rien d'autre : ni `xlsx`, ni `s3`, ni `ftps`
(§37, §38, §80, §81). Les valeurs sont en **minuscules** en base ; le prompt les
écrit en majuscules, ce sont les mêmes.

### Champs conditionnels — règle serveur réelle

`host` est `required` seulement pour les transports dont `requiresHost()` est
vrai. `settings` est **obligatoire** pour `email` — sans destinataire rien ne
partirait. La règle `ExportSettings` valide en plus, selon le transport :
`authType` parmi les modes de `RestAuthentication`, `apiKeyHeader` limité aux
lettres/chiffres/tirets, `tokenUrl` obligatoire en OAuth2,
`tokenLifetimeSeconds` entre 120 et 86 400, et les destinataires courriel.

Le formulaire Phase 6 implémente déjà cette logique conditionnelle : il est
réutilisé, pas réécrit.

### Mot de passe

`password` est acceptée en écriture, jamais rendue en lecture. Le formulaire
d'édition ne préremplit rien et **l'absence du champ signifie « inchangé »** —
une chaîne vide effacerait le secret sans le vouloir. C'est déjà le
comportement Phase 6.

### SSRF, motif de nom, répertoire distant

Portés par le serveur : `RemoteTargetGuard`, `SafeFileNamePattern`,
`ExportFileName`. Le frontend n'appelle jamais FTP/SFTP et ne génère aucun
fichier (§59, §61, §105).

## 24–29. ExportJob

Champs exposés conformes au §49, sauf `storagePath` remplacé par `hasFile`
(§55, §75).

### Statuts — référentiel déjà en place

`StatusSeeder` sème `MorphMap::EXPORT_JOB` avec `pending`, `processing`, `sent`,
`failed`. La source est donc **déjà** au référentiel depuis la Phase 6 : le §101
est satisfait, il reste à le confirmer dans l'audit. Aucun `status_id`, aucune
enum `ExportJobStatus`.

Les configurations, elles, portent `isActive` (booléen) et **pas** de `status` :
elles n'entrent pas au référentiel (§53, §101).

### entityType

Validé par `Rule::in(array_keys(MorphMap::registered()))` — les alias contrôlés
du serveur, jamais un nom de classe PHP (§51). Invoice réutilise l'alias
Phase 6.

### Retry

`RetryExportJobAction` refuse un job déjà envoyé (`sent_at != null`) — contrôlé
avant la transaction puis sous verrou — et lève `ExportJobNotRetryable`. Il
réutilise le **même** job : `attempt_count` n'est incrémenté que si l'envoi est
réellement retenté, et `ProcessExportJob` est redispatché après commit. Aucune
facture n'est dupliquée (§57).

`POST /export-jobs/{id}/retry` attend un champ `status`.

### Queue

`ProcessExportJob` est la seule voie de génération et d'envoi. React ne produit
aucun fichier B2B.

## 30. Filtres, tris, pagination réels

`IntegrationListQuery` définit quatre profils. Tris autorisés :

| Profil | `sort` acceptés | Défaut |
|---|---|---|
| import | `name`, `source_type`, `file_format`, `is_active` | `name` |
| api | `name`, `is_active`, `last_used_at` | `name` |
| export | `name`, `export_type`, `format`, `transport`, `frequency`, `is_active` | `name` |
| job | `generated_at`, `sent_at`, `attempt_count`, `status`, `file_name` | `generated_at` |

Recherche : import sur `name`/`source_type`/`file_format` ; api sur `name` ;
export sur `name`/`export_type`/`host`/`username` ; job sur
`file_name`/`status`/`error_message`/`entity_type`.

Filtres communs : `customerId`, `isActive`. Plages de dates : `lastUsedFrom/To`
(api), `generatedFrom/To` et `sentFrom/To` (job).

## 31. Permissions

Les 17 codes du §86 existent **sauf un** :

```text
customer_import_configurations.{view,create,update,delete}   ✔
customer_api_configurations.{view,create,update,delete,rotate_key}  ✔
customer_export_configurations.{view,create,update,delete}   ✔
export_jobs.{view,create,retry}                              ✔
export_jobs.download                                         ✘ absent
```

Tous rattachés à `MenuSection::INTEGRATIONS` via `PermissionMenuMap`.

## 32. Multi-organisation

`IntegrationScopeGuard` côté serveur, scope `inOrganization()` sur chaque
modèle, `abort_unless(... 404)` dans chaque `show`. Toutes les routes frontend
seront `organizationOnly`.

## 33. Exclusions

Aucune des entités du §4 n'existe ni ne sera créée : `Import*`, `Export*`
(Template/Batch/Result/History/Scheduled), `Api*` (Token/Client/RequestLog/
UsageLog), `Webhook*`, `FileTransferLog`, `NotificationJob`.

---

## Blockers

1. **Téléchargement d'un export impossible.** Les §56, §58, §65 et §86
   prévoient `GET /export-jobs/{id}/download` et la permission
   `export_jobs.download`. **Ni l'un ni l'autre n'existe.** La ressource expose
   bien `hasFile`, mais rien ne sert le fichier. Le §58 interdit par ailleurs
   de construire une URL depuis `storagePath` — que le serveur ne rend pas non
   plus, à juste titre.

   Décision : aucun bouton de téléchargement n'est posé. `hasFile` est affiché
   comme information (« fichier généré »), et le blocker est documenté. Poser
   un bouton mènerait à un 404, et fabriquer une URL violerait le §58 et le
   §105.

2. **`sourceType` et `fileFormat` sans liste contrôlée.** Aucune énumération ni
   table. Conformément aux §8 et §9 : saisie libre, aucune valeur inventée.

3. **Modules interdits aux clés API non exposés.** `ApiPermissionValidator`
   exclut cinq modules, mais aucune route ne publie cette liste. Le frontend
   propose les permissions de `GET /permissions` et laisse le serveur refuser
   en 422 plutôt que de recopier la règle.

## Tableau Fonction UI → Endpoint

| Fonction UI | Endpoint | Permission | État |
|---|---|---|---|
| Hub intégrations | — | `customer_export_configurations.view` | à créer |
| Liste imports | `GET /customer-import-configurations` | `customer_import_configurations.view` | à créer |
| Imports d'un client | `GET /customers/{c}/import-configurations` | idem | à créer |
| Créer / modifier / supprimer import | `POST`/`PATCH`/`DELETE` | `.create` / `.update` / `.delete` | à créer |
| Liste accès API | `GET /customer-api-configurations` | `customer_api_configurations.view` | à créer |
| Créer accès API (clé unique) | `POST /customer-api-configurations` | `.create` | à créer |
| Renouveler la clé | `POST /customer-api-configurations/{c}/rotate-key` | `.rotate_key` | à créer |
| Liste configs export | `GET /customer-export-configurations` | `customer_export_configurations.view` | **Phase 6, à consolider** |
| Créer / modifier / supprimer export | `POST`/`PATCH`/`DELETE` | `.create` / `.update` / `.delete` | **Phase 6, à consolider** |
| Liste exports | `GET /export-jobs` | `export_jobs.view` | **Phase 6, à consolider** |
| Fiche export | `GET /export-jobs/{id}` | `export_jobs.view` | à créer |
| Export manuel | `POST /export-jobs` | `export_jobs.create` | à créer |
| Relancer | `POST /export-jobs/{id}/retry` | `export_jobs.retry` | **Phase 6, à consolider** |
| Télécharger | — | — | **bloqué** |
