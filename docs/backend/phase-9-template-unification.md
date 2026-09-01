# Unification des modèles — Phase 9

`CommunicationTemplate` devient `Template`. Une seule table de modèles gouverne
désormais toute la plateforme : messages et documents.

## 1. Pourquoi

Un modèle de facture et un modèle d'e-mail sont la même chose : un texte à
trous, résolu puis rendu. Les séparer aurait imposé deux moteurs de rendu, deux
écrans, deux jeux de permissions — et la première divergence entre les deux
serait passée inaperçue jusqu'au jour où l'une des deux aurait laissé passer une
variable non déclarée.

Le §0.1 l'interdit nommément : ni `invoice_templates`, ni `email_templates`, ni
`document_templates`.

## 2. Renommages

| Avant | Après |
|---|---|
| `App\Modules\Communications\Models\CommunicationTemplate` | `App\Modules\Templates\Models\Template` |
| `CommunicationTemplateType` | `App\Modules\Templates\Enums\TemplateType` |
| `CommunicationTemplateRenderer` | `App\Modules\Templates\Services\TemplateRenderer` |
| `ValidateCommunicationTemplateVariables` | `App\Modules\Templates\Services\ValidateTemplateVariables` |
| `ManageCommunicationTemplateAction` | `App\Modules\Templates\Actions\ManageTemplateAction` |
| `CommunicationTemplateInUse` | `App\Modules\Templates\Exceptions\TemplateInUse` |
| table `communication_templates` | table `templates` |
| alias polymorphe `communication_template` | `template` |
| permissions `communication_templates.*` | `templates.*` |
| routes `/api/v1/communication-templates` | `/api/v1/templates` |
| actions d'audit `communication_template.*` | `template.*` |

### Le module quitte Communications

Un template de facture n'est pas une communication : il n'a ni canal, ni
destinataire, ni objet. Le laisser dans `app/Modules/Communications/` aurait fait
dépendre la facturation du module de messagerie pour produire un PDF.

`CommunicationChannel` reste dans Communications — c'est bien une notion de
messagerie — et `Templates` l'importe.

`CommunicationTemplateInUse::ruleHasCommunications()` n'a pas suivi : elle
parlait des règles, pas des modèles. Elle devient
`App\Modules\Communications\Exceptions\CommunicationRuleInUse::hasCommunications()`.

## 3. Migrations

Quatre, toutes non destructives.

### `2026_09_01_100001_rename_communication_templates_to_templates`

`RENAME TABLE` conserve les identifiants, et MySQL réoriente de lui-même les
clés étrangères qui visaient `communication_templates` :
`communication_rules.template_id` et `order_communications.template_id`
continuent de désigner les mêmes lignes. **Aucune communication historique n'est
perdue, aucun doublon n'est créé.**

Ajoute `customer_id` (nullable, RESTRICT vers `customers`) et rend `channel`
nullable.

### `2026_09_01_100002_rename_communication_template_permissions`

Met à jour `code` et `module` des quatre permissions. **Les rôles sont
préservés** : `role_permissions` pointe sur l'identifiant, que rien ne touche.
Un administrateur qui pouvait éditer les modèles hier le peut encore.

### `2026_09_01_100003_add_template_snapshot_to_invoices_table`

`invoices` reçoit `template_id`, `rendered_body`, `rendered_at`. Voir §6.

### `2026_09_01_100004_rename_communication_template_morph_alias`

`audit_logs.entity_type` passe de `communication_template` à `template`. Sans
elle, l'écran d'audit afficherait des entrées qu'il ne sait plus relier.

## 4. Modèle final

```text
Template
- id, organizationId
- customerId  nullable   ← nouveau
- serviceId   nullable
- code, name
- channel     nullable   ← devenu facultatif
- templateType: TemplateType  (13 valeurs, INVOICE ajouté)
- subjectTemplate nullable
- bodyTemplate, bodyFormat
- language, availableVariables
- isDefault, isActive
```

`customer_id` nul désigne le modèle global du transporteur ; renseigné, il
désigne un modèle propre à un client.

`channel` nul signifie **document**. `TemplateNature` refuse trois combinaisons :
une facture avec un canal, une facture avec un objet, un message sans canal.

## 5. Le moteur, étendu et jamais dupliqué

`TemplateRenderer` gagne deux capacités, toutes deux fermées :

- **chemins pointés** — `{{ invoice.invoiceNumber }}`, quatre segments au plus ;
- **sections** — `{{#invoice.lines}} … {{/invoice.lines}}`, une profondeur.

Dans une section, un champ de ligne s'écrit avec son **chemin complet** —
`{{ invoice.lines.description }}`. Une seule liste blanche gouverne alors tout
le modèle.

### Ce qui reste interdit

Aucune expression, aucun filtre, aucune condition, aucune boucle imbriquée,
aucune valeur non scalaire, aucun accès à un modèle Eloquent. Le remplacement
reste un `preg_replace_callback` sur une table close.

### Une donnée n'est jamais du modèle

Une section développée est mise de côté derrière un jeton, et remise en place
**après** la passe sur les chemins. Sans cela, une ligne dont la description
contient littéralement `{{ invoice.total }}` verrait ce texte résolu : la donnée
deviendrait du modèle, et un client pourrait faire écrire à sa propre facture ce
qu'il n'a pas le droit de lire.

### Deux modes de rendu

| Méthode | Variable non déclarée fournie | Employé par |
|---|---|---|
| `render()` | refusée | communications |
| `renderDocument()` | ignorée | factures |

La distinction est réelle : pour une communication, l'appelant compose son jeu
de variables en regardant le modèle, et un nom en trop signale une faute de
frappe. Pour une facture, le contexte est le même pour tous les modèles —
dix-neuf chemins plus les lignes — et un modèle qui en nomme trois est normal.

## 6. Immuabilité d'une facture close

Le §0.22 exige qu'une facture close reste identique après modification du
modèle ; le §0.23 interdit d'y répondre par une table `invoice_templates`.

`invoices` reçoit trois colonnes :

| Colonne | Rôle |
|---|---|
| `template_id` | référence d'audit — quel modèle a servi |
| `rendered_body` | **le document produit**, figé |
| `rendered_at` | quand |

`CloseInvoiceAction` résout, rend et fige, **dans la transaction** : c'est un
calcul de chaîne sur des données déjà chargées, pas un appel réseau. Le renvoyer
après le commit laisserait une facture close sans document le temps d'un
incident. Les envois, eux, restent mis en file après le commit — le §0.21
interdit un appel FTP ou REST dans la transaction.

Toute relecture d'une facture close sert `rendered_body` ; le modèle courant
n'est jamais rejoué.

## 7. Résolution

`ResolveTemplateAction` applique deux principes :

1. **du plus précis au plus général** — le modèle du client l'emporte sur le
   global ; à égalité de client, celui qui vise le service l'emporte ;
2. **jamais le modèle d'un tiers** — les candidats sont ceux du client demandé
   ou les globaux.

L'ordre est déterministe : à précision égale, `is_default` tranche, puis le code.

### Le cas non couvert par le §0.9

Le §0.9 ne dit rien du cas où **aucun** modèle `invoice` n'existe — celui de
toutes les organisations au jour de la migration.

`ResolveTemplateAction` retourne `null`, et `RenderInvoiceAction` retombe sur la
mise en page Blade livrée en Phase 8 (`resources/views/exports/invoice.blade.php`).
Une facture continue donc de se produire sans configuration préalable. Ce repli
est un défaut de mise en page, **jamais le modèle d'un autre client** : le §0.9
est respecté.

## 8. PDF et mappings

Le §0.26 sépare deux natures :

```text
PDF          → document, rendu depuis le Template INVOICE
JSON/XML/CSV → mapping, rendus depuis InvoiceExportData
```

`InvoicePdfFormatter` n'implémente donc plus `InvoiceFormatter` : ce contrat
transpose un DTO, et un PDF ne se produit pas à partir d'un DTO seul. Il expose
`fromDocument(RenderedInvoice, array $settings)`. Le déclarer et lever une
exception aurait laissé croire à l'appelant qu'il pouvait l'employer.

`ExportDispatcher` choisit explicitement entre les deux natures. Les valeurs
fixes du client (`staticValues`) s'ajoutent en pied de PDF ; le reste du
document appartient au modèle.

## 9. Ce qui n'a pas changé

- `CustomerExportConfiguration` et `ExportJob` restent le moteur d'envoi (§0.25) ;
- `CommunicationEventType` ne reçoit pas `INVOICE_CLOSED` (§0.28) ;
- `OrderCommunication` reste lié à `Order` ; aucun `InvoiceCommunication` ;
- `order_communications.status` reste textuel ; aucun `status_id`.

---

## 10. Déclenchement automatique — évolution du 1er septembre 2026

Le rapport backend Phase 9 déclarait « aucun déclenchement automatique des
règles » : les onze `CommunicationEventType` n'étaient émis nulle part. Une
règle « commande annulée » se configurait donc sans jamais rien produire.

**Trois événements sont désormais émis.** Les huit autres ne le sont toujours
pas, et c'est délibéré : le §2 interdit d'inventer leur sémantique — à partir de
quand un arrêt est-il « imminent » ? Les câbler au jugé produirait des messages
au mauvais moment, ce qui est pire que pas de message.

### Ce qui est émis

| Événement Laravel | Émis par | `CommunicationEventType` |
|---|---|---|
| `Orders\Events\OrderCreated` | `CreateFullOrder` | `ORDER_CREATED` |
| `Orders\Events\OrderStatusChanged` | `ChangeOrderStatus` | `ORDER_CONFIRMED`, `ORDER_CANCELLED` |

Un seul événement porte toutes les transitions, avec l'état de départ et celui
d'arrivée. Une classe par statut aurait multiplié dix fichiers identiques, et
obligé chaque nouvel état du référentiel à en créer un onzième.

Les Orders ne connaissent pas `CommunicationEventType` : la correspondance vit
dans le listener, côté Communications. Une commande change d'état ; ce que la
messagerie en fait la regarde seule.

### Après le commit, jamais pendant

Les deux événements sont émis **hors** de la transaction. Un abonné qui agirait
dedans enverrait un message que le rollback ne rattraperait pas : le client
recevrait l'annulation d'une commande qui ne l'est pas.

### L'enchaînement

```text
événement → règles de l'organisation → même eventType → active → automatique
          → service compatible → conditions vraies → communication créée
          → mise en file, ou programmée si la règle porte un délai
```

`ApplyCommunicationRules` porte cette logique. Elle n'est pas dans un contrôleur
— le §52 l'interdit — et se teste sans HTTP.

### Le contexte de variables

`OrderCommunicationContext` est l'équivalent d'`InvoiceRenderContext` pour une
commande : une **liste close, écrite à la main**. Quinze clés plates, en
minuscules — c'est ce que le motif des conditions accepte.

```text
order_number  order_status  order_type  order_source  order_date
external_reference  customer_reference  group_code  currency_code
weight  volume  package_count  customer_code  customer_name  agency_name
```

Les mêmes valeurs servent au rendu **et** aux conditions. Deux jeux distincts
auraient laissé configurer une condition sur un champ qu'aucun modèle ne peut
nommer, sans que rien ne le signale.

Le contexte est filtré sur ce que le modèle déclare avant le rendu : le moteur
refuse une valeur non déclarée, et lui passer les quinze ferait échouer tout
modèle n'en nommant qu'une partie — c'est-à-dire tous.

### Idempotence, sans nouvelle colonne

Le §53 interdit d'inventer un `eventId`. La clé retenue est le couple déjà
présent en base : `order_id` + `communication_rule_id`. Une règle ne produit
qu'un message par commande.

C'est exact pour les trois événements câblés, qui ne surviennent qu'une fois
dans la vie d'une commande. Un rejeu de file, un double-clic ou un incident ne
produit donc pas de doublon. Un événement récurrent — `TOUR_STOP_APPROACHING`,
par exemple — demanderait une autre clé ; c'est l'une des raisons de ne pas
l'avoir câblé.

### Un échec ne bloque rien

Une règle qui échoue — modèle irrésoluble, destinataire introuvable, canal sans
transporteur — est journalisée et **n'empêche ni les autres règles, ni
l'opération métier**. L'inverse rendrait l'annulation d'une commande tributaire
d'un texte : personne ne pourrait plus annuler tant que le modèle n'est pas
corrigé.

Le journal porte les identifiants et la raison, jamais le corps du message : le
§125 interdit d'y déverser un contenu personnel.

### Ce qui n'est toujours pas câblé

```text
SERVICE_PLANNED         APPOINTMENT_REQUESTED   APPOINTMENT_CONFIRMED
DRIVER_ASSIGNED         TOUR_STOP_APPROACHING   SERVICE_COMPLETED
POD_CREATED             CLAIM_CREATED
```

Les règles qui les visent s'enregistrent et restent sans effet. L'écran des
règles le dit.
