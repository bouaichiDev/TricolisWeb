# Rapport final — Phase 8 : intégrations clients

---

## 1. Branche

```text
feature/backend-phase-8-customer-integrations
```

Créée depuis `feature/backend-phase-7-customer-stock` (commit `584671f`), non
depuis `main`. Même écart assumé qu'aux Phases 3 à 7.

Aucune fusion, aucun rebase, aucun push.

## 2. Diagrammes

Les `.puml` du §1 n'existent pas ; les `.txt` font foi. Classes lignes 160-214,
enums lignes 70-83, relations lignes 851-854.

**Aucun conflit.** C'est la première phase depuis la Phase 4 où prompt et
diagramme concordent exactement — aucun `legacyId` fantôme.

Colonnes créées, conformes attribut par attribut :

```text
customer_import_configurations    8 colonnes   (8 attributs)
customer_api_configurations       8 colonnes   (8 attributs)
customer_export_configurations   16 colonnes   (16 attributs)
export_jobs                      12 colonnes   (12 attributs)
```

## 3. Classes et enums

```text
CustomerImportConfiguration   CustomerApiConfiguration
CustomerExportConfiguration   ExportJob

ExportFormat    : XML, CSV, JSON, PDF
ExportTransport : FTP, SFTP, REST_API, EMAIL, MANUAL
```

`exportType`, `sourceType`, `fileFormat`, `frequency`, `encoding` et
`ExportJob.status` restent des `string` : le diagramme ne les énumère pas.

## 4. Migrations, modèles, Actions

**4 migrations**, **4 modèles**, **7 DTOs**, **3 services**, **2 exceptions**,
**1 Query Object**, **8 Requests**, **6 Resources**, **4 Policies**,
**4 Controllers**, **4 factories**.

**Actions (7)** :

```text
CreateCustomerApiConfigurationAction   RotateCustomerApiKeyAction
ManageApiConfigurationAction           ManageImportConfigurationAction
ManageExportConfigurationAction        CreateExportJobAction
RetryExportJobAction
```

Plus `WriteConfigurationAudit`, partagée : les trois configurations suivent
exactement le même cycle — créer, comparer, journaliser les seuls champs
changés. Trois Actions identiques à un nom de table près n'auraient rien
apporté.

## 5. Permissions et routes

**16 permissions** ; total du projet : **169**.

**26 routes**, aucun doublon sur les 307 du projet.

`rotate_key` et `retry` ont leur propre permission : la première coupe l'accès
des intégrations en cours, la seconde relance un envoi. Ce ne sont pas des
`update`.

## 6. Tests

| Fichier | Tests | Points saillants |
|---------|-------|------------------|
| `Integrations/ApiConfigurationTest` | 15 | **Clé retournée une fois**, **seule l'empreinte stockée**, jamais exposée en lecture ni en liste, **clé fournie par l'appelant ignorée**, **absente de l'audit**, rotation invalidant l'ancienne, IP et CIDR acceptés, IP malformée refusée, permission inconnue et administrative refusées, nom dupliqué, IDOR, routes client |
| `Exports/ExportConfigurationTest` | 16 | `host` exigé pour FTP/SFTP/REST_API, facultatif pour EMAIL/MANUAL, format et transport hors enum refusés, **traversée de chemin refusée**, port hors bornes, **mot de passe chiffré et jamais retourné**, **conservé si omis**, **effacé si `null`**, **absent de l'audit**, refus de suppression avec exports, IDOR, colonnes interdites absentes |
| `Exports/ExportJobTest` | 14 | Client **déduit** de la configuration, client fourni ignoré, configuration inactive refusée, hors organisation refusée, alias morph accepté, classe PHP refusée, **champs de traitement ignorés**, retry incrémentant et effaçant l'erreur, **retry refusé après envoi**, absence de `PATCH`/`DELETE` (405), ordre décroissant, IDOR, audit |
| `Integrations/ImportConfigurationTest` | 10 | Mapping libre accepté, mapping non-tableau refusé, client hors organisation, nom dupliqué par client mais libre ailleurs, CRUD, IDOR, recherche et filtres, **absence des tables d'exécution d'import**, audit |
| `Integrations/IntegrationPermissionTest` | 7 | Lecture, création, **rotation** et **relance** refusées sans permission ; lire un accès ne permet pas d'en renouveler la clé ; en-tête requis ; non authentifié refusé |

**62 tests ajoutés.**

## 7. Résultats

```text
composer validate                                valid
php artisan migrate:fresh --seed --env=testing   OK
php artisan test                                 593 passed (1930 assertions)
./vendor/bin/pint --test                         PASS
php artisan route:list                           307 routes, aucun doublon
TODO / classes vides                             aucun
constructions PostgreSQL                         aucune
```

531 tests des Phases 1 à 7, 62 de la Phase 8. **Aucune régression.**

`MorphMap.php` reste au-dessus des 200 lignes recommandées — **276 lignes**,
contre 260 en Phase 7 : les quatre alias de la Phase 8 en ajoutent seize. La
raison est celle exposée au rapport de la Phase 7 : c'est un registre plat, et
le scinder renommerait 40 usages dans 20 fichiers. C'est le seul fichier de
`app/` au-dessus de la limite.

Le rapport de la Phase 7 annonçait 202 lignes : ce comptage ignorait les lignes
vides. Le chiffre réel à la Phase 7 était **260**.

## 8. Décisions structurantes

### Le hash de la clé API

Bcrypt a été **écarté** : trop lent pour une vérification par requête, et non
déterministe — il faudrait comparer la clé présentée à toutes celles du client.

**SHA-256 retenu**, avec index unique. Un hash rapide est cryptographiquement
suffisant *parce que la clé est générée, pas choisie* : 64 caractères aléatoires
ne se cassent pas par force brute. C'est le raisonnement de Sanctum, déjà utilisé
ici, et la convention `hash('sha256', …)` existe déjà dans le projet
(`LoginUser`, `email_hash`).

Cinq garanties, toutes testées : la clé est générée par le serveur, retournée une
seule fois, jamais relisible, jamais auditée, et une clé fournie par l'appelant
est ignorée.

### Le mot de passe de transport

`Crypt::encryptString()` — **réversible**, contrairement aux mots de passe
utilisateurs, parce que le transport devra le présenter au serveur distant.

Jamais retourné, jamais journalisé, et **conservé** quand le payload l'omet :
sans cette règle, modifier le seul `username` l'effacerait.

### Ni permissions ni schémas inventés

`permissions` valide contre la table `permissions`, jamais contre une liste
recopiée. Cinq modules sont interdits à une clé client, dérivés du champ
`module` — la règle reste juste quand un module gagne une permission.

`mapping`, `validationRules` et `settings` restent librement structurables : le
diagramme n'en définit pas le schéma.

## 9. Ce qui n'est pas livré — décision explicite

**La génération de fichier et les transports ne sont pas implémentés.**

Le §30 l'impose : « Si les règles de contenu ne sont pas définies : […] ne pas
inventer un schéma métier […] Ne pas produire de faux export métier. »

Aucune règle n'existe : rien ne dit ce que contient un export XML de commandes ni
comment nommer ses balises. Un générateur écrit à l'aveugle produirait un fichier
que le client rejetterait — pire qu'une absence.

`QUEUE_CONNECTION=database` est configuré mais **aucun job n'existe dans le
projet** ; le §29 n'autorise `ProcessExportJob` que « si le projet utilise les
queues ». Aucun transporteur vide n'est créé non plus : le §5 interdit les
classes vides.

Ce qui manque est **une seule chose, délimitée** : le moteur qui lit un
`ExportJob`, produit le fichier et le dépose. Il s'ajoutera sans migration ni
changement d'API. En attendant, `generatedAt` et `sentAt` restent nuls.

## 10. Ambiguïtés levées

| # | Ambiguïté | Traitement |
|---|-----------|------------|
| A | Mécanisme de hash (§12) | SHA-256, justifié par l'entropie de la clé et la convention du projet |
| B | Rotation de clé (§13) | Implémentée, sans table d'historique |
| C | Structure d'`allowedIps` (§14) | Liste plate d'IP ou CIDR, documentée |
| D | Permissions API (§15) | Codes existants, cinq modules administratifs interdits |
| E | Nullabilité conditionnelle (§19) | Seul `host` rendu obligatoire ; le §19 dit « peuvent » |
| F | Mot de passe en modification (§20) | Trois branches : omis conserve, valeur remplace, `null` efface |
| G | Jetons de `fileNamePattern` (§21) | Aucun : le projet n'en définit pas |
| H | Liste d'`encoding` (§22) | Aucune : le §22 interdit de l'inventer |
| I | `customerId` redondant (§24) | Déduit de la configuration, jamais accepté |
| J | Traitement asynchrone (§29, §30) | **Non implémenté**, documenté — voir §9 |

## 11. Fichiers

**43 créés**, **4 modifiés** par ajout (`routes/api.php`, `PermissionSeeder`,
`AuthServiceProvider`, `MorphMap`). Aucune ligne des Phases 1 à 7 supprimée.

## 12. Éléments exclus

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
`response_body`, `metadata`.

## 13. Risques

1. **Aucun export n'est réellement produit** — voir §9. C'est la limite connue et
   délimitée de la phase.
2. **`lastUsedAt` n'est jamais renseigné.** Le §17 demandait un mécanisme minimal
   après validation réussie d'une clé ; **aucun point d'entrée n'authentifie par
   clé API** dans le projet. Le champ existe, prêt, mais rien ne l'écrit tant
   qu'un middleware d'authentification client n'existe pas. C'est le pendant du
   point 1.
3. **`allowedIps` et `permissions` ne sont pas appliqués.** Ils sont stockés et
   validés, mais aucun code ne les vérifie — pour la même raison.
4. **Perdre `APP_KEY` rend les mots de passe de transport illisibles.** C'est le
   compromis assumé de `Crypt`, et il vaut mieux que le stockage en clair.
5. **La dette des Phases 4 à 7 reste ouverte** : `DeleteTourAction` ne refuse
   toujours pas la suppression d'une tournée référencée par un `TrackingEvent`,
   une `ProofOfDelivery` ou une `Claim`.

## 14. Prochaine phase

**Non commencée** : la Phase 9 (communications et templates) attend une
validation explicite.
