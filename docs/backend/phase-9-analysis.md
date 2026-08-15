# Phase 9 — Analyse préalable : communication et templates

Document exigé par le §3. Aucune migration n'a été écrite avant sa finalisation.

---

## 1. Diagrammes utilisés

Les fichiers `.puml` cités au §1 n'existent pas dans le dépôt. Les diagrammes
livrés sont deux `.txt`, déclarés dernière version par l'utilisateur :

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Le paquet `Communication et templates` occupe les lignes 617-750 du diagramme
interne ; les relations, les lignes 825-841.

**Aucun conflit entre le prompt et le diagramme.** Les cinq enums, les quatre
classes, chacun de leurs attributs et chacune de leurs relations concordent
attribut par attribut. C'est la deuxième phase consécutive sans arbitrage à
rendre — les Phases 5, 6 et 7 avaient chacune un `legacyId` fantôme.

---

## 2. Tableau d'alignement PlantUML → MySQL

### CommunicationTemplate → `communication_templates`

| Attribut PlantUML | Type PlantUML | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| id | ULID | `id` CHAR(26) | non | PK | — |
| organizationId | ULID | `organization_id` CHAR(26) | non | index + unique composite | FK `organizations` RESTRICT |
| serviceId | ULID | `service_id` CHAR(26) | **oui** | index | FK `services` RESTRICT |
| code | string | `code` VARCHAR(64) | non | unique `(organization_id, code)` | — |
| name | string | `name` VARCHAR(255) | non | — | — |
| channel | CommunicationChannel | `channel` VARCHAR(32) | non | index | — |
| templateType | CommunicationTemplateType | `template_type` VARCHAR(32) | non | index | — |
| subjectTemplate | text | `subject_template` TEXT | **oui** | — | — |
| bodyTemplate | longtext | `body_template` LONGTEXT | non | — | — |
| language | string | `language` VARCHAR(10) | non | index | — |
| availableVariables | JSON | `available_variables` JSON | **oui** | — | — |
| isDefault | boolean | `is_default` TINYINT(1) | non, défaut `0` | index | — |
| isActive | boolean | `is_active` TINYINT(1) | non, défaut `1` | index | — |
| createdAt | datetime | `created_at` TIMESTAMP | oui | index | — |
| updatedAt | datetime | `updated_at` TIMESTAMP | oui | — | — |

### CommunicationRule → `communication_rules`

| Attribut PlantUML | Type PlantUML | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| id | ULID | `id` CHAR(26) | non | PK | — |
| organizationId | ULID | `organization_id` CHAR(26) | non | index | FK `organizations` RESTRICT |
| serviceId | ULID | `service_id` CHAR(26) | **oui** | index | FK `services` RESTRICT |
| templateId | ULID | `template_id` CHAR(26) | non | index | FK `communication_templates` RESTRICT |
| eventType | CommunicationEventType | `event_type` VARCHAR(32) | non | index | — |
| recipientRole | RecipientRole | `recipient_role` VARCHAR(32) | non | index | — |
| delayValue | int | `delay_value` INT | non, défaut `0` | — | — |
| delayUnit | string | `delay_unit` VARCHAR(16) | non | — | — |
| conditions | JSON | `conditions` JSON | **oui** | — | — |
| isAutomatic | boolean | `is_automatic` TINYINT(1) | non, défaut `1` | index | — |
| isActive | boolean | `is_active` TINYINT(1) | non, défaut `1` | index | — |
| createdAt | datetime | `created_at` TIMESTAMP | oui | index | — |
| updatedAt | datetime | `updated_at` TIMESTAMP | oui | — | — |

### OrderCommunication → `order_communications`

| Attribut PlantUML | Type PlantUML | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| id | ULID | `id` CHAR(26) | non | PK | — |
| organizationId | ULID | `organization_id` CHAR(26) | non | index | FK `organizations` RESTRICT |
| orderId | ULID | `order_id` CHAR(26) | non | index | FK `orders` RESTRICT |
| templateId | ULID | `template_id` CHAR(26) | **oui** | index | FK `communication_templates` **SET NULL** |
| communicationRuleId | ULID | `communication_rule_id` CHAR(26) | **oui** | index | FK `communication_rules` **SET NULL** |
| channel | CommunicationChannel | `channel` VARCHAR(32) | non | index | — |
| communicationType | CommunicationTemplateType | `communication_type` VARCHAR(32) | non | index | — |
| recipientRole | RecipientRole | `recipient_role` VARCHAR(32) | non | index | — |
| recipientName | string | `recipient_name` VARCHAR(255) | non | — | — |
| recipientEmail | string | `recipient_email` VARCHAR(255) | **oui** | — | — |
| recipientPhone | string | `recipient_phone` VARCHAR(32) | **oui** | — | — |
| subject | text | `subject` TEXT | **oui** | — | — |
| body | longtext | `body` LONGTEXT | non | — | — |
| templateVariables | JSON | `template_variables` JSON | **oui** | — | — |
| status | CommunicationStatus | `status` VARCHAR(16) | non | index | — |
| scheduledAt | datetime | `scheduled_at` TIMESTAMP | **oui** | index | — |
| queuedAt | datetime | `queued_at` TIMESTAMP | **oui** | index | — |
| sentAt | datetime | `sent_at` TIMESTAMP | **oui** | index | — |
| deliveredAt | datetime | `delivered_at` TIMESTAMP | **oui** | index | — |
| readAt | datetime | `read_at` TIMESTAMP | **oui** | index | — |
| failedAt | datetime | `failed_at` TIMESTAMP | **oui** | index | — |
| providerMessageId | string | `provider_message_id` VARCHAR(255) | **oui** | index | — |
| providerResponse | JSON | `provider_response` JSON | **oui** | — | — |
| errorMessage | text | `error_message` TEXT | **oui** | — | — |
| createdBy | ULID | `created_by` CHAR(26) | **oui** | index | FK `users` **SET NULL** |
| createdAt | datetime | `created_at` TIMESTAMP | oui | index | — |
| updatedAt | datetime | `updated_at` TIMESTAMP | oui | — | — |

### CommunicationAttachment → `communication_attachments`

| Attribut PlantUML | Type PlantUML | Colonne MySQL | Nullable | Index | Relation |
|---|---|---|---|---|---|
| id | ULID | `id` CHAR(26) | non | PK | — |
| communicationId | ULID | `communication_id` CHAR(26) | non | unique composite | FK `order_communications` **CASCADE** |
| documentId | ULID | `document_id` CHAR(26) | non | index | FK `documents` RESTRICT |
| fileNameSnapshot | string | `file_name_snapshot` VARCHAR(255) | non | — | — |
| mimeTypeSnapshot | string | `mime_type_snapshot` VARCHAR(128) | non | — | — |
| createdAt | datetime | `created_at` TIMESTAMP | oui | index | — |

`CommunicationAttachment` **n'a pas d'`updatedAt`** dans le diagramme :
`$timestamps` est donc désactivé et `created_at` est posé à la main, comme pour
`DocumentLink` en Phase 2 et `TourStopService` en Phase 4.

---

## 3. Enums

Les cinq enums sont repris **à la lettre**, sans ajout :

```text
CommunicationChannel       5 valeurs   EMAIL … INTERNAL_NOTIFICATION
CommunicationTemplateType 12 valeurs   APPOINTMENT_REQUEST … CUSTOM
CommunicationEventType    11 valeurs   ORDER_CREATED … CLAIM_CREATED
CommunicationStatus        9 valeurs   DRAFT … CANCELLED
RecipientRole              6 valeurs   CUSTOMER … CUSTOM
```

Stockage en `VARCHAR`, valeurs en `snake_case` : convention des Phases 2, 4 et 6
(`OrderStatus`, `TourStatus`, `ExportFormat`). Aucun `ENUM` SQL — MySQL impose
un `ALTER TABLE` pour toute valeur ajoutée.

`delayUnit` **reste une `string`**, comme le §17 l'exige explicitement. Aucun
enum n'est créé pour elle.

---

## 4. Dépendances vers les phases précédentes

| Dépendance | Phase | Usage |
|---|---|---|
| `Organization` | 1 | périmètre des trois classes principales |
| `User` | 1 | `createdBy`, contexte d'audit |
| `Service` | 2 | portée facultative d'un template et d'une règle |
| `Order`, `OrderService` | 3 | rattachement d'une communication |
| `OrderServiceContact`, `ContactRole` | 3 | résolution du destinataire |
| `Customer` | 2 | destinataire `CUSTOMER` |
| `Document` | 2 | pièces jointes |
| `AuditLog`, `WriteAuditLog` | 1 | journalisation |
| `MorphMap` | 2 | alias métier des quatre nouvelles entités |
| `BaseOrganizationPolicy` | 1 | autorisations |
| `ApiResponse`, `ListRequest`, `PartialAttributes` | 1-2 | couche HTTP |

**Rien n'est recréé.** Le `ContactRole` du Shared couvre déjà `LOAD`,
`DELIVERY` et `BILLING` : la résolution du destinataire s'y adosse au lieu
d'inventer une seconde nomenclature.

---

## 5. État du code existant

```text
Laravel 13.23.0 · PHP 8.4.2 · MySQL 8 · Sanctum 4 · Pest 5 · Pint · Scramble
593 tests, 1930 assertions, verts avant la phase
```

Constats structurants relevés à l'analyse :

- **`app/Jobs/` n'existe pas.** Aucun job n'a jamais été écrit dans le projet.
  `QUEUE_CONNECTION=database` est configuré et la table `jobs` existe.
- **`app/Mail/` et `app/Notifications/` n'existent pas.** `MAIL_MAILER=log`.
  Aucun transporteur SMS, WhatsApp ou push n'est configuré, ni aucun identifiant
  fournisseur.
- `routes/console.php` ne contient que la commande `inspire` de Laravel : aucun
  scheduler n'est en place.
- Un moteur de statut commun existe déjà, sous forme de méthodes d'enum :
  `OrderStatus::allowedTransitions()` / `canTransitionTo()`. `TourStatus` suit la
  même forme. **C'est ce moteur qui est réutilisé** (§24), pas un nouveau.

---

## 6. Nullabilité — décisions

### Ce que la cardinalité impose

| Colonne | Cardinalité | Décision |
|---|---|---|
| `*.organization_id` | `Organization "1"` | NOT NULL |
| `*.service_id` | `Service "0..1"` | NULL |
| `communication_rules.template_id` | `CommunicationTemplate "1"` | NOT NULL |
| `order_communications.template_id` | `CommunicationTemplate "0..1"` | NULL |
| `order_communications.communication_rule_id` | `CommunicationRule "0..1"` | NULL |
| `order_communications.order_id` | `Order "1"` | NOT NULL |
| `order_communications.created_by` | `User "0..1"` | NULL |
| `communication_attachments.communication_id` | `"1" *--` | NOT NULL |
| `communication_attachments.document_id` | `Document "1"` | NOT NULL |

### Ce que la cardinalité ne dit pas

**`subject_template` est nullable.** Le §11 l'impose : « Ne pas rendre
`subject_template` obligatoire pour SMS ou WhatsApp sans règle explicite. » Un
SMS n'a pas d'objet. La validation ne l'exige que pour `EMAIL` — seul canal où
l'absence d'objet est une anomalie constatable.

**`body_template` est NOT NULL.** Un template sans corps n'a pas d'objet, quel
que soit le canal.

**`recipient_email` et `recipient_phone` sont tous deux nullables** en base, et
rendus obligatoires **par la validation, selon le canal** :

| Canal | Champ exigé |
|---|---|
| `EMAIL` | `recipientEmail` |
| `SMS`, `WHATSAPP` | `recipientPhone` |
| `PUSH_NOTIFICATION`, `INTERNAL_NOTIFICATION` | aucun |

Le §20 l'ordonne dans les deux sens : « Ne pas rendre email obligatoire pour
SMS. Ne pas rendre téléphone obligatoire pour EMAIL. » Une contrainte SQL ne
peut pas exprimer cette conditionnalité sans `CHECK` propre à un moteur.

**`recipient_name` est NOT NULL.** Toute résolution de destinataire produit un
nom — celui du client, du contact ou de l'utilisateur. `CUSTOM` l'exige.

**`subject` est nullable, `body` NOT NULL** : mêmes raisons que le template.

**Les six horodatages d'état sont nullables.** `queued_at`, `sent_at`,
`delivered_at`, `read_at`, `failed_at` ne sont posés que par la transition qui
les produit ; `scheduled_at` n'existe que pour une communication programmée.
Aucun n'a de valeur par défaut : une date fictive vaudrait affirmation d'un
événement qui n'a pas eu lieu.

**`provider_message_id`, `provider_response`, `error_message` sont nullables** :
ils n'existent qu'après une réponse du transporteur.

---

## 7. Stratégie de suppression

| Clé étrangère | Choix | Raison |
|---|---|---|
| `communication_templates.organization_id` | RESTRICT | convention de toutes les phases |
| `communication_templates.service_id` | RESTRICT | supprimer un service ne doit pas vider silencieusement le périmètre d'un template ; l'association `--` n'est pas une composition |
| `communication_rules.organization_id` | RESTRICT | idem |
| `communication_rules.service_id` | RESTRICT | idem |
| `communication_rules.template_id` | RESTRICT | doublé d'un refus métier — voir plus bas |
| `order_communications.organization_id` | RESTRICT | idem |
| `order_communications.order_id` | RESTRICT | une communication documente une commande ; la commande ne peut pas disparaître sous elle |
| `order_communications.template_id` | **SET NULL** | le contenu est figé dans `subject`/`body` : perdre le lien ne perd aucune information |
| `order_communications.communication_rule_id` | **SET NULL** | idem |
| `order_communications.created_by` | **SET NULL** | convention des Phases 3 à 7 pour `created_by` |
| `communication_attachments.communication_id` | **CASCADE** | `*--` : composition stricte du diagramme |
| `communication_attachments.document_id` | RESTRICT | un document reste un document ; la pièce jointe ne le possède pas |

`SET NULL` sur `template_id` et `communication_rule_id` n'est pas une
contradiction avec le refus métier : le refus arrive **avant**, à la
suppression via l'API. Le `SET NULL` ne protège que d'une suppression faite
hors application.

### Refus métier

| Ressource | Refus | Statut HTTP |
|---|---|---|
| `CommunicationTemplate` | référencé par une règle **ou** par une communication | 409 |
| `CommunicationRule` | référencée par une communication | 409 |
| `OrderCommunication` | statut autre que `DRAFT` | 409 |
| `CommunicationAttachment` | communication dont le statut n'accepte plus de modification | 409 |

Le §42 le demande : « Ne pas supprimer les communications historiques »,
« Pour DRAFT, suppression possible selon permission ».

---

## 8. Rendu des templates — stratégie

Service : `CommunicationTemplateRenderer`.

Syntaxe retenue : **`{{ nom_de_variable }}`**, avec espaces facultatifs. Un seul
motif, aucune expression, aucun filtre, aucune condition, aucune boucle.

```text
motif accepté      /\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/
```

Cinq règles, toutes vérifiées par test :

1. **Seules les variables déclarées** dans `availableVariables` sont
   remplaçables. Une variable présente dans le corps mais absente de la
   déclaration fait **échouer le rendu** (§13 : « refuser les variables
   inconnues »).
2. Aucune notation à points : `{{ order.customer.name }}` est refusé au motif
   même. Le §13 interdit « l'accès arbitraire aux propriétés des modèles » —
   l'interdire au niveau lexical est plus sûr que de le filtrer ensuite.
3. `eval`, `Blade::render`, `preg_replace` avec modificateur `e` : aucun n'est
   employé. Le remplacement est un `str_replace` sur une table close.
4. **Échappement par canal** : `EMAIL` échappe en HTML (`htmlspecialchars`,
   `ENT_QUOTES`) ; les quatre autres canaux sont du texte brut et ne subissent
   aucune transformation. Échapper en HTML un SMS y écrirait `&amp;`.
5. Le rendu **ne touche jamais** au `CommunicationTemplate` : il retourne un
   couple `subject`/`body`.

Les valeurs passées au rendu sont contraintes : `string`, `int`, `float`, `bool`
ou `null`. Un tableau ou un objet est refusé — il n'a pas de représentation
textuelle évidente et ouvrirait la porte à une sérialisation arbitraire.

---

## 9. `availableVariables` — schéma retenu

Le §12 interdit d'« inventer silencieusement un schéma complexe ». Le schéma
retenu est donc **le plus simple qui réponde au besoin** : une liste plate de
noms.

```json
["order_number", "customer_name", "delivery_date"]
```

Validation, par `ValidateCommunicationTemplateVariables` :

- tableau JSON, liste (clés `0..n`), jamais un objet ;
- chaque entrée est une chaîne conforme à `^[a-zA-Z][a-zA-Z0-9_]{0,63}$` ;
- pas de doublon ;
- 100 entrées au maximum.

Ce que le motif exclut mécaniquement : les points, les parenthèses, les `$`, les
espaces, les chemins. Un nom de variable ne peut donc jamais désigner une
méthode, un chemin de fichier ou une propriété chaînée.

---

## 10. `conditions` — schéma retenu

Le §16 est explicite : « Si aucun schéma de conditions n'est défini, documenter
le point et ne pas inventer des opérateurs complexes. »

Aucun schéma n'est défini nulle part dans les diagrammes ni dans les prompts des
Phases 1 à 8. Le schéma retenu est donc **volontairement minimal** — une
conjonction plate d'égalités et de comparaisons :

```json
{
  "all": [
    {"field": "order_status", "operator": "eq", "value": "confirmed"},
    {"field": "package_count", "operator": "gte", "value": 3}
  ]
}
```

- une seule clé racine, `all` — conjonction ; **pas de `any`, pas de `not`**,
  pas d'imbrication ;
- `field` : `^[a-z][a-z0-9_]{0,63}$` ;
- `operator` : `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `in`, `not_in` — huit
  valeurs, closes ;
- `value` : scalaire, ou liste de scalaires pour `in` / `not_in` ;
- 20 conditions au maximum ;
- `conditions` peut valoir `null` : la règle est alors inconditionnelle.

`CommunicationRuleConditionEvaluator` évalue cette structure contre un tableau
de faits scalaires. **Il n'accède à aucun modèle**, n'exécute aucun appel de
méthode et n'ouvre aucune base : il compare des valeurs déjà extraites. C'est
ce qui le rend déterministe et testable sans base de données.

**Ce qui n'est pas livré et pourquoi :** aucun déclencheur automatique n'appelle
cet évaluateur. Les onze `CommunicationEventType` sont des événements métier
(`ORDER_CONFIRMED`, `POD_CREATED`…) que rien, dans les Phases 1 à 8, n'émet :
le projet n'a **aucun** `Event` Laravel ni listener. Câbler onze événements
imaginerait la sémantique de chacun — précisément ce que le §2 interdit.
L'évaluateur est livré, testé et prêt ; son branchement attend que les
événements existent.

---

## 11. Délai des règles

`delayValue` (int) et `delayUnit` (string), sans enum, conformément au §17.

Aucune convention d'unité n'existe dans le projet. Les unités **supportées par
le moteur technique** — c'est-à-dire celles que `CarbonInterval` sait ajouter —
sont documentées et validées :

```text
minutes · hours · days
```

`delayValue` est un entier de 0 à 100 000 : positif, car un délai négatif
signifierait envoyer avant l'événement, ce qu'aucun texte ne définit.
`scheduledExpression` et `cronExpression` ne sont pas créés (§17).

---

## 12. Destinataire — logique exacte

Service : `ResolveOrderCommunicationRecipient`. Il ne lit que des données
existantes ; **aucune table de destinataires n'est créée** (§22).

| `RecipientRole` | Source | Champs produits |
|---|---|---|
| `CUSTOMER` | `order.customer` | `name`, `email`, `phone` |
| `LOAD_CONTACT` | `OrderServiceContact` de la commande, `contact_role = load` | `first_name_snapshot` + `last_name_snapshot`, `email_snapshot`, `phone_snapshot` ou `mobile_snapshot` |
| `DELIVERY_CONTACT` | idem, `contact_role = delivery` | idem |
| `BILLING_CONTACT` | idem, `contact_role = billing` | idem |
| `INTERNAL_USER` | l'utilisateur authentifié (`AuditContext`) | `name`, `email`, aucun téléphone |
| `CUSTOM` | le payload | les trois champs, fournis explicitement |

Précisions :

- pour les trois rôles de contact, le contact **`is_primary`** est préféré ;
  à défaut, le premier par ordre de création ;
- les colonnes lues sont **les snapshots** de `OrderServiceContact`, pas le
  `Contact` partagé : la commande doit rester lisible telle qu'elle a été passée
  (raison déjà retenue en Phase 3) ;
- `phone_snapshot` prime sur `mobile_snapshot` ; le mobile sert de repli ;
- si aucun contact ne porte le rôle demandé, la création est refusée en **422**
  avec un message nommant le rôle. Elle n'est pas silencieusement basculée sur
  le client : substituer un destinataire est plus grave qu'échouer ;
- pour les cinq rôles non-`CUSTOM`, un destinataire fourni dans le payload est
  **ignoré** : le rôle est la source de vérité. Sinon `recipientRole` deviendrait
  décoratif.

---

## 13. Statuts et transitions

Le moteur est celui des Phases 3 et 4 : des méthodes portées par l'enum.

```text
DRAFT      → SCHEDULED, QUEUED, CANCELLED
SCHEDULED  → DRAFT, QUEUED, CANCELLED
QUEUED     → SENDING, FAILED, CANCELLED
SENDING    → SENT, FAILED
SENT       → DELIVERED, READ, FAILED
DELIVERED  → READ
READ       → (final)
FAILED     → QUEUED          (c'est le retry)
CANCELLED  → (final)
```

Justification des choix non triviaux :

- `SCHEDULED → DRAFT` est permis : retirer la date de programmation ramène au
  brouillon, et rien n'est encore parti ;
- `SENT → FAILED` est permis : un transporteur peut signaler un échec après
  acceptation (rejet différé, adresse invalide) ;
- `SENT → READ` sans passer par `DELIVERED` est permis : tous les canaux ne
  signalent pas la remise ;
- `FAILED → QUEUED` est la seule sortie de `FAILED`, et c'est exactement ce que
  fait `retry`. Aucune colonne de compteur n'est ajoutée (§29) ;
- `CANCELLED` et `READ` sont finaux.

**`retry` exige `FAILED`**, alors que le graphe autorise aussi `DRAFT → QUEUED` :
sans ce refus supplémentaire, la permission `order_communications.retry`
permettrait d'expédier un brouillon sans détenir `order_communications.queue`.
Le refus est porté par `RetryOrderCommunicationAction`, pas par le graphe — les
deux transitions sont légitimes, c'est le chemin qui ne l'est pas.

Statut initial, **jamais inventé** (§23) : `SCHEDULED` si `scheduledAt` est
fourni, `DRAFT` sinon. Aucune autre valeur n'est posée à la création.

Le contenu est modifiable en `DRAFT` **et en `SCHEDULED`** : rien n'est encore
parti. Dès la mise en file, il est figé. `allowsContentChanges()` porte cette
règle sur l'enum, comme `OrderStatus::allowsContentChanges()` en Phase 3. La
suppression, elle, reste réservée au seul `DRAFT` (§42) : pour une communication
programmée, l'annulation est le geste prévu.

---

## 14. Envoi et queue — stratégie, et sa limite

`QueueOrderCommunicationAction` place la communication en `QUEUED`, pose
`queuedAt` et dépêche `SendOrderCommunicationJob`. Le job :

1. verrouille la ligne (`lockForUpdate`) ;
2. refuse si le statut n'est plus `QUEUED` — **idempotence** : un double
   dispatch ne produit pas deux envois ;
3. passe à `SENDING` ;
4. choisit le transporteur par canal via `CommunicationSenderRegistry` ;
5. sur succès : `providerMessageId`, `providerResponse`, `sentAt`, `SENT` ;
6. sur échec : `failedAt`, `errorMessage`, `FAILED` ;
7. audite, sans aucun secret.

`QUEUE_CONNECTION=database` est configuré et la table `jobs` existe : la
condition du §25 (« si les queues sont configurées ») est remplie.

### La limite, énoncée franchement

**Aucun transporteur réel n'est livré.** Le projet n'a ni compte SMTP, ni
fournisseur SMS, ni compte WhatsApp Business, ni service push — `MAIL_MAILER=log`,
et aucune clé n'existe nulle part. Le §26 interdit d'« ajouter de fournisseur
métier dans la base si absent du diagramme », et le §5 interdit les classes
vides.

Les cinq transporteurs sont donc livrés dans leur **seule forme honnête** :

- `EmailCommunicationSender` envoie **réellement**, par `Mail::raw()` /
  `Mail::html()` selon le canal — donc dans le log en développement, et par SMTP
  dès qu'un mailer est configuré. C'est du vrai code, pas une façade ;
- `InternalCommunicationSender` **réussit toujours** : une notification interne
  est, par définition, la ligne `order_communications` elle-même. Elle est
  marquée `SENT` immédiatement, avec un `providerMessageId` interne ;
- `SmsCommunicationSender`, `WhatsappCommunicationSender` et
  `PushCommunicationSender` **échouent explicitement**, avec un message nommant
  le fournisseur manquant. La communication passe en `FAILED` avec
  `errorMessage`, ce qui est l'état vrai du système. Ils ne prétendent pas
  envoyer.

Chaque transporteur retourne un `SenderResult` normalisé et peut être remplacé
par un fake dans les tests (§26). Brancher un fournisseur réel consistera à
remplacer un seul de ces trois corps de méthode — aucune migration, aucun
changement d'API.

---

## 15. Communication programmée

`ProcessScheduledCommunications`, commande Artisan enregistrée au scheduler
(`routes/console.php`, toutes les minutes).

- ne prend que les `SCHEDULED` dont `scheduledAt <= maintenant` ;
- verrouille par lot dans une transaction ;
- délègue à `QueueOrderCommunicationAction`, qui refait la vérification de
  statut : deux exécutions concurrentes ne peuvent pas mettre deux fois en file ;
- aucune table `ScheduledCommunication` (§27).

---

## 16. Callbacks fournisseur

**Aucun endpoint de callback n'est créé.** Le §28 le conditionne : « uniquement
si le projet a déjà les intégrations nécessaires ». Il n'en a aucune, et
« ne pas inventer de protocole fournisseur » interdit d'écrire une validation de
signature contre un fournisseur imaginaire.

`deliveredAt` et `readAt` restent donc alimentables uniquement par les
transitions internes. Le risque est consigné au rapport final.

---

## 17. Permissions prévues

18 permissions, conformes au §38 :

```text
communication_templates.view / create / update / delete
communication_rules.view / create / update / delete
order_communications.view / create / update / delete / queue / cancel / retry
communication_attachments.view / create / delete
```

`queue`, `cancel` et `retry` sont distinctes d'`update` : elles déclenchent un
envoi ou l'interrompent, ce qui n'est pas une modification de contenu.
`communication_attachments` n'a pas d'`update` — les snapshots sont immuables,
il n'y a donc pas de route `PATCH` (§31).

---

## 18. Endpoints prévus

```text
GET|POST          /communication-templates
GET|PATCH|DELETE  /communication-templates/{communicationTemplate}

GET|POST          /communication-rules
GET|PATCH|DELETE  /communication-rules/{communicationRule}

GET|POST          /order-communications
GET|PATCH|DELETE  /order-communications/{orderCommunication}
POST              /order-communications/{orderCommunication}/queue
POST              /order-communications/{orderCommunication}/cancel
POST              /order-communications/{orderCommunication}/retry
GET|POST          /orders/{order}/communications

GET|POST          /order-communications/{orderCommunication}/attachments
GET|DELETE        /order-communications/{orderCommunication}/attachments/{attachment}
```

Pas de `PATCH` sur les pièces jointes (§31). `PATCH` sur une communication n'est
accepté qu'en `DRAFT` (§29).

---

## 19. Tests prévus

| Fichier | Couverture |
|---|---|
| `CommunicationTemplateTest` | création, service facultatif, service hors organisation, code unique par organisation, enums, `availableVariables`, variables invalides, recherche, filtres, IDOR, audit, suppression protégée |
| `CommunicationTemplateRendererTest` | rendu nominal, variable inconnue refusée, notation à points refusée, échappement HTML pour EMAIL, absence d'échappement pour SMS, valeur non scalaire refusée, template inchangé |
| `CommunicationRuleTest` | création, template obligatoire, template hors organisation, incohérence service/template, enums, conditions valides, conditions dangereuses, `delayValue` et `delayUnit` invalides, filtres, IDOR, audit, suppression protégée |
| `CommunicationRuleConditionEvaluatorTest` | huit opérateurs, conjonction, absence de fait, structure refusée |
| `OrderCommunicationTest` | création manuelle, depuis template, depuis règle, snapshots figés, six rôles de destinataire, validation email/téléphone par canal, statut initial, programmation, queue, envoi, échec, retry, annulation, idempotence, IDOR, audit, modification refusée après envoi |
| `CommunicationAttachmentTest` | ajout, document hors organisation, snapshots, doublon, consultation, suppression avant envoi, suppression refusée après envoi, IDOR, audit |
| `CommunicationPermissionTest` | chaque permission, `queue`/`cancel`/`retry` distinctes d'`update`, en-tête requis, non authentifié |

---

## 20. Éléments explicitement exclus

Aucune de ces classes ou tables n'est créée, conformément aux §2 et §53 :

```text
CommunicationRecipient      Notification              NotificationTemplate
NotificationPreference      EmailLog                  SmsLog
WhatsappLog                 PushNotificationLog       InternalNotification
CommunicationQueue          CommunicationProvider     CommunicationProviderConfiguration
CommunicationStatusHistory  CommunicationDelivery     Webhook
WebhookDelivery             MessageThread             Conversation
Message                     ScheduledCommunication
```

Aucun des attributs interdits au §2 n'est ajouté : ni `customer_id`,
`driver_id`, `provider_id`, `tour_id`, `tour_stop_id`, `claim_id`, ni `cc`,
`bcc`, `reply_to`, `sender_name`, `sender_email`, `provider_name`,
`retry_count`, `max_attempts`, `priority`, `metadata`, ni `softDeletes`.

Un test vérifie l'absence de ces tables et de ces colonnes.
