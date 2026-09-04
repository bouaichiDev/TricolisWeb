# Phase 9 — Décisions de base de données

Document exigé par le §40. MySQL 8 exclusivement : aucun `JSONB`, aucun `ILIKE`,
aucun index partiel, aucun `ENUM` SQL, aucune extension.

---

## 1. Ordre des migrations

```text
2026_08_07_100001_create_communication_templates_table
2026_08_07_100002_create_communication_rules_table
2026_08_07_100003_create_order_communications_table
2026_08_07_100004_create_communication_attachments_table
```

L'ordre est imposé par les clés étrangères : les règles pointent les modèles, les
communications pointent les deux, les pièces jointes pointent les
communications.

---

## 2. Nullabilité

### Ce que la cardinalité décide seule

| Colonne | Cardinalité PlantUML | Nullable |
|---|---|---|
| `communication_templates.organization_id` | `Organization "1"` | non |
| `communication_templates.service_id` | `Service "0..1"` | **oui** |
| `communication_rules.organization_id` | `Organization "1"` | non |
| `communication_rules.service_id` | `Service "0..1"` | **oui** |
| `communication_rules.template_id` | `CommunicationTemplate "1"` | non |
| `order_communications.organization_id` | `Organization "1"` | non |
| `order_communications.order_id` | `Order "1"` | non |
| `order_communications.template_id` | `CommunicationTemplate "0..1"` | **oui** |
| `order_communications.communication_rule_id` | `CommunicationRule "0..1"` | **oui** |
| `order_communications.created_by` | `User "0..1"` | **oui** |
| `communication_attachments.communication_id` | `"1" *--` | non |
| `communication_attachments.document_id` | `Document "1"` | non |

### Ce que la cardinalité ne dit pas

**`subject_template` et `subject` sont nullables.** Un SMS n'a pas d'objet, et
le §11 interdit d'exiger `subjectTemplate` pour SMS ou WhatsApp. La contrainte
est portée par la validation, pour le seul canal `EMAIL`.

**`body_template` et `body` sont NOT NULL.** Un message sans corps n'a rien à
transmettre, quel que soit le canal.

**`recipient_email` et `recipient_phone` sont tous deux nullables**, et rendus
obligatoires par la validation **selon le canal** :

| Canal | Champ exigé |
|---|---|
| `EMAIL` | `recipientEmail` |
| `SMS`, `WHATSAPP` | `recipientPhone` |
| `PUSH_NOTIFICATION`, `INTERNAL_NOTIFICATION` | aucun |

Le §20 l'ordonne dans les deux sens. Une contrainte SQL ne peut exprimer cette
conditionnalité qu'avec un `CHECK` — non portable, et invisible pour l'appelant
qui recevrait une erreur SQL au lieu d'un 422 nommant le champ.

**`recipient_name` est NOT NULL.** Toute résolution produit un nom ; le rôle
`CUSTOM` l'exige explicitement.

**Les six horodatages d'état sont nullables, sans valeur par défaut.**
`scheduled_at`, `queued_at`, `sent_at`, `delivered_at`, `read_at`, `failed_at`
ne sont posés que par la transition qui les produit. Une date par défaut
affirmerait un événement qui n'a pas eu lieu.

**`provider_message_id`, `provider_response`, `error_message` sont nullables** :
ils n'existent qu'après réponse du transporteur.

**`available_variables` et `conditions` sont nullables.** `null` signifie
« aucune variable déclarée » et « règle inconditionnelle » — deux états
distincts d'une liste vide, qui signifierait une déclaration explicitement vide.

---

## 3. Stratégies de suppression

| Clé étrangère | Choix | Raison |
|---|---|---|
| `communication_templates.organization_id` | RESTRICT | convention de toutes les phases |
| `communication_templates.service_id` | RESTRICT | l'association `--` n'est pas une composition ; supprimer un service ne doit pas élargir silencieusement le périmètre d'un modèle |
| `communication_rules.organization_id` | RESTRICT | idem |
| `communication_rules.service_id` | RESTRICT | idem |
| `communication_rules.template_id` | RESTRICT | doublé d'un refus métier en 409 |
| `order_communications.organization_id` | RESTRICT | idem |
| `order_communications.order_id` | RESTRICT | une communication documente une commande ; celle-ci ne peut pas disparaître sous elle |
| `order_communications.template_id` | **SET NULL** | le contenu est figé dans `subject`/`body` : perdre le lien ne perd aucune information |
| `order_communications.communication_rule_id` | **SET NULL** | idem |
| `order_communications.created_by` | **SET NULL** | convention des Phases 3 à 8 pour `created_by` |
| `communication_attachments.communication_id` | **CASCADE** | `*--` : composition stricte du diagramme |
| `communication_attachments.document_id` | RESTRICT | la pièce jointe référence le document, elle ne le possède pas |

`SET NULL` sur `template_id` et `communication_rule_id` ne contredit pas le refus
métier : celui-ci arrive **avant**, à la suppression par l'API. Le `SET NULL` ne
protège que d'une suppression faite hors application.

### Refus métier, en 409

| Ressource | Condition du refus |
|---|---|
| `CommunicationTemplate` | référencé par une règle **ou** par une communication |
| `CommunicationRule` | référencée par une communication |
| `OrderCommunication` | statut autre que `DRAFT` |
| `CommunicationAttachment` | communication au-delà de `DRAFT`/`SCHEDULED` |

Le §42 l'impose : « Ne pas supprimer les communications historiques » ; « Pour
DRAFT, suppression possible selon permission ».

Une communication `SCHEDULED` n'est **pas** supprimable : l'annulation est le
geste prévu, et elle laisse une trace.

---

## 4. Index

Chaque colonne servant de filtre, de tri ou de clé étrangère porte un index.
Aucun index sur les `TEXT`, `LONGTEXT` ou `JSON` : ils sont cherchables par
`LIKE`, mais un index MySQL y imposerait une longueur de préfixe arbitraire pour
un gain nul sur `%terme%`.

| Table | Index |
|---|---|
| `communication_templates` | `organization_id`, `service_id`, `channel`, `template_type`, `language`, `is_default`, `is_active`, `created_at` |
| `communication_rules` | `organization_id`, `service_id`, `template_id`, `event_type`, `recipient_role`, `is_automatic`, `is_active`, `created_at` |
| `order_communications` | `organization_id`, `order_id`, `template_id`, `communication_rule_id`, `channel`, `communication_type`, `recipient_role`, `status`, les six horodatages, `provider_message_id`, `created_by`, `created_at` |
| `communication_attachments` | `communication_id`, `document_id`, `created_at` |

### Contraintes uniques

```text
communication_templates    UNIQUE (organization_id, code)
communication_attachments  UNIQUE (communication_id, document_id)
```

La première suit le §41 : un code identifie un modèle **dans une organisation**,
pas dans le monde. Deux transporteurs peuvent nommer le leur
`delivery-confirmation`.

La seconde n'est pas dans le §41 mais découle du §30 (« éviter le même Document
deux fois sur la même Communication ») : deux pièces identiques arriveraient
chez le destinataire.

---

## 5. Types MySQL

| Type PlantUML | Type MySQL | Motif |
|---|---|---|
| ULID | `CHAR(26)` | convention de toutes les phases |
| enum métier | `VARCHAR(16..32)` | un `ENUM` SQL impose un `ALTER TABLE` pour toute valeur ajoutée |
| text | `TEXT` | objets et messages d'erreur |
| longtext | `LONGTEXT` | corps de message, sans limite pratique |
| JSON | `JSON` | type natif MySQL 8 — **jamais `JSONB`**, qui est PostgreSQL |
| boolean | `TINYINT(1)` | avec valeur par défaut explicite |
| int | `INT` | `delay_value`, signé : la validation borne à `[0, 100000]` |
| datetime | `TIMESTAMP` | convention du projet |

`delay_value` est **signé** alors que la validation refuse le négatif : la
colonne reste tolérante, la règle métier est portée là où elle produit un
message lisible. C'est l'inverse du choix fait en Phase 4 pour `sequence`, où
l'`UNSIGNED` était nécessaire au réordonnancement.

---

## 6. JSON — trois colonnes, trois schémas documentés

Aucune des trois n'est laissée libre : le §12 et le §16 imposent de valider la
structure, et de ne pas en inventer une complexe.

**`communication_templates.available_variables`** — liste plate de noms :

```json
["order_number", "customer_name"]
```

Motif de nom : `^[a-zA-Z][a-zA-Z0-9_]{0,63}$`, 100 entrées au maximum, sans
doublon. Le motif exclut points, parenthèses, `$` et espaces : un nom ne peut
donc jamais désigner une méthode ou une propriété chaînée.

**`communication_rules.conditions`** — conjonction plate :

```json
{"all": [{"field": "order_status", "operator": "eq", "value": "confirmed"}]}
```

Une seule clé racine, huit opérateurs clos, 20 conditions au maximum, aucune
imbrication.

**`order_communications.template_variables`** et **`provider_response`** —
snapshots. Le premier fige les valeurs employées au rendu ; le second conserve
la réponse du transporteur, filtrée par liste blanche avant restitution.

---

## 7. Snapshots

Six colonnes sont des snapshots, et c'est la raison d'être de la table
`order_communications` :

```text
subject             body               template_variables
recipient_name      recipient_email    recipient_phone
```

Plus deux sur les pièces jointes :

```text
file_name_snapshot  mime_type_snapshot
```

Modifier un `CommunicationTemplate`, une `CommunicationRule`, un `Contact` ou un
`Document` **ne touche aucune de ces colonnes**. Un test le vérifie pour le
modèle et pour le document.

C'est le même raisonnement qu'en Phase 3 pour `OrderServiceContact` et qu'en
Phase 6 pour `InvoiceLineAddressSnapshot` : une trace doit rester lisible telle
qu'elle a été produite.

---

## 8. Tables volontairement absentes

```text
communication_recipients      notifications            notification_templates
notification_preferences      email_logs               sms_logs
whatsapp_logs                 push_notification_logs   internal_notifications
communication_queues          communication_providers  communication_status_histories
communication_deliveries      webhooks                 webhook_deliveries
message_threads               conversations            messages
scheduled_communications      export_templates
```

Aucune n'est créée. Un test le vérifie table par table.

Les destinataires sont portés par `OrderCommunication` (§22), la file par la
table `jobs` de Laravel (§25), l'historique par `audit_logs` (§39), et les
notifications internes par `order_communications` elle-même (§26).

---

## 9. Évolution du 1er septembre 2026 — modèles unifiés

Quatre migrations, toutes non destructives. Le détail est dans
`docs/backend/phase-9-template-unification.md` ; voici les décisions de schéma.

### `communication_templates` devient `templates`

Un **renommage**, pas une recréation. `RENAME TABLE` conserve les identifiants,
et MySQL réoriente de lui-même les clés étrangères qui la visaient :
`communication_rules.template_id` et `order_communications.template_id`
continuent de désigner les mêmes lignes. Aucune communication historique n'est
perdue, aucun doublon n'est créé.

Les migrations d'origine (`2026_08_07_100001`, `2026_08_25_100000`) ne sont
**pas** réécrites : elles décrivent l'état d'où l'on part, et les modifier
casserait une installation neuve, où le renommage s'exécute après elles.

### `templates.customer_id`, nullable, RESTRICT

Nul, le modèle vaut pour toute l'organisation ; renseigné, il ne vaut que pour
ce client. C'est ce qui permet le repli « client → global » sans jamais servir
le modèle d'un tiers.

RESTRICT et non CASCADE : supprimer un client ne doit pas effacer en silence une
mise en page dont d'autres factures dépendent.

### `templates.channel` devient nullable

Une facture est un document, pas un message : elle n'a ni canal, ni objet, ni
destinataire. Lui inventer `channel = email` mentirait sur sa nature et la
ferait apparaître dans les sélecteurs de messagerie — ce que le §0.7 interdit
nommément.

L'unicité `(organization_id, code)` est inchangée : elle suffit, les codes des
modèles de facture étant distincts (`INVOICE_DEFAULT`, `INVOICE_IKEA`).

### `invoices.template_id`, `rendered_body`, `rendered_at`

Trois colonnes sur une table existante — la « structure minimale validée » du
§0.23, point 3.

`rendered_body` fige **le document produit** à la clôture, pas une copie du
modèle : le §0.23 interdit d'y répondre par une seconde table de modèles.
`template_id` ne sert qu'à l'audit et reste en RESTRICT ; le document, lui, ne
dépend plus de lui une fois figé.

Les trois sont nulles pour un brouillon : il se rend à la demande, depuis le
modèle du moment, et c'est bien ce qu'un aperçu doit montrer.

### `audit_logs.entity_type` et `permissions.code`

Deux mises à jour de chaînes. L'alias polymorphe passe de
`communication_template` à `template` ; les quatre permissions de
`communication_templates.*` à `templates.*`, **module compris**.

Les rôles sont préservés : `role_permissions` pointe sur l'identifiant, que rien
ne touche. Un administrateur qui pouvait éditer les modèles hier le peut encore,
et sur les modèles de facture par la même permission.

### Tables toujours absentes

Aux vingt de la section 8 s'ajoutent celles que l'évolution aurait pu faire
naître, et qui n'existent pas :

```text
invoice_templates    customer_invoice_templates    invoice_template_lines
email_templates      sms_templates                 whatsapp_templates
document_templates
```

Un test les vérifie table par table.
