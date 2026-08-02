# Rapport final — Phase 9 : communication et templates

---

## 1. Branche

```text
feature/backend-phase-9-communications
```

Créée depuis `feature/backend-phase-8-customer-integrations` (commit `2f8449a`),
non depuis `main`. Même écart assumé qu'aux Phases 3 à 8 : `main` est un
squelette vide au commit `c97dc0d`, et chaque phase s'appuie sur la précédente.

Aucune fusion, aucun rebase, aucun push.

## 2. Diagrammes

Les `.puml` du §1 n'existent pas ; les `.txt` font foi. Paquet
`Communication et templates` lignes 617-750, relations lignes 825-841.

**Aucun conflit.** Les cinq enums, les quatre classes, chacun de leurs attributs
et chacune de leurs relations concordent avec le prompt.

Colonnes créées, conformes attribut par attribut :

```text
communication_templates     15 colonnes   (15 attributs)
communication_rules         13 colonnes   (13 attributs)
order_communications        27 colonnes   (27 attributs)
communication_attachments    6 colonnes   (6 attributs)
```

## 3. Classes et enums

```text
CommunicationTemplate    CommunicationRule
OrderCommunication       CommunicationAttachment

CommunicationChannel       EMAIL, SMS, WHATSAPP, PUSH_NOTIFICATION, INTERNAL_NOTIFICATION
CommunicationTemplateType  12 valeurs, APPOINTMENT_REQUEST … CUSTOM
CommunicationEventType     11 valeurs, ORDER_CREATED … CLAIM_CREATED
CommunicationStatus        DRAFT, SCHEDULED, QUEUED, SENDING, SENT, DELIVERED, READ, FAILED, CANCELLED
RecipientRole              CUSTOMER, LOAD_CONTACT, DELIVERY_CONTACT, BILLING_CONTACT, INTERNAL_USER, CUSTOM
```

`delayUnit` reste une `string` : le §17 interdit d'en faire un enum. Les unités
supportées — `minutes`, `hours`, `days` — sont validées, pas énumérées en base.

## 4. Livrables

**4 migrations**, **4 modèles**, **5 enums**, **7 DTOs**, **16 services**,
**3 exceptions**, **1 Query Object**, **7 Requests**, **8 Resources**,
**4 Policies**, **5 Controllers**, **4 factories**, **1 Job**, **1 commande
Artisan**.

**Actions (8)** :

```text
ManageCommunicationTemplateAction     ManageCommunicationRuleAction
CreateOrderCommunicationAction        UpdateDraftOrderCommunicationAction
QueueOrderCommunicationAction         CancelOrderCommunicationAction
RetryOrderCommunicationAction         ManageCommunicationAttachmentAction
```

Plus `ApplyCommunicationTransition`, par où passe **toute** transition d'état, et
`WriteCommunicationAudit`.

**Services (16)**, dont deux objets de valeur (`RenderedContent`,
`ResolvedRecipient`), l'interface `CommunicationSender` et son `SenderResult` :

```text
CommunicationTemplateRenderer            ValidateCommunicationTemplateVariables
CommunicationRuleConditionEvaluator      ResolveOrderCommunicationRecipient
CommunicationScopeGuard                  CommunicationSenderRegistry
5 transporteurs : Email, Internal, Sms, Whatsapp, Push
  — les trois derniers héritent de UnconfiguredChannelSender
```

### Une refonte de la Phase 8

`WriteConfigurationAudit`, écrite en Phase 8, portait un mécanisme dont les
communications avaient besoin à l'identique : comparer avant/après, ne
journaliser que les champs changés, expurger les colonnes sensibles. Seule la
liste des colonnes à masquer diffère.

Le mécanisme est donc extrait dans `App\Shared\Audit\WriteModelAudit`, dont
`WriteConfigurationAudit` et `WriteCommunicationAudit` héritent. Recopier
90 lignes aurait été le contraire de ce que demande le §5.

Trois Actions de la Phase 8 perdent au passage un argument devenu inutile
(`$auditedColumns` sur `update()`, jamais lu). **Aucun comportement ne change** :
les 62 tests de la Phase 8 passent sans modification.

## 5. Permissions et routes

**18 permissions** ; total du projet : **187**.

**24 routes**, aucun doublon sur les **331** du projet.

`queue`, `cancel` et `retry` ont leur propre permission : elles déclenchent ou
interrompent un envoi vers un tiers, ce qui n'est pas une modification de
contenu. Un test vérifie que `order_communications.update` ne suffit à aucune
des trois.

`communication_attachments` n'a pas d'`update` : les snapshots sont immuables,
la route `PATCH` n'existe pas et répond `405`.

## 6. Tests

| Fichier | Tests | Points saillants |
|---|---|---|
| `Communications/CommunicationTemplateRendererTest` | 12 | Rendu nominal, **variable non déclarée refusée**, **notation à points refusée**, **expression PHP refusée**, valeur non scalaire refusée, **échappement HTML pour EMAIL**, absence d'échappement pour SMS, **modèle jamais modifié** |
| `Communications/CommunicationRuleConditionEvaluatorTest` | 13 | Huit opérateurs, conjonction, `any` refusé, imbrication refusée, champ à chemin refusé, **fait absent ⇒ condition fausse** |
| `Api/V1/Communications/CommunicationTemplateTest` | 17 | Service facultatif et hors organisation, **code unique par organisation**, objet exigé pour EMAIL seulement, enums, **variables à chemin ou expression refusées**, code non modifiable, **suppression protégée** (règle et historique), IDOR, audit sans FQCN, colonnes et tables interdites absentes |
| `Api/V1/Communications/CommunicationRuleTest` | 15 | Modèle obligatoire et hors organisation, **incohérence service/modèle refusée**, enums, unité de délai, délai négatif, **conditions dangereuses refusées**, suppression protégée, IDOR |
| `Api/V1/Communications/OrderCommunicationTest` | 28 | Création manuelle, depuis modèle, depuis règle, **snapshot figé malgré modification du modèle**, **six rôles de destinataire**, contact principal préféré, repli sur le mobile, **champs d'exécution ignorés**, validation e-mail/téléphone par canal dans les deux sens, programmation, **édition refusée après envoi**, IDOR |
| `Api/V1/Communications/OrderCommunicationSendingTest` | 17 | Mise en file, envoi, échec, **idempotence d'un double dispatch**, **clé fournisseur non listée jamais exposée**, **corps et réponse fournisseur absents de l'audit**, transporteurs réels, annulation, **relance réservée à l'échec**, planification n'envoyant que les échues, **pas deux fois la même** |
| `Api/V1/Communications/CommunicationAttachmentTest` | 13 | Snapshots figés malgré renommage du document, document hors organisation, **doublon refusé**, ajout et retrait refusés après envoi, **cascade sur le brouillon supprimé sans toucher au document**, IDOR, `PATCH` en 405 |
| `Api/V1/Communications/CommunicationPermissionTest` | 9 | Chaque permission, **`update` ne permet ni `queue`, ni `cancel`, ni `retry`**, `retry` fonctionne avec sa propre permission, en-tête requis, non authentifié |

**124 tests ajoutés.**

## 7. Résultats

```text
composer validate                                valid
php artisan migrate:fresh --seed --env=testing   OK
php artisan test                                 717 passed (2332 assertions)
./vendor/bin/pint --test                         PASS
php artisan route:list                           331 routes, aucun doublon
TODO / classes vides                             aucun
constructions PostgreSQL                         aucune
```

593 tests des Phases 1 à 8, 124 de la Phase 9. **Aucune régression.**

`MorphMap.php` reste le seul fichier de `app/` au-dessus des 200 lignes
recommandées — **292 lignes**, contre 276 en Phase 8 : les quatre alias de cette
phase en ajoutent seize. Même arbitrage qu'aux Phases 7 et 8 : c'est un registre
plat, et le scinder renommerait plus de quarante usages.

## 8. Décisions structurantes

### Le rendu ne peut rien exécuter

Un seul motif reconnu, `{{ nom }}`, avec un nom conforme à
`[a-zA-Z][a-zA-Z0-9_]{0,63}`. Ni `eval`, ni Blade, ni `preg_replace` exécutable :
le remplacement est une substitution sur une table close.

Trois barrières se cumulent :

1. **le motif** rejette lexicalement `{{ order.customer.email }}` et
   `{{ phpinfo() }}` — l'accès arbitraire aux propriétés est impossible à
   écrire, pas seulement filtré ensuite ;
2. **la déclaration** : une variable absente d'`availableVariables` fait échouer
   le rendu, dans le corps comme dans les valeurs fournies ;
3. **le type** : seules les valeurs scalaires sont acceptées.

L'échappement dépend du canal : HTML pour `EMAIL`, aucun pour les quatre autres.
Échapper un SMS y écrirait `&amp;`.

### Les conditions restent déclaratives

Aucun schéma de conditions n'existe dans les diagrammes ni dans les huit prompts
précédents. Le §16 impose alors de documenter et de ne pas inventer d'opérateurs
complexes.

Le schéma retenu est le plus petit qui serve : une conjonction plate, huit
opérateurs clos, aucune imbrication, aucune expression. L'évaluateur **n'accède
à aucun modèle** et n'ouvre aucune base — il compare des faits scalaires déjà
extraits. C'est ce qui le rend testable sans base de données.

### Le destinataire vient du rôle, jamais du payload

Pour les cinq rôles autres que `CUSTOM`, les coordonnées fournies par l'appelant
sont **ignorées** : le rôle est la source de vérité, sinon `recipientRole`
deviendrait décoratif. Un test le prouve en envoyant un destinataire usurpé.

Les trois rôles de contact s'adossent au `ContactRole` du Shared, déjà utilisé
par `OrderServiceContact` depuis la Phase 3 : aucune seconde nomenclature n'est
créée. Les colonnes lues sont les **snapshots** de la commande, pas le contact
partagé.

Si aucun contact ne porte le rôle demandé, la création est **refusée** en 422.
Elle n'est pas basculée silencieusement sur le client : substituer un
destinataire est plus grave qu'échouer.

### Une seule porte pour les transitions

`ApplyCommunicationTransition` verrouille la ligne, **relit le statut en base**,
vérifie la transition contre l'enum, puis écrit. Tout passe par là : mise en
file, envoi, échec, annulation, relance.

C'est ce qui rend l'envoi idempotent — un second dispatch du même Job trouve la
communication déjà partie et s'arrête. Un test lance le Job deux fois et vérifie
que le transporteur n'a été appelé qu'une fois.

`retry` ajoute un refus que le graphe seul ne donne pas : il exige `FAILED`.
Sans lui, la permission `order_communications.retry` permettrait d'expédier un
brouillon sans détenir `order_communications.queue`.

### L'audit ne transporte ni corps ni réponse fournisseur

`WriteCommunicationAudit` expurge `body` et `provider_response`. Le §39 le
demande pour le premier — un corps contient des données personnelles, et le
journal se consulte plus largement que la communication. Le second vient d'un
tiers et pourrait contenir un identifiant technique.

Ni l'un ni l'autre n'est perdu : ils restent sur la ligne, lisibles avec la
permission de lecture. En restitution, `providerResponse` passe en outre par une
**liste blanche de clés** — un fournisseur futur qui y renverrait un jeton ne le
publierait pas pour autant.

## 9. Ce qui n'est pas livré — décisions explicites

### Aucun transporteur SMS, WhatsApp ou push

Le projet n'a ni compte SMTP, ni agrégateur SMS, ni compte WhatsApp Business, ni
service push : `MAIL_MAILER=log`, et aucune clé n'existe nulle part. Le §26
interdit d'ajouter un fournisseur métier absent du diagramme ; le §5 interdit
les classes vides.

Les cinq transporteurs sont donc livrés dans leur seule forme honnête :

- **`EmailCommunicationSender` envoie réellement**, par le mailer configuré —
  dans le log en développement, par SMTP dès qu'un mailer existe ;
- **`InternalCommunicationSender` réussit toujours** : une notification interne
  *est* la ligne `order_communications`, consultable par l'API dès sa création ;
- **SMS, WhatsApp et push échouent explicitement**, en nommant ce qui manque. La
  communication passe en `FAILED` avec `errorMessage` — l'état vrai du système.

Un transporteur qui retournerait « succès » sans rien envoyer serait pire qu'une
absence : la communication passerait en `SENT` alors que rien n'est parti.

Raccorder un fournisseur consistera à remplacer **un seul corps de méthode** :
ni migration, ni changement d'API, ni changement de contrat.

### Aucun déclenchement automatique des règles

Les onze `CommunicationEventType` sont des événements métier — `ORDER_CONFIRMED`,
`POD_CREATED`, `TOUR_STOP_APPROACHING` — que **rien, dans les Phases 1 à 8,
n'émet** : le projet n'a aucun `Event` Laravel ni listener.

Câbler onze événements imaginerait la sémantique de chacun : à quel moment
exactement un arrêt est-il « imminent » ? Le §2 l'interdit.

Les règles sont donc enregistrées, validées, et leur évaluateur est livré et
testé. Leur branchement attend que les événements existent.

### Aucun endpoint de callback fournisseur

Le §28 le conditionne : « uniquement si le projet a déjà les intégrations
nécessaires ». Il n'en a aucune, et « ne pas inventer de protocole fournisseur »
interdit d'écrire une validation de signature contre un fournisseur imaginaire.

`deliveredAt` et `readAt` restent donc sans alimentation. Ils existent en base,
conformes au diagramme, et se rempliront le jour où un fournisseur les
signalera.

## 10. Ambiguïtés levées

| # | Ambiguïté | Traitement |
|---|---|---|
| A | Schéma d'`availableVariables` (§12) | Liste plate de noms, motif strict, documentée |
| B | Schéma de `conditions` (§16) | Conjonction plate, huit opérateurs clos ; §16 impose de documenter plutôt qu'inventer |
| C | Unités de `delayUnit` (§17) | `minutes`, `hours`, `days` — celles du moteur technique, validées sans enum |
| D | Graphe de transitions (§24) | Porté par l'enum, comme `OrderStatus` en Phase 3 ; chaque arête justifiée |
| E | Statut initial (§23) | `SCHEDULED` si date fournie, `DRAFT` sinon — aucune autre valeur posée |
| F | Modification d'une communication programmée (§29) | Autorisée : rien n'est parti. Figée dès la mise en file |
| G | Suppression d'une communication programmée (§42) | Refusée : l'annulation est le geste prévu, et elle laisse une trace |
| H | Pièces jointes réellement attachées à l'e-mail (§30) | Non : « ne pas copier physiquement le fichier sans besoin », et le stockage n'est pas garanti local |
| I | Nullabilité de `subject` par canal (§11, §20) | Nullable en base, exigé par validation pour `EMAIL` seul |

## 11. Fichiers

**Créés — 96** :

```text
app/Modules/Communications/         48
  ├─ Services/                        16   (dont 7 transporteurs et registre)
  ├─ Actions/                         10
  ├─ DTOs/                             7
  ├─ Enums/                            5
  ├─ Models/                           4
  ├─ Exceptions/                       3
  ├─ Queries/                          1
  ├─ Jobs/                             1
  └─ Console/                          1
app/Http/Resources/Api/V1/…          9
app/Http/Requests/Api/V1/…           8
app/Http/Controllers/Api/V1/…        6
tests/                               8
database/factories/                  4
database/migrations/                 4
app/Policies/                        4
docs/backend/                        4   (analysis, database-decisions, api-examples, ce rapport)
app/Shared/Audit/                    1
```

**Modifiés — 10**, dont sept par simple ajout :

```text
routes/api.php                    24 routes ajoutees
routes/console.php                planification de la commande
bootstrap/app.php                 declaration de la commande de module
database/seeders/PermissionSeeder 18 permissions
app/Shared/Database/MorphMap      4 alias
app/Providers/AuthServiceProvider 4 policies
app/Modules/Integrations/Actions/WriteConfigurationAudit      refonte (héritage)
app/Modules/Integrations/Actions/ManageApiConfigurationAction argument retiré
app/Modules/Integrations/Actions/ManageImportConfigurationAction  idem
app/Modules/Exports/Actions/ManageExportConfigurationAction       idem
```

Aucune ligne des Phases 1 à 8 supprimée. Aucun test antérieur modifié.

## 12. Éléments exclus — confirmation

Classes implémentées :

```text
- CommunicationTemplate
- CommunicationRule
- OrderCommunication
- CommunicationAttachment
```

Enums implémentés :

```text
- CommunicationChannel
- CommunicationTemplateType
- CommunicationEventType
- CommunicationStatus
- RecipientRole
```

**Aucun des éléments suivants n'a été inventé**, et un test vérifie l'absence de
chaque table :

```text
- CommunicationRecipient      - Notification            - NotificationTemplate
- EmailLog                    - SmsLog                  - WhatsappLog
- CommunicationProvider       - CommunicationStatusHistory
- Webhook                     - MessageThread
```

Ni `NotificationPreference`, `PushNotificationLog`, `InternalNotification`,
`CommunicationQueue`, `CommunicationProviderConfiguration`,
`CommunicationDelivery`, `WebhookDelivery`, `Conversation`, `Message`, ni
`ScheduledCommunication`.

Aucune colonne interdite par le §2 : ni `customer_id`, `driver_id`,
`provider_id`, `tour_id`, `tour_stop_id`, `claim_id`, ni `cc`, `bcc`,
`reply_to`, `sender_name`, `sender_email`, `provider_name`, `retry_count`,
`max_attempts`, `priority`, `metadata`, ni `softDeletes`. Un test l'atteste
colonne par colonne.

## 13. Risques

| # | Risque | Portée |
|---|---|---|
| 1 | **SMS, WhatsApp et push échouent systématiquement** | Toute communication sur ces trois canaux finit en `FAILED`. C'est délibéré et annoncé, mais un exploitant doit le savoir avant de créer des règles dessus |
| 2 | **Aucune règle ne se déclenche seule** | Les règles décrivent une intention que rien n'exécute encore. Les communications se créent par l'API |
| 3 | **`deliveredAt` et `readAt` restent nuls** | Aucun callback fournisseur n'existe. Les transitions correspondantes sont dans le graphe mais aucune route ne les emprunte |
| 4 | **Le scheduler doit tourner** | `communications:process-scheduled` est planifiée chaque minute ; sans `schedule:work` ou cron, les communications programmées restent `SCHEDULED` indéfiniment |
| 5 | **Un worker de queue doit tourner** | Avec `QUEUE_CONNECTION=database`, sans `queue:work` les communications restent `QUEUED` |

## 14. Dette antérieure, toujours ouverte

Signalée depuis la Phase 5, non traitée ici car hors périmètre :

> `DeleteTourAction` ne refuse pas la suppression d'une tournée référencée par un
> `TrackingEvent`, une `ProofOfDelivery` ou une `Claim` — les trois tables
> existent depuis la Phase 5. S'y ajoute le cas d'un `OrderService` facturé ou
> décompté, ouvert en Phase 6.

Ces communications n'y ajoutent rien : `OrderCommunication` pointe la commande,
pas la tournée.

## 15. Prochaine phase

**Phase 10 — durcissement final.**
Fichier : `Phase10_Backend_Final_Hardening_Strict_Diagrams.md`.

Aucune fusion n'a été faite. La branche attend la validation de l'utilisateur.
