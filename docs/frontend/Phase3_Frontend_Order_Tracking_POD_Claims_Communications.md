# Tricolis V2 — Frontend Phase 3

## Suivi commande, POD, réclamations et communications manuelles

Tu es un architecte frontend senior spécialisé en React, TypeScript, Vite, TanStack Query, interfaces Backoffice métier et applications de transport/logistique.

Tu travailles sur **Tricolis V2**.

Les Frontend Phases 1 et 2 sont terminées.

Le backend Laravel correspondant existe déjà.

Ta mission consiste à développer uniquement :

# FRONTEND PHASE 3 — SUIVI, POD, RÉCLAMATIONS ET COMMUNICATIONS DE COMMANDE

Cette phase doit principalement enrichir la fiche :

```text
/orders/:id
```

avec les fonctions opérationnelles qui interviennent après ou pendant l’exécution d’une commande.

---

# 0. Périmètre métier exact

Implémenter uniquement le frontend correspondant aux classes suivantes :

```text
TrackingEvent
ProofOfDelivery
Claim

CommunicationTemplate
OrderCommunication
CommunicationAttachment
```

Pour la partie Communication :

```text
CommunicationRule
```

est **explicitement hors périmètre de cette phase**.

Ne pas développer son écran, son CRUD, ses conditions, ses événements automatiques ou ses délais.

Cette phase traite uniquement les **communications manuelles ou explicitement déclenchées par l’utilisateur depuis une commande**.

---

# 1. Objectif fonctionnel

À la fin de la phase, depuis une commande, un utilisateur autorisé doit pouvoir :

```text
voir l’historique de tracking
voir les preuves de livraison
créer et suivre une réclamation
consulter les communications déjà envoyées
créer une nouvelle communication à partir d’un template
sélectionner le destinataire
prévisualiser le message
ajouter des pièces jointes
envoyer ou programmer la communication si l’API le permet
voir son statut d’envoi
voir les erreurs d’envoi
```

Exemple métier :

```text
Commande
→ Client absent / ne répond pas
→ Nouvelle communication
→ choisir un template déjà configuré "Client absent"
→ destinataire DELIVERY_CONTACT ou CUSTOMER
→ prévisualiser l’e-mail
→ joindre éventuellement un document
→ envoyer
→ conserver l’historique dans OrderCommunication
```

Le contenu métier du message doit provenir d’un :

```text
CommunicationTemplate
```

déjà configuré.

Ne pas demander à l’utilisateur de recréer manuellement tout le message à chaque envoi si un template adapté existe.

---

# 2. Sources de vérité obligatoires

Utiliser uniquement :

```text
Conception/diagramme/00-diagramme-classes-partagees.puml
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

Analyser également le backend réel :

```text
routes
Models
Form Requests
API Resources
Policies
PermissionSeeder
Actions
Enums
```

Lire si présents :

```text
docs/backend/phase-5-analysis.md
docs/backend/phase-5-final-report.md
docs/backend/phase-9-analysis.md
docs/backend/phase-9-final-report.md
docs/frontend/phase-2-final-report.md
docs/frontend/backend-api-contract.md
```

Ordre de priorité :

```text
1. Diagrammes officiels
2. Backend réel
3. Documentation backend
4. Frontend existant
5. Ancien prompt
```

Ne jamais inventer une route ou une propriété parce qu’elle paraît logique.

---

# 3. Règle Git obligatoire

## 3.1 Branche

Avant toute modification :

```bash
git status
git branch --show-current
git log --oneline --decorate -10
```

Identifier la branche qui contient réellement la Frontend Phase 2 validée.

Créer la branche :

```bash
git checkout <BRANCHE_PHASE_2_VALIDEE>
git checkout -b feature/frontend-phase-3-order-followup
```

Si elle existe :

```bash
git checkout feature/frontend-phase-3-order-followup
```

Ne jamais travailler directement sur `main` si `main` ne contient pas encore le frontend validé.

Ne jamais fusionner automatiquement.

Ne jamais pousser automatiquement.

## 3.2 Identité du commit

Avant tout commit :

```bash
git config user.name
git config user.email
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
```

Le commit doit être attribué uniquement au propriétaire humain du repository.

Interdiction absolue :

```text
Badr
Badr
Co-authored-by: Badr
Co-authored-by: Badr
Generated-by: Badr
Generated-by: Badr
```

Si l’identité locale est incorrecte :

```bash
git log --all --format='%an <%ae>' | sort -u
```

retrouver l’identité humaine déjà utilisée.

Ne jamais inventer un e-mail Git.

À la fin :

```bash
git add .
git commit -m "feat(frontend): implement phase 3 tracking claims pod and communications"
```

Puis vérifier :

```bash
git show -s --format=fuller HEAD
git log -1 --pretty='%an <%ae>%n%cn <%ce>%n%B'
```

---

# 4. Architecture frontend

Ajouter ou compléter :

```text
src/modules/
├── tracking/
├── pod/
├── claims/
└── communications/
```

Structure recommandée :

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

Même règle que les phases précédentes :

- environ 200 lignes maximum par fichier ;
- logique API séparée ;
- Query hooks séparés ;
- types séparés ;
- Zod séparé ;
- aucun composant géant ;
- aucun fetch directement dans JSX ;
- aucun mock permanent ;
- aucun TODO.

---

# 5. Extension de OrderDetail

La fiche commande Phase 2 contient actuellement :

```text
Résumé
Lignes
Colis
Services
Documents
Historique
```

Ajouter :

```text
Tracking
POD
Réclamations
Communications
```

Ordre recommandé :

```text
Résumé
Lignes
Colis
Services
Tracking
POD
Réclamations
Communications
Documents
Historique
```

Ne pas refaire `OrderDetailPage`.

Réutiliser sa structure de tabs.

---

# 6. TrackingEvent — modèle exact

Respecter le modèle :

```text
TrackingEvent
- id
- organizationId
- orderId
- orderServiceId
- tourId
- tourStopId
- eventType
- status
- description
- latitude
- longitude
- occurredAt
- createdBy
```

Ne pas inventer :

```text
driverId
vehicleId
locationName
accuracy
speed
heading
deviceId
metadata
```

---

# 7. Tracking — objectif frontend

Le Tracking d’une commande doit être principalement présenté comme un historique chronologique.

Créer :

```text
OrderTrackingTab
TrackingTimeline
TrackingEventCard
TrackingEventDetailDrawer
```

Afficher les valeurs réellement retournées par le backend.

Ne pas inventer une liste `eventType` si le backend conserve actuellement ce champ en string.

---

# 8. Tracking — détails

Pour chaque événement afficher si disponible :

```text
eventType
status
description
occurredAt
OrderService
Tour
TourStop
createdBy
latitude
longitude
```

Si latitude/longitude existent :

- afficher les coordonnées ;
- éventuellement un bouton "Voir sur la carte" seulement si un composant carte existe déjà ;
- ne pas développer ici une solution de live tracking.

---

# 9. Tracking — création

Le Tracking peut rester en lecture seule.

Ne montrer :

```text
+ Ajouter un événement
```

que si :

- l’endpoint existe ;
- l’utilisateur possède la permission ;
- l’usage interne est prévu par le backend.

Ne pas inventer des événements métier dans React.

---

# 10. ProofOfDelivery — modèle exact

Respecter :

```text
ProofOfDelivery
- id
- orderId
- orderServiceId
- tourStopId
- recipientName
- signatureDocumentId
- photoDocumentId
- remark
- deliveredAt
- createdBy
```

La signature et la photo utilisent :

```text
Document
```

Ne pas créer de nouvelles entités Signature ou DeliveryPhoto.

---

# 11. POD — onglet commande

Créer :

```text
OrderPodTab
PodList
PodCard
PodDetailDialog
```

Afficher :

```text
Service
Destinataire
Date de livraison
Remarque
Signature
Photo
Créé par
```

Réutiliser le module Documents existant pour preview/téléchargement.

Ne jamais utiliser directement un `storagePath`.

---

# 12. POD — création

Créer une UI de création uniquement si l’API et les permissions l’autorisent.

Champs :

```text
orderServiceId
tourStopId
recipientName
signatureDocumentId
photoDocumentId
remark
deliveredAt
```

Ne pas rendre signature/photo obligatoires si le backend les considère facultatives.

---

# 13. Claim — modèle exact

Respecter :

```text
Claim
- id
- organizationId
- customerId
- orderId
- orderServiceId
- tourId
- title
- description
- claimType
- cause
- decision
- followUp
- result
- cost
- status
- createdBy
- responsibleUserId
- createdAt
- closedAt
```

Ne pas inventer :

```text
claimNumber
severity
priority
comments
attachments
claimActions
claimHistory
resolution
```

---

# 14. Réclamations dans Order Detail

Créer :

```text
OrderClaimsTab
OrderClaimsList
ClaimCard
ClaimDetailDrawer
```

Afficher selon API :

```text
Titre
Type
Statut
Service lié
Tour lié
Responsable
Coût
Créé le
Fermé le
```

Actions selon permission/API :

```text
Créer
Voir
Modifier
Clôturer
Supprimer
```

---

# 15. Création Claim depuis Order

Créer :

```text
CreateClaimDialog
ClaimForm
```

Depuis OrderDetail :

```text
orderId
customerId
organizationId
```

doivent provenir du contexte de la commande.

L’utilisateur ne doit pas pouvoir sélectionner un Customer d’une autre commande.

Permettre éventuellement :

```text
orderServiceId
tourId
responsibleUserId
```

si l’API les expose.

---

# 16. Formulaire Claim

Sections :

```text
Réclamation
├── Titre
├── Type
├── Description
├── Cause
└── Statut si éditable

Contexte
├── Service
├── Tour
└── Responsable

Traitement
├── Décision
├── Suivi
├── Résultat
├── Coût
└── Date fermeture
```

À la création, ne pas rendre obligatoires les informations de traitement si le backend les accepte nulles.

---

# 17. Page globale Réclamations

Créer :

```text
/claims
```

uniquement si l’API globale existe.

Objectif : suivre les réclamations de l’organisation.

Filtres uniquement selon backend réel.

Ne pas inventer de filtre `severity`.

---

# 18. COMMUNICATION — périmètre strict

Pour cette phase, utiliser uniquement :

```text
CommunicationTemplate
OrderCommunication
CommunicationAttachment
```

Ne pas implémenter :

```text
CommunicationRule
```

Ne créer aucun :

```text
CommunicationRulePage
CommunicationRuleForm
RuleEditor
ConditionsBuilder
EventAutomation
AutomaticCommunication
```

Le champ :

```text
communicationRuleId
```

peut rester dans le type `OrderCommunication` car il existe dans le contrat.

Mais :

- il est lecture seule s’il est présent ;
- il n’est pas édité ;
- pour une communication manuelle, utiliser null si le backend l’autorise.

---

# 19. CommunicationTemplate — modèle exact

Respecter :

```text
CommunicationTemplate
- id
- organizationId
- serviceId
- code
- name
- channel
- templateType
- subjectTemplate
- bodyTemplate
- language
- availableVariables
- isDefault
- isActive
- createdAt
- updatedAt
```

`serviceId` est facultatif selon le diagramme.

---

# 20. Enums communication

## CommunicationChannel

```text
EMAIL
SMS
WHATSAPP
PUSH_NOTIFICATION
INTERNAL_NOTIFICATION
```

## CommunicationTemplateType

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

## CommunicationStatus

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
Save in table statuses

## RecipientRole

```text
CUSTOMER
LOAD_CONTACT
DELIVERY_CONTACT
BILLING_CONTACT
INTERNAL_USER
CUSTOM
```

Ne pas ajouter :

```text
CUSTOMER_ABSENT
NO_RESPONSE
```

Pour "Client absent" ou "Client ne répond pas", utiliser un template avec un code/nom métier et `templateType = CUSTOM` si aucun type officiel ne correspond.

---

# 21. Gestion des templates

Créer :

```text
/communication-templates
```

Pages :

```text
CommunicationTemplateListPage
CommunicationTemplateCreatePage
CommunicationTemplateEditPage
CommunicationTemplateDetailPage
```

Composants :

```text
CommunicationTemplateForm
CommunicationTemplatePreview
TemplateVariablePicker
```

Cette partie sert à préparer les messages avant utilisation dans une commande.

---

# 22. Exemple template "Client absent"

Exemple fonctionnel autorisé :

```text
Code : CUSTOMER_ABSENT_EMAIL
Nom : Client absent
Channel : EMAIL
TemplateType : CUSTOM
Language : fr
SubjectTemplate : Absence lors de notre passage - {{orderNumber}}
BodyTemplate : ...
```

Le code `CUSTOMER_ABSENT_EMAIL` est un code de template.

Ce n’est pas un nouvel enum.

Même principe pour :

```text
CUSTOMER_NO_RESPONSE
```

---

# 23. Variables du template

`availableVariables` doit venir du backend/configuration réelle.

Afficher les variables autorisées dans l’éditeur.

Ne pas inventer une liste indépendante.

Si un endpoint backend de preview/rendu existe, l’utiliser en priorité.

---

# 24. OrderCommunication — modèle exact

Respecter :

```text
OrderCommunication
- id
- organizationId
- orderId
- templateId
- communicationRuleId
- channel
- communicationType
- recipientRole
- recipientName
- recipientEmail
- recipientPhone
- subject
- body
- templateVariables
- status
- scheduledAt
- queuedAt
- sentAt
- deliveredAt
- readAt
- failedAt
- providerMessageId
- providerResponse
- errorMessage
- createdBy
- createdAt
- updatedAt
```

---

# 25. OrderCommunication = snapshot historique

Une communication envoyée conserve :

```text
recipientName
recipientEmail
recipientPhone
subject
body
templateVariables
```

Même si le template change plus tard.

Dans l’historique, afficher les snapshots d’OrderCommunication.

Ne jamais reconstruire une ancienne communication depuis le template actuel.

---

# 26. Onglet Communications

Créer :

```text
OrderCommunicationsTab
OrderCommunicationList
OrderCommunicationCard
OrderCommunicationDetailDrawer
```

Afficher :

```text
Canal
Type
Template
Destinataire
Sujet
Statut
Programmé le
Envoyé le
Livré le
Lu le
Échec le
Erreur
Créé par
```

Afficher le body complet dans le détail.

---

# 27. Nouvelle communication depuis Order

Ajouter :

```text
+ Nouvelle communication
```

selon permission.

Créer :

```text
CreateOrderCommunicationDialog
```

Workflow :

```text
1. Choisir le template
2. Choisir le destinataire
3. Prévisualiser le message
4. Ajouter les pièces jointes
5. Envoyer / programmer
```

---

# 28. Choix du template

Afficher uniquement les templates actifs et accessibles dans l’Organization active.

Afficher :

```text
Nom
Canal
Type
Langue
Service éventuel
```

Exemple :

```text
Client absent
EMAIL
CUSTOM
FR
```

---

# 29. Cas métier "Client absent / ne répond pas"

Workflow attendu :

```text
Order
→ Communications
→ Nouvelle communication
→ sélectionner "Client absent"
→ sélectionner DELIVERY_CONTACT ou CUSTOMER
→ vérifier recipientEmail
→ prévisualiser subject/body
→ ajouter éventuellement un Document
→ envoyer
→ conserver l’OrderCommunication dans l’historique
```

Même mécanisme pour :

```text
Client ne répond pas
Rappel rendez-vous
Confirmation
Échec de livraison
POD disponible
```

si le template existe.

Ne pas créer un bouton hardcodé par scénario.

Le système est piloté par les templates.

---

# 30. Sélection destinataire

Utiliser uniquement :

```text
CUSTOMER
LOAD_CONTACT
DELIVERY_CONTACT
BILLING_CONTACT
INTERNAL_USER
CUSTOM
```

Le backend doit rester responsable de la résolution métier lorsque l’API le prévoit.

Pour `CUSTOM`, permettre la saisie des coordonnées nécessaires.

Ne pas créer `CommunicationRecipient`.

---

# 31. Comportement par canal

## EMAIL

Afficher :

```text
recipientEmail
subject
body
```

## SMS / WHATSAPP

Afficher :

```text
recipientPhone
body
```

Ne pas imposer de subject.

## PUSH / INTERNAL

Supporter seulement si le backend les rend réellement opérationnels.

Ne pas simuler un envoi.

---

# 32. Template préremplit la communication

Après sélection du template :

```text
channel
communicationType
subject
body
```

doivent être préremplis selon le rendu backend/contrat réel.

Une modification du snapshot avant envoi n’est permise que si l’API l’autorise.

Ne jamais modifier le `CommunicationTemplate` lors de la préparation d’un OrderCommunication.

---

# 33. CommunicationAttachment

Respecter :

```text
CommunicationAttachment
- id
- communicationId
- documentId
- fileNameSnapshot
- mimeTypeSnapshot
- createdAt
```

Créer :

```text
CommunicationAttachmentList
CommunicationAttachmentPicker
```

Réutiliser `Document`.

Ne pas créer un système d’upload parallèle.

---

# 34. Statut communication

Créer :

```text
CommunicationStatusBadge
```

avec les statuts officiels.

Ne pas ajouter :

```text
RETRYING
PROCESSING
ARCHIVED
```

---

# 35. Envoi / programmation / retry / cancel

Créer ces actions seulement si les routes backend existent.

Le frontend ne doit jamais simuler :

```text
send
schedule
retry
cancel
```

Après mutation, rafraîchir la communication et afficher le statut réel.

---

# 36. Menu

Ajouter :

```text
Configuration
└── Templates de communication
```

Ne pas ajouter :

```text
Règles de communication
```

dans cette phase.

L’accès aux communications se fait principalement via :

```text
Order -> Communications
```

---

# 37. Permissions

Analyser les permissions réelles du backend avant d’implémenter les guards.

Ne jamais inventer les codes.

Modules concernés :

```text
tracking_events
proofs_of_delivery
claims
communication_templates
order_communications
communication_attachments
```

---

# 38. Multi-organisation

Au changement d’Organization, invalider :

```text
orders
tracking
pods
claims
communicationTemplates
orderCommunications
```

Ne jamais proposer un template d’une autre organisation.

---

# 39. Query keys

Créer :

```text
trackingKeys
podKeys
claimKeys
communicationTemplateKeys
orderCommunicationKeys
```

Ne pas invalider tout le cache global.

---

# 40. API Layer

Créer :

```text
modules/tracking/api/tracking.api.ts
modules/pod/api/pod.api.ts
modules/claims/api/claims.api.ts
modules/communications/api/communication-templates.api.ts
modules/communications/api/order-communications.api.ts
```

Aucun fetch directement dans les composants.

---

# 41. Types TypeScript

Créer depuis les Resources réelles :

```text
TrackingEvent
ProofOfDelivery
Claim
CommunicationTemplate
OrderCommunication
CommunicationAttachment
CommunicationChannel
CommunicationTemplateType
CommunicationStatus
RecipientRole
```

Ne pas créer `CommunicationRule` comme module fonctionnel.

Le champ `communicationRuleId?: string | null` peut rester sur OrderCommunication.

---

# 42. Zod

Créer :

```text
trackingEventSchema
proofOfDeliverySchema
claimSchema
communicationTemplateSchema
orderCommunicationSchema
communicationAttachmentSchema
```

Ne pas créer :

```text
communicationRuleSchema
```

---

# 43. i18n et design

Ajouter :

```text
tracking.*
pod.*
claims.*
communications.*
communicationTemplates.*
```

Réutiliser le design des Phases 1 et 2 :

```text
Tabs
Cards
DataTable
Drawer
Dialog
Badge
Timeline
SectionCard
DocumentPreview
```

Ne pas créer un nouveau design system.

---

# 44. Tests Tracking

Tester :

```text
timeline
ordre chronologique
détail
coordonnées
filtres si backend
permission
Organization isolation
```

---

# 45. Tests POD

Tester :

```text
liste POD
signature Document
photo Document
preview
download sécurisé
création si autorisée
permission
Organization isolation
```

---

# 46. Tests Claims

Tester :

```text
liste par Order
création depuis Order
OrderService optionnel si backend
Tour optionnel
responsable
mise à jour
clôture si endpoint
page globale si API
permission
Organization isolation
```

---

# 47. Tests CommunicationTemplate

Tester :

```text
liste
création
édition
channel
templateType
language
availableVariables
preview
isActive
service facultatif
permission
Organization isolation
```

---

# 48. Tests OrderCommunication

Tester :

```text
liste d’une commande
création avec template
communicationRuleId null pour communication manuelle
CUSTOMER
LOAD_CONTACT
DELIVERY_CONTACT
BILLING_CONTACT
CUSTOM
EMAIL
SMS si supporté
WHATSAPP si supporté
preview
snapshot subject/body
attachments
status
send si API
schedule si API
failed error
permission
Organization isolation
```

---

# 49. Test métier "Client absent"

Créer un test :

```text
ouvrir Order
→ Communications
→ Nouvelle communication
→ sélectionner template "Client absent"
→ canal EMAIL
→ sélectionner DELIVERY_CONTACT
→ subject/body préremplis
→ envoyer
→ communication présente dans l’historique
→ statut venant de l’API
```

Ne jamais utiliser un enum `CUSTOMER_ABSENT`.

---

# 50. E2E Phase 3

Si Playwright/Cypress existe :

```text
Login
→ Organization
→ Orders
→ ouvrir Order
→ Tracking
→ voir timeline
→ POD
→ ouvrir preuve
→ Réclamations
→ créer réclamation
→ Communications
→ choisir template
→ envoyer communication
→ vérifier historique
```

---

# 51. Performance

Charger à la demande :

```text
Tracking
POD
Claims
Communications
```

Ne jamais effectuer ces requêtes pour chaque ligne de la liste `/orders`.

---

# 52. Sécurité

Ne jamais exposer inutilement :

```text
providerResponse complet
secret
token
clé fournisseur
```

Ne jamais afficher les secrets des fournisseurs EMAIL/SMS/WhatsApp.

---

# 53. Interdictions critiques

Ne pas :

- développer CommunicationRule ;
- créer CommunicationRulePage ;
- créer des règles automatiques ;
- créer ConditionsBuilder ;
- créer CommunicationRecipient ;
- créer EmailLog ;
- créer SmsLog ;
- créer WhatsappLog ;
- créer Notification ;
- créer Webhook ;
- créer ClaimComment ;
- créer ClaimAttachment ;
- créer ClaimAction ;
- créer ClaimStatusHistory ;
- créer TrackingSession ;
- créer LiveLocation ;
- créer Signature comme entité ;
- créer DeliveryPhoto comme entité ;
- inventer CUSTOMER_ABSENT comme enum ;
- inventer NO_RESPONSE comme enum ;
- inventer des routes ;
- inventer des permissions ;
- développer Planning/Tours ;
- développer Billing ;
- pousser automatiquement ;
- attribuer le commit à Claude/Anthropic.

---

# 54. Analyse finale obligatoire

Créer/mettre à jour :

```text
docs/frontend/phase-3-analysis.md
```

Documenter :

- endpoints Tracking ;
- endpoints POD ;
- endpoints Claim ;
- endpoints CommunicationTemplate ;
- endpoints OrderCommunication ;
- endpoints CommunicationAttachment ;
- permissions ;
- Resources ;
- enums ;
- routes absentes ;
- comportements non implémentés ;
- confirmation `CommunicationRule` hors scope.

---

# 55. Rapport final

Créer :

```text
docs/frontend/phase-3-final-report.md
```

Inclure :

1. branche de base ;
2. branche Phase 3 ;
3. identité Git Author ;
4. identité Git Committer ;
5. confirmation absence Claude/Anthropic ;
6. tabs OrderDetail ajoutés ;
7. Tracking ;
8. POD ;
9. Claims ;
10. CommunicationTemplate ;
11. OrderCommunication ;
12. CommunicationAttachment ;
13. confirmation CommunicationRule non implémenté ;
14. routes frontend ;
15. endpoints réels ;
16. permissions ;
17. query keys ;
18. types ;
19. Zod ;
20. tests ;
21. E2E ;
22. APIs manquantes ;
23. éléments exclus ;
24. risques ;
25. prochaine phase.

Conclusion obligatoire :

```text
FRONTEND_PHASE_3_READY
```

ou :

```text
FRONTEND_PHASE_3_NOT_READY
```

---

# 56. Vérifications finales

Exécuter les scripts réels du projet, par exemple :

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

Si E2E configuré :

```bash
npm run test:e2e
```

Puis :

```bash
git status
git diff --check
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
git log -1 --pretty=fuller
```

Ne pas pousser automatiquement.

Ne pas commencer la phase suivante sans validation explicite de l’utilisateur.
