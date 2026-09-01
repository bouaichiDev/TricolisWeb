# Rapport final — Frontend Phase 9

Templates génériques, règles de communication, automatisation, templates de facture.

## 1. Branche de base

`feature/frontend-phase-8-customer-integrations`, commit `2c9da4f`.

Le travail Phase 8 restait non commité — transporteurs EMAIL/MANUAL, formats PDF
et CSV, authentification REST — et l'a été sur sa branche avant l'ouverture de la
Phase 9.

## 2. Branche Phase 9

`feature/frontend-phase-9-communication-rules`

| Commit | Objet |
|---|---|
| `e6e2e8d` | une seule table de modèles pour toute la plateforme |
| `96096b5` | règles de communication et modèles unifiés côté frontend |

Ni fusion, ni poussée automatique.

## 3. Git Author

`Badr <bouaichibadr@gmail.com>`

## 4. Git Committer

`Badr <bouaichibadr@gmail.com>`

## 5. Absence d'attribution automatisée

Aucun commit ne porte de mention `Co-authored-by`, `Generated-by` ni de
référence à un assistant. Vérifié par `git log --format=%an,%ae,%b`.

## 6. Consolidation de la Phase 3

Le flux manuel de la Phase 3 est **refactoré, jamais recréé**. Les fichiers ont
été déplacés par `git mv`, l'historique est conservé.

| Phase 3 | Phase 9 |
|---|---|
| `communications/pages/CommunicationTemplateListPage.tsx` | `templates/pages/TemplateListPage.tsx` |
| `communications/components/CommunicationTemplateForm.tsx` | `templates/components/TemplateForm.tsx` |
| `communications/components/CommunicationTemplateDialog.tsx` | `templates/components/TemplateDialog.tsx` |
| `communications/components/CommunicationTemplatePreview.tsx` | `templates/components/TemplatePreview.tsx` |
| `communications/components/TemplateVariablePicker.tsx` | `templates/components/TemplateVariablePicker.tsx` |

`OrderCommunicationsTab`, `CreateOrderCommunicationDialog`,
`OrderCommunicationDetailDrawer`, `CommunicationAttachmentList` et
`CommunicationRowActions` restent en place. Aucun `OrderCommunicationFormV2`
n'a été créé.

## 7. Templates

Un seul écran, `/templates`, pour toute la plateforme. Le menu y mène par deux
portes :

```text
Communication  → Templates            → /templates?category=communication
Facturation    → Templates de facture → /templates?templateType=invoice
```

Même API, même modèle, même table, mêmes composants. Le §0.15 interdit
`/communications/templates` et `/billing/invoice-templates` comme deux CRUD.

Filtres : recherche, type, client — avec « modèles du transporteur » comme
troisième réponse —, canal, état.

## 8. Moteur de rendu

`TemplateRenderer`, un seul pour toute la plateforme. Deux capacités ajoutées,
toutes deux fermées :

- **chemins pointés** — `{{ invoice.invoiceNumber }}`, quatre segments au plus ;
- **sections** — `{{#invoice.lines}} … {{/invoice.lines}}`, une profondeur.

Une section développée est mise de côté derrière un jeton et remise en place
**après** la passe sur les chemins : sans cela, une ligne dont la description
contient littéralement `{{ invoice.total }}` verrait ce texte résolu, et un
client pourrait faire écrire à sa propre facture ce qu'il n'a pas le droit de
lire.

Ce qui reste interdit ne bouge pas : ni expression, ni filtre, ni condition, ni
section imbriquée, ni valeur non scalaire, ni accès à un modèle Eloquent.

## 9. Variables

`availableVariables` accepte désormais des chemins. Le motif exclut toujours
parenthèses, `$`, espaces, `::`, `->` et séparateurs de fichier.

Pour un message, la liste reste **libre** : aucun contexte canonique n'existe, et
le §23 interdit d'en inventer un. Pour une facture, l'écran propose les dix-neuf
chemins et les seize champs de ligne que `InvoiceRenderContext` fournit
réellement.

## 10. Règles

`/communications/rules` — liste, création, modification, suppression protégée.

Colonnes : événement, prestation, modèle, canal *via le modèle*, destinataire,
délai, automatique, active.

## 11. Événements

Les onze `CommunicationEventType` sont proposés au formulaire, avec leurs codes
exacts. **Aucun n'est émis par la plateforme** ; l'écran le dit, et le §7
interdit au frontend de déclencher quoi que ce soit lui-même.

## 12. Conditions

Le schéma du serveur est stable — `{"all": [{field, operator, value}]}`, huit
opérateurs, vingt clauses. Le §40 s'applique donc, pas le §41 :
`CommunicationRuleConditionsBuilder` est un éditeur guidé, pas un champ JSON
libre. Un éditeur libre laisserait composer ce que l'évaluateur refuse, et
l'erreur n'arriverait qu'au premier événement.

Le champ reste libre — l'évaluateur compare des faits déjà extraits, et aucune
liste blanche n'existe côté serveur — mais son motif est celui du serveur :
minuscules, chiffres et tirets bas.

## 13. Délais

`delayValue` entier positif, `delayUnit` parmi `minutes`, `hours`, `days` —
liste lue dans `StoreCommunicationRuleRequest`, jamais inventée. Aucune valeur
négative n'est offerte : le serveur la refuse, et son message dit pourquoi.

## 14. Automatisation

Configurable, **non déclenchable**. Voir §36.

## 15. Résolution du destinataire

Le rôle est choisi, jamais l'adresse. `custom` n'est **pas** proposé aux règles :
il n'a aucune source de résolution automatique, et la règle ne produirait rien
(§69). Il reste offert au formulaire manuel, où l'utilisateur saisit les
coordonnées.

## 16. Communications manuelles

Inchangées. Le sélecteur de modèles écarte les documents : une facture n'a pas
de canal par où partir.

## 17. Communications programmées

`/communications/history`, onglet « Programmées » : le même historique filtré sur
`status = scheduled`. Aucune table `ScheduledCommunication` n'est créée (§86).

## 18. File d'attente

Actions `queue`, `cancel`, `retry` réutilisées telles quelles depuis la Phase 3.
Aucun `PATCH status=` depuis React.

## 19. Transporteurs

Inchangés. SMS, WhatsApp et push échouent explicitement, faute de fournisseur —
état vrai du système, hérité du backend Phase 9.

## 20. Relance

`POST /order-communications/{id}/retry` sur la **même** communication. Aucune
nouvelle ligne, aucun `retryCount`.

## 21. Annulation

`POST /order-communications/{id}/cancel`, offert selon `abilitiesOf(status)`.

## 22. Livré / lu

`deliveredAt` et `readAt` sont affichés s'ils existent. Aucun fournisseur ne les
alimente aujourd'hui.

## 23. Pièces jointes

Inchangées. `CommunicationAttachmentList` reste éditable tant que le statut le
permet.

## 24. Instantanés

Le détail montre ce qui est parti — sujet, corps, destinataire, variables — et
jamais ce que le modèle dirait aujourd'hui. Le lien vers le modèle mène au
modèle **actuel**, et le panneau le distingue du contenu envoyé (§122).

## 25. Statuses

`order_communications.status` reste textuel. Les neuf codes sont semés depuis
l'énumération PHP. Aucun `status_id`. Confirmé dans
`docs/backend/statuses-global-audit.md`.

## 26. Permissions

`communication_templates.*` devient `templates.*` — code **et** module — par
migration de données. Les rôles sont préservés : `role_permissions` pointe sur
l'identifiant, que rien ne touche.

Les trois autres familles — `communication_rules.*`, `order_communications.*`,
`communication_attachments.*` — sont inchangées.

## 27. Multi-organisation

`TemplateScopeGuard` vérifie client et service ; `ResolvesTemplateScope` renvoie
404 hors périmètre. `ResolveTemplateAction` ne sert jamais le modèle d'un autre
client, ni d'une autre organisation. Testé.

## 28. Couche API

```text
templates/api/templates.api.ts
communications/api/communication-rules.api.ts
communications/api/order-communications.api.ts   (+ list globale)
```

Aucun `fetch` dans du JSX.

## 29. Query keys

`templateKeys`, `communicationRuleKeys`, `orderCommunicationKeys`
(+ `history`), `invoiceKeys.document`.

Une mutation de modèle invalide les listes de modèles et les sélecteurs de
règles. Elle **ne touche pas** les communications historiques : leur contenu est
un instantané, et le §129 interdit de le recalculer.

## 30. Types

`Template`, `TemplateType`, `CommunicationRule`, `RuleConditions`,
`OrderCommunication`, `CommunicationAttachment`, `InvoiceDocument`. Valeurs
d'énumération en `snake_case`, exactement celles du serveur.

## 31. Zod

`templateSchema` et `communicationRuleSchema`. Le premier porte la règle de
nature — document sans canal ni objet, message avec canal — qui reflète
`TemplateNature` côté serveur.

## 32. Audit

Actions renommées `communication_template.*` → `template.*`.
`WriteTemplateAudit` expurge `body_template` et `subject_template` : une mise en
page de facture pèse plusieurs kilo-octets, et deux copies par retouche
rendraient le journal illisible.

## 33. Sécurité

- aucun `eval`, aucun Blade, aucune expression exécutable dans le rendu ;
- une donnée de ligne n'est jamais relue comme du modèle ;
- contexte de facture construit à la main depuis un DTO, jamais par réflexion ;
- aperçus HTML rendus en **iframe cloisonnée** (`sandbox=""`, `srcDoc`), jamais
  par `dangerouslySetInnerHTML` ;
- `providerResponse` n'est jamais affiché ; `providerMessageId` l'est comme
  référence technique ;
- conditions déclaratives, motif de champ contraint ;
- cross-organisation et cross-client refusés, testés.

## 34. Tests

**Backend — 1342 tests, tous verts.**

| Fichier | Ajouts |
|---|---|
| `tests/Feature/Templates/TemplateRendererTest.php` | déplacé, chemins pointés, profondeur |
| `tests/Feature/Templates/TemplateSectionRenderTest.php` | **nouveau** — sections, échappement, injection |
| `tests/Feature/Templates/ResolveTemplateTest.php` | **nouveau** — repli, cross-client, cross-org |
| `tests/Feature/Api/V1/Billing/InvoiceDocumentTest.php` | **nouveau** — aperçu, immuabilité |
| `tests/Feature/Api/V1/Templates/TemplateTest.php` | déplacé, `customer_id`, audit renommé |
| `tests/Unit/Exports/InvoiceFormatterTest.php` | PDF depuis un document rendu |
| `tests/Feature/Hardening/MenuPermissionConsistencyTest.php` | chemin comparé sans sa requête |

**Frontend — 606 tests, tous verts.**

| Fichier | Ajouts |
|---|---|
| `templates/pages/TemplateListPage.test.tsx` | déplacé + mode facture |
| `communications/pages/CommunicationRuleListPage.test.tsx` | **nouveau** — 8 tests |
| `communications/pages/CommunicationHistoryPage.test.tsx` | **nouveau** — 4 tests |
| `billing/components/InvoiceDocumentDialog.test.tsx` | **nouveau** — 4 tests |

Migrations vérifiées sur une base réelle, **aller et retour** :
`migrate`, `migrate:rollback --step=4`, `migrate`.

## 35. Vérifications

```text
npm run typecheck   passe
npm run lint        passe (18 avertissements préexistants, aucun nouveau)
npm run test        606 / 606
npm run build       passe
php artisan test    1342 / 1342
./vendor/bin/pint --test   passe
php artisan migrate --force / rollback / migrate   passe
```

Aucun script E2E n'existe dans le projet.

## 36. Régressions

Phase 3, 5, 6 et 8 vérifiées par leurs suites, toutes vertes. Les changements
de contrat sont ceux, voulus, de la section 0.

## 37. Différences entre la base, l'UML et les prompts

| # | Écart | Traitement |
|---|---|---|
| A | Les valeurs d'enum sont en `snake_case`, les prompts les écrivent en majuscules | Les prompts nomment les **cas** PHP ; la valeur persistée est `snake_case`. Le frontend envoie la valeur. |
| B | `body_format` n'est dans aucun diagramme | Ajouté en Phase 8, conservé et documenté. |
| C | `MorphMap` expose `template`, le §192 écrit `order_communications` | La source est l'alias, au singulier — même écart qu'`export_job` en Phase 8. |
| D | Le §0.9 ne dit rien du cas « aucun modèle » | Repli sur la mise en page livrée en Phase 8, documenté. Jamais le modèle d'un tiers. |
| E | `CommunicationRule` n'a pas de `channel` | Conforme au §158 ; l'écran le lit depuis le modèle. |

## 38. Éléments exclus

1. **Automatisation de bout en bout** — aucun événement métier n'est émis par
   les Phases 1 à 8. Le backend Phase 9 l'avait déjà déclaré (§9 de son
   rapport) ; la Phase 9 frontend ne l'invente pas. Le §2 interdit d'imaginer la
   sémantique de onze événements.
2. **Simulation de règle (§50)** — conditionnée par le §50 à l'existence d'un
   endpoint non persistant. Il n'y en a pas.
3. **Tableau de bord communications (§110)** — déclaré optionnel, subordonné à
   des agrégats backend qui n'existent pas. Charger l'historique pour compter est
   interdit par le même paragraphe.
4. **Callbacks livré / lu** — aucun fournisseur.
5. **Boucles imbriquées** — une facture a des lignes, pas des lignes de lignes.
6. Aucune des tables interdites au §196 n'a été créée. Un test le vérifie table
   par table.

## 39. Risques

| Risque | Portée | Atténuation |
|---|---|---|
| Le renommage touche 43 fichiers PHP et 12 fichiers TypeScript | élevée si une référence est oubliée | Les 1342 tests backend et 606 frontend couvrent chaque chemin ; `migrate`/`rollback` vérifiés sur base réelle. |
| Un modèle de facture mal écrit fait échouer la clôture | une facture bloquée | Le rendu échoue **avant** le passage à `closed`, dans la transaction : la facture reste au brouillon, corrigeable. L'aperçu montre l'erreur avant. |
| Le repli sur la mise en page livrée peut passer inaperçu | mise en page inattendue | L'aperçu nomme la portée employée, et signale explicitement « aucun modèle configuré ». |
| Les règles ne se déclenchent pas | attente déçue | Dit sur l'écran de liste, dans le résumé de chaque règle, et ici. |
| Un `<script>` dans un modèle | tous les lecteurs de l'aperçu | Iframes cloisonnées partout ; échappement HTML des valeurs par le moteur. |

## 40. Prochaine phase

Non entamée. Après validation :

```text
FRONTEND PHASE 10 — CONSOLIDATION FINALE
```

---

# MODIFICATIONS APPLIQUÉES AUX PHASES PRÉCÉDENTES

## Phase 3

- `CommunicationTemplate` → `Template` ; `communication_templates` → `templates`
  par `RENAME`, identifiants conservés ;
- `CommunicationTemplateType` → `TemplateType` ; `CommunicationTemplateRenderer`
  → `TemplateRenderer` ;
- API `/communication-templates` → `/templates` ; permissions
  `communication_templates.*` → `templates.*` ;
- écrans déplacés vers `modules/templates/`, **fonctionnalités inchangées** :
  communication manuelle, aperçu, destinataire, pièces jointes, historique,
  file / annulation / relance ;
- toutes les communications historiques conservées ; aucun doublon créé.

## Phase 6

- modèle de facture dans la table générique, jamais dans `invoice_templates` ;
- `customerId` porte l'override client ; repli client → global ;
- aperçu de la facture depuis `GET /invoices/{invoice}/document` ;
- document final **immuable** après clôture — `invoices.rendered_body` ;
- `Invoice.templateId` ajouté comme référence d'audit, diagramme mis à jour ;
- menu : « Facturation › Templates de facture » mène à `/templates?templateType=invoice` ;
- fiche client : section « Modèle de facture », avec création d'un modèle
  spécifique ;
- `CustomerExportConfiguration` et `ExportJob` conservés, inchangés.

## Phase 8

- moteur `ExportJob` conservé ;
- **PDF** rendu depuis le modèle `INVOICE` résolu ; `InvoicePdfFormatter`
  n'implémente plus `InvoiceFormatter` — ce contrat transpose un DTO, et un PDF
  ne s'en produit pas ;
- **JSON, XML et CSV** conservent leurs mappings `InvoiceExportData` ; le §0.26
  interdit d'y convertir le HTML du modèle ;
- les valeurs fixes du client (`staticValues`) s'ajoutent en pied de PDF.

## Diagrammes

`Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt` :
`CommunicationTemplate` → `Template` (+ `customerId`),
`CommunicationTemplateType` → `TemplateType` (+ `INVOICE`), `Invoice` reçoit
`templateId`, `renderedBody`, `renderedAt`, relations `Customer "0..1" -- "0..*"
Template` et `Template "0..1" -- "0..*" Invoice`.

`Conception/diagramme/sequence-4-communication-envoi.puml` : `TemplateRenderer`.

---

# FRONTEND_PHASE_9_READY

Sous une réserve, dite plutôt que masquée : **l'automatisation est configurable
et observable, pas démontrable de bout en bout.** Les onze événements métier ne
sont émis nulle part dans les Phases 1 à 8 — écart hérité du backend Phase 9,
qui l'avait lui-même déclaré. Les tests §172 à §175, §184 et §188 à §190 ne sont
donc pas exécutables aujourd'hui, et le seront sans changement de contrat le jour
où les événements existeront.

Tout le reste du §201 est satisfait : templates fonctionnels, règles
fonctionnelles, événements exacts, conditions sécurisées, délai fonctionnel,
destinataires résolus, communication manuelle préservée, programmation, file,
relance, pièces jointes, instantanés immuables, statuts centralisés, aucun
`status_id`, permissions appliquées, multi-organisation protégée, tests et build
verts, Phase 3 non régressée.
