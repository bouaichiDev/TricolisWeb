# Analyse — Frontend Phase 9

Templates génériques, règles de communication, automatisation, templates de facture.

## 1. Branche source

`feature/frontend-phase-8-customer-integrations`, commit `2c9da4f`
(« livrer les transports et formats de facture restants »). Le travail Phase 8
restait non commité ; il l'a été sur sa branche avant l'ouverture de
`feature/frontend-phase-9-communication-rules`.

## 2. Frontend Phase 3 existant

`frontend/src/modules/communications/` livre déjà, en 22 fichiers :

| Livré | Fichier |
|---|---|
| Liste des modèles | `pages/CommunicationTemplateListPage.tsx` |
| Création/édition modèle | `components/CommunicationTemplateDialog.tsx`, `CommunicationTemplateForm.tsx` |
| Aperçu de modèle | `components/CommunicationTemplatePreview.tsx` |
| Insertion de variable | `components/TemplateVariablePicker.tsx` |
| Onglet commande | `components/OrderCommunicationsTab.tsx` |
| Message manuel | `components/CreateOrderCommunicationDialog.tsx` |
| Détail d'un envoi | `components/OrderCommunicationDetailDrawer.tsx` |
| Pièces jointes | `components/CommunicationAttachmentList.tsx` |
| Actions file/annuler/relancer | `components/CommunicationRowActions.tsx`, `utils/communicationActions.ts` |

Ce flux est **conservé et refactoré**, jamais recréé.

Absents côté frontend : `CommunicationRule` (aucun fichier), historique global,
page de détail, vues programmées/en échec, section de menu Communication.

## 3. Backend Phase 9 réel

`app/Modules/Communications/` est complet : modèles, actions, DTO, requêtes,
ressources, politiques, file d'attente, ordonnanceur, transporteurs.

Le rapport backend (`docs/backend/phase-9-final-report.md`, §9) déclare trois
absences **assumées**, confirmées par relecture du code :

1. **Aucun déclenchement automatique.** Les onze `CommunicationEventType` ne
   sont émis nulle part : `grep -rl CommunicationEventType app/` ne retourne que
   l'enum, le modèle `CommunicationRule` et deux Form Requests. Il n'existe
   aucun `Event` Laravel ni listener dans le projet.
2. **Aucun transporteur SMS, WhatsApp ou push.** Ils échouent explicitement.
   `EmailCommunicationSender` envoie réellement ; `InternalCommunicationSender`
   réussit toujours.
3. **Aucun callback fournisseur.** `deliveredAt` et `readAt` restent sans
   alimentation.

Conséquence pour la Phase 9 frontend : l'automatisation est **configurable et
observable**, pas démontrable de bout en bout. Les §184, §188, §189 et §190
(E2E automation, Phase 5, POD, Claim) ne sont pas exécutables tant qu'aucun
événement n'est émis. Voir §37.

## 4. Champs Template

Table `communication_templates`, migration `2026_08_07_100001` + `2026_08_25_100000`.

| Colonne | Type | Nullable |
|---|---|---|
| `id` | char(26) | non |
| `organization_id` | char(26) | non |
| `service_id` | char(26) | oui |
| `code` | varchar(64) | non, unique par organisation |
| `name` | varchar(255) | non |
| `channel` | varchar(32) | **non** → devient nullable (§0.4) |
| `template_type` | varchar(32) | non |
| `subject_template` | text | oui |
| `body_template` | longtext | non |
| `body_format` | varchar(16) | non, défaut `text` |
| `language` | varchar(10) | non |
| `available_variables` | json | oui |
| `is_default`, `is_active` | boolean | non |

`body_format` n'apparaît pas dans le diagramme : ajouté en Phase 8 pour
distinguer un corps HTML d'un corps texte. Il est conservé.

Manque au regard du §0.4 : `customer_id`.

## 5. Champs CommunicationRule

Table `communication_rules`, migration `2026_08_07_100002`. Conforme au §29 :
`service_id` nullable, `template_id` NOT NULL en RESTRICT, `delay_value` entier
défaut 0, `delay_unit` chaîne(16), `conditions` json nullable, `is_automatic` et
`is_active` booléens.

Aucun `channel`, aucune `priority` : conforme aux §113 et §158.

## 6. Champs OrderCommunication

Conforme au §56. `status` est une **colonne textuelle** ; aucun `status_id`.
Aucun `event_type`, aucun `retry_count`, aucun `attempt_count` : conforme aux
§111, §112 et §144.

## 7. Champs CommunicationAttachment

Conforme au §94 : `document_id`, `file_name_snapshot`, `mime_type_snapshot`,
`created_at` seul (pas d'`updated_at`), cascade sur la communication parente.

## 8. Enums exacts

Les valeurs stockées sont en **snake_case**, pas en majuscules : les cas PHP le
sont. Le frontend envoie `delivery_confirmation`, jamais `DELIVERY_CONFIRMATION`.

- `CommunicationChannel` : `email`, `sms`, `whatsapp`, `push_notification`, `internal_notification`
- `CommunicationTemplateType` : 12 valeurs → **13 avec `invoice`** (§0.6)
- `CommunicationEventType` : 11 valeurs
- `CommunicationStatus` : 9 valeurs
- `RecipientRole` : 6 valeurs

## 9. Référentiel statuses

`MorphMap::ORDER_COMMUNICATION` est déjà semé depuis `CommunicationStatus` par
`StatusSeeder`. Les neuf codes existent, la colonne reste textuelle, aucun
`status_id`. Conforme aux §13 et §182 : **aucun changement requis**.

## 10. Syntaxe du renderer

`CommunicationTemplateRenderer` accepte un seul motif : `{{ nom }}`, nom simple,
espaces facultatifs. Aucune expression, aucun filtre, aucune boucle. Le
remplacement est un `preg_replace_callback` sur une table close.

La notation à points est **refusée au niveau lexical** (motif
`MALFORMED_PATTERN`). Trois refus testés : variable non déclarée, variable sans
valeur, valeur non scalaire.

Le §0.13 exige `invoice.invoiceNumber` et le §0.14 une boucle sur les lignes :
le moteur doit donc être **étendu**, pas remplacé. Voir la décision en §38.

## 11. Schéma d'availableVariables

Liste plate de noms, motif `/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/`, 100 entrées au
maximum, pas de doublon. Le motif exclut mécaniquement points, parenthèses et
espaces.

L'extension aux chemins pointés impose d'assouplir ce motif de façon bornée.

## 12. Schéma des conditions

**Stable et documenté** — le §40 s'applique donc, pas le §41 :

```json
{"all": [{"field": "order_status", "operator": "eq", "value": "confirmed"}]}
```

Conjonction plate uniquement. Huit opérateurs : `eq`, `neq`, `gt`, `gte`, `lt`,
`lte`, `in`, `not_in`. Champ conforme à `/^[a-z][a-z0-9_]{0,63}$/`, 20 clauses
au maximum. Ni `any`, ni `not`, ni imbrication.

L'évaluation n'accède à aucun modèle et n'ouvre aucune base : elle compare des
faits scalaires déjà extraits. Un constructeur de conditions guidé est donc
réalisable côté frontend.

## 13. Unités de délai

`StoreCommunicationRuleRequest::DELAY_UNITS` = `['minutes', 'hours', 'days']`.
La liste est lue depuis le backend, jamais inventée (§37).

## 14. Sémantique du délai

`delayValue` est validé `min:0`, `max:100000`. Le message de validation est
explicite : « un délai négatif n'a pas de sens : il ferait envoyer avant
l'événement ». Le délai est donc **toujours postérieur** à l'événement. Le
formulaire n'offre pas de valeur négative (§38).

## 15. Sémantique d'isAutomatic

`is_automatic` défaut `true`. Aucune consommation actuelle : rien ne lit ce
champ tant qu'aucun événement n'est émis. Le frontend l'expose sans lui prêter
de comportement que le backend n'a pas (§43).

## 16. Architecture de dispatch

**Inexistante.** Aucun `Event`, aucun `Listener`, aucun `EventServiceProvider`
métier. La Phase 9 frontend ne la crée pas : le §7 est formel, « le frontend ne
déclenche jamais lui-même l'automatisation métier », et le §2 interdit
d'imaginer la sémantique de chaque événement.

## 17. Contexte Event → Order

Sans dispatch, sans objet. Documenté comme écart.

## 18. Correspondance Service

`CommunicationScopeGuard::service()` vérifie l'appartenance du service à
l'organisation. La cohérence Service règle / Service template (§33) est vérifiée
côté action.

## 19. Résolution du destinataire

`ResolveOrderCommunicationRecipient` traite les six rôles. La règle ne stocke
jamais l'adresse finale (§35) : elle est résolue à la création et figée dans
`recipient_name`, `recipient_email`, `recipient_phone`.

## 20. Destinataire CUSTOM

Résolu depuis la charge utile manuelle. Une règle automatique en `custom` n'a
aucune source de résolution : le formulaire de règle l'exclut et l'explique
(§69).

## 21. Communications manuelles

`POST /api/v1/orders/{order}/communications` et
`POST /api/v1/order-communications`. Flux Phase 3 conservé intégralement.

## 22. Instantanés

`subject`, `body`, `template_variables`, `recipient_*` sont figés à la création ;
`file_name_snapshot` et `mime_type_snapshot` à l'attachement. Aucun recalcul
frontend (§60 à §62, §96).

## 23. File d'attente

`QueueOrderCommunicationAction` → `SendOrderCommunicationJob`.

## 24. Ordonnanceur

`ProcessScheduledCommunications` : les `scheduled` dont `scheduled_at` est échu
passent en `queued`.

## 25. Relance

`RetryOrderCommunicationAction` réutilise **la même** communication ; aucune
nouvelle ligne (§81).

## 26. Callbacks

Aucun. `deliveredAt` et `readAt` restent nuls.

## 27. Transporteurs

`CommunicationSenderRegistry` + cinq transporteurs. SMS, WhatsApp et push
échouent en nommant ce qui manque ; `UnconfiguredChannelSender` existe pour les
canaux non câblés.

## 28. Configuration fournisseur

Aucune table, aucun CRUD : conforme au §92. La configuration reste dans
`config/` et `.env`.

## 29. Secrets

`providerResponse` est exposé par la ressource mais **jamais affiché** par le
frontend. L'audit ne transporte ni corps ni réponse fournisseur.

## 30. Permissions

`PermissionSeeder` porte les 18 permissions attendues au §103.
`communication_templates.*` devient `templates.*` (§0.31) ; les trois autres
familles sont inchangées.

## 31. Multi-organisation

`ResolvesCommunicationScope` + `CommunicationScopeGuard` + politiques. Aucun
accès inter-organisation.

## 32. Routes API

Existantes : `communication-templates`, `communication-rules`,
`order-communications` (+ `queue`, `cancel`, `retry`), attachments imbriquées,
`orders/{order}/communications`.

`communication-templates` devient `templates` (§0.30).

## 33. Filtres et tris

`CommunicationListQuery` et `ListCommunicationRequest` portent les filtres du
§72. Le filtre `customerId` est ajouté pour les templates.

## 34. Régressions Phase 3

Le renommage `CommunicationTemplate → Template` touche les types, l'API et les
composants du module. Les tests existants
(`OrderCommunicationsTab.test.tsx`, `CommunicationTemplateListPage.test.tsx`)
servent de garde-fou et sont conservés.

## 35. Intégration Phase 5

Aucune. Le glisser-déposer n'émet aucun événement de communication et ne doit
pas en émettre (§151).

## 36. Intégration POD / Claim

Aucune, même raison (§156, §157).

## 37. Tests

Existant : `tests/Feature/Api/V1/Communications/` (4 fichiers),
`tests/Feature/Communications/CommunicationTemplateRendererTest.php`.

Ajouts Phase 9 : migration des templates, résolution avec repli, rendu de
facture, boucle sur les lignes, immuabilité après clôture, écrans de règles.

Non exécutables faute d'émetteur d'événement : §172 à §175, §184, §188 à §190.

## 38. Éléments exclus et écarts assumés

1. **Automatisation de bout en bout** — aucun événement métier n'est émis dans
   les Phases 1 à 8. Configurable, non déclenchable. Écart hérité du backend
   Phase 9, redocumenté ici.
2. **Simulation de règle (§50)** — le backend n'expose aucun endpoint non
   persistant. Le §50 la conditionne à son existence : non livrée.
3. **Tableau de bord communications (§110)** — déclaré optionnel, subordonné à
   des agrégats backend qui n'existent pas. Non livré.
4. **Callbacks livré/lu** — aucun fournisseur.
5. **Boucles imbriquées dans les templates** — une seule profondeur de section
   est livrée. Une facture a des lignes, pas des lignes de lignes.

## 39. Décisions de conception de cette phase

### Le module Templates quitte Communications

Un template de facture n'est pas une communication : il n'a ni canal, ni
destinataire, ni objet. Le laisser dans `app/Modules/Communications/` ferait
dépendre la facturation du module de messagerie pour produire un PDF.

`app/Modules/Templates/` accueille donc le modèle, l'enum, le moteur de rendu,
la résolution et le cycle de vie. `CommunicationChannel` reste dans
Communications — c'est bien une notion de messagerie — et Templates l'importe.

### Le moteur est étendu, jamais dupliqué

Le §0.11 interdit deux moteurs concurrents. `TemplateRenderer` gagne deux
capacités, toutes deux fermées :

- **chemins pointés** — `{{ invoice.invoiceNumber }}`, quatre segments au plus,
  chaque chemin devant figurer dans `availableVariables` ;
- **sections** — `{{#invoice.lines}} … {{/invoice.lines}}`, une seule
  profondeur, la liste devant elle aussi être déclarée.

Dans une section, un champ de ligne s'écrit avec son chemin complet —
`{{ invoice.lines.description }}` — et non par un nom relatif. Une seule liste
blanche gouverne alors tout le template, et aucun nom ne change de sens selon
l'endroit où il est écrit.

Ce qui reste interdit ne bouge pas : aucune expression, aucun filtre, aucune
condition, aucune boucle imbriquée, aucune valeur non scalaire, aucun accès à un
modèle.

### Le repli de facture s'arrête à la mise en page livrée

Le §0.9 impose Customer → GLOBAL. Il ne dit rien du cas où **aucun** template
`invoice` n'existe — celui de toutes les organisations existantes au moment de
la migration.

`ResolveTemplateAction` retourne alors `null`, et le générateur de PDF retombe
sur la mise en page Blade livrée en Phase 8. Une facture continue donc de se
produire sans configuration préalable. Ce repli est un défaut de mise en page,
jamais le template d'un autre client — le §0.9 est respecté.

### L'immuabilité passe par le rendu, pas par un second référentiel

Le §0.22 exige qu'une facture close reste identique après modification du
template ; le §0.23 interdit de résoudre cela par une table `invoice_templates`.

`invoices` reçoit trois colonnes : `template_id` (référence d'audit, §0.24),
`rendered_body` (le document produit) et `rendered_at`. La clôture résout,
rend et fige. Toute relecture d'une facture close sert `rendered_body` ; le
template courant n'est jamais rejoué sur une facture close.

C'est la « structure minimale validée » du §0.23, point 3 : trois colonnes sur
une table existante, aucun nouveau modèle.
