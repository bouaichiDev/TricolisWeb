# Phase 8 Frontend — Rapport final

Intégrations clients : configurations d'import, accès API, configurations
d'export et historique des envois.

> Préalable : [phase-8-analysis.md](phase-8-analysis.md) (analyse du backend
> réel et du code Phase 6 réutilisé).

## 1. Branche

```text
feature/frontend-phase-8-customer-integrations
```

Créée depuis `feature/frontend-phase-7-customer-stock` (commit `3143427`,
`FRONTEND_PHASE_7_READY`). Ni fusion ni poussée automatique.

**À signaler** : l'arbre de travail portait déjà 28 fichiers non commités du
module Exports (formats CSV/PDF, transports e-mail et REST). Ils n'ont pas été
commités ni modifiés — mais cette phase **s'appuie dessus**, puisque ce sont eux
qui rendent les cinq transports réels.

## 2. Identité Git

```text
git config user.name   → Badr
git config user.email  → bouaichibadr@gmail.com
GIT_AUTHOR_IDENT       → Badr <bouaichibadr@gmail.com>
GIT_COMMITTER_IDENT    → Badr <bouaichibadr@gmail.com>
```

## 3. Absence de mention Claude / Anthropic

Aucun fichier produit, aucun message de commit et aucune ligne de documentation
ne mentionne Claude ou Anthropic. Aucun `Co-authored-by` ni `Generated-by`.

## 4. Configurations d'import

| Route | Écran |
|---|---|
| `/integrations/imports` | Liste : recherche, filtre client, tri serveur |
| `/integrations/imports/create` | Création |
| `/integrations/imports/:id` | Fiche |
| `/integrations/imports/:id/edit` | Modification, client verrouillé |

`sourceType` et `fileFormat` sont des champs **libres** : aucune énumération,
aucune table, aucune constante ne les contraint côté serveur, et les §8 et §9
interdisent d'en inventer une liste.

**Aucun écran d'exécution ni d'historique d'import**, et un test le vérifie : il
n'existe ni table `Import`, ni `ImportRow`, ni `ImportError`, ni route de
déclenchement. Le §5 s'arrête à la configuration, et l'interface aussi.

## 5. Configuration JSON

`JsonConfigurationEditor` (partagé) sert `mapping`, `validationRules` et
`settings`. Il formate, valide et réinitialise ; l'erreur affichée est celle de
`JSON.parse`, qui indique la position fautive — « JSON invalide » sans position
obligerait à relire cent lignes.

### La documentation qui va avec

Le §12 exige un éditeur contrôlé **et** une documentation. Un éditeur seul laisse
l'utilisateur écrire un JSON sans savoir quelles clés sont valides — le reproche
qu'on peut légitimement faire à un champ libre.

`ImportTargetFieldsReference` documente donc le **côté gauche** d'une
correspondance, relevé une par une sur `StoreOrderRequest` et
`StoreClaimRequest`, avec l'obligation et la contrainte de chaque champ,
copiable au clic.

Deux cibles sont présentées **séparément**, parce que ce sont deux documents
servis par deux endpoints — les fondre laisserait croire qu'un même fichier
porte des colis et un type de réclamation :

| Cible | Sections |
|---|---|
| Import de commandes | Commande · Lignes · Colis · **Services** · Contacts et colis d'un service |
| Import de réclamations | Réclamation |

La section **Services** mérite l'attention : `services` est `required|min:1` et
quinze de ses champs sont obligatoires — `serviceNumber`, `sequence`,
`requestedDate`, `quantity`, `unit`, les deux durées, poids, volume, nombre de
colis, les quatre montants et le statut. C'est la partie la plus exigeante d'un
import de commande, et l'ignorer produirait un 422 systématique.

Le **côté droit** décrit le fichier du client et n'est pas contraint : le backend
valide `mapping` comme un tableau sans schéma, et le §11 interdit d'inventer un
langage de correspondance qu'il ne possède pas.

Le panneau dit enfin ce qui est le plus facile à manquer — **cette
correspondance est enregistrée, pas exécutée** — pour qu'enregistrer ne laisse
pas croire qu'un import se déclenche. Deux tests le vérifient.

Les identifiants (`customerId`, `agencyId`, `catalogItemId`) sont volontairement
absents de la référence : un fichier client porte des références métier, pas les
ULID de notre base.

**Rien n'est exécuté** : le contenu est analysé pour vérifier sa syntaxe puis
renvoyé tel quel. Ni `eval`, ni fonction dynamique, ni nom de classe interprété
(§10, §41).

Le choix de l'éditeur JSON plutôt que d'un formulaire à champs est celui du
§12 : le backend valide `array|max:500` **sans schéma**, et supposer une
structure casserait au premier client dont le mapping diffère.

## 6. Accès API clients

| Route | Écran |
|---|---|
| `/integrations/api-access` | Liste |
| `/integrations/api-access/create` | Création + clé unique |
| `/integrations/api-access/:id` | Fiche + rotation |
| `/integrations/api-access/:id/edit` | Modification |

Colonnes : nom, résumé des IP, résumé des droits, activité, dernière
utilisation. **Jamais de clé, jamais de hash.**

`lastUsedAt` est en lecture seule : aucun formulaire ne l'envoie (§28), et un
test vérifie qu'aucun champ de saisie ne porte ce libellé.

### Ne pas confondre les deux sens

Le module `integrations` contenait déjà les `OrganizationApiConfiguration` :
les API **que l'organisme appelle** (télématique GPS). La Phase 8 y ajoute les
`CustomerApiConfiguration` : les clés **avec lesquelles un client nous appelle**.
Le §19 distingue les deux ; tous les fichiers de cette phase portent le préfixe
`customer-`, comme le §76 le prescrit.

## 7. Clé API affichée une seule fois

`ApiKeyCreatedDialog` montre la clé, avec son avertissement et un bouton
« Copier ». Le dialogue ne se ferme pas au clic à côté : on ne referme pas par
mégarde une valeur irrécupérable.

La clé vit **uniquement** dans l'état local du composant. Elle n'entre pas au
cache TanStack Query — `onSuccess` n'invalide que la liste, sans `setQueryData`
— ni en `localStorage`, ni en `sessionStorage`, ni dans une URL, ni dans un
journal. Quatre tests le vérifient, dont un qui inspecte les deux stockages et
l'URL après fermeture.

Le formulaire ne demande jamais `apiKeyHash` ni de clé, et un test vérifie que
la charge utile envoyée n'en contient aucune trace.

## 8. Rotation

`POST /customer-api-configurations/{id}/rotate-key`, derrière
`customer_api_configurations.rotate_key` et une confirmation qui dit ce qui va
se passer : « l'ancienne clé sera immédiatement invalidée ». La nouvelle suit le
même chemin — affichée une fois, jamais relisible.

**Aucun historique de clés** : ni `ApiKeyHistory`, ni `PreviousKeys`, ni
`ApiToken` (§25). Une clé remplacée n'existe plus.

## 9. allowedIps

`AllowedIpEditor` : une liste d'entrées, ajout à la volée, retrait au clic. La
structure est **connue** — la règle serveur `IpOrCidr` valide chaque entrée —
donc le §26 autorise mieux qu'un éditeur JSON.

La validation fine reste au serveur : reconnaître un CIDR IPv6 correctement
demande plus qu'une expression régulière, et une règle approximative refuserait
des adresses que le backend accepte.

**Une liste vide n'est pas une restriction vide** : le champ part en `null` et
la clé fonctionne depuis n'importe où. L'écran le dit plutôt que de laisser
croire qu'il reste à remplir.

## 10. Permissions API

`CustomerApiPermissionsEditor` propose les codes du **référentiel RBAC**, groupés
par section de menu et cherchables — parce que le backend n'en a pas d'autre :
`ApiPermissionValidator` valide chaque code contre la table `permissions`.

**Les cinq modules interdits ne sont pas recopiés.** `ApiPermissionValidator`
exclut `organizations`, `users`, `roles`, `permissions` et `subscriptions`, mais
aucune route ne publie cette liste. Le serveur refuse ces codes en 422 et son
message est affiché ; une liste dupliquée côté frontend divergerait au premier
changement, et le §105 interdit d'inventer une vérité métier.

## 11. Configurations d'export

`/integrations/exports` : liste globale, tous clients confondus — client, nom,
type d'export, transport, format, hôte, fréquence, secret posé ou non, activité.

**Aucun mot de passe affiché** : le serveur ne rend que `hasPassword`, un
booléen. La colonne dit qu'un secret existe, jamais lequel (§40, §45).

Le réglage lui-même passe par l'écran client à client, réutilisé tel quel.

## 12. Consolidation Phase 6

| Élément | Décision |
|---|---|
| `exports.api.ts` | **scindé** en `customer-export-configurations.api.ts` et `export-jobs.api.ts` (§76) |
| `exportKeys` | refondu en un jeu unique, partagé par Facturation et Intégrations |
| `ExportConfigurationDialog` + ses 3 panneaux | **réutilisés tels quels**, non réécrits |
| `ExportConfigurationListPage` | rendu montable avec un client imposé (§47) |
| `ExportJobListPage` | enrichi (type d'entité, fiche, génération), non dupliqué |
| Déclenchement automatique `Invoice CLOSED` | **intact** |

Il n'existe **qu'une** implémentation d'`ExportJob` et **qu'un** jeu de clés de
cache : une relance déclenchée depuis Facturation se voit dans Intégrations, et
réciproquement (§77).

`/billing/export-configurations` et `/billing/exports` fonctionnent toujours
(§48). Les 521 tests antérieurs passent sans modification — aucune régression.

### Écart assumé au §76

Le §76 place les quatre fichiers d'API sous `modules/integrations/api/`. Deux y
sont ; les deux autres restent dans `modules/exports/`, où vit l'implémentation
Phase 6. Les déplacer aurait réécrit des fichiers portant du travail non commité
d'un tiers, pour un gain nul : ce que le §77 exige — une seule implémentation,
un seul cache — est obtenu sans ce déplacement.

## 13–17. Les cinq transports

`ftp`, `sftp`, `rest_api`, `email`, `manual` — exactement les cinq cas de
`ExportTransport`, ni plus (§38, §81). Le formulaire conditionnel de la Phase 6
est réutilisé : il applique déjà les règles réelles du serveur — `host` requis
pour les transports qui l'exigent, `settings` obligatoire pour `email` sans quoi
rien ne partirait, modes d'authentification de `RestAuthentication` pour
`rest_api`.

React n'appelle jamais FTP ni SFTP et ne génère aucun fichier : `ProcessExportJob`
et les cinq transporteurs serveur s'en chargent (§59, §61).

## 18. Formats

`xml`, `csv`, `json`, `pdf` — les quatre cas de `ExportFormat`, ni plus (§37,
§80). Les valeurs sont en minuscules : c'est ce que la base stocke.

## 19. ExportJob

| Route | Écran |
|---|---|
| `/integrations/export-jobs` | Liste : filtres client, statut, recherche |
| `/integrations/export-jobs/:id` | Fiche |

La fiche montre statut, tentatives, dates, message d'erreur, destination, canal
et document. **Ni modification, ni suppression** — la route ne les expose pas —
et un test le vérifie.

`storagePath` n'apparaît nulle part : le serveur ne le renvoie pas, et un test
vérifie qu'aucun chemin ne fuit (§55).

Génération manuelle (`GenerateExportDialog`) derrière `export_jobs.create`, avec
la seule liste blanche réelle : **`invoice`**. `ExportDispatcher` ne connaît que
la facture — `invoiceOf()` refuse tout autre `entity_type`, et les cinq
formatteurs sont des formatteurs de facture. Le §60 interdit d'aller au-delà.

## 20. Retry

`POST /export-jobs/{id}/retry`, derrière `export_jobs.retry`. Le job est
**réutilisé**, jamais dupliqué : `RetryExportJobAction` n'incrémente
`attempt_count` que si l'envoi est réellement retenté, et redispatche
`ProcessExportJob` après commit.

Un envoi déjà transmis est refusé — contrôlé avant la transaction puis sous
verrou. Le bouton disparaît alors, mais c'est le 409 qui fait autorité ; deux
tests couvrent les deux faces.

## 21. Download — non implémenté

**Blocker.** Voir §33 ci-dessous. Aucun bouton n'est posé, et un test vérifie
qu'il n'y en a pas.

## 22. Statuts

`ExportJob.status` reste textuel, décrit par le référentiel. La source est
`export_job` — l'alias de `MorphMap`, au singulier ; le prompt écrit
`export_jobs`, qui est le nom de la table. `StatusBadge source="export_job"` est
employé sur les trois écrans d'envoi ; aucune couleur, aucun libellé en dur.

Les configurations portent `isActive`, un booléen, et **pas** de `status` : elles
n'entrent pas au référentiel (§53). Aucun `status_id`, aucune enum
`ExportJobStatus`.

`docs/backend/statuses-global-audit.md` est mis à jour : `export_job` était déjà
semé en Phase 6, il est confirmé, et la divergence de nommage est documentée.

## 23. Secrets

Vérifié ressource par ressource : **aucune** n'expose `apiKeyHash`,
`encryptedPassword` ni `storagePath`. Le serveur rend `hasPassword` et `hasFile`,
deux booléens. Les jeux de test reproduisent ces absences — un jeu plus généreux
que l'API masquerait précisément la fuite qu'on veut interdire.

Le formulaire d'export ne préremplit jamais le secret : son absence de la charge
utile signifie « inchangé », une chaîne vide l'effacerait (§40).

## 24. SSRF et chemins

Portés par le serveur : `RemoteTargetGuard` (SSRF), `SafeFileNamePattern` (motif
de nom), `ExportFileName`, et la validation du répertoire distant. Le frontend
n'ouvre aucune connexion et ne construit aucun chemin (§42, §43, §44).

## 25. Multi-organisation

`IntegrationScopeGuard` côté serveur, scope `inOrganization()` sur chaque
modèle, `abort_unless(... 404)` dans chaque `show`. Toutes les routes frontend
sont `organizationOnly` : un compte plateforme n'a pas de clients. Un
identifiant d'une autre organisation revient en 404, jamais en 403.

## 26. Couche API

```text
integrations/api/customer-import-configurations.api.ts
integrations/api/customer-api-configurations.api.ts
exports/api/customer-export-configurations.api.ts
exports/api/export-jobs.api.ts
```

`export-jobs.api.ts` n'expose ni `update`, ni `remove`, ni `download` :
l'absence est le contrat.

## 27. Clés de requête

`integrationKeys` (imports, accès API) et `exportKeys` (configurations, envois),
avec les variantes `list`, `detail`, `byCustomer`. **Aucune clé ne porte de clé
API** : le résultat de `create` et de `rotate-key` ne passe jamais par le cache.

## 28. Types TypeScript

`CustomerImportConfiguration`, `CustomerApiConfiguration`,
`CustomerApiKeyIssued`, `CustomerExportConfiguration`, `ExportJob`,
`ExportFormat`, `ExportTransport`, plus les filtres et les listes de tri
calquées sur `IntegrationListQuery`.

Restent des chaînes libres, conformément au §82 : `sourceType`, `fileFormat`,
`exportType`, `frequency`, `entityType`, `ExportJob.status`.

## 29. Zod

`customerImportConfigurationSchema` et `customerApiConfigurationSchema`. La
validation conditionnelle des exports selon le transport est déjà portée par le
formulaire Phase 6, réutilisé.

## 30. Tests

80 fichiers, 547 tests, tous verts — 26 ajoutés par cette phase.

| Fichier | Couvre |
|---|---|
| `CustomerApiConfigurationCreatePage.test.tsx` | clé absente du formulaire et de la charge utile, clé montrée une fois, copie, absence de tout stockage après fermeture |
| `CustomerApiConfigurationDetailPage.test.tsx` | restrictions affichées, clé et hash jamais montrés, `lastUsedAt` en lecture seule, rotation confirmée puis clé unique, permission |
| `CustomerImportConfigurationForm.test.tsx` | aucune exécution d'import, JSON envoyé tel quel, JSON invalide refusé, champs vides acceptés, formatage, référence des champs cibles, séparation commande/réclamation, mention « pas exécutée » |
| `ExportJobDetailPage.test.tsx` | statut et erreur, chemin de stockage jamais révélé, aucun téléchargement, ni édition ni suppression, relance, relance retirée si transmis, permission |

Vérifications :

```text
npm run lint       ✔ 0 erreur
npm run typecheck  ✔
npm run test       ✔ 547 / 547
npm run build      ✔
php artisan test   ✔ 1251 / 1251
./vendor/bin/pint  ✔
```

## 31. E2E

**Non exécuté : aucun harnais E2E n'est configuré dans ce dépôt** — ni script
`test:e2e`, ni Playwright, ni Cypress. Le §106 dit d'ailleurs « E2E si
configuré ».

Les parcours des §97 à §99 sont couverts au niveau composant : le cycle complet
de la clé API (§97) l'est intégralement — création, affichage unique, copie,
fermeture, absence, rotation, nouvelle clé unique. Le §98 l'est pour la partie
formulaire et JSON. Le §99 demande une infrastructure FTP/SFTP de test, qui
n'existe pas dans ce dépôt.

## 32. Régression Phase 6

Les 521 tests antérieurs passent **sans modification**, dont ceux de
`ExportConfigurationListPage` et `ExportJobListPage`. Le déclenchement
automatique à la clôture d'une facture n'a pas été touché : `InvoiceExportTrigger`
et `ProcessExportJob` sont intacts, et la règle `Invoice CLOSED` est vérifiée par
`ExportDispatcher::invoiceOf()` — une facture non clôturée est refusée quel que
soit le chemin, y compris par la génération manuelle (§63).

Les envois de facture apparaissent bien dans les deux vues, puisque ce sont les
mêmes `ExportJob` lus par le même cache.

## 33. Différences DB / UML, et blockers

1. **Téléchargement impossible.** Les §56, §58, §65 et §86 prévoient
   `GET /export-jobs/{id}/download` et la permission `export_jobs.download` :
   **ni l'un ni l'autre n'existe**. La ressource expose `hasFile`, mais rien ne
   sert le fichier. Fabriquer une URL depuis `storagePath` serait la seule autre
   voie — le §58 l'interdit, et le serveur ne renvoie pas ce chemin, à juste
   titre. Aucun bouton n'est posé ; l'écran dit qu'un fichier existe, sans
   promettre de le servir.
2. **`sourceType` et `fileFormat` sans liste contrôlée.** Aucune énumération ni
   table. Saisie libre, conformément aux §8 et §9.
2 bis. **Le mapping n'a pas de structure définie, et personne ne le lit.** Le
   backend le valide comme `array|max:500` sans schéma, et aucune Action ne
   l'interprète — il n'existe pas de moteur d'import (§5). Une correspondance
   imbriquée — un XML dont les lignes sont répétées, dont les adresses se
   distinguent par un code — ne peut donc pas être exprimée par une convention
   validée : il faudrait en inventer une, ce que le §11 interdit. La référence
   des champs cibles documente ce qui est certain ; le reste attend la
   spécification du moteur.
3. **Modules interdits aux clés API non exposés.** `ApiPermissionValidator` en
   exclut cinq, mais aucune route ne publie la liste. Le serveur refuse en 422
   plutôt que de voir la règle recopiée.
4. **`encryptedPassword` et `storagePath` remplacés par des booléens.** Le
   modèle du §33 et du §49 les nomme ; les ressources exposent `hasPassword` et
   `hasFile`. C'est **plus** sûr que ce que le prompt décrit, et conforme aux
   §40, §55 et §75.
5. **Source de statut au singulier.** Le §52 écrit `src = export_jobs` ; la
   valeur réelle est `export_job`, l'alias de `MorphMap`.
6. **Un seul `entityType` exportable.** Le §51 parle d'alias au pluriel ; seul
   `invoice` a un formatteur. Le §60 interdit d'en offrir d'autres.

## 34. Éléments exclus

Aucune entité du §4 n'a été créée : ni `Import*`, ni `ExportTemplate`,
`ExportBatch`, `ExportResult`, `ExportError`, `ExportHistory`, `ScheduledExport`,
ni `ApiRequestLog`, `ApiUsageLog`, `ApiToken`, `ApiClient`, ni `Webhook`,
`WebhookDelivery`, `FileTransferLog`, `NotificationJob`.

Le menu ne propose ni webhooks, ni exports planifiés, ni journaux d'appels API,
ni historique d'import.

## 35. Risques

1. **Le téléchargement manque à l'exploitation.** Un envoi échoué se relance,
   mais son fichier ne se récupère pas. Une route serveur lèverait la limite ;
   elle n'existe pas.
2. **`sourceType`, `fileFormat`, `exportType` et `frequency` en saisie libre**
   produiront des variantes orthographiques. Le modèle ne permet pas mieux
   aujourd'hui.
3. **La génération manuelle demande un identifiant de facture à la main.** Il
   n'existe pas de sélecteur : le lier demanderait de charger les factures
   clôturées du client, ce que la route `export-jobs` ne suggère pas. Une facture
   non clôturée est refusée à l'exécution, donc en file — le refus se lit sur
   l'envoi, pas à la saisie.
4. **Deux notions d'« API » cohabitent dans le même module.** Le préfixe
   `customer-` les sépare, et les commentaires le disent, mais la vigilance
   reste nécessaire.
5. **Aucun E2E.** Voir §31.

## 36. Phase suivante

Ne pas démarrer sans validation. La suite prévue est :

```text
FRONTEND PHASE 9 — COMMUNICATION RULES & AUTOMATISATION
```

Les communications manuelles de la Phase 3 y seront réutilisées ; seule
l'automatisation autour de `CommunicationRule` s'y ajoute.

## Conclusion

```text
FRONTEND_PHASE_8_READY
```
