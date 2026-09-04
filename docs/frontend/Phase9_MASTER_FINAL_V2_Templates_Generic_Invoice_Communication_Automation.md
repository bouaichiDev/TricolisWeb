# Tricolis V2 — FRONTEND PHASE 9 MASTER FINAL V2

## Templates génériques + Communication Rules & Automatisation + Templates de factures

### Templates + règles événementielles + communications automatiques/manuelles + programmation + retry + pièces jointes + suivi d’envoi + statuses centralisés

> **Ce fichier est la source unique de travail pour la Frontend Phase 9.**
>
> Les Frontend Phases 1 à 8 sont terminées ou validées.
>
> La Phase 3 a déjà introduit côté frontend :
>
> ```text
> CommunicationTemplate
> OrderCommunication
> CommunicationAttachment
> ```
>
> avec la communication manuelle depuis une commande.
>
> **La Phase 9 ne doit pas recréer cette fonctionnalité.**
>
> Elle doit :
>
> 1. auditer ce qui existe réellement depuis la Phase 3 ;
> 2. le consolider si le backend final Phase 9 a évolué ;
> 3. ajouter `CommunicationRule` ;
> 4. ajouter l’automatisation basée sur `CommunicationEventType` ;
> 5. fournir la gestion complète des règles, templates, communications planifiées/échouées et historique d’envoi ;
> 6. conserver toutes les communications existantes et leurs snapshots.

---

# 0. ÉVOLUTION PHASE 9 — TEMPLATE UNIQUE POUR TOUTE LA PLATEFORME

Cette Phase 9 contient explicitement les modifications nécessaires aux Phases précédentes.

**Ne pas modifier séparément les anciens prompts Phase 3 / Phase 6 pour créer une autre source de vérité.**
La correction est réalisée ici, dans la Phase 9, puis appliquée au code existant.

---

## 0.1 Décision métier obligatoire

Il doit exister **une seule table de templates** pour toute la plateforme.

Le même référentiel de templates doit gérer :

```text
communications EMAIL
communications SMS
communications WHATSAPP
PUSH_NOTIFICATION
INTERNAL_NOTIFICATION
rendez-vous
confirmation livraison
POD
annulation
facture
custom
```

Interdiction de créer en parallèle :

```text
communication_templates
invoice_templates
email_templates
sms_templates
whatsapp_templates
document_templates
```

Architecture cible :

```text
Template
↓
table unique : templates
```

---

## 0.2 Modification du diagramme officiel dans la Phase 9

Le diagramme Phase 9 actuel contient encore `CommunicationTemplate`.
Cette demande constitue une **évolution explicite de conception**.

Avant migration, mettre à jour :

```text
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

Remplacer :

```text
CommunicationTemplate
```

par :

```text
Template
```

et documenter le changement dans :

```text
docs/backend/phase-9-template-unification.md
docs/frontend/phase-9-analysis.md
```

---

## 0.3 Migration non destructive

Si la table existe déjà :

```text
communication_templates
```

ne pas supprimer/recréer les données.

Effectuer une migration contrôlée :

```text
communication_templates
        ↓ rename
templates
```

Conserver tous les IDs existants afin que :

```text
communication_rules.template_id
order_communications.template_id
```

continuent à référencer les mêmes templates.

Aucune communication historique ne doit être perdue.

---

## 0.4 Modèle générique Template final

Le modèle final devient :

```text
Template
- id: ULID
- organizationId: ULID
- customerId: ULID nullable
- serviceId: ULID nullable
- code: string
- name: string
- channel: CommunicationChannel nullable
- templateType: TemplateType
- subjectTemplate: text nullable
- bodyTemplate: longtext
- language: string
- availableVariables: JSON
- isDefault: boolean
- isActive: boolean
- createdAt: datetime
- updatedAt: datetime
```

Table :

```text
templates
```

Nouveau champ obligatoire pour l'évolution :

```text
customer_id nullable
```

Rendre également :

```text
channel nullable
subject_template nullable
```

car un template de facture est un document et non un message.

---

## 0.5 Relations finales

Mettre à jour le diagramme :

```text
Organization "1" -- "0..*" Template
Customer "0..1" -- "0..*" Template
Service "0..1" -- "0..*" Template

Template "1" -- "0..*" CommunicationRule
Template "0..1" -- "0..*" OrderCommunication
```

Ne pas créer :

```text
InvoiceTemplate
CustomerInvoiceTemplate
InvoiceTemplateLine
```

---

## 0.6 TemplateType générique

Renommer :

```text
CommunicationTemplateType
```

en :

```text
TemplateType
```

Valeurs finales :

```text
APPOINTMENT_REQUEST
APPOINTMENT_CONFIRMATION
APPOINTMENT_REMINDER
DRIVER_ASSIGNED
DRIVER_DEPARTED
ARRIVAL_ESTIMATE
ARRIVAL_SOON
DELIVERY_CONFIRMATION
DELIVERY_FAILED
POD_AVAILABLE
ORDER_CANCELLED
INVOICE
CUSTOM
```

`INVOICE` est ajouté explicitement par cette évolution Phase 9.

Ne pas créer un deuxième enum :

```text
InvoiceTemplateType
```

---

## 0.7 Template communication vs template facture

### Communication

Exemple :

```text
templateType = DELIVERY_CONFIRMATION
channel = EMAIL
subjectTemplate = ...
bodyTemplate = ...
```

### Facture

```text
templateType = INVOICE
channel = null
serviceId = null
subjectTemplate = null
bodyTemplate = HTML/document facture
```

Ne jamais mettre artificiellement :

```text
channel = EMAIL
```

sur le document facture.

---

## 0.8 Templates GLOBAL et spécifiques Customer

Le champ `customerId` permet :

```text
customerId = null
→ template GLOBAL du transporteur

customerId = CUSTOMER_ID
→ template spécifique au Customer
```

Exemple :

```text
INVOICE_DEFAULT
customerId = null
templateType = INVOICE
isDefault = true

INVOICE_IKEA
customerId = IKEA
templateType = INVOICE
isDefault = true

INVOICE_QOQA
customerId = QOQA
templateType = INVOICE
isDefault = true
```

---

## 0.9 Fallback obligatoire Customer → GLOBAL pour les factures

Pour une facture :

```text
Invoice
↓
Customer
↓
chercher Template INVOICE spécifique Customer
```

Si trouvé :

```text
utiliser le template Customer
```

Sinon :

```text
utiliser le template GLOBAL
```

Donc :

```text
Template INVOICE Customer
        ↓ absent
Template INVOICE GLOBAL
```

Ne jamais utiliser le template d'un autre Customer.

---

## 0.10 ResolveTemplateAction

Créer/réutiliser une seule logique de résolution :

```text
ResolveTemplateAction
```

Entrées conceptuelles :

```text
organizationId
customerId nullable
serviceId nullable
templateType
channel nullable
language
```

Pour `INVOICE` :

```text
Customer exact
→ GLOBAL
```

Pour les communications avec Service, si la personnalisation Customer est activée :

```text
1. Customer + Service
2. Customer générique
3. Global + Service
4. Global générique
```

Le comportement exact doit être documenté et déterministe.

---

## 0.11 Un seul renderer

Renommer/refactorer :

```text
CommunicationTemplateRenderer
```

vers :

```text
TemplateRenderer
```

Ce renderer sert :

```text
communications
+
factures
```

Interdit :

```text
CommunicationTemplateRenderer
+
InvoiceTemplateRenderer
```

comme deux moteurs concurrents.

---

## 0.12 Sécurité TemplateRenderer

Toujours interdire :

```text
eval
PHP
SQL
JavaScript libre
méthodes arbitraires
accès direct libre aux Models
fonctions utilisateurs arbitraires
```

Le renderer reçoit uniquement un DTO/contexte whitelisté.

---

## 0.13 Variables facture

Pour :

```text
templateType = INVOICE
```

le contexte doit être construit depuis les données validées :

```text
Invoice
InvoiceLine[]
InvoiceLineAddressSnapshot[]
Customer
Organization
```

Variables possibles selon DTO final :

```text
invoice.invoiceNumber
invoice.invoiceDate
invoice.periodFrom
invoice.periodTo
invoice.currencyCode
invoice.subtotal
invoice.taxTotal
invoice.total
invoice.externalReference
invoice.remark

customer.*
organization.*
invoice.lines[]
```

Ne pas donner au template l'objet Eloquent complet.

---

## 0.14 Boucle des lignes facture

Une facture contient plusieurs lignes.
Le renderer unique doit supporter une itération **contrôlée** sur les lignes.

Si le moteur actuel ne le permet pas, l'étendre avec une syntaxe de section limitée, par exemple conceptuellement :

```text
{{#invoice.lines}}
  ...
{{/invoice.lines}}
```

La syntaxe définitive doit être unique, documentée et testée.

Interdit :

```text
foreach PHP
for JavaScript
expression libre
```

---

## 0.15 Une seule UI Templates

Créer/refactorer une seule page :

```text
/templates
```

Filtres :

```text
Customer
Service
TemplateType
Channel
Language
Default
Active
```

Ne pas créer deux CRUD :

```text
/communications/templates
/billing/invoice-templates
```

Le menu peut présenter deux accès ergonomiques vers **le même écran** :

```text
Communication
→ Templates
→ /templates?category=communication

Facturation
→ Templates de facture
→ /templates?templateType=INVOICE
```

Même API.
Même Model.
Même table.
Même composants.

---

# 0.16 MODIFICATION DE LA PHASE 3

La Phase 3 utilisait la notion `CommunicationTemplate`.
Dans cette Phase 9, appliquer le refactor :

```text
CommunicationTemplate → Template
communication_templates → templates
CommunicationTemplateType → TemplateType
CommunicationTemplateRenderer → TemplateRenderer
```

Les fonctionnalités Phase 3 restent :

```text
communication manuelle
preview
destinataire
attachments
historique
retry/cancel selon backend
```

Ne pas recréer ces écrans.

Tester toutes les non-régressions.

---

# 0.17 MODIFICATION DE LA PHASE 6 — FACTURATION

**Toutes les modifications de la Phase 6 liées aux templates sont incluses ici, dans la Phase 9.**

Ne pas créer un nouveau fichier Phase 6 comme deuxième source de vérité.

La Phase 9 doit modifier le code de facturation déjà développé pour ajouter :

```text
Templates de facture
Template GLOBAL
Template spécifique Customer
Fallback Customer → GLOBAL
Preview facture
Génération document/PDF depuis Template
Immutabilité du rendu après clôture
```

---

## 0.18 Phase 6 — menu Facturation

Le menu final doit inclure :

```text
Facturation
├── Préfacturation
├── Factures clients
├── Tarification
├── Templates de facture
├── Décomptes fournisseurs
└── Exports / Envois
```

`Templates de facture` ouvre :

```text
/templates?templateType=INVOICE
```

Il ne possède pas son propre CRUD.

---

## 0.19 Phase 6 — Customer Detail

Dans :

```text
/customers/:id
```

Partie Facturation, afficher :

```text
Template de facture
```

Cas A :

```text
Template spécifique : INVOICE_IKEA
```

Cas B :

```text
Aucun template spécifique
→ le template global sera utilisé
```

Action :

```text
Créer un template spécifique
```

crée une ligne dans :

```text
templates
```

avec :

```text
customerId = Customer courant
templateType = INVOICE
channel = null
serviceId = null
```

---

## 0.20 Phase 6 — Preview Invoice

Dans Invoice DRAFT, ajouter :

```text
Prévisualiser la facture
```

Workflow :

```text
Invoice
→ ResolveTemplateAction
→ TemplateRenderer
→ HTML sécurisé
→ PDF preview si moteur PDF disponible
```

Afficher :

```text
Template utilisé
Scope CUSTOMER / GLOBAL
```

Ne pas rendre la facture avec un moteur JavaScript différent.

---

## 0.21 Phase 6 — clôture Invoice

Lors de :

```text
DRAFT → CLOSED
```

le système doit résoudre le template de facture avant de produire le document final.

Workflow :

```text
BEGIN
lock Invoice
valider Invoice
résoudre Template INVOICE
valider le contexte de rendu
passer Invoice à CLOSED
AuditLog
COMMIT

AFTER COMMIT
→ générer le rendu final nécessaire
→ déclencher les ExportJob configurés
```

Ne jamais exécuter un appel FTP/SFTP/REST dans la transaction DB.

---

## 0.22 Immutabilité de la facture CLOSED

Une facture clôturée doit rester visuellement identique.

Exemple :

```text
01/09 : Invoice CLOSED avec Template version A
05/09 : Template modifié vers version B
```

Résultat obligatoire :

```text
ancienne Invoice CLOSED
→ toujours document version A
```

Ne jamais re-rendre une ancienne facture CLOSED avec le template actuel.

Le snapshot est le **résultat rendu/document généré**, pas une deuxième table de templates.

---

## 0.23 Stockage du rendu final Invoice

Réutiliser en priorité le mécanisme réel déjà présent pour :

```text
PDF facture
Document
ExportJob/storage sécurisé
```

Si le backend actuel ne possède aucun mécanisme permettant de conserver le document final d'une facture CLOSED :

1. documenter le gap ;
2. mettre à jour le diagramme ;
3. ajouter uniquement la structure minimale validée.

Ne jamais résoudre ce problème en créant `invoice_templates`.

---

## 0.24 Référence au Template utilisé

Si l'audit exige de connaître le Template utilisé par l'Invoice, envisager :

```text
Invoice.templateId nullable
```

mais uniquement si :

1. le diagramme est mis à jour dans cette Phase 9 ;
2. la migration est explicitement documentée ;
3. le document final reste figé indépendamment de la modification du Template.

Ne pas ajouter ce champ silencieusement.

---

## 0.25 Phase 6/8 — ExportJob reste le moteur d'envoi

Ne pas remplacer :

```text
CustomerExportConfiguration
ExportJob
```

par le moteur Template.

Responsabilités :

```text
Template
→ présentation/rendu

ExportJob
→ génération/transport externe
```

Conserver :

```text
Invoice CLOSED
→ CustomerExportConfiguration
→ ExportJob
→ REST_API / FTP / SFTP / EMAIL / MANUAL
```

---

## 0.26 PDF vs JSON/XML

Règle importante :

```text
PDF
→ utiliser Template INVOICE
```

Mais :

```text
JSON
XML
→ utiliser les DTO/mappings d'export Phase 6/8
```

Ne jamais convertir le HTML du template Invoice en JSON/XML.

```text
Template INVOICE ≠ Export Mapping
```

---

## 0.27 Email accompagnant une facture

Ne pas confondre :

```text
Template INVOICE
→ document facture
```

et :

```text
Template EMAIL
→ texte du mail accompagnant la facture
```

Les deux sont stockés dans la **même table `templates`** mais correspondent à deux lignes différentes.

Exemple :

```text
INVOICE_IKEA
customerId = IKEA
templateType = INVOICE
channel = null

INVOICE_EMAIL_IKEA
customerId = IKEA
templateType = CUSTOM
channel = EMAIL
```

Ne pas ajouter `INVOICE_AVAILABLE` sans nouvelle validation de conception.

---

## 0.28 Pas de INVOICE_CLOSED dans CommunicationEventType pour l'instant

Le modèle actuel `OrderCommunication` est lié à `Order`, pas à `Invoice`, et `CommunicationEventType` ne contient pas `INVOICE_CLOSED`.

Donc ne pas inventer dans cette Phase 9 :

```text
INVOICE_CLOSED event
InvoiceCommunication
```

L'envoi externe des factures continue d'être géré par Phase 6/8 :

```text
CustomerExportConfiguration
ExportJob
```

Si un véritable message automatisé lié directement à Invoice est demandé plus tard, faire évoluer explicitement le modèle.

---

# 0.29 MODIFICATION DE LA PHASE 8

La Phase 8 reste compatible.

Réutiliser :

```text
CustomerExportConfiguration
ExportJob
```

Règles :

```text
PDF Invoice
→ rendu depuis Template INVOICE

JSON/XML Invoice
→ mapping ExportJob existant
```

Ne pas créer de moteur d'export parallèle.

---

# 0.30 API générique Templates

La cible finale devient :

```text
GET    /api/v1/templates
POST   /api/v1/templates
GET    /api/v1/templates/{template}
PATCH  /api/v1/templates/{template}
DELETE /api/v1/templates/{template}
```

Filtres :

```text
organizationId
customerId
serviceId
templateType
channel
language
isDefault
isActive
```

Recherche :

```text
code
name
subject_template
body_template
```

---

## 0.31 Permissions génériques

Remplacer les permissions spécifiques :

```text
communication_templates.*
```

par :

```text
templates.view
templates.create
templates.update
templates.delete
```

Pour les templates INVOICE, combiner si nécessaire avec la permission Billing correspondante dans Policy/UI.

Ne pas créer :

```text
invoice_templates.*
```

---

## 0.32 Tests obligatoires migration Template

Tester :

```text
anciens templates conservés
IDs inchangés
CommunicationRule.templateId intact
OrderCommunication.templateId intact
aucune communication historique perdue
aucun duplicate créé
```

---

## 0.33 Tests obligatoires Invoice Template

Tester :

```text
création INVOICE GLOBAL
création INVOICE Customer
channel null
serviceId null
subjectTemplate null
bodyTemplate obligatoire
variables Invoice
boucle InvoiceLine sécurisée
preview
fallback Customer → GLOBAL
cross Customer refusé
cross Organization refusé
```

---

## 0.34 Test fallback

Given :

```text
GLOBAL INVOICE = T-GLOBAL
IKEA sans template spécifique
```

Invoice IKEA utilise :

```text
T-GLOBAL
```

Puis créer :

```text
IKEA INVOICE = T-IKEA
```

Invoice IKEA utilise :

```text
T-IKEA
```

Un autre Customer continue à utiliser :

```text
T-GLOBAL
```

---

## 0.35 Test immutabilité CLOSED

```text
Invoice CLOSED
→ rendu A

modifier Template
→ rendu futur B

re-télécharger ancienne Invoice
→ A
```

Jamais B.

---

## 0.36 Test ExportJob facture

```text
Invoice CLOSED
+ export PDF
→ PDF généré avec Template INVOICE résolu

Invoice CLOSED
+ export JSON
→ payload JSON mapping Phase 6/8
```

---

## 0.37 Documentation finale — modifications des phases précédentes

Dans :

```text
docs/frontend/phase-9-final-report.md
```

ajouter obligatoirement :

```text
MODIFICATIONS APPLIQUÉES AUX PHASES PRÉCÉDENTES
```

avec :

```text
Phase 3
- CommunicationTemplate → Template
- migration API/UI
- communications historiques conservées

Phase 6
- template facture dans table générique
- Customer override
- fallback GLOBAL
- preview facture
- PDF/document final immuable après CLOSED
- ExportJob conservé

Phase 8
- moteur ExportJob conservé
- PDF utilise Template INVOICE
- JSON/XML utilisent mappings existants
```

---

## 0.38 Priorité de cette section

Cette section 0 est une **évolution explicitement validée** et a priorité sur les parties historiques plus bas dans ce fichier.

Donc toute ancienne mention de :

```text
CommunicationTemplate
communication_templates
CommunicationTemplateType
CommunicationTemplateRenderer
communication_templates.*
```

est à interpréter/remplacer par :

```text
Template
templates
TemplateType
TemplateRenderer
templates.*
```

et `TemplateType` contient désormais :

```text
INVOICE
```

---

# 1. Mission

Tu es un architecte frontend/backend senior spécialisé en :

- React ;
- TypeScript ;
- Vite ;
- TanStack Query ;
- React Hook Form ;
- Zod ;
- Laravel ;
- MySQL 8 ;
- Events / Listeners ;
- Laravel Queue ;
- Scheduler ;
- email ;
- SMS ;
- WhatsApp ;
- push notifications ;
- notifications internes ;
- templates sécurisés ;
- règles événementielles ;
- idempotence ;
- retry ;
- sécurité multi-organisation ;
- audit.

Tu travailles sur **Tricolis V2**.

Ta mission est d’implémenter :

# FRONTEND PHASE 9 — COMMUNICATION RULES & AUTOMATISATION

---

# 2. Sources de vérité obligatoires

Utiliser dans cet ordre :

```text
1. Schéma DB réellement validé
2. Backend Phase 9 réellement implémenté
3. Conception/diagramme/00-diagramme-classes-partagees.puml
4. Conception/diagramme/01-diagramme-plateforme-interne.puml
5. Frontend Phase 3 réellement livré
6. Documentation Phase 9
7. Anciennes documentations / legacy
```

Avant de coder, analyser :

```text
database/migrations/
app/Modules/Communications/
app/Modules/Orders/
app/Modules/Planning/
app/Modules/Tracking/
app/Modules/Claims/
app/Modules/Documents/
Models
Actions
DTOs
Events
Listeners
Jobs
Services
Form Requests
API Resources
Policies
PermissionSeeder
routes/
tests/
frontend/src/
docs/
```

Ne pas supposer qu’une fonctionnalité Phase 3 est identique au backend Phase 9 final.

---

# 3. Scope métier exact

Classes autorisées :

```text
CommunicationTemplate
CommunicationRule
OrderCommunication
CommunicationAttachment
```

Enums exacts :

```text
CommunicationChannel
CommunicationTemplateType
CommunicationEventType
CommunicationStatus
RecipientRole
```

Réutiliser :

```text
Organization
Service
Order
OrderService
Tour
TourStop
ProofOfDelivery
Claim
Document
User
AuditLog
statuses
```

uniquement via les relations/événements réellement exposés.

---

# 4. Hors scope strict

Ne pas créer :

```text
CommunicationRecipient
Notification
NotificationTemplate
NotificationPreference

EmailLog
SmsLog
WhatsappLog
PushNotificationLog

InternalNotification
CommunicationQueue
CommunicationProvider
CommunicationProviderConfiguration
CommunicationStatusHistory

MessageThread
Conversation
Message
Webhook
WebhookDelivery
ScheduledCommunication
CommunicationSchedule
CommunicationBatch
CommunicationCampaign
CommunicationHistory
```

Ne pas créer une table par canal.

---

# 5. Continuité avec Phase 3

La Phase 3 a déjà couvert conceptuellement :

```text
Order
→ Communications
→ Nouveau message
→ choisir Template
→ destinataire
→ preview
→ Document attachment
→ envoyer/programmer
→ historique
```

La Phase 9 doit réutiliser ce flow.

Ne pas créer un deuxième :

```text
OrderCommunicationFormV2
```

ou un deuxième module parallèle si le premier peut être refactoré.

---

# 6. Nouveauté principale Phase 9

La vraie nouveauté est :

```text
CommunicationRule
```

qui relie :

```text
Event
+ Service optionnel
+ Template
+ RecipientRole
+ Delay
+ Conditions
+ Automatic
```

pour produire automatiquement des :

```text
OrderCommunication
```

---

# 7. Architecture fonctionnelle

```text
Événement métier
    ↓
CommunicationEventType
    ↓
chercher CommunicationRule actives
    ↓
évaluer Service + conditions
    ↓
isAutomatic ?
    ↓ oui
résoudre destinataire
    ↓
rendre Template
    ↓
créer OrderCommunication snapshot
    ↓
DRAFT / SCHEDULED / QUEUED selon règle
    ↓
Queue
    ↓
Sender par channel
    ↓
SENT / FAILED / DELIVERED / READ
```

Le frontend configure et observe.

**Le frontend ne déclenche jamais lui-même l’automatisation métier.**

---

# 8. Enum exact — CommunicationChannel

Utiliser exactement :

```text
EMAIL
SMS
WHATSAPP
PUSH_NOTIFICATION
INTERNAL_NOTIFICATION
```

Ne pas ajouter :

```text
TELEGRAM
SLACK
VOICE
FAX
WEBHOOK
```

---

# 9. Enum exact — CommunicationTemplateType

Utiliser exactement :

```text
APPOINTMENT_REQUEST
APPOINTMENT_CONFIRMATION
APPOINTMENT_REMINDER
DRIVER_ASSIGNED
DRIVER_DEPARTED
ARRIVAL_ESTIMATE
ARRIVAL_SOON
DELIVERY_CONFIRMATION
DELIVERY_FAILED
POD_AVAILABLE
ORDER_CANCELLED
CUSTOM
```

Ne pas ajouter d’autres valeurs.

---

# 10. Enum exact — CommunicationEventType

Utiliser exactement :

```text
ORDER_CREATED
ORDER_CONFIRMED
ORDER_CANCELLED
SERVICE_PLANNED
APPOINTMENT_REQUESTED
APPOINTMENT_CONFIRMED
DRIVER_ASSIGNED
TOUR_STOP_APPROACHING
SERVICE_COMPLETED
POD_CREATED
CLAIM_CREATED
```

Ne pas inventer :

```text
INVOICE_CLOSED
STOCK_CHANGED
TOUR_CREATED
DRIVER_ARRIVED
...
```

sans évolution du diagramme.

---

# 11. Enum exact — CommunicationStatus

Utiliser exactement :

```text
DRAFT
SCHEDULED
QUEUED
SENDING
SENT
DELIVERED
READ
FAILED
CANCELLED
```

Ne pas ajouter :

```text
CREATED
PROCESSING
RETRYING
EXPIRED
ARCHIVED
```

---

# 12. Enum exact — RecipientRole

Utiliser exactement :

```text
CUSTOMER
LOAD_CONTACT
DELIVERY_CONTACT
BILLING_CONTACT
INTERNAL_USER
CUSTOM
```

Ne pas ajouter d’autres rôles.

---

# 13. Règle globale statuses

Même si le backend utilise `CommunicationStatus` comme enum PHP/cast :

```text
order_communications.status
```

reste une colonne textuelle.

Conformément aux décisions globales Tricolis :

```text
status dans table source = code texte
+
statuses = metadata centrale
```

Donc :

```text
src = order_communications
```

doit contenir les codes :

```text
DRAFT
SCHEDULED
QUEUED
SENDING
SENT
DELIVERED
READ
FAILED
CANCELLED
```

Ne jamais créer :

```text
status_id
```

---

# 14. Les autres enums ne vont pas dans statuses par obligation

Ne pas forcer :

```text
CommunicationChannel
CommunicationTemplateType
CommunicationEventType
RecipientRole
```

dans `statuses` juste parce qu’ils sont des enums.

La convention centrale `statuses` concerne les champs métier `status`.

---

# 15. Modèle exact — CommunicationTemplate

Respecter :

```text
CommunicationTemplate
- id: ULID
- organizationId: ULID
- serviceId: ULID
- code: string
- name: string
- channel: CommunicationChannel
- templateType: CommunicationTemplateType
- subjectTemplate: text
- bodyTemplate: longtext
- language: string
- availableVariables: JSON
- isDefault: boolean
- isActive: boolean
- createdAt: datetime
- updatedAt: datetime
```

Table :

```text
communication_templates
```

---

# 16. Relations CommunicationTemplate

Respecter :

```text
Organization "1" -- "0..*" CommunicationTemplate
Service "0..1" -- "0..*" CommunicationTemplate
CommunicationTemplate "1" -- "0..*" CommunicationRule
CommunicationTemplate "0..1" -- "0..*" OrderCommunication
```

`serviceId` est facultatif.

---

# 17. Template global vs Service-specific

Un template :

```text
serviceId = null
```

peut être générique.

Un template :

```text
serviceId = DELIVERY
```

est spécifique à ce Service.

Ne pas créer :

```text
TemplateServicePivot
```

---

# 18. Code unique Template

Le backend doit conserver :

```text
(organizationId, code) unique
```

Le frontend doit afficher une erreur métier claire si le code existe déjà.

---

# 19. Subject selon canal

Ne pas rendre `subjectTemplate` obligatoire pour :

```text
SMS
WHATSAPP
```

sans règle backend explicite.

Pour EMAIL, utiliser le contrat réel.

---

# 20. availableVariables

`availableVariables` est un JSON.

Il décrit uniquement les variables autorisées pour le template.

Ne pas permettre :

```text
accès arbitraire à Model
order.customer.secret
appel de fonction
PHP
SQL
```

---

# 21. Template variables editor

Créer/réutiliser :

```text
TemplateVariablesEditor
TemplateVariablePicker
```

L’utilisateur doit pouvoir insérer une variable autorisée dans :

```text
subjectTemplate
bodyTemplate
```

sans mémoriser sa syntaxe.

---

# 22. Syntaxe de variables

Inspecter le `CommunicationTemplateRenderer` réel.

Ne pas inventer une nouvelle syntaxe frontend.

Exemple uniquement si backend réel l’utilise :

```text
{{order.number}}
{{customer.name}}
```

Le frontend doit envoyer exactement la syntaxe reconnue par le backend.

---

# 23. Rendu sécurisé

Le backend utilise/réutilise :

```text
CommunicationTemplateRenderer
```

Règles :

```text
remplacer uniquement variables autorisées
refuser variable inconnue
pas eval
pas PHP
pas SQL
pas accès arbitraire Model
escape selon channel
```

Le frontend ne doit pas reproduire un moteur différent.

---

# 24. Preview Template

Créer :

```text
CommunicationTemplatePreview
```

Le preview doit idéalement utiliser un endpoint backend de rendu si disponible.

Sinon le frontend peut afficher la structure brute mais ne doit pas prétendre simuler fidèlement le renderer.

Ne pas créer un second moteur de template JS divergent.

---

# 25. Templates page

Créer/consolider :

```text
/communications/templates
```

Page :

```text
CommunicationTemplateListPage
```

Colonnes :

```text
Code
Name
Service
Channel
Type
Language
Default
Active
UpdatedAt
Actions
```

---

# 26. Template filters

Utiliser les filtres backend réels :

```text
organizationId
serviceId
channel
templateType
language
isDefault
isActive
```

Recherche :

```text
code
name
subject_template
body_template
```

---

# 27. Template Create/Edit/Detail

Routes :

```text
/communications/templates/create
/communications/templates/:id
/communications/templates/:id/edit
```

Formulaire :

```text
Service optional
Code
Name
Channel
TemplateType
SubjectTemplate
BodyTemplate
Language
AvailableVariables
Default
Active
```

---

# 28. Ne pas supprimer Template utilisé

Si utilisé par :

```text
CommunicationRule
OrderCommunication historique
```

le backend doit protéger/refuser Delete.

Frontend affiche le conflit.

Ne pas cascade-delete.

---

# 29. Modèle exact — CommunicationRule

Respecter :

```text
CommunicationRule
- id: ULID
- organizationId: ULID
- serviceId: ULID
- templateId: ULID
- eventType: CommunicationEventType
- recipientRole: RecipientRole
- delayValue: int
- delayUnit: string
- conditions: JSON
- isAutomatic: boolean
- isActive: boolean
- createdAt: datetime
- updatedAt: datetime
```

Table :

```text
communication_rules
```

---

# 30. Relations CommunicationRule

Respecter :

```text
Organization "1" -- "0..*" CommunicationRule
Service "0..1" -- "0..*" CommunicationRule
CommunicationTemplate "1" -- "0..*" CommunicationRule
CommunicationRule "0..1" -- "0..*" OrderCommunication
```

---

# 31. Template obligatoire Rule

Chaque Rule doit avoir :

```text
templateId
```

Le Template doit appartenir à la même Organization.

---

# 32. Service facultatif Rule

`serviceId` est facultatif.

Cela permet conceptuellement :

```text
règle globale sur l'événement
```

ou :

```text
règle pour un Service précis
```

Ne pas forcer Service si backend ne le demande pas.

---

# 33. Cohérence Service / Template

Si :

```text
Rule.serviceId = DELIVERY
```

et Template est spécifique à :

```text
Service MONTAGE
```

refuser.

Le backend est autorité finale.

---

# 34. eventType

Le formulaire doit utiliser exactement `CommunicationEventType`.

Afficher un label traduit mais envoyer le code exact.

Exemple :

```text
SERVICE_COMPLETED
```

pas :

```text
"Service terminé"
```

dans l’API.

---

# 35. recipientRole

La règle définit le rôle du destinataire.

Exemple conceptuel :

```text
SERVICE_COMPLETED
→ DELIVERY_CONTACT
```

Le backend résout le contact lors de la création de la communication.

La Rule ne stocke pas l’email/téléphone final.

---

# 36. delayValue / delayUnit

Le modèle possède :

```text
delayValue: int
delayUnit: string
```

Ne pas créer :

```text
cronExpression
scheduledExpression
rrule
```

---

# 37. delayUnit reste string

Ne pas créer un enum si backend ne le définit pas.

Analyser les unités réellement supportées.

Si backend documente par exemple :

```text
MINUTES
HOURS
DAYS
```

utiliser cette whitelist.

Sinon documenter la limitation.

Ne pas hardcoder une liste inventée.

---

# 38. Sens du délai

Inspecter le backend final pour déterminer si :

```text
delayValue > 0
```

signifie après événement,

et si une valeur négative est autorisée pour avant événement.

Ne pas inventer cette sémantique.

Le formulaire doit suivre la validation réelle.

---

# 39. conditions JSON

`conditions` reste un JSON déclaratif.

Interdit :

```text
PHP
SQL
JavaScript libre
eval
method call
model path arbitraire
fonction utilisateur
```

---

# 40. Conditions Builder

Si le backend définit un schéma déclaratif stable :

créer :

```text
CommunicationRuleConditionsBuilder
```

basé uniquement sur :

```text
fields autorisés
operators autorisés
values autorisées
```

---

# 41. Si aucun schéma conditions stable

Ne pas inventer un builder complexe.

Utiliser :

```text
JsonConfigurationEditor
```

avec validation backend.

Afficher clairement :

```text
Configuration avancée
```

---

# 42. Rule Condition Evaluator

Le frontend ne doit pas évaluer les règles métier.

Le backend utilise/réutilise :

```text
CommunicationRuleConditionEvaluator
```

Le frontend ne fait qu’éditer/preview les données.

---

# 43. isAutomatic

Règle essentielle :

```text
isAutomatic = true
```

→ la Rule peut produire automatiquement une OrderCommunication lorsqu’un événement correspondant survient.

```text
isAutomatic = false
```

→ ne pas déclencher automatiquement.

Ne pas inventer ce qui doit se passer avec une Rule non automatique au-delà du backend réel.

---

# 44. isActive

Même avec :

```text
isAutomatic = true
```

si :

```text
isActive = false
```

la règle ne doit pas être appliquée.

Frontend affiche clairement :

```text
Active
Inactive
```

---

# 45. Communication Rules page

Créer :

```text
/communications/rules
```

Page :

```text
CommunicationRuleListPage
```

Colonnes :

```text
Event
Service
Template
Channel via Template
Recipient
Delay
Automatic
Active
CreatedAt
Actions
```

---

# 46. Rule filters

Utiliser :

```text
organizationId
serviceId
templateId
eventType
recipientRole
delayUnit
isAutomatic
isActive
```

---

# 47. Rule Create/Edit/Detail

Routes :

```text
/communications/rules/create
/communications/rules/:id
/communications/rules/:id/edit
```

Formulaire :

```text
Service optional
EventType
Template
RecipientRole
DelayValue
DelayUnit
Conditions
Automatic
Active
```

---

# 48. Template selector Rule

Filtrer les templates par :

```text
Organization
Service compatibility
Active si création
```

Afficher :

```text
Name
Channel
Type
Language
Service
```

Ne pas permettre une incohérence évidente.

---

# 49. Rule summary preview

Avant Save, afficher :

```text
Quand : SERVICE_COMPLETED
Pour : Service DELIVERY
Si : conditions ...
Attendre : 10 MINUTES
Envoyer : Template "Livraison terminée"
Canal : EMAIL
À : CUSTOMER
Automatique : Oui
```

C’est un résumé UX.

Backend décide la validité finale.

---

# 50. Simulation de Rule — facultative

Si backend expose un endpoint non persistant :

```text
Tester la règle sur une Order
```

alors afficher :

```text
rule matched?
recipient resolved?
template renderable?
scheduledAt calculé?
```

Ne pas réellement envoyer.

Ne pas créer ce endpoint si backend n’en possède pas/si non nécessaire.

---

# 51. Événements et backend

Les événements doivent venir du backend métier.

Exemples exacts :

```text
ORDER_CREATED
ORDER_CONFIRMED
ORDER_CANCELLED
SERVICE_PLANNED
APPOINTMENT_REQUESTED
APPOINTMENT_CONFIRMED
DRIVER_ASSIGNED
TOUR_STOP_APPROACHING
SERVICE_COMPLETED
POD_CREATED
CLAIM_CREATED
```

React ne doit pas créer une communication automatique parce qu’il observe un changement UI.

---

# 52. Event → Rule evaluation

Backend conceptuel :

```text
Event métier
→ ResolveCommunicationRules
→ filter Organization
→ filter eventType
→ filter Service optional
→ isActive
→ isAutomatic
→ evaluate conditions
→ create communication
```

Ne pas implémenter cette logique dans Controllers.

---

# 53. Idempotence de l’automatisation

Un même événement métier ne doit pas produire le même message plusieurs fois à cause :

```text
retry event
queue retry
double dispatch
double click
replay
```

Le backend Phase 9 doit utiliser la stratégie d’idempotence réellement prévue.

Ne pas inventer une colonne `eventId` si absente.

Documenter la stratégie.

---

# 54. Plusieurs Rules sur un même événement

Le système peut avoir plusieurs Rules compatibles.

Exemple :

```text
SERVICE_COMPLETED
→ EMAIL CUSTOMER
→ SMS CUSTOMER
→ INTERNAL_NOTIFICATION INTERNAL_USER
```

Chaque Rule peut créer sa propre `OrderCommunication`.

Ne pas limiter arbitrairement à une seule Rule.

---

# 55. Même Template utilisé par plusieurs Rules

Autorisé si le modèle le permet.

Ne pas dupliquer le Template.

---

# 56. Modèle exact — OrderCommunication

Respecter :

```text
OrderCommunication
- id: ULID
- organizationId: ULID
- orderId: ULID
- templateId: ULID
- communicationRuleId: ULID
- channel: CommunicationChannel
- communicationType: CommunicationTemplateType
- recipientRole: RecipientRole
- recipientName: string
- recipientEmail: string
- recipientPhone: string
- subject: text
- body: longtext
- templateVariables: JSON
- status: CommunicationStatus
- scheduledAt: datetime
- queuedAt: datetime
- sentAt: datetime
- deliveredAt: datetime
- readAt: datetime
- failedAt: datetime
- providerMessageId: string
- providerResponse: JSON
- errorMessage: text
- createdBy: ULID
- createdAt: datetime
- updatedAt: datetime
```

Table :

```text
order_communications
```

---

# 57. Relations OrderCommunication

Respecter :

```text
Organization "1" -- "0..*" OrderCommunication
CommunicationTemplate "0..1" -- "0..*" OrderCommunication
CommunicationRule "0..1" -- "0..*" OrderCommunication
Order "1" -- "0..*" OrderCommunication
User "0..1" -- "0..*" OrderCommunication : createdBy
OrderCommunication "1" *-- "0..*" CommunicationAttachment
```

---

# 58. Communication manuelle

Une communication peut être créée :

```text
sans Rule
```

et éventuellement :

```text
sans Template
```

si la nullabilité/Request backend l’autorise.

Ne pas casser le flow manuel Phase 3.

---

# 59. Communication automatique

Une communication créée par automatisation doit conserver :

```text
communicationRuleId
templateId
```

si utilisés.

Cela permet d’expliquer son origine.

---

# 60. Snapshot obligatoire

Lors de création :

```text
subject
body
templateVariables
recipientName
recipientEmail
recipientPhone
```

sont figés.

Changer un Template ou une Rule plus tard ne modifie jamais le message historique.

---

# 61. Template variables snapshot

`templateVariables` doit conserver les valeurs réellement utilisées.

Le frontend peut afficher en Detail :

```text
Variables utilisées
```

mais ne pas les recalculer.

---

# 62. Destinataire snapshot

Même si le contact du client change après envoi :

la communication historique continue d’afficher :

```text
recipientName
recipientEmail
recipientPhone
```

du moment de l’envoi.

---

# 63. ResolveOrderCommunicationRecipient

Backend résout les rôles :

```text
CUSTOMER
LOAD_CONTACT
DELIVERY_CONTACT
BILLING_CONTACT
INTERNAL_USER
CUSTOM
```

Ne pas créer `CommunicationRecipient`.

---

# 64. CUSTOMER

La logique exacte de :

```text
CUSTOMER
```

doit être documentée depuis les Contacts/Customer réels.

Ne pas inventer un email Customer si plusieurs contacts existent.

---

# 65. LOAD_CONTACT

Résoudre via les contacts du/des `OrderService` de chargement selon backend.

Ne pas chercher "loadContactEmail" dans Order s’il n’existe pas.

---

# 66. DELIVERY_CONTACT

Même règle :

résoudre via les contacts réels du Service/OrderService concerné.

---

# 67. BILLING_CONTACT

Résoudre via les informations de contact facturation existantes.

Ne pas créer une nouvelle colonne dans Customer pour cette phase.

---

# 68. INTERNAL_USER

Le backend détermine quel User interne correspond selon règle/contexte réellement implémenté.

Ne pas inventer :

```text
internalUserId
```

dans `CommunicationRule`.

---

# 69. CUSTOM

Pour :

```text
CUSTOM
```

le formulaire manuel peut permettre :

```text
RecipientName
RecipientEmail
RecipientPhone
```

selon channel.

Pour une Rule automatique CUSTOM, analyser comment le backend peut résoudre ce destinataire.

Si aucune source n’existe :

ne pas autoriser une automation CUSTOM impossible à résoudre.

Documenter.

---

# 70. Validation par channel

## EMAIL

Email valide requis selon backend.

## SMS

Phone valide requis.

## WHATSAPP

Phone valide requis.

## PUSH_NOTIFICATION

Résolution technique selon backend.

## INTERNAL_NOTIFICATION

Résolution User interne selon backend.

Ne pas rendre tous les champs obligatoires simultanément.

---

# 71. Communication list globale

Créer/consolider :

```text
/communications/history
```

ou route existante.

Page :

```text
OrderCommunicationListPage
```

Colonnes :

```text
Date
Order
Customer
Type
Channel
Recipient
Rule
Template
Status
ScheduledAt
SentAt
Error
CreatedBy
Actions
```

---

# 72. Filtres communications

Utiliser exactement ceux supportés :

```text
organizationId
orderId
templateId
communicationRuleId
channel
communicationType
recipientRole
status
createdBy
scheduledFrom
scheduledTo
sentFrom
sentTo
failedFrom
failedTo
```

---

# 73. Recherche communications

Backend peut rechercher :

```text
recipient_name
recipient_email
recipient_phone
subject
body
provider_message_id
error_message
```

Utiliser le contrat réel.

---

# 74. Order Detail — Communications

Conserver l’onglet existant :

```text
/orders/:id
→ Communications
```

Afficher :

```text
historique
new manual communication
scheduled
failed
sent
attachments
```

---

# 75. Communication origin

Afficher si possible :

```text
Manuelle
Règle automatique
Règle non automatique
```

basé sur les données réelles :

```text
communicationRuleId
createdBy
```

sans inventer un champ `origin`.

---

# 76. OrderCommunication Detail

Créer/consolider :

```text
/communications/:id
```

Afficher :

```text
Order
Template
Rule
Channel
Type
RecipientRole
Recipient snapshot
Subject
Body
Variables snapshot
Status
ScheduledAt
QueuedAt
SentAt
DeliveredAt
ReadAt
FailedAt
ProviderMessageId
ErrorMessage
Creator
Attachments
```

---

# 77. providerResponse sensible

`providerResponse` peut contenir des données techniques/sensibles.

Ne pas l’afficher brut au frontend si Resource le masque.

Si backend expose une version sanitized :

afficher dans une section technique réservée aux permissions appropriées.

Ne jamais exposer secret/token.

---

# 78. Status lifecycle

Utiliser le backend comme autorité.

États exacts disponibles :

```text
DRAFT
SCHEDULED
QUEUED
SENDING
SENT
DELIVERED
READ
FAILED
CANCELLED
```

Ne pas construire des transitions frontend arbitraires.

---

# 79. Actions spécifiques

Backend peut exposer :

```text
POST /api/v1/order-communications/{id}/queue
POST /api/v1/order-communications/{id}/cancel
POST /api/v1/order-communications/{id}/retry
```

Réutiliser ces actions.

Ne pas faire :

```text
PATCH status=SENT
```

directement depuis React.

---

# 80. Queue action

Afficher :

```text
Envoyer
```

ou :

```text
Mettre en file
```

uniquement si :

- permission ;
- status compatible ;
- backend le permet.

---

# 81. Retry

Pour FAILED :

```text
Réessayer
```

utilise l’Action backend.

Ne pas créer une nouvelle `OrderCommunication` pour le retry sauf si backend le fait explicitement.

---

# 82. Cancel

Pour DRAFT/SCHEDULED/états annulables :

utiliser :

```text
cancel
```

Ne pas mettre status directement côté frontend.

---

# 83. Modification après envoi

Une communication :

```text
SENT
DELIVERED
READ
```

ne doit pas être éditable.

Les snapshots restent historiques.

---

# 84. Suppression communication

Backend strict :

```text
DRAFT
→ suppression possible selon permission/règle
```

Après envoi :

```text
pas de suppression physique
```

Frontend doit suivre la capability réelle.

---

# 85. Communication programmée

Champ :

```text
scheduledAt
```

permet une communication future.

Formulaire manuel :

```text
Envoyer maintenant
Programmer
Sauvegarder brouillon
```

uniquement si backend supporte ces modes.

---

# 86. Scheduled list

Créer une vue/filtre :

```text
Communications programmées
```

basée sur :

```text
status = SCHEDULED
```

et `scheduledAt`.

Ne pas créer une table `ScheduledCommunication`.

---

# 87. Automation delay → scheduledAt

Pour une Rule automatique avec délai :

le backend calcule `scheduledAt`.

React ne doit pas calculer lui-même la date finale métier.

---

# 88. Queue et Scheduler

Le backend gère :

```text
SCHEDULED
→ scheduledAt <= now
→ QUEUED
→ SENDING
```

avec verrouillage/idempotence.

Frontend affiche seulement l’état.

---

# 89. Delivery / Read

Si le provider supporte les callbacks :

```text
deliveredAt
readAt
```

peuvent évoluer.

Frontend affiche :

```text
Envoyé
Livré
Lu
```

selon status/timestamps réels.

---

# 90. Provider callbacks

Ne pas développer une UI Webhook.

Le backend peut avoir un endpoint technique :

```text
POST /api/v1/communications/provider-callback/{channel}
```

si intégration existante.

Frontend n’interagit pas avec ce callback.

---

# 91. Canaux techniques

Backend doit réutiliser :

```text
CommunicationSender
EmailCommunicationSender
SmsCommunicationSender
WhatsappCommunicationSender
PushCommunicationSender
InternalCommunicationSender
```

ou équivalents.

Ne pas créer une table Provider.

---

# 92. Configuration fournisseurs communication

Le diagramme Phase 9 n’a pas :

```text
CommunicationProviderConfiguration
```

Donc ne pas créer un CRUD :

```text
Twilio
Infobip
SMTP Provider
WhatsApp Provider
```

dans cette phase.

Inspecter la configuration technique existante :

```text
configs
.env
services.php
```

Réutiliser.

Si une UI de configuration est déjà prévue ailleurs, ne pas dupliquer.

---

# 93. Ne pas exposer les secrets fournisseurs

Ne jamais afficher/logguer :

```text
SMTP password
API token
WhatsApp token
SMS secret
provider auth header
```

---

# 94. Modèle exact — CommunicationAttachment

Respecter :

```text
CommunicationAttachment
- id: ULID
- communicationId: ULID
- documentId: ULID
- fileNameSnapshot: string
- mimeTypeSnapshot: string
- createdAt: datetime
```

Table :

```text
communication_attachments
```

---

# 95. Attachment = Document existant

Une pièce jointe référence :

```text
Document
```

Ne pas uploader un second fichier dans une table communication séparée.

Réutiliser le module Documents.

---

# 96. Snapshot attachment

Lors de l’ajout :

```text
fileNameSnapshot
mimeTypeSnapshot
```

sont figés.

Modifier le Document ensuite ne doit pas modifier ces snapshots.

---

# 97. Attachment before send

Ajouter/supprimer une pièce jointe seulement lorsque le status permet encore la modification.

Après envoi :

protéger l’historique.

---

# 98. Ne pas supprimer le Document

Supprimer `CommunicationAttachment` ne supprime jamais automatiquement :

```text
Document
```

---

# 99. Attachment API

Utiliser :

```text
GET    /api/v1/order-communications/{communication}/attachments
POST   /api/v1/order-communications/{communication}/attachments
GET    /api/v1/order-communications/{communication}/attachments/{attachment}
DELETE /api/v1/order-communications/{communication}/attachments/{attachment}
```

Pas de PATCH.

---

# 100. Template API

Utiliser :

```text
GET    /api/v1/communication-templates
POST   /api/v1/communication-templates
GET    /api/v1/communication-templates/{id}
PATCH  /api/v1/communication-templates/{id}
DELETE /api/v1/communication-templates/{id}
```

---

# 101. Rule API

Utiliser :

```text
GET    /api/v1/communication-rules
POST   /api/v1/communication-rules
GET    /api/v1/communication-rules/{id}
PATCH  /api/v1/communication-rules/{id}
DELETE /api/v1/communication-rules/{id}
```

---

# 102. OrderCommunication API

Utiliser :

```text
GET    /api/v1/order-communications
POST   /api/v1/order-communications
GET    /api/v1/order-communications/{id}
PATCH  /api/v1/order-communications/{id}
DELETE /api/v1/order-communications/{id}
```

Nested :

```text
GET  /api/v1/orders/{order}/communications
POST /api/v1/orders/{order}/communications
```

Actions :

```text
POST /api/v1/order-communications/{id}/queue
POST /api/v1/order-communications/{id}/cancel
POST /api/v1/order-communications/{id}/retry
```

selon routes finales.

---

# 103. Permissions exactes attendues

Vérifier dans `PermissionSeeder` :

```text
communication_templates.view
communication_templates.create
communication_templates.update
communication_templates.delete

communication_rules.view
communication_rules.create
communication_rules.update
communication_rules.delete

order_communications.view
order_communications.create
order_communications.update
order_communications.delete
order_communications.queue
order_communications.cancel
order_communications.retry

communication_attachments.view
communication_attachments.create
communication_attachments.delete
```

Ne pas inventer d’autres permissions si celles-ci sont présentes.

---

# 104. PermissionGuard

Appliquer à :

```text
Create/Edit/Delete Template
Create/Edit/Delete Rule
Create Communication
Edit Draft
Delete Draft
Queue
Cancel
Retry
Add/Delete Attachment
```

Backend Policies restent l’autorité finale.

---

# 105. Multi-organisation

Toujours vérifier :

```text
Template.organizationId
Rule.organizationId
OrderCommunication.organizationId
Order.organization
Service.organization/context
Document.organization
User organization/context
```

Aucun cross-Organization.

---

# 106. Template / Rule cross-org

Interdire :

```text
Rule Org A
→ Template Org B
```

---

# 107. Communication / Order cross-org

Interdire :

```text
Communication Org A
→ Order Org B
```

---

# 108. Attachment cross-org

Interdire :

```text
Communication Org A
→ Document Org B
```

---

# 109. Menu Backoffice

Ajouter/consolider :

```text
Communication
├── Templates
├── Règles automatiques
└── Historique
```

Ne pas ajouter :

```text
Providers
Webhooks
Campaigns
Threads
```

---

# 110. Dashboard Communications

Optionnel si données réelles efficaces :

```text
/communications
```

Afficher :

```text
Scheduled
Queued
Failed
Sent today
```

uniquement via agrégats backend.

Ne pas charger toutes les communications pour compter.

---

# 111. Failed communications view

Créer filtre/page :

```text
Échecs
```

basé sur :

```text
status = FAILED
```

Afficher :

```text
Order
Channel
Recipient
Error
FailedAt
Attempt/Retry capability
```

`attemptCount` n’existe pas dans OrderCommunication.

Ne pas l’inventer.

---

# 112. Aucun retryCount DB

Ne pas ajouter :

```text
retryCount
maxAttempts
```

au modèle.

Le retry technique est géré par queue/job/configuration.

---

# 113. Pas de priority

Ne pas ajouter :

```text
priority
```

à CommunicationRule ou OrderCommunication.

---

# 114. Pas de CC/BCC

Le modèle ne contient pas :

```text
cc
bcc
replyTo
```

Ne pas les ajouter dans le formulaire.

---

# 115. Langue

Template possède :

```text
language
```

Le backend décide comment sélectionner un template par langue.

Ne pas créer un moteur de traduction automatique du message.

Afficher/filter `language`.

---

# 116. isDefault

Template possède :

```text
isDefault
```

Ne pas inventer une contrainte "un seul default par channel/type/service" sans backend.

Afficher le flag réel.

---

# 117. Template picker manuel

Dans nouvelle communication manuelle :

filtrer par :

```text
Organization
Service pertinent si connu
Channel
TemplateType
Language
isActive
```

selon API.

---

# 118. Template snapshot preview manuel

Après sélection :

backend doit rendre un preview avec les données de l’Order si endpoint disponible.

Afficher :

```text
Recipient
Subject
Body
Variables
```

avant Save/Queue si flow supporté.

---

# 119. Communication sans Template

Si backend autorise :

```text
templateId = null
```

permettre mode :

```text
Message personnalisé
```

avec :

```text
channel
communicationType
recipientRole
recipient
subject
body
```

selon validation réelle.

---

# 120. CommunicationType

Même pour un message personnalisé :

```text
communicationType
```

reste un `CommunicationTemplateType`.

Utiliser `CUSTOM` si c’est la règle backend réellement prévue.

Ne pas l’imposer sans vérifier Request.

---

# 121. Rule origin dans history

Si `communicationRuleId` existe :

afficher :

```text
Déclenché par règle : <Rule summary>
```

avec lien vers Rule.

---

# 122. Template origin dans history

Si `templateId` existe :

afficher lien vers Template actuel, tout en montrant le snapshot historique du message.

Ne jamais afficher le body actuel du Template comme contenu "envoyé".

---

# 123. CreatedBy automatique

Une communication automatique peut avoir :

```text
createdBy = null
```

selon nullabilité.

Afficher :

```text
Système
```

seulement si cette convention est validée.

Ne pas créer un faux User System.

---

# 124. Audit

Réutiliser `AuditLog`.

Auditer selon conventions :

```text
communication_template.created
communication_template.updated
communication_template.deleted

communication_rule.created
communication_rule.updated
communication_rule.deleted

order_communication.created
order_communication.updated
order_communication.queued
order_communication.cancelled
order_communication.retried
order_communication.sent
order_communication.failed
order_communication.delivered
order_communication.read

communication_attachment.created
communication_attachment.deleted
```

Les noms exacts suivent backend réel.

---

# 125. providerResponse dans Audit

Ne jamais stocker des secrets provider en Audit.

Sanitizer :

```text
Authorization
API token
secret
password
credentials
```

---

# 126. Frontend architecture

Créer/consolider :

```text
src/modules/communications/
```

Structure :

```text
communications/
├── pages/
├── components/
├── api/
├── hooks/
├── schemas/
├── types/
└── utils/
```

Refactorer le code Phase 3 au lieu de le copier.

---

# 127. API Layer

Créer/consolider :

```text
communication-templates.api.ts
communication-rules.api.ts
order-communications.api.ts
communication-attachments.api.ts
```

Aucun fetch dans JSX.

---

# 128. Query Keys

Créer/consolider :

```text
communicationTemplateKeys
communicationRuleKeys
orderCommunicationKeys
communicationAttachmentKeys
```

Exemples :

```text
communicationTemplateKeys.list(filters)
communicationTemplateKeys.detail(id)

communicationRuleKeys.list(filters)
communicationRuleKeys.detail(id)

orderCommunicationKeys.list(filters)
orderCommunicationKeys.detail(id)
orderCommunicationKeys.byOrder(orderId)
orderCommunicationKeys.failed(filters)
orderCommunicationKeys.scheduled(filters)

communicationAttachmentKeys.byCommunication(id)
```

---

# 129. Invalidation Template

Après Create/Edit/Delete :

```text
template lists
template detail
rule forms/options
manual communication template picker
```

Ne pas invalider les snapshots des anciennes communications.

---

# 130. Invalidation Rule

Après Rule mutation :

```text
rule list/detail
rule selectors
```

Ne pas modifier/refetch comme données dérivées les communications historiques pour recalculer leur contenu.

---

# 131. Invalidation Communication

Après Create/Queue/Cancel/Retry :

```text
OrderCommunication detail
global history
Order communications
failed/scheduled views
```

---

# 132. Types TypeScript

Créer selon Resources :

```text
CommunicationTemplate
CommunicationRule
OrderCommunication
CommunicationAttachment

CommunicationChannel
CommunicationTemplateType
CommunicationEventType
CommunicationStatus
RecipientRole
```

Projections UI autorisées :

```text
CommunicationRuleSummary
CommunicationPreview
ResolvedRecipientPreview
```

seulement si endpoints réels.

---

# 133. Zod Template

Créer :

```text
communicationTemplateSchema
```

avec :

```text
code
name
serviceId
channel
templateType
subjectTemplate
bodyTemplate
language
availableVariables
isDefault
isActive
```

---

# 134. Zod Rule

Créer :

```text
communicationRuleSchema
```

avec :

```text
serviceId
templateId
eventType
recipientRole
delayValue
delayUnit
conditions
isAutomatic
isActive
```

---

# 135. Zod manual Communication

Créer :

```text
orderCommunicationSchema
```

validation conditionnelle selon channel/recipientRole.

Ne pas dupliquer la validation métier complète du backend.

---

# 136. Attachment form

Réutiliser Document selector Phase 1/3.

Ne pas uploader un File brut directement dans CommunicationAttachment si l’API attend `documentId`.

---

# 137. Status UI

Pour `OrderCommunication.status` :

réutiliser :

```text
StatusBadge
useStatuses("order_communications")
```

Ne pas hardcoder couleur/label.

---

# 138. Enum labels UI

Pour :

```text
channel
templateType
eventType
recipientRole
```

des labels i18n peuvent être définis dans le frontend.

Le code API reste exact.

---

# 139. i18n

Ajouter/consolider :

```text
communications.*
communicationTemplates.*
communicationRules.*
communicationEvents.*
communicationChannels.*
recipientRoles.*
```

---

# 140. Search/Pagination

Templates, Rules, Communications :

```text
pagination serveur
filters serveur
sort serveur
search serveur
```

Ne pas charger tout l’historique.

---

# 141. Performance Order Detail

L’onglet Communications d’une Order charge :

```text
communications de cette Order
```

avec pagination/lazy loading si nécessaire.

Ne pas charger l’historique global.

---

# 142. N+1

Backend Resource list doit éviter :

```text
N Template requests
N Rule requests
N Order requests
N User requests
```

Utiliser les Resources relationnelles optimisées.

---

# 143. Automation observability

L’UI doit permettre de comprendre :

```text
Pourquoi ce message a été créé ?
```

Afficher :

```text
Rule
Event
Template
Recipient
ScheduledAt
Status
```

uniquement si informations disponibles.

Ne pas créer un historique Event séparé.

---

# 144. EventType snapshot absent

`OrderCommunication` ne possède pas :

```text
eventType
```

Ne pas l’ajouter.

Si une Communication vient d’une Rule :

```text
communicationRule.eventType
```

peut être affiché en référence actuelle.

Mais ne pas prétendre que c’est un snapshot historique si Rule peut être modifiée.

Le contenu envoyé reste snapshot ; l’EventType n’est pas snapshot dans ce modèle.

---

# 145. Protéger Rule utilisée

Si Rule est utilisée par des OrderCommunication historiques :

Delete doit être protégé/refusé selon backend.

Privilégier :

```text
isActive = false
```

pour l’arrêter.

---

# 146. Désactiver Rule

UX :

```text
Activer / Désactiver
```

via Edit/PATCH.

Une Rule inactive n’impacte pas les communications déjà créées.

---

# 147. Modifier Rule

Les nouvelles communications utilisent la nouvelle Rule.

Les anciennes restent figées par leurs snapshots.

---

# 148. Event ORDER_CREATED

Si Rule automatique correspond :

backend peut produire communication à la création Order.

Frontend ne fait rien de spécifique dans OrderCreatePage.

---

# 149. Event ORDER_CONFIRMED

Même principe :

la transition Order côté backend émet l’événement.

React ne crée pas le message après le PATCH status.

---

# 150. Event ORDER_CANCELLED

Réutiliser l’événement backend de cancellation.

Pas de second appel communication depuis frontend.

---

# 151. Event SERVICE_PLANNED

Doit être émis par le workflow de planning backend lorsque le Service est réellement planifié.

Le DnD Phase 5 ne doit pas directement envoyer le message depuis React.

---

# 152. APPOINTMENT events

```text
APPOINTMENT_REQUESTED
APPOINTMENT_CONFIRMED
```

doivent venir du workflow réel.

Ne pas créer de faux bouton event uniquement pour tester l’automation.

---

# 153. DRIVER_ASSIGNED

Émis lorsque backend considère l’affectation Driver réellement valide.

Ne pas créer communication directement dans le formulaire Tour.

---

# 154. TOUR_STOP_APPROACHING

C’est un événement opérationnel backend.

Il peut servir à :

```text
ARRIVAL_ESTIMATE
ARRIVAL_SOON
```

mais le choix vient des Rules/Templates.

Ne pas hardcoder :

```text
approaching => SMS
```

---

# 155. SERVICE_COMPLETED

Peut produire :

```text
DELIVERY_CONFIRMATION
```

ou autre template configuré.

Ne pas hardcoder la correspondance.

---

# 156. POD_CREATED

Peut produire :

```text
POD_AVAILABLE
```

via Rule.

Le frontend POD Phase 3 n’envoie pas lui-même.

---

# 157. CLAIM_CREATED

Peut produire communication selon Rules.

Ne pas hardcoder un destinataire.

---

# 158. Multiple channels

Pour un même événement, on peut configurer :

```text
Rule A -> EMAIL
Rule B -> SMS
Rule C -> INTERNAL_NOTIFICATION
```

via Templates associés.

Le Rule n’a pas `channel` directement ; le channel vient du Template.

Ne pas ajouter `channel` dans CommunicationRule.

---

# 159. Conditions liées aux données

Si backend ConditionEvaluator supporte des champs comme :

```text
order
service
customer
tour
claim
```

utiliser uniquement la whitelist.

Ne pas permettre au frontend de saisir un chemin arbitraire.

---

# 160. Scheduled communication manual

Exemple :

```text
Order Communications
→ New
→ Template Appointment Reminder
→ Recipient
→ Schedule 2026-09-02 09:00
```

Backend crée :

```text
status = SCHEDULED
scheduledAt = ...
```

selon Action réelle.

---

# 161. DRAFT manual

Permettre :

```text
Save draft
```

si Request/action backend le prévoit.

Draft peut être modifié tant que status autorise.

---

# 162. Queue now

Pour envoyer maintenant :

```text
create DRAFT/QUEUED selon Action
→ queue endpoint
```

suivre contrat réel.

Ne pas manipuler `queuedAt` côté frontend.

---

# 163. Timeline communication

Dans Detail, afficher :

```text
Created
Scheduled
Queued
Sent
Delivered
Read
Failed
```

à partir des timestamps réellement non null.

C’est une projection UI, pas une table historique.

---

# 164. Failed timeline

Si :

```text
failedAt
errorMessage
```

afficher clairement.

Après retry réussi, suivre les valeurs renvoyées backend.

Ne pas inventer `retryHistory`.

---

# 165. ProviderMessageId

Peut être affiché dans :

```text
Détails techniques
```

si permission/Resource.

Ne pas l’utiliser comme identifiant métier principal.

---

# 166. Internal notifications

`INTERNAL_NOTIFICATION` existe comme channel.

Ne pas créer une table `InternalNotification`.

Utiliser OrderCommunication + sender technique.

---

# 167. Push notifications

Même règle :

`PUSH_NOTIFICATION` est un channel, pas une nouvelle table.

---

# 168. Provider configuration gap

Si un channel exact existe mais aucun sender technique n’est configuré :

le frontend peut montrer :

```text
Canal indisponible/non configuré
```

si backend expose cette capability.

Ne pas inventer une configuration persistante.

---

# 169. Tests Template

Tester :

```text
list
create
edit
detail
delete protection
Service optional
cross-org Service refused
code unique
channels exacts
types exacts
availableVariables
unknown variable refused
render preview if endpoint
permissions
IDOR
audit
```

---

# 170. Tests Rule

Tester :

```text
list
create
edit
detail
Template required
Service optional
cross-org Template refused
Service/Template mismatch
event exact
recipient exact
conditions JSON
dangerous conditions refused
delay validation
automatic
active
delete protection
permissions
IDOR
audit
```

---

# 171. Tests Communication manual

Tester :

```text
manual create
from Template
without Template if allowed
recipient role
EMAIL validation
SMS validation
WhatsApp validation
Draft
Scheduled
Queue
Cancel
Retry
edit protection sent
delete protection sent
snapshots
attachments
permissions
IDOR
```

---

# 172. Tests Automation

Tester backend + frontend regression :

```text
event emitted
active automatic matching Rule
conditions true
→ OrderCommunication created

Rule inactive
→ none

Rule isAutomatic false
→ none automatic

conditions false
→ none

wrong Service
→ none

wrong Organization
→ none
```

---

# 173. Test multiple Rules

Event unique avec 3 Rules automatiques compatibles :

```text
→ 3 communications
```

selon règles.

---

# 174. Test idempotence

Même événement rejoué techniquement :

ne doit pas produire un double envoi non prévu.

Tester la stratégie backend réelle.

---

# 175. Test delay

Event + Rule delay :

```text
→ SCHEDULED
→ scheduledAt correct
→ pas envoyé avant date
→ queue après échéance
```

selon unités supportées.

---

# 176. Test sender failure

Fake sender :

```text
throws / failure
```

Résultat :

```text
FAILED
failedAt
errorMessage
```

Pas de secret dans error.

---

# 177. Test retry

FAILED :

```text
Retry
→ même communication
→ nouvel envoi
→ SENT si succès
```

selon Action backend.

---

# 178. Test delivered/read callback

Avec fake provider/callback si supporté :

```text
SENT
→ DELIVERED
→ READ
```

sans table Webhook.

---

# 179. Test snapshot Template

Créer communication depuis Template.

Modifier ensuite Template.

Vérifier :

```text
communication.subject inchangé
communication.body inchangé
templateVariables inchangées
```

---

# 180. Test snapshot Recipient

Créer communication.

Modifier contact ensuite.

Vérifier :

```text
recipientEmail/Phone historique inchangé
```

---

# 181. Test Attachment snapshot

Ajouter Document.

Modifier Document metadata ensuite.

Vérifier :

```text
fileNameSnapshot inchangé
mimeTypeSnapshot inchangé
```

---

# 182. Test status registry

Vérifier :

```text
order_communications.status textuel
statuses src=order_communications
aucun status_id
labels/couleurs dynamiques
```

---

# 183. E2E Templates + Rule

```text
Login
→ Communication
→ Templates
→ créer Template EMAIL DELIVERY_CONFIRMATION
→ choisir variables
→ preview
→ save

→ Règles automatiques
→ créer Rule
Event SERVICE_COMPLETED
Service DELIVERY
Template précédent
Recipient CUSTOMER
Automatic yes
Active yes
```

---

# 184. E2E Automation

```text
Order avec DELIVERY
→ workflow réel jusqu'à SERVICE_COMPLETED
→ backend déclenche Rule
→ OrderCommunication créée
→ visible dans Order > Communications
→ queued/sent
→ snapshot correct
```

Aucune requête manuelle communication depuis le frontend au moment de l’événement.

---

# 185. E2E Scheduled

```text
New manual communication
→ Template Reminder
→ ScheduledAt future
→ SCHEDULED
→ scheduler
→ SENT
```

---

# 186. E2E Failure/Retry

```text
sender fake fails
→ FAILED
→ History
→ Retry
→ sender success
→ SENT
```

---

# 187. E2E Phase 3 regression

Vérifier que l’ancien flow reste valide :

```text
Order
→ Communications
→ New
→ Template
→ Recipient
→ Document
→ Queue
→ Sent
```

---

# 188. E2E Phase 5 integration

```text
Planification Service
→ backend SERVICE_PLANNED
→ Rule automatic
→ communication
```

Le DnD frontend reste indépendant.

---

# 189. E2E POD integration

```text
Create POD
→ backend POD_CREATED
→ Rule
→ POD_AVAILABLE communication
```

---

# 190. E2E Claim integration

```text
Create Claim
→ CLAIM_CREATED
→ configured Rule
→ communication
```

---

# 191. Analyse obligatoire

Créer :

```text
docs/frontend/phase-9-analysis.md
```

Inclure au minimum :

1. branche source Phase 8 ;
2. frontend Phase 3 existant ;
3. backend Phase 9 réel ;
4. Template fields ;
5. Rule fields ;
6. OrderCommunication fields ;
7. Attachment fields ;
8. enums exacts ;
9. statuses registry ;
10. template renderer syntax ;
11. availableVariables schema ;
12. conditions schema ;
13. delay units ;
14. delay semantics ;
15. isAutomatic semantics ;
16. event dispatch architecture ;
17. event → Order context ;
18. Service matching ;
19. recipient resolution ;
20. CUSTOM recipient ;
21. manual communications ;
22. snapshots ;
23. queue ;
24. scheduler ;
25. retry ;
26. callbacks ;
27. senders ;
28. provider configuration strategy ;
29. secrets ;
30. permissions ;
31. multi-org ;
32. API routes ;
33. filters/sorts ;
34. Phase 3 regressions ;
35. Phase 5 integration ;
36. POD/Claim integration ;
37. tests ;
38. éléments exclus.

---

# 192. Audit statuses

Mettre à jour :

```text
docs/backend/statuses-global-audit.md
```

Confirmer :

```text
src = order_communications
```

avec tous les codes CommunicationStatus.

Ne pas créer `status_id`.

---

# 193. Rapport final

Créer :

```text
docs/frontend/phase-9-final-report.md
```

Inclure :

1. branche de base ;
2. branche Phase 9 ;
3. Git Author ;
4. Git Committer ;
5. absence Claude/Anthropic ;
6. consolidation Phase 3 ;
7. Templates ;
8. Template renderer ;
9. variables ;
10. Rules ;
11. events ;
12. conditions ;
13. delays ;
14. automation ;
15. recipient resolver ;
16. manual communications ;
17. scheduled communications ;
18. queue ;
19. senders ;
20. retry ;
21. cancellation ;
22. delivery/read ;
23. attachments ;
24. snapshots ;
25. statuses ;
26. permissions ;
27. multi-org ;
28. API Layer ;
29. Query Keys ;
30. Types ;
31. Zod ;
32. audit ;
33. security ;
34. tests ;
35. E2E ;
36. regressions ;
37. différences DB/UML ;
38. éléments exclus ;
39. risques ;
40. prochaine phase.

Conclusion :

```text
FRONTEND_PHASE_9_READY
```

ou :

```text
FRONTEND_PHASE_9_NOT_READY
```

---

# 194. Git Branch

Identifier la branche contenant la Phase 8 validée.

Créer :

```bash
git checkout <BRANCHE_PHASE_8_VALIDEE>
git checkout -b feature/frontend-phase-9-communication-rules
```

Si existe :

```bash
git checkout feature/frontend-phase-9-communication-rules
```

Ne pas travailler directement sur main si les phases frontend ne sont pas fusionnées.

Ne pas auto-merge.

Ne pas auto-push.

---

# 195. Git Identity

Avant commit :

```bash
git config user.name
git config user.email
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
```

Interdit :

```text
Badr
Badr
Co-authored-by: Badr
Co-authored-by: Badr
Generated-by: Badr
Generated-by: Badr
```

Ne jamais inventer l’email humain.

Commit recommandé :

```bash
git add .
git commit -m "feat(frontend): implement phase 9 communication automation"
```

---

# 196. Interdictions absolues

Ne pas :

- recréer le module manuel Phase 3 ;
- créer CommunicationRecipient ;
- créer Notification ;
- créer NotificationTemplate ;
- créer EmailLog ;
- créer SmsLog ;
- créer WhatsappLog ;
- créer PushNotificationLog ;
- créer CommunicationQueue ;
- créer ScheduledCommunication ;
- créer CommunicationProvider ;
- créer CommunicationProviderConfiguration ;
- créer MessageThread ;
- créer Conversation ;
- créer Webhook ;
- créer CommunicationStatusHistory ;
- créer retryCount ;
- créer maxAttempts ;
- créer priority ;
- créer cc/bcc/replyTo ;
- ajouter channel dans CommunicationRule ;
- ajouter eventType dans OrderCommunication ;
- ajouter status_id ;
- hardcoder couleurs de status ;
- inventer un CommunicationEventType ;
- inventer un RecipientRole ;
- inventer une unité de delay sans backend ;
- inventer un DSL conditions ;
- utiliser eval ;
- exécuter PHP/SQL depuis Template/Rule ;
- accéder arbitrairement aux Models ;
- faire l’automatisation dans React ;
- envoyer une communication automatiquement après un PATCH frontend ;
- recalculer une communication historique ;
- recalculer le destinataire historique ;
- supprimer historique SENT ;
- modifier SENT/DELIVERED/READ ;
- supprimer Document lors du retrait Attachment ;
- exposer provider secrets ;
- afficher providerResponse brut sensible ;
- créer une table par channel ;
- créer un fournisseur métier absent du diagramme ;
- casser Phase 3 ;
- casser Phase 5 ;
- pousser automatiquement ;
- attribuer commit à Claude/Anthropic ;
- laisser des TODO.

---

# 197. Vérifications Frontend

Exécuter les scripts réellement présents :

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

Si E2E :

```bash
npm run test:e2e
```

---

# 198. Vérifications Backend

Si correction backend nécessaire :

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
php artisan migrate:status
php artisan route:list --path=api/v1
```

Si queues/scheduler configurés, vérifier les commandes/tests existants sans lancer de process production permanent.

---

# 199. Vérifications sécurité

Confirmer :

```text
aucun eval
aucun PHP template
aucun SQL conditions
variables whitelistées
conditions déclaratives
provider secrets masqués
providerResponse sanitized
cross-org refusé
Document cross-org refusé
recipient validation par channel
sent snapshots immuables
```

---

# 200. Vérifications de non-régression

Tester au minimum :

```text
Phase 3:
Tracking / POD / Claims / manual communications

Phase 5:
Planning / SERVICE_PLANNED / DRIVER_ASSIGNED

Phase 6:
Billing inchangé

Phase 8:
Integrations inchangées
```

Ne pas supprimer des fonctionnalités déjà validées.

---

# 201. Critères READY

La Phase 9 n’est READY que si :

```text
Templates fonctionnels
Rules fonctionnelles
Automation backend fonctionne
Events exacts
Conditions sécurisées
Delay fonctionnel
Recipients résolus
Manual communication préservée
Scheduled fonctionne
Queue fonctionne
Retry fonctionne
Attachments fonctionnent
Snapshots immuables
OrderCommunication statuses centralisés
aucun status_id
permissions appliquées
multi-org protégé
tests passent
build passe
Phase 3 non régressée
```

---

# 202. Suite

Ne pas commencer automatiquement la phase suivante.

Après validation utilisateur :

```text
FRONTEND PHASE 10 — CONSOLIDATION FINALE
```

Cette phase devra couvrir :

```text
UX globale
navigation
permissions
security hardening
performance
API consistency
responsive
error handling
accessibility
tests E2E transverses
cleanup
documentation
release readiness
```

sans introduire de nouvelles entités métier.
