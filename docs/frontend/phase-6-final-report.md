# Phase 6 — Rapport final

Facturation client, décomptes fournisseurs, export automatique des factures
clôturées. Rédigé le 28 août 2026.

## 1. Branches et identité Git

| | |
|---|---|
| Branche de base | `feature/frontend-phase-5-planning-tours` (dernier commit `65f700d`) |
| Branche Phase 6 | `feature/frontend-phase-6-billing-exports` |
| Git Author | `Badr <bouaichibadr@gmail.com>` |
| Git Committer | `Badr <bouaichibadr@gmail.com>` |

Aucun commit ne porte `Claude`, `Anthropic`, `Co-authored-by` ni `Generated-by`,
ni comme auteur, ni comme committer, ni dans le message. Vérifié sur les huit
commits de la branche.

Rien n'a été fusionné vers `main`, rien n'a été poussé.

## 2. Ce que la phase a trouvé, et ce qu'elle a construit

Le backend portait déjà l'essentiel : les sept tables du domaine existaient,
conformes à l'UML, avec leurs contraintes d'unicité. `docs/frontend/phase-6-analysis.md`
en fait le relevé. Manquaient le référentiel de statuts, la clôture, les
sélecteurs de prestations, le moteur d'export et tout le frontend.

### Factures clients

- `/billing/invoices` — liste, filtres client, période, statut, recherche.
- `/billing/invoices/create` — composition à partir des prestations facturables.
- `/billing/invoices/:id` — fiche, lignes, clôture.

Le client se fige dès la première prestation retenue : une facture ne porte
qu'un destinataire, et en changer en cours de route produirait un document
mêlant les deux.

### Prestations facturables

`GET /customers/{customer}/billable-services`. **Le serveur décide** (§42) : une
règle recopiée dans React finirait par diverger et proposerait des prestations
que la création refuserait. Sont éligibles les prestations `completed` sans
ligne de facture, dans la période demandée, chez ce client et cette
organisation.

### Lignes, clichés d'adresse, totaux

Les lignes sont créées avec la facture — le serveur exige au moins une ligne
(§8). Le cliché d'adresse est repris à l'affichage : le §13 veut qu'une facture
d'août montre encore l'adresse d'août, même après un déménagement. **Les totaux
ne sont jamais calculés côté écran** : `RecalculateInvoiceTotals` les établit à
l'enregistrement, et le total affiché pendant la composition est explicitement
indicatif.

### Clôture et immutabilité

`POST /invoices/{invoice}/close`, précédé de `GET /invoices/{invoice}/closure`
qui annonce les destinations (§52). La clôture est le **seul** déclencheur
d'envoi : il n'existe pas de route `/send`, le §24 la refuse.

Une facture clôturée ne se modifie plus (§22) : le serveur répond 422 sur
`PATCH`, `DELETE`, l'ajout et le retrait de ligne ; l'écran retire ces actions
plutôt que de les laisser échouer au clic.

La clôture est idempotente : deux clics ne produisent qu'un envoi par
destination (`firstOrCreate` sur `configuration_id + entity_type + entity_id`).

### Statuts

| Source | Codes | Transitions |
|---|---|---|
| `invoice` | `draft`, `closed` | `draft → closed` |
| `invoice_line` | `billable` | aucune |
| `provider_settlement` | `draft`, `closed` | `draft → closed` |
| `export_job` | `pending`, `processing`, `sent`, `failed` | aucune |

Le code de « Clôturée » est **`closed`**, stocké en clair dans
`invoices.status`. Aucune des quatre tables ne porte de `status_id` : le
référentiel décrit les codes, il ne les héberge pas. Détail dans
`docs/backend/statuses-global-audit.md`.

### Décomptes fournisseurs

`/billing/settlements`, plus un onglet « Décomptes » sur la fiche fournisseur,
d'où part la création avec le fournisseur prérempli (§101).

`GET /providers/{provider}/settleable-services` applique le §17, qui est le
point délicat du domaine : un service peut avoir plusieurs affectations, chez
des fournisseurs différents — une tentative échouée chez l'un, la livraison chez
l'autre. **Le fournisseur à payer est celui de l'affectation active**, que la
Phase 5 garantit unique. Une tournée sans fournisseur ne rend rien réglable :
personne à payer.

**Prix client et coût fournisseur ne se confondent pas.** Le décompte reprend
`providerUnitCost`, fixé à la commande ; `customerUnitPrice` est affiché à côté
pour se repérer. Régler le second reverserait la marge au fournisseur. La
ressource `SettleableServiceResource` a été complétée pour exposer le coût —
la commande le portait déjà, la ressource l'omettait.

Conformément au §108, **aucun décompte n'est transmis** par les configurations
d'export client : la fiche n'offre aucune destination.

### Destinations d'export et envois

`/billing/export-configurations` (client par client, §113) et `/billing/exports`
(historique, relance).

- `exportType = invoice`, `frequency = on_invoice_closed` : ce que la clôture
  consulte.
- Formats proposés : **JSON et XML seulement**. Le §32 ne retient CSV et PDF que
  si leur générateur existe ; il n'existe pas, et offrir CSV créerait une
  destination qui échoue à chaque clôture, loin de l'écran qui l'accepte.
- Transports proposés : REST, FTP, SFTP. `email` et `manual` n'ont aucun
  transporteur de facture.

**REST** : `POST`/`PUT` uniquement (§70), sans suivre les redirections — une 302
vers un hôte interne contournerait le contrôle qu'on vient de faire. Le secret
part en `Bearer` depuis le champ chiffré ; un `Authorization` déclaré dans
`settings` est **ignoré** (§72).

**FTP/SFTP** : un seul transporteur, le disque étant construit à la volée depuis
la configuration. Le répertoire distant est nettoyé de toute remontée
d'arborescence (§80).

**Mapping** : déclaratif et sur liste blanche (§66, §67). `fieldMapping` renomme
des champs connus, `staticValues` en ajoute de fixes. Aucune évaluation, et un
chemin inventé est ignoré plutôt qu'exploré.

### File, reprise, idempotence

L'envoi passe par `ProcessExportJob`, en file (§161) : la clôture rend la main
tout de suite, et aucun appel réseau n'a lieu dans une transaction (§26). Le job
ne porte que l'identifiant — il recharge à l'exécution et agit sur l'état
courant.

La reprise (§147) a été **corrigée** : héritée de la Phase 8, elle ne faisait que
remettre les compteurs. Tant qu'aucun export ne partait, cela suffisait ; une
facture étant désormais transmise pour de bon, un bouton « Relancer » qui
n'envoie rien mentirait. Elle remet donc l'envoi en file — pour les factures
seulement, seul contenu qu'on sait produire — et laisse le renvoi compter sa
propre tentative plutôt que de l'avancer deux fois. Un envoi déjà transmis est
refusé en 409 : le client aurait deux fois la même facture.

### Sécurité

- Le mot de passe n'est **jamais** rendu : le serveur n'expose que
  `hasPassword`. Dans le formulaire, un champ vide veut dire « inchangé », pas
  « effacé », et l'écran le dit.
- `storagePath` n'est pas exposé ; la ressource ne rend que `hasFile`.
- Aucun message d'échec ne contient de secret : le transporteur REST ne retient
  que le statut HTTP, le transporteur de fichiers que la classe de l'exception —
  le message du pilote contient volontiers l'URL de connexion, donc le mot de
  passe.
- SSRF : `RemoteTargetGuard` vérifie le schéma puis **l'adresse réellement
  résolue**, et refuse les plages privées et réservées. Un nom public pointant
  vers une adresse interne est donc refusé.
- Le §114 est revérifié à l'envoi : configuration, job et facture doivent
  désigner le même client, même si le job a été forgé.
- Le §88 aussi : une facture non clôturée ne part pas, quel que soit le chemin.

### Permissions et multi-organisation

`invoices.view|create|update|delete|close` (la dernière ajoutée par cette
phase), `provider_settlements.*`, `customer_export_configurations.*`,
`export_jobs.view|create|retry`. Chaque route les déclare, chaque écran les
respecte via `PermissionGuard`.

L'isolation passe par `X-Organization-Id` et répond **404**, jamais 403, pour une
ressource hors périmètre : le contraire révélerait son existence.

### Frontend : conventions

- Clés de requête : `invoiceKeys`, `settlementKeys`, `exportKeys`, chacune
  dérivant liste, détail et sélecteurs de ses filtres.
- Types : `invoice.ts`, `settlement.ts`, `export.ts`. Les montants restent des
  **chaînes** — un décimal passé en flottant se relit `108.10000000000001`.
- **Zod n'est pas utilisé dans ce module.** Les écrans de cette phase sont des
  sélections et des actions, non des formulaires libres : la validation
  structurante est celle du serveur, et un second schéma aurait donné deux
  règles à maintenir. Les formulaires à champs libres du projet (commandes,
  ressources) continuent d'utiliser `react-hook-form` + Zod.

### Tarification (§169CB)

La V2 de la phase lève l'exclusion du moteur tarifaire. Le diagramme interne a
été mis à jour **avant** les migrations, comme le §169A l'impose.

| Attendu | Livré |
|---|---|
| Tarification globale | `PriceList` de portée `global`, écran *Formules globales* |
| Tarification client | Portée `customer`, rattachement par client, écran dédié |
| Repli client → global | `PricingResolver`, repli **partiel** : un service non négocié garde le barème général |
| Formule obligatoire | `PriceRule.formula`, validée à l'enregistrement par le parseur réel |
| Matrice facultative | Le résolveur consulte les matrices puis les règles nues |
| Matrices par zone / code postal | `PriceMatrixRow` avec `match_mode` : `numeric`, `prefix`, `exact` |
| Règles par service | `service_id` facultatif ; la règle nommant le service passe avant la générique |
| Parseur de formule | Tokenizer → parseur → AST, sans nœud « appel de fonction » |
| Validateur | `POST /pricing/formulas/validate` |
| Testeur | Écran dédié et panneau dans l'éditeur de règle, **même moteur que le calcul** |
| Historique | `pricing_calculations` : formule et variables recopiées |
| Préfacturation | `/billing/prebilling` — ce qui reste à facturer, et le tarif que le barème donnerait |
| Prix à la facture | Le barème décide ; sans barème la ligne est refusée |
| « Modifier » dans la liste | Colonne d'actions : consulter, modifier, supprimer |

**Ce que la sécurité tient par construction.** Le tokenizer n'accepte que ce
qu'il connaît — nombres, `{P:}`, `{V:}`, quatre opérateurs, parenthèses — plutôt
que d'interdire des motifs dangereux, liste qu'on oublie toujours de compléter.
L'arbre ne porte aucun nœud d'appel : il n'y a rien à appeler. `eval("2+2")`,
`system("ls")`, `; DROP TABLE`, `` `whoami` `` sont refusés, et un test les
énumère.

**Décimal, jamais flottant.** BCMath, déjà présent : 6 décimales de travail,
arrondi commercial à 2 **une seule fois à la fin** — arrondir entre une division
et une multiplication fausserait « par tranche de 100 kg ».

**Trois décisions que les tests ont imposées :**

1. **Une règle citée par une matrice ne s'applique que par elle.** Sans cela, un
   code postal hors de toute zone retombait sur la même règle par la porte d'à
   côté, et les bornes du barème ne voulaient plus rien dire.
2. **« Pas de tarif » n'est pas zéro.** Zéro reste un prix qu'une formule peut
   produire ; les confondre ferait partir des factures à zéro sans que personne
   ne le voie.
3. **`priceOverride` assume le prix *soumis*, pas celui de la commande.** Le
   champ s'appelait d'abord `acceptOrderPrice` ; un test de bout en bout l'a
   corrigé — il soumettait 450 pour une prestation à 0 et se voyait facturer 0.

**Écarts et limites de la tarification :**

1. **`distance` n'a aucune source par prestation.** La tournée en porte une,
   mais elle vaut pour le trajet entier, pas pour un arrêt. Une formule qui la
   nomme échoue clairement plutôt que de rendre un prix bâti sur la mauvaise
   valeur.
2. **Le fichier de diagramme cité par la spécification n'existe pas.** Le §169A
   nomme `01-diagramme-plateforme-interne.puml` ; les diagrammes de classes du
   projet sont deux `.txt`. Le paquet a été ajouté au fichier réel, sans
   renommage — renommer des sources de conception aurait cassé les références
   des phases précédentes.
3. **`ProviderPriceList` reste hors périmètre**, comme le §169 l'autorise : le
   décompte fournisseur n'en dépend pas.
4. **Les plages de zones peuvent se chevaucher.** Elles sont départagées par un
   ordre déterministe plutôt qu'interdites : les barèmes réels chevauchent
   souvent, et une contrainte les aurait rendus inexprimables.

### Interdictions du §170 — vérifiées

Aucune des entités prohibées n'existe : `InvoiceExport`, `InvoiceDelivery`,
`AccountingExport`, `FileTransferLog`, `ApiRequestLog`, `InvoiceStatusHistory`,
`Payment`, `CreditNote`, `PricingRule`. Aucune migration n'a été créée pour ce
domaine.

Deux fichiers portent un nom voisin sans être des entités :
`InvoiceExportData` est le DTO canonique que le §63 demande explicitement, et
`InvoiceExportTrigger` un service. Ni l'un ni l'autre n'a de table ni de
modèle.

Le seul `status_id` du schéma est dans `status_transitions`, qui relie deux
lignes de `statuses` entre elles — c'est le référentiel lui-même, pas une
entité métier qui pointerait vers lui.

Les interdictions du §169CC tiennent aussi : aucun `eval`, aucune formule
exécutable stockée, aucune « première règle trouvée » sans priorité, jamais zéro
faute de formule, matrice jamais obligatoire, repli global jamais coupé par une
règle client partielle, facture clôturée jamais recalculée, aucun `status_id`,
et le moteur de formule n'existe qu'au serveur — React ne fait que l'appeler.

Le résultat d'envoi n'est stocké que sur `ExportJob` : rien n'est écrit dans
`Invoice`, et `externalReference` reste une référence métier, jamais un journal
technique.

## 3. Tests

| Suite | Résultat |
|---|---|
| Backend — sélecteurs (`BillableServiceTest`, `SettleableServiceTest`) | 14 tests, verts |
| Backend — clôture (`InvoiceClosureTest`) | 15 tests, verts |
| Backend — moteur d'export (dispatch, reprise, formats, garde, transport) | 37 tests, verts |
| Backend — suite complète (`php artisan test`) | **1087 tests, 3561 assertions, verts** |
| Frontend — `npm test` | 451 tests avant la phase, 466 après, verts |
| Frontend — `npm run build` (`tsc -b`) | vert |
| Frontend — `npm run lint` (oxlint) | aucune erreur |
| Backend — `pint --test` | vert |

Le test du §17 a été vérifié par mutation : en retirant le filtre
`is_active_assignment`, le fournisseur dont la tentative avait échoué réapparaît
et le test tombe. Il mord donc réellement.

**Aucun test E2E n'a été écrit** : le projet n'a pas de harnais E2E configuré
(`npm run test:e2e` n'existe pas), et en installer un dépasse le cadre de cette
phase. Les parcours §159 et §160 sont couverts au niveau intégration — MSW côté
écran, base réelle côté API — mais pas de bout en bout dans un navigateur.

## 4. Limites connues, écarts et risques

**Écarts assumés, documentés :**

1. **Le secret REST sortant réutilise `encryptedPassword`.** La table ne porte
   pas de champ dédié au jeton d'API sortant, et le §170 interdit d'en ajouter
   un. Le champ chiffré existant le porte donc. Le §72 est respecté sur le
   fond — le secret n'est ni lisible en base, ni rendu par l'API.
2. **La géométrie XML des noms de balises est nettoyée**, pas rejetée : un
   mapping client proposant un nom invalide retombe sur un nom par défaut plutôt
   que de produire un document que le destinataire refuserait.
3. **`provider_settlement.closed` n'a aucune action qui y mène.** Le statut
   existe au référentiel et la mise à jour générique l'accepte ; la phase 6 ne
   décrit pas de clôture de décompte, et en inventer une aurait dépassé la
   demande.

**Aucun écart DB/UML n'a été trouvé** sur les sept tables du domaine : colonnes,
types et cardinalités correspondent. Détail dans `phase-6-analysis.md`.

**Risques :**

1. **La file doit tourner.** `QUEUE_CONNECTION=database` en développement : sans
   `php artisan queue:work`, une facture se clôture mais son envoi reste en
   attente. C'est visible dans `/billing/exports`, mais il faut le savoir.
2. **La reprise ne connaît pas de plafond.** Un envoi peut être relancé
   indéfiniment vers une destination durablement en panne. `attemptCount` le
   rend visible ; aucune coupure automatique n'a été posée, la spécification
   n'en demandant pas.
3. **Les destinations sont saisies à la main.** Un hôte mal orthographié ne se
   découvre qu'à la première clôture. Un bouton « tester la destination »
   rendrait service — il n'est pas demandé, et créerait un envoi qui n'en est
   pas un.

## 5. Phase suivante

- Un test de bout en bout, si un harnais E2E est adopté.
- Le bouton de test de destination, si l'exploitation le réclame.
- La clôture d'un décompte, si le métier en veut une.

## Conclusion

FRONTEND_PHASE_6_READY
