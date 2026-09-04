# Tricolis V2 — FRONTEND PHASE 6 MASTER FINAL V2

## Tarification + facturation client + décomptes fournisseur + export automatique des factures clôturées
### REST API / FTP / SFTP / JSON / XML + historique d’envoi + statuses centralisés

> **Ce fichier est la source de travail complète pour la Frontend Phase 6.**
>
> Cette phase couvre à la fois :
>
> 1. la facturation des prestations réalisées pour les **clients du transporteur** ;
> 2. les décomptes dus aux **fournisseurs / transporteurs partenaires** ;
> 3. l’envoi automatique des factures clôturées vers les systèmes externes des clients ;
4. le calcul des tarifs globalement pour le transporteur et spécifiquement par Customer ;
5. les formules sécurisées par prestation, poids, volume, distance et zones/code postal ;
6. les matrices tarifaires optionnelles globales ou par Customer.

---

# 1. Mission

Tu es un architecte frontend/backend senior spécialisé en :

- React ;
- TypeScript ;
- Vite ;
- TanStack Query ;
- Laravel ;
- MySQL 8 ;
- API REST ;
- facturation B2B ;
- exports JSON/XML ;
- FTP/SFTP ;
- intégrations API client ;
- queues Laravel ;
- transactions ;
- sécurité multi-organisation ;
- gestion sécurisée de secrets.

Tu travailles sur **Tricolis V2**.

Les Frontend Phases 1 à 5 sont terminées ou validées.

Ta mission est d’implémenter :

# FRONTEND PHASE 6 — FACTURATION CLIENT & DÉCOMPTES FOURNISSEUR

avec une extension contrôlée de la partie intégration client nécessaire à la facturation.

---

# 2. Besoin métier fondamental

Tricolis est un transporteur.

Il transporte des commandes appartenant à ses clients :

```text
Organization / Transporteur
    ↓
Customer
    ↓
Order
    ↓
OrderService
```

La facturation client doit donc être :

```text
Customer
    ↓
Invoice
    ↓
InvoiceLine
    ↓
OrderService facturé
```

Autrement dit :

> la facture appartient au `Customer` et contient les prestations/services réalisés pour ses commandes.

Ne pas facturer une commande entière comme une seule ligne opaque.

La granularité de facturation prévue par le modèle est :

```text
OrderService
→ InvoiceLine
```

---

# 3. Deux côtés de facturation

Cette phase couvre deux flux séparés.

## A. Facturation CLIENT

```text
Customer
→ Order
→ OrderService
→ InvoiceLine
→ Invoice
```

C’est ce que le transporteur facture à son client.

## B. Décompte FOURNISSEUR

```text
Provider
→ OrderService réellement réalisé
→ ProviderSettlementLine
→ ProviderSettlement
```

C’est ce que le transporteur doit au fournisseur/prestataire.

Ne pas mélanger les prix client et les coûts fournisseur.

---

# 4. Sources de vérité obligatoires

Utiliser :

```text
Conception/diagramme/00-diagramme-classes-partagees.puml
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

Analyser également :

```text
database/migrations/
app/Modules/
Models
Actions
Services
Jobs
Events
Listeners
Form Requests
API Resources
Policies
PermissionSeeder
Seeders
routes/
docs/backend/
docs/frontend/
```

Ordre de priorité :

```text
1. Schéma DB réellement validé
2. Backend réellement implémenté
3. Diagrammes officiels
4. Documentation
5. Anciennes implémentations
```

Si un écart existe :

- ne pas inventer une correction silencieuse ;
- documenter ;
- préserver les données existantes ;
- respecter la décision validée du projet.

---

# 5. Classes de facturation exactes

Implémenter/utiliser :

```text
Invoice
InvoiceLine
InvoiceLineAddressSnapshot
ProviderSettlement
ProviderSettlementLine
```

Pour l’envoi externe des factures, réutiliser les classes d’intégration déjà prévues :

```text
CustomerExportConfiguration
ExportJob
ExportFormat
ExportTransport
```

Ne pas créer :

```text
InvoiceExport
InvoiceExportLine
InvoiceDelivery
InvoiceTransmission
InvoiceApiLog
InvoiceFtpLog
InvoiceSftpLog
ExternalInvoice
AccountingExport
FileTransferLog
```

---

# 6. Pourquoi utiliser CustomerExportConfiguration + ExportJob

Le système possède déjà :

```text
Customer
    1
    ↓
0..* CustomerExportConfiguration

CustomerExportConfiguration
    1
    ↓
0..* ExportJob
```

Une facture clôturée est donc exportée via :

```text
Invoice
→ Customer
→ CustomerExportConfiguration
→ ExportJob
→ transport externe
```

Ne pas créer un deuxième moteur d’intégration spécifique à la facturation.

---

# 7. Modèle exact — Invoice

Respecter :

```text
Invoice
- id: ULID
- legacyId: bigint
- organizationId: ULID
- customerId: ULID
- invoiceNumber: string
- invoiceDate: date
- periodFrom: date
- periodTo: date
- currencyCode: string
- subtotal: decimal
- taxTotal: decimal
- total: decimal
- externalReference: string
- remark: text
- status: string
- createdAt: datetime
```

Ne pas ajouter :

```text
dueDate
paymentStatus
paidAt
validatedAt
validatedBy
issuedBy
billingAddressId
invoiceType
accountingReference
exportStatus
sentAt
exportedAt
```

L’état d’envoi externe appartient à :

```text
ExportJob
```

pas à `Invoice`.

---

# 8. Relations Invoice

Respecter :

```text
Customer "1" -- "0..*" Invoice
Invoice "1" *-- "1..*" InvoiceLine
```

Eloquent :

```text
Invoice belongsTo Organization
Invoice belongsTo Customer
Invoice hasMany InvoiceLine
```

Une facture doit obligatoirement contenir au moins une ligne.

---

# 9. Modèle exact — InvoiceLine

Respecter :

```text
InvoiceLine
- id: ULID
- legacyId: bigint
- invoiceId: ULID
- orderServiceId: ULID
- orderId: ULID
- lineNumber: int
- serviceCode: string
- description: string
- customerOrderReference: string
- quantity: decimal
- unitPrice: decimal
- discountRate: decimal
- taxRate: decimal
- totalExcludingTax: decimal
- totalIncludingTax: decimal
- serviceCompletedAt: datetime
- status: string
```

Ne pas ajouter :

```text
unit
productCode
discountAmount
taxAmount
cost
sourceType
pricingSnapshot
```

---

# 10. Cardinalité OrderService → InvoiceLine

Le modèle impose :

```text
OrderService "1" -- "0..1" InvoiceLine
```

Donc :

> un `OrderService` ne peut être facturé qu’une seule fois.

Ne jamais permettre :

```text
même OrderService
→ InvoiceLine facture A
→ InvoiceLine facture B
```

Le backend doit protéger la règle.

Utiliser une contrainte unique sur :

```text
order_service_id
```

si elle existe déjà ou si elle est compatible avec le schéma validé.

---

# 11. Service déjà présent dans facture DRAFT

Tant qu’un `OrderService` appartient à une `InvoiceLine`, il ne doit pas être disponible dans une autre facture.

Si une facture DRAFT est supprimée selon les règles backend :

les lignes correspondantes disparaissent et les services peuvent redevenir éligibles.

Une facture clôturée ne doit pas être supprimable dans cette phase.

---

# 12. Modèle exact — InvoiceLineAddressSnapshot

Respecter :

```text
InvoiceLineAddressSnapshot
- id: ULID
- invoiceLineId: ULID
- addressCode: string
- name: string
- addressLine1: string
- addressLine2: string
- postalCode: string
- city: string
- country: string
```

Ne pas ajouter de FK vers `Address`.

Le snapshot doit rester historique.

Une modification future de l’adresse originale ne doit jamais modifier une facture existante.

---

# 13. Pourquoi le snapshot est important

Exemple :

```text
Facture clôturée le 01/08
Adresse du service :
Rue A, Marrakech
```

Le client modifie ensuite son adresse :

```text
Rue B, Marrakech
```

La facture du 01/08 doit continuer à afficher :

```text
Rue A, Marrakech
```

Ne pas recalculer le snapshot lors de l’affichage.

---

# 14. Modèle exact — ProviderSettlement

Respecter :

```text
ProviderSettlement
- id: ULID
- organizationId: ULID
- providerId: ULID
- settlementNumber: string
- periodFrom: date
- periodTo: date
- subtotal: decimal
- taxTotal: decimal
- total: decimal
- status: string
```

---

# 15. Modèle exact — ProviderSettlementLine

Respecter :

```text
ProviderSettlementLine
- id: ULID
- settlementId: ULID
- orderServiceId: ULID
- description: string
- quantity: decimal
- unitCost: decimal
- totalCost: decimal
```

Ne pas ajouter :

```text
taxRate
taxAmount
status
serviceDate
providerContractId
pricingRuleId
sourceType
```

---

# 16. Cardinalité ProviderSettlementLine

Le modèle impose :

```text
OrderService "1" -- "0..1" ProviderSettlementLine
```

Donc :

> un service ne doit pas être réglé deux fois à un fournisseur.

Le backend doit empêcher le double règlement.

---

# 17. Attention à la replanification Phase 5

Un OrderService peut avoir plusieurs planifications historiques :

```text
OrderService
→ TourStopService OLD
→ TourStopService NEW
```

Pour le décompte fournisseur :

ne pas déterminer le Provider simplement avec :

```text
dernière Tour créée
```

Le backend doit identifier la prestation réellement exécutée et le Provider réellement éligible au règlement selon les données opérationnelles validées.

Exemple :

```text
Provider A -> tentative échouée
Provider B -> livraison réellement terminée
```

Le système ne doit pas payer Provider A simplement parce qu’il existe dans l’historique.

Documenter la règle réelle de sélection du Provider.

---

# 18. Règle globale statuses

Toutes les tables ayant `status` conservent un status **textuel**.

Pour cette phase :

```text
invoices.status
invoice_lines.status
provider_settlements.status
export_jobs.status
```

restent des chaînes.

Ne jamais créer :

```text
status_id
```

Tous les codes utilisés doivent être présents dans :

```text
statuses
```

avec par exemple :

```text
src = invoices
src = invoice_lines
src = provider_settlements
src = export_jobs
```

---

# 19. Facture clôturée — règle métier nouvelle obligatoire

Une facture doit être envoyée aux systèmes externes du Customer **uniquement lorsqu’elle devient clôturée**.

Le concept métier est :

```text
FACTURE CLÔTURÉE
```

Analyser les statuses existants.

Si un code existe déjà pour ce concept :

```text
réutiliser ce code
```

Sinon créer dans `statuses` :

```text
src = invoices
code = CLOSED
label = Clôturée
```

selon les colonnes réelles de `statuses`.

Ne pas créer un enum `InvoiceStatus`.

---

# 20. DRAFT vs CLOSED

Workflow minimum attendu :

```text
DRAFT
  ↓
CLOSED
```

mais réutiliser les autres statuts existants s’ils sont déjà validés.

La règle absolue est :

```text
entrée dans le status métier "Clôturée"
→ déclenchement des exports client
```

Ne pas déclencher l’envoi simplement à la création de facture.

---

# 21. Pas d’envoi avant clôture

Interdit :

```text
Invoice DRAFT
→ REST API client

Invoice DRAFT
→ FTP

Invoice DRAFT
→ SFTP
```

Même si une configuration active existe.

Tant que la facture n’est pas clôturée :

```text
aucun ExportJob automatique de facture
```

---

# 22. Clôture = facture figée

Une facture envoyée à un système externe doit être historiquement stable.

Après clôture :

interdire au minimum :

```text
modifier Customer
ajouter ligne
supprimer ligne
modifier quantité
modifier prix
modifier remise
modifier taxe
modifier snapshot
supprimer facture
```

Le backend doit appliquer la règle.

Ne pas se contenter de désactiver les boutons React.

---

# 23. Pas de réouverture dans cette phase

Ne pas créer automatiquement :

```text
Reopen Invoice
Unclose Invoice
```

Si une règle de réouverture existe déjà dans le backend, l’analyser.

Sinon une facture clôturée reste figée dans cette phase.

Ne pas inventer `CreditNote` pour contourner le problème.

---

# 24. Action de clôture

Ajouter une Action métier backend :

```text
CloseInvoiceAction
```

ou réutiliser l’Action existante équivalente.

Créer une route conforme aux conventions du projet, par exemple :

```text
POST /api/v1/invoices/{invoice}/close
```

Cette route est autorisée par le nouveau besoin métier explicite.

Ne pas créer un bouton générique `/send`.

L’envoi est automatique après clôture.

---

# 25. CloseInvoiceAction — transaction

Pseudo workflow :

```text
BEGIN

lock Invoice

check Organization
check permission
check current status
check Invoice has >= 1 line
check totals
check line consistency
check Customer consistency

transition Invoice.status -> CLOSED

find active CustomerExportConfiguration
for this Customer
for invoice export

create ExportJob rows idempotently

write AuditLog

COMMIT

dispatch ProcessExportJob after commit
```

Le transport distant ne doit pas être exécuté dans la transaction DB.

---

# 26. Envoi asynchrone après COMMIT

Utiliser :

```text
ProcessExportJob
```

ou le Job technique existant.

Important :

```text
Invoice close transaction
COMMIT
↓
queue export jobs
↓
REST / FTP / SFTP
```

Ne pas maintenir une transaction MySQL ouverte pendant un appel réseau.

---

# 27. Si l’envoi externe échoue

Une erreur :

```text
REST 500
timeout
FTP inaccessible
SFTP auth failed
```

ne doit pas rouvrir la facture.

La facture reste :

```text
CLOSED
```

L’échec appartient à :

```text
ExportJob.status
ExportJob.errorMessage
ExportJob.attemptCount
```

L’utilisateur peut ensuite :

```text
Retry
```

si autorisé.

---

# 28. Si aucune configuration d’export n’existe

Tous les Customers n’ont pas forcément un système externe.

Donc :

```text
Invoice CLOSED
Customer sans export config
```

est valide.

Résultat :

```text
facture clôturée
0 ExportJob
```

Frontend :

```text
Aucune intégration de facturation configurée pour ce client.
```

Ne pas bloquer la clôture uniquement parce qu’il n’y a pas de destination externe, sauf future règle explicite.

---

# 29. Si plusieurs configurations sont actives

Un Customer peut avoir plusieurs :

```text
CustomerExportConfiguration
```

Exemple :

```text
REST_API JSON
+
SFTP XML
```

Si les deux sont actives pour les factures clôturées :

créer :

```text
1 ExportJob pour REST_API
1 ExportJob pour SFTP
```

La même facture peut donc être transmise vers plusieurs destinations, sans dupliquer `Invoice`.

---

# 30. Idempotence de la clôture

Si un utilisateur double-clique :

```text
Clôturer
Clôturer
```

ne pas créer des exports en double.

Si Invoice est déjà CLOSED :

- retourner l’état existant ;
- ne pas recréer les ExportJob.

Pour une même combinaison :

```text
configurationId
entityType
entityId
```

éviter les jobs automatiques dupliqués.

Réutiliser un job existant pour les retries.

---

# 31. CustomerExportConfiguration — modèle exact

Respecter :

```text
CustomerExportConfiguration
- id: ULID
- customerId: ULID
- name: string
- exportType: string
- format: ExportFormat
- transport: ExportTransport
- host: string
- port: int
- username: string
- encryptedPassword: text
- remoteDirectory: string
- fileNamePattern: string
- encoding: string
- frequency: string
- settings: JSON
- isActive: boolean
```

Ne pas ajouter de colonnes spécifiques :

```text
apiUrl
apiMethod
apiToken
ftpPath
sftpPath
invoiceMappingId
```

Utiliser les champs existants.

---

# 32. ExportFormat exact

Utiliser uniquement :

```text
XML
CSV
JSON
PDF
```

Besoin minimum de cette phase :

```text
JSON
XML
```

car :

- certains clients veulent une API JSON ;
- certains veulent des fichiers JSON/XML sur FTP/SFTP.

CSV/PDF peuvent rester disponibles uniquement si leur mapping/générateur existe réellement.

---

# 33. ExportTransport exact

Utiliser uniquement :

```text
FTP
SFTP
REST_API
EMAIL
MANUAL
```

Besoin minimum demandé :

```text
REST_API
FTP
SFTP
```

Ne pas ajouter :

```text
FTPS
S3
WEBDAV
HTTP
HTTPS
```

`REST_API` couvre l’API HTTP/HTTPS configurée.

---

# 34. Export invoice : exportType

`exportType` reste un `string`.

Pour les configurations utilisées par cette phase :

utiliser un code métier contrôlé représentant la facture, par exemple :

```text
INVOICE
```

Si le backend contient déjà un code équivalent :

réutiliser celui-ci.

Ne pas créer un enum `ExportType`.

Dans le formulaire Facturation :

```text
exportType = INVOICE
```

peut être forcé/read-only.

---

# 35. Trigger invoice : frequency

`frequency` reste un `string`.

Pour le besoin :

```text
envoyer lorsque la facture est clôturée
```

utiliser un code contrôlé tel que :

```text
ON_INVOICE_CLOSED
```

si aucun code existant ne représente déjà ce trigger.

Ne pas créer d’enum `ExportFrequency`.

Documenter les codes utilisés.

---

# 36. Configurations considérées à la clôture

Lors de la clôture d’une Invoice :

chercher uniquement les configurations :

```text
CustomerExportConfiguration.customerId = Invoice.customerId
isActive = true
exportType = code Invoice
frequency = code clôture
```

et appartenant au bon contexte Organization via Customer.

---

# 37. Configuration côté Customer

Dans :

```text
/customers/:id
```

ajouter un onglet ou sous-section :

```text
Facturation
```

avec :

```text
Factures
Configuration d’export
Historique des envois
```

Cela ne signifie pas encore implémenter tout le portail client.

C’est le Backoffice interne du transporteur, centré sur le Customer.

---

# 38. Pages facturation client

Créer :

```text
/billing/invoices
/billing/invoices/create
/billing/invoices/:id
/billing/invoices/:id/edit
```

ou suivre le routing existant.

Créer :

```text
InvoiceListPage
InvoiceCreatePage
InvoiceDetailPage
InvoiceEditPage
InvoiceForm
InvoiceLinesEditor
InvoiceCloseDialog
```

---

# 39. Liste factures

Afficher :

```text
invoiceNumber
Customer
invoiceDate
periodFrom
periodTo
currencyCode
subtotal
taxTotal
total
status
createdAt
externalReference
```

Filtres selon API réelle :

```text
customerId
invoiceNumber
invoiceDateFrom
invoiceDateTo
periodFrom
periodTo
currencyCode
status
externalReference
```

---

# 40. Factures dans fiche Customer

Dans :

```text
CustomerDetail
→ Facturation
→ Factures
```

la liste doit être automatiquement filtrée :

```text
customerId = Customer courant
```

Ne pas demander à l’utilisateur de resélectionner le Customer.

---

# 41. Création facture depuis Customer

Action :

```text
+ Nouvelle facture
```

Depuis une fiche Customer :

préremplir :

```text
customerId
organizationId
```

et ne pas permettre de sélectionner un Customer d’une autre Organization.

---

# 42. Services facturables

Créer un sélecteur :

```text
BillableOrderServices
```

Afficher uniquement les services :

- appartenant au Customer de la facture ;
- éligibles selon les règles backend ;
- non déjà liés à une InvoiceLine ;
- dans la période si le filtre métier l’exige ;
- dans la bonne Organization.

Ne pas décider côté React seul qu’un service est facturable.

---

# 43. Éligibilité à la facturation

Analyser le workflow réel.

Une règle probable est :

```text
service réellement terminé
```

mais ne pas hardcoder uniquement :

```text
status == COMPLETED
```

si le backend utilise d’autres conditions.

Créer une Action/query backend qui retourne explicitement les services facturables.

---

# 44. Affichage services facturables

Afficher :

```text
Order
Customer reference
Service
Service code
Date service
Quantity
Customer unit price
Customer total price si disponible
Address
Status
Already invoiced = false
```

Les prix finaux de InvoiceLine suivent le modèle InvoiceLine et les règles backend.

---

# 45. Ajouter plusieurs services à une facture

L’utilisateur peut sélectionner plusieurs :

```text
OrderService
```

du même Customer.

Créer les InvoiceLine correspondantes.

Ne jamais autoriser un service d’un Customer différent.

---

# 46. Création atomique Invoice

La création doit être atomique :

```text
Invoice
+ >= 1 InvoiceLine
+ snapshots éventuels
```

Si une ligne échoue :

```text
rollback complet
```

Ne pas laisser :

```text
Invoice vide
InvoiceLine partielle
Snapshot orphelin
```

---

# 47. Calcul InvoiceLine

Réutiliser les règles backend existantes.

Si aucune règle plus spécifique n’existe, la formule minimale à valider est :

```text
base = quantity × unitPrice
discounted = base × (1 - discountRate / 100)
totalExcludingTax = discounted
totalIncludingTax = discounted × (1 + taxRate / 100)
```

Ne pas faire confiance aux totaux envoyés par React.

---

# 48. Totaux Invoice

Centraliser le calcul backend :

```text
subtotal = somme(totalExcludingTax)
total = somme(totalIncludingTax)
taxTotal = total - subtotal
```

si cette règle est confirmée.

React affiche les résultats.

---

# 49. InvoiceLineAddressSnapshot

Au moment de la création de la ligne :

créer le snapshot à partir de l’adresse métier appropriée du service, selon le contrat backend.

Ne pas garder une relation dynamique vers Address.

---

# 50. Détail facture

Tabs/sections :

```text
Résumé
Lignes facturées
Export / Envois
Audit
```

et éventuellement :

```text
Document/PDF
```

uniquement si un endpoint PDF existe déjà.

Ne pas créer `InvoiceDocument`.

---

# 51. Bouton Clôturer

Afficher :

```text
Clôturer la facture
```

uniquement si :

- permission ;
- statut actuel autorise la clôture ;
- facture possède >= 1 ligne ;
- backend la considère clôturable.

Ouvrir :

```text
InvoiceCloseDialog
```

---

# 52. Dialog de clôture

Afficher avant confirmation :

```text
Facture : INV-...
Client : ...
Lignes : N
Total HT : ...
TVA : ...
Total TTC : ...

Intégrations actives :
- REST API / JSON
- SFTP / XML
```

Si aucune :

```text
Aucune intégration externe active.
La facture sera clôturée sans envoi externe.
```

---

# 53. Après clôture UI

Afficher :

```text
Status : Clôturée
```

et rendre les champs métier read-only.

Afficher dans une section :

```text
Envois externes
```

les ExportJob créés.

---

# 54. ExportJob — modèle exact

Respecter :

```text
ExportJob
- id: ULID
- customerId: ULID
- configurationId: ULID
- entityType: string
- entityId: ULID
- fileName: string
- storagePath: string
- status: string
- attemptCount: int
- generatedAt: datetime
- sentAt: datetime
- errorMessage: text
```

Ne pas ajouter :

```text
responseBody
httpStatus
remoteFilePath
externalId
lastErrorAt
completedAt
payload
metadata
```

---

# 55. ExportJob pour une Invoice

Pour l’export de facture :

```text
entityType = code contrôlé Invoice
entityId = Invoice.id
customerId = Invoice.customerId
configurationId = CustomerExportConfiguration.id
```

Si une morph map existe :

réutiliser son alias.

Ne pas stocker le nom complet d’une classe PHP.

---

# 56. ExportJob historique

ExportJob est l’historique de transmission.

Il ne doit pas être édité comme un CRUD classique.

Frontend :

```text
voir
retry si failed
download fichier généré si autorisé
```

Pas :

```text
modifier entityId
modifier customerId
modifier configurationId
modifier sentAt
```

---

# 57. Status ExportJob

`ExportJob.status` reste un string.

Tous les codes réels doivent exister dans :

```text
statuses
src = export_jobs
```

Ne pas créer d’enum.

Analyser les codes actuels.

Si le moteur n’a pas encore de lifecycle complet, définir les codes minimaux validés, par exemple :

```text
PENDING
PROCESSING
SENT
FAILED
```

uniquement après vérification des conventions existantes.

---

# 58. Affichage ExportJob

Dans la facture :

```text
Destination
Format
Transport
Status
Tentatives
Généré le
Envoyé le
Erreur
Actions
```

Ne jamais afficher :

```text
storagePath brut
encryptedPassword
secret
token
```

---

# 59. Retry ExportJob

Si status représente un échec :

afficher :

```text
Réessayer
```

Le retry doit réutiliser le même ExportJob.

Incrémenter :

```text
attemptCount
```

selon le moteur backend.

Ne pas créer une deuxième facture.

---

# 60. Fichier généré

Le ProcessExportJob doit :

1. charger Invoice ;
2. charger InvoiceLines ;
3. charger snapshots ;
4. générer le format ;
5. stocker le fichier via Laravel Filesystem ;
6. transmettre ;
7. mettre à jour ExportJob.

`storagePath` reste interne.

---

# 61. Téléchargement sécurisé

Si besoin :

```text
GET /api/v1/export-jobs/{exportJob}/download
```

avec permission + contrôle Customer/Organization.

Ne jamais exposer directement :

```text
/storage/app/...
storagePath
```

au frontend.

---

# 62. Contenu exporté — principe

Le client externe doit recevoir :

```text
la facture
+
les services facturés
```

Donc l’export doit contenir :

```text
Invoice header
InvoiceLine[]
InvoiceLineAddressSnapshot éventuel
```

Ne pas envoyer uniquement :

```text
invoiceNumber + total
```

si le Customer attend les prestations détaillées.

---

# 63. DTO canonique Invoice Export

Créer un DTO technique, sans nouvelle table, par exemple :

```text
InvoiceExportData
InvoiceExportLineData
InvoiceExportAddressData
```

La structure canonique peut exposer les champs métier existants uniquement.

Exemple conceptuel :

```json
{
  "invoiceNumber": "INV-2026-001",
  "invoiceDate": "2026-08-28",
  "periodFrom": "2026-08-01",
  "periodTo": "2026-08-31",
  "currencyCode": "CHF",
  "subtotal": "1000.00",
  "taxTotal": "81.00",
  "total": "1081.00",
  "externalReference": "...",
  "lines": [
    {
      "lineNumber": 1,
      "serviceCode": "DELIVERY",
      "description": "Livraison",
      "customerOrderReference": "CLIENT-123",
      "quantity": "1",
      "unitPrice": "100.00",
      "discountRate": "0",
      "taxRate": "8.1",
      "totalExcludingTax": "100.00",
      "totalIncludingTax": "108.10",
      "serviceCompletedAt": "..."
    }
  ]
}
```

Les noms exacts du payload final peuvent être remappés par Customer.

---

# 64. Ne pas exposer inutilement les ULID internes

Dans le payload client par défaut :

privilégier les références métier :

```text
invoiceNumber
customerOrderReference
serviceCode
externalReference
```

Ne pas envoyer automatiquement tous les IDs internes Tricolis.

Un mapping client peut les inclure uniquement si le contrat externe le demande.

---

# 65. Mapping spécifique par Customer

Chaque client externe peut demander un JSON/XML différent.

Ne pas créer :

```text
ExportTemplate
InvoiceExportMapping
```

comme nouvelle table.

Utiliser :

```text
CustomerExportConfiguration.settings
```

pour stocker une configuration déclarative contrôlée.

---

# 66. Mapping déclaratif uniquement

Le mapping dans `settings` peut décrire :

```text
fieldMapping
rootName
lineNodeName
dateFormat
decimalFormat
staticValues
headers non secrets
endpointPath
method
contentType
```

selon la convention backend validée.

Ne jamais permettre :

```text
eval
PHP expression
JavaScript arbitraire
shell command
SQL
```

dans le mapping.

---

# 67. Variables autorisées du mapping

Créer une whitelist des données disponibles.

Exemples :

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

line.lineNumber
line.serviceCode
line.description
line.customerOrderReference
line.quantity
line.unitPrice
line.discountRate
line.taxRate
line.totalExcludingTax
line.totalIncludingTax
line.serviceCompletedAt

address.addressCode
address.name
address.addressLine1
address.addressLine2
address.postalCode
address.city
address.country
```

Ne pas autoriser des chemins arbitraires vers le modèle Laravel.

---

# 68. Configuration export JSON REST API

Exemple métier :

```text
Customer IKEA
Transport = REST_API
Format = JSON
```

Formulaire :

```text
Name
ExportType = INVOICE
Format = JSON
Transport = REST_API
Host / base URL
Frequency = ON_INVOICE_CLOSED
Settings
Active
```

Le `settings` peut contenir les options REST non secrètes validées.

---

# 69. REST_API host

Pour REST_API :

```text
host
```

peut contenir l’URL/base URL selon le contrat Phase 8.

Exemple conceptuel :

```text
https://customer.example.com/api
```

Le path de facturation peut être placé dans `settings` si le contrat backend le prévoit.

Ne pas ajouter une colonne `invoice_api_url`.

---

# 70. REST method

Si les clients utilisent différentes méthodes :

configurer dans `settings` :

```text
POST
PUT
```

selon whitelist.

Ne jamais permettre une méthode arbitraire dangereuse.

Pour création de facture externe, `POST` sera généralement la valeur par défaut si le contrat le confirme.

---

# 71. REST content type

Selon format :

```text
JSON -> application/json
XML -> application/xml
```

ou valeur contractuelle du Customer.

Ne pas envoyer XML avec un Content-Type incorrect.

---

# 72. Auth REST API

Ne jamais stocker un bearer token/API secret en clair dans :

```text
settings
```

Analyser le mécanisme sécurisé existant.

Si `encryptedPassword` est déjà utilisé comme emplacement de secret pour le transport REST :

réutiliser cette convention.

Sinon :

documenter que le modèle actuel ne possède pas de champ secret REST générique et ne pas inventer une nouvelle colonne sans validation de conception.

Les options non secrètes d’auth peuvent être dans `settings`.

---

# 73. CustomerApiConfiguration n’est pas automatiquement le secret sortant

Ne pas détourner sans analyse :

```text
CustomerApiConfiguration.apiKeyHash
```

car un hash est non réversible.

Cette classe sert à la configuration API client existante selon son contrat.

Elle ne doit pas être utilisée comme token sortant simplement parce qu’elle contient "API".

---

# 74. Sécurité REST

Obligatoire :

- timeout ;
- contrôle URL/host ;
- protection SSRF ;
- pas de redirection arbitraire ;
- validation des settings ;
- aucune donnée secrète dans les logs ;
- TLS normal en production.

---

# 75. Réponse REST

Le modèle ExportJob ne possède pas :

```text
responseBody
httpStatus
externalRemoteId
```

Ne pas ajouter ces colonnes automatiquement.

En succès :

```text
sentAt
status
```

suffisent pour l’historique actuel.

En échec :

```text
errorMessage
attemptCount
status
```

Ne pas détourner `Invoice.externalReference` pour stocker une réponse d’API externe sans règle métier explicite.

---

# 76. Export FTP

Exemple :

```text
Transport = FTP
Format = XML ou JSON
```

Utiliser :

```text
host
port
username
encryptedPassword
remoteDirectory
fileNamePattern
encoding
```

Ne pas créer une table FTPConfig.

---

# 77. Export SFTP

Même principe :

```text
Transport = SFTP
Format = XML ou JSON
```

Le transporter SFTP doit être isolé et testable.

Utiliser Laravel Filesystem / driver approprié selon architecture.

---

# 78. Mot de passe FTP/SFTP

`encryptedPassword` :

- chiffré avec Laravel Encryption ;
- jamais retourné au frontend ;
- jamais affiché ;
- jamais journalisé.

La Resource peut retourner :

```text
hasPassword: true
```

si cette convention existe.

---

# 79. Formulaire password

Dans Edit :

```text
Password : ••••••••
```

Ne jamais préremplir le secret réel.

Si champ laissé vide :

conserver l’ancien mot de passe si cette règle est retenue.

---

# 80. remoteDirectory

Valider :

```text
remoteDirectory
```

pour empêcher :

- traversée de chemin ;
- caractères dangereux ;
- chemins interdits.

---

# 81. fileNamePattern

Utiliser le champ existant.

Exemple conceptuel :

```text
invoice_{invoiceNumber}.xml
```

mais le moteur réel doit utiliser une whitelist de variables.

Ne pas permettre :

```text
../
shell
PHP expression
```

---

# 82. Exemples noms fichiers

Exemples :

```text
INV_2026_0001.json
INV_2026_0001.xml
```

Le nom final doit être nettoyé.

---

# 83. Export XML

Créer un générateur :

```text
InvoiceXmlExporter
```

ou implémentation équivalente.

Il utilise :

```text
InvoiceExportData
+
mapping CustomerExportConfiguration.settings
```

Ne pas construire du XML par concaténation non échappée.

---

# 84. Export JSON

Créer :

```text
InvoiceJsonExporter
```

ou équivalent.

Utiliser une sérialisation JSON correcte.

Respecter :

```text
encoding
mapping
types/dates
```

selon contrat client.

---

# 85. Un moteur unique, plusieurs transports

Architecture :

```text
Invoice
↓
InvoiceExportData
↓
Format exporter
├── JSON
└── XML
↓
fichier généré
↓
Transporter
├── REST_API
├── FTP
└── SFTP
```

Ne pas dupliquer la génération JSON dans :

```text
RestApiTransporter
FtpTransporter
SftpTransporter
```

---

# 86. Transporters

Réutiliser/créer les classes techniques prévues :

```text
ExportTransporter
FtpExportTransporter
SftpExportTransporter
RestApiExportTransporter
```

`EMAIL` et `MANUAL` peuvent rester supportés par le moteur général si déjà présents.

---

# 87. ProcessExportJob

Workflow :

```text
load ExportJob
load CustomerExportConfiguration
check Customer
load Invoice via entityType/entityId
verify Invoice.status = CLOSED
generate payload/file
store file
transmit
increment attemptCount
set generatedAt
set sentAt on success
set status
set errorMessage on failure
```

Une facture non clôturée doit être refusée par ce workflow automatique.

---

# 88. Vérification CLOSED dans ProcessExportJob

Même si quelqu’un crée manuellement un ExportJob par API :

pour :

```text
entityType = INVOICE
```

le Job ne doit pas envoyer une Invoice non clôturée.

La sécurité métier ne dépend pas uniquement de `CloseInvoiceAction`.

---

# 89. ExportJob manuel pour Invoice

Dans cette Phase 6 :

ne pas permettre à l’utilisateur de contourner la clôture avec :

```text
Créer export maintenant
```

sur une facture DRAFT.

Pour une facture CLOSED ayant un export FAILED :

utiliser :

```text
Retry
```

---

# 90. Historique d’envoi dans Customer

Dans :

```text
CustomerDetail
→ Facturation
→ Historique des envois
```

afficher les ExportJob liés aux factures du Customer.

Filtres :

```text
Invoice
Transport
Format
Status
Date
Configuration
```

uniquement selon APIs réelles.

---

# 91. Page configurations export facturation

Créer une UI scoped Customer :

```text
CustomerInvoiceExportConfigurations
CustomerInvoiceExportConfigurationForm
```

Ne pas afficher ici :

```text
CustomerImportConfiguration
```

car ce n’est pas le besoin de la Phase 6.

Cette phase implémente uniquement le sous-ensemble des intégrations nécessaire aux exports de factures.

---

# 92. Formulaire configuration dynamique

Champs communs :

```text
name
format
transport
fileNamePattern
encoding
isActive
```

Champs imposés/read-only :

```text
exportType = INVOICE
frequency = ON_INVOICE_CLOSED
```

si ce sont les codes validés.

---

# 93. Champs FTP/SFTP

Pour :

```text
FTP
SFTP
```

afficher :

```text
host
port
username
password
remoteDirectory
fileNamePattern
encoding
format
```

---

# 94. Champs REST_API

Pour :

```text
REST_API
```

afficher :

```text
host
format
settings configurables
secret selon mécanisme sécurisé existant
```

Ne pas afficher les champs FTP inutiles.

---

# 95. Tester configuration

Un bouton :

```text
Tester la connexion
```

peut être ajouté comme endpoint technique non persistant si le backend le supporte proprement.

Il peut vérifier :

```text
REST reachability
FTP login
SFTP login
remote directory
```

Il ne doit :

- créer aucune Invoice ;
- créer aucun ExportJob historique réel ;
- écrire aucun fichier de production sauf test contrôlé ;
- exposer aucun secret.

Cette fonctionnalité est utile mais non obligatoire si elle n’existe pas encore.

---

# 96. Preview payload

Avant d’activer une configuration, permettre si possible :

```text
Prévisualiser l’export
```

sur une facture exemple CLOSED existante du même Customer.

Le preview :

- ne transmet rien ;
- ne crée pas de faux ExportJob envoyé ;
- utilise le vrai mapping ;
- masque les données sensibles.

---

# 97. Preview JSON

Afficher formaté :

```json
{
  "...": "..."
}
```

sans exposer secrets/headers sensibles.

---

# 98. Preview XML

Afficher XML échappé/formaté.

Ne jamais rendre le XML comme HTML non sécurisé.

---

# 99. Provider Settlements — pages

Créer :

```text
/billing/provider-settlements
/billing/provider-settlements/create
/billing/provider-settlements/:id
/billing/provider-settlements/:id/edit
```

ou suivre le router existant.

---

# 100. Provider Detail

Dans :

```text
/resources/providers/:id
```

ajouter :

```text
Décomptes
```

avec la liste filtrée par Provider.

---

# 101. Création décompte

Depuis Provider :

```text
+ Nouveau décompte
```

Préremplir :

```text
providerId
organizationId
```

Sélectionner :

```text
periodFrom
periodTo
```

Puis afficher les `OrderService` éligibles.

---

# 102. Services réglables fournisseur

Le backend doit retourner les services :

- réellement éligibles au Provider ;
- non déjà présents dans ProviderSettlementLine ;
- exécutés selon les règles métier ;
- dans la période si applicable.

Ne pas construire cette logique uniquement dans React.

---

# 103. ProviderSettlementLine

Pour chaque service sélectionné :

```text
description
quantity
unitCost
totalCost
```

Le coût doit venir des données/règles validées.

Ne pas réutiliser :

```text
customerUnitPrice
```

comme coût fournisseur.

---

# 104. Calcul ProviderSettlementLine

Si règle minimale validée :

```text
totalCost = quantity × unitCost
```

Le backend recalcule.

React ne doit pas être la source de vérité.

---

# 105. Totaux ProviderSettlement

Recalculer :

```text
subtotal
taxTotal
total
```

selon les règles backend existantes.

Ne pas inventer une taxe par ligne puisque ProviderSettlementLine n’a pas `taxRate`.

---

# 106. Pas de double règlement

Si OrderService existe déjà dans :

```text
provider_settlement_lines
```

ne pas le proposer dans un autre décompte.

Protection finale backend obligatoire.

---

# 107. Statuses ProviderSettlement

`provider_settlements.status` reste textuel.

Les codes viennent de :

```text
statuses
src = provider_settlements
```

Ne pas créer un enum local dans React.

---

# 108. Export externe ProviderSettlement

Ne pas envoyer automatiquement les ProviderSettlement via le système CustomerExportConfiguration.

Le besoin actuel d’export concerne :

```text
Invoice du Customer
```

Pas les décomptes fournisseur.

Toute intégration Provider sera une décision séparée.

---

# 109. Menu Backoffice

Ajouter/compléter :

```text
Facturation
├── Factures clients
├── Décomptes fournisseurs
└── Exports / Envois
```

`Exports / Envois` peut afficher les ExportJobs de type Invoice.

La configuration des exports reste également accessible depuis Customer.

---

# 110. Permissions

Analyser les permissions réelles.

Ne pas inventer les codes.

Attendus possibles à vérifier :

```text
invoices.view
invoices.create
invoices.update
invoices.delete
invoices.change_status
invoices.close

invoice_lines.view
invoice_lines.create
invoice_lines.update
invoice_lines.delete

provider_settlements.view
provider_settlements.create
provider_settlements.update
provider_settlements.delete

customer_export_configurations.view
customer_export_configurations.create
customer_export_configurations.update
customer_export_configurations.delete

export_jobs.view
export_jobs.create
export_jobs.retry
```

Utiliser seulement ceux du backend ou ajouter ceux nécessaires selon les conventions existantes.

---

# 111. Permission de clôture

La clôture est une action sensible.

Ne pas réutiliser arbitrairement :

```text
invoices.update
```

si le projet sépare les actions métier.

Préférer une permission dédiée si la convention permissions le permet.

Documenter le choix.

---

# 112. Multi-organisation

Vérifier systématiquement :

```text
Invoice.organizationId
Invoice.customerId
Customer Organization
InvoiceLine OrderService Customer
CustomerExportConfiguration Customer
ExportJob Customer
ProviderSettlement Organization
Provider Organization
```

Aucune donnée d’une autre Organization ne doit être accessible.

Retourner 404 pour les ressources hors périmètre lorsque cette convention est utilisée.

---

# 113. CustomerExportConfiguration scope

Un export config de Customer A ne doit jamais être utilisé pour :

```text
Invoice Customer B
```

même si l’utilisateur modifie manuellement le payload HTTP.

Protection backend obligatoire.

---

# 114. ExportJob scope

Vérifier :

```text
ExportJob.customerId
==
CustomerExportConfiguration.customerId
==
Invoice.customerId
```

pour `entityType = Invoice`.

---

# 115. API Layer frontend

Créer :

```text
modules/billing/api/invoices.api.ts
modules/billing/api/provider-settlements.api.ts
modules/billing/api/billable-services.api.ts
modules/customer-integrations/api/invoice-export-configurations.api.ts
modules/customer-integrations/api/export-jobs.api.ts
```

Aucun fetch directement dans JSX.

---

# 116. TanStack Query keys

Créer :

```text
invoiceKeys
billableServiceKeys
providerSettlementKeys
settleableServiceKeys
customerExportConfigurationKeys
exportJobKeys
```

Exemples :

```text
invoiceKeys.list(filters)
invoiceKeys.detail(id)
invoiceKeys.byCustomer(customerId)

billableServiceKeys.byCustomer(customerId, filters)

providerSettlementKeys.list(filters)
providerSettlementKeys.byProvider(providerId)

customerExportConfigurationKeys.invoice(customerId)
exportJobKeys.byInvoice(invoiceId)
exportJobKeys.byCustomer(customerId)
```

---

# 117. Invalidation ciblée

Après clôture Invoice :

invalider :

```text
invoice detail
invoice list
customer invoice list
billable services
export jobs for invoice
export jobs for customer
```

Ne pas invalider tout le cache global.

---

# 118. Types TypeScript

Créer selon Resources réelles :

```text
Invoice
InvoiceLine
InvoiceLineAddressSnapshot
ProviderSettlement
ProviderSettlementLine

CustomerExportConfiguration
ExportJob

ExportFormat
ExportTransport
```

Créer aussi des projections UI non persistantes si nécessaires :

```text
BillableOrderService
SettleableOrderService
InvoiceExportPreview
```

---

# 119. Zod

Créer :

```text
invoiceSchema
invoiceLineSchema
providerSettlementSchema
providerSettlementLineSchema
customerInvoiceExportConfigurationSchema
```

Ne pas créer un énorme schéma monolithique.

---

# 120. Validation frontend conditionnelle export config

## REST_API

Valider selon contrat :

```text
host
format
settings
```

## FTP/SFTP

Valider :

```text
host
port
username
remoteDirectory
fileNamePattern
format
```

Password selon création/mise à jour.

Le backend reste autorité finale.

---

# 121. i18n

Ajouter :

```text
billing.*
invoices.*
invoiceLines.*
providerSettlements.*
exports.*
exportConfigurations.*
exportJobs.*
```

Les labels des statuses viennent de `statuses`.

---

# 122. StatusBadge

Réutiliser le composant global.

Ne pas coder :

```ts
if (status === "CLOSED") color = "green"
```

si `statuses` fournit couleur/label.

---

# 123. Audit

Réutiliser :

```text
AuditLog
```

Auditer selon conventions :

```text
invoice.created
invoice.updated
invoice.line_added
invoice.line_removed
invoice.closed

provider_settlement.created
provider_settlement.updated

customer_export_configuration.created
customer_export_configuration.updated
customer_export_configuration.deleted

export_job.created
export_job.retry_requested
export_job.sent
export_job.failed
```

Ne pas créer de table `InvoiceStatusHistory`.

---

# 124. Sécurité secrets

Ne jamais afficher/logger :

```text
encryptedPassword
decrypted password
bearer token
API secret
SFTP password
FTP password
Authorization header
```

Masquer également les secrets dans les exceptions.

---

# 125. SSRF REST API

Les URLs client sont configurables.

Le backend doit protéger contre SSRF.

Valider :

- schéma autorisé ;
- host ;
- DNS/IP selon politique ;
- redirects ;
- timeout.

Ne pas accepter aveuglément n’importe quelle URL fournie par le frontend.

---

# 126. FTP/SFTP sécurité

Valider :

```text
host
port
username
remoteDirectory
```

Utiliser timeout.

Ne pas permettre des paths dangereux.

Ne pas exposer l’hôte/secret au-delà des utilisateurs autorisés.

---

# 127. File storage

Les fichiers générés par ExportJob utilisent Laravel Filesystem.

`storagePath` reste interne.

Ne pas créer un `Document` automatiquement pour chaque export.

---

# 128. Nettoyage fichiers

Ne pas supprimer automatiquement le fichier historique d’un ExportJob sans politique de rétention validée.

Ne pas inventer de cron de purge dans cette phase.

---

# 129. Nommage fichier

Utiliser :

```text
fileNamePattern
```

de la config.

Si aucun pattern valable :

utiliser un fallback backend sûr basé sur :

```text
invoiceNumber
format
```

uniquement si cette stratégie est validée.

---

# 130. Encodage

Utiliser :

```text
CustomerExportConfiguration.encoding
```

pour XML/JSON/fichier si applicable.

Ne pas hardcoder UTF-8 si le Customer exige un encodage différent et que le moteur le supporte.

UTF-8 peut être le fallback si c’est la convention existante.

---

# 131. Clôture + multiple destinations

Exemple complet :

```text
Customer IKEA

Config 1
REST_API
JSON
Active

Config 2
SFTP
XML
Active

Invoice INV-001
status DRAFT
↓
Clôturer
↓
Invoice.status = CLOSED
↓
ExportJob A = REST_API / JSON
ExportJob B = SFTP / XML
↓
ProcessExportJob A
ProcessExportJob B
```

---

# 132. Exemple REST API JSON

```text
Invoice CLOSED
↓
Generate JSON
↓
store generated file
↓
POST vers API Customer
↓
success
↓
ExportJob.sentAt
ExportJob.status = status de succès
```

---

# 133. Exemple SFTP XML

```text
Invoice CLOSED
↓
Generate XML
↓
store generated file
↓
connect SFTP
↓
upload remoteDirectory/fileName
↓
success
↓
ExportJob.sentAt
```

---

# 134. Échec d’une destination parmi plusieurs

Exemple :

```text
REST_API -> SENT
SFTP -> FAILED
```

La facture reste :

```text
CLOSED
```

Afficher :

```text
1 envoi réussi
1 envoi en erreur
```

Permettre retry uniquement SFTP.

---

# 135. Ne pas renvoyer les destinations déjà SENT au retry

Retry sur un ExportJob FAILED :

```text
retry ce job
```

Ne pas recréer/rejouer les autres ExportJob déjà SENT.

---

# 136. Tests Invoice

Tester :

```text
création >= 1 ligne
Customer Organization
calcul ligne
calcul totaux
snapshot
double OrderService refusé
liste
filtres
permissions
multi-org
```

---

# 137. Test clôture

Given :

```text
Invoice DRAFT valide
```

When :

```text
close
```

Then :

```text
Invoice CLOSED
édition bloquée
AuditLog créé
```

---

# 138. Test pas d’export avant clôture

Invoice DRAFT + config REST active.

Vérifier :

```text
0 ExportJob auto
0 appel REST
```

---

# 139. Test export à la clôture

Invoice DRAFT + 1 config active.

Clôturer.

Vérifier :

```text
1 ExportJob
entityId = Invoice.id
Customer cohérent
queue dispatch après commit
```

---

# 140. Test plusieurs configs

Customer possède :

```text
REST JSON
SFTP XML
```

Clôture :

```text
2 ExportJob
```

---

# 141. Test config inactive

Config :

```text
isActive = false
```

Clôture :

```text
aucun ExportJob pour cette config
```

---

# 142. Test autre Customer

Invoice Customer A.

Config Customer B.

Vérifier :

```text
config B jamais utilisée
```

---

# 143. Test idempotence clôture

Appeler close deux fois.

Vérifier :

```text
Invoice une seule fois CLOSED
aucun ExportJob dupliqué
```

---

# 144. Test ProcessExportJob vérifie CLOSED

Créer un job Invoice pointant vers une Invoice DRAFT dans un test contrôlé.

Vérifier :

```text
aucun envoi externe
```

---

# 145. Test REST JSON

Mock HTTP.

Vérifier :

```text
payload contient Invoice + lines
Content-Type JSON
mapping Customer appliqué
sentAt renseigné
attemptCount cohérent
```

---

# 146. Test REST failure

Mock 500/timeout.

Vérifier :

```text
Invoice reste CLOSED
ExportJob FAILED
sentAt null
errorMessage renseigné
attemptCount augmenté
```

---

# 147. Test retry

Given ExportJob FAILED.

Retry.

Mock succès.

Vérifier :

```text
même ExportJob
attemptCount augmenté
sentAt renseigné
status succès
```

---

# 148. Test XML

Vérifier :

```text
XML valide
échappement
Invoice
Lines
Address snapshot selon mapping
encoding
```

---

# 149. Test FTP

Mock/fake filesystem FTP.

Vérifier :

```text
bon host/config
bon remoteDirectory
bon fileName
contenu JSON/XML
status success
```

---

# 150. Test SFTP

Même principe.

Ne jamais utiliser un vrai serveur de production dans les tests.

---

# 151. Test secrets

Vérifier Resources/logs :

```text
encryptedPassword absent
password absent
Authorization absent
```

---

# 152. Test double facturation

Invoice A contient OS-1.

Tenter Invoice B avec OS-1.

Résultat :

```text
422 / conflit métier
```

selon convention.

---

# 153. Tests ProviderSettlement

Tester :

```text
création
Provider Organization
period
service eligible
unitCost
totalCost
double settlement refusé
permissions
multi-org
```

---

# 154. Test provider avec replanification

Créer un service avec plusieurs affectations historiques.

Vérifier que la sélection du Provider à payer suit la règle opérationnelle validée.

Ne pas payer la tentative échouée si le métier ne la considère pas payable.

---

# 155. E2E Facturation client

```text
Login
→ Customer
→ Facturation
→ Nouvelle facture
→ choisir période
→ voir services facturables
→ sélectionner plusieurs services
→ créer facture
→ vérifier lignes/totaux
→ Clôturer
→ facture read-only
→ voir ExportJob
```

---

# 156. E2E REST API

```text
Customer
→ Configuration export
→ REST_API / JSON
→ Active

Invoice
→ CLOSED

→ ExportJob
→ SENT
```

avec backend externe mocké.

---

# 157. E2E SFTP XML

```text
Customer
→ Configuration export
→ SFTP / XML
→ Active

Invoice
→ CLOSED

→ ExportJob
→ fichier généré
→ upload SFTP fake
→ SENT
```

---

# 158. E2E échec + retry

```text
Invoice CLOSED
→ REST_API DOWN
→ ExportJob FAILED
→ utilisateur voit erreur
→ API revient
→ Retry
→ SENT
```

---

# 159. E2E décompte Provider

```text
Provider
→ Décomptes
→ Nouveau
→ période
→ sélectionner services éligibles
→ générer lignes
→ vérifier total
→ enregistrer
```

---

# 160. Performance

Éviter :

```text
charger tous les services historiques
charger toutes les factures
charger tous les ExportJob
```

Utiliser :

```text
pagination
server filters
date range
Customer scope
Provider scope
lazy tabs
```

---

# 161. Queue

Les exports distants doivent utiliser la queue si l’infrastructure Laravel du projet est configurée.

Ne pas faire attendre l’utilisateur pendant :

```text
connexion FTP
upload SFTP
appel API client
```

La clôture retourne rapidement après création des jobs.

---

# 162. État UI après clôture

Immédiatement après close :

```text
Invoice CLOSED
Export jobs PENDING/équivalent
```

Puis TanStack Query peut poll/refetch raisonnablement l’état des ExportJob si nécessaire.

Ne pas poller agressivement.

---

# 163. Notifications d’erreur

Afficher clairement :

```text
Facture clôturée avec succès.
1 export externe a échoué.
```

Ne pas afficher :

```text
Clôture échouée
```

si seule la transmission externe asynchrone a échoué après la clôture.

---

# 164. Config manquante

Dans Customer Detail :

si aucune config active :

```text
Aucune configuration d’export de facture
[Configurer]
```

selon permissions.

---

# 165. Page globale Exports

Créer éventuellement :

```text
/billing/exports
```

si l’API ExportJob globale existe.

Afficher uniquement les exports accessibles de l’Organization.

Filtres :

```text
Customer
Invoice
Transport
Format
Status
Date
```

---

# 166. Pas de Customer Portal dans cette phase

Cette Phase 6 reste le Backoffice interne.

Le fait que la facturation soit "côté client" signifie :

```text
Invoice appartient au Customer
Facturation accessible depuis Customer
Export vers système externe du Customer
```

Ne pas développer maintenant un portail client complet de consultation/paiement.

Ce sera un scope séparé si demandé.

---

# 167. Pas de paiement

Ne pas créer :

```text
Payment
InvoicePayment
PaymentMethod
PaidAt
PaymentStatus
```

Cette phase concerne :

```text
facturation
clôture
transmission
```

pas l’encaissement.

---

# 168. Pas d’avoir

Ne pas créer :

```text
CreditNote
CreditNoteLine
```

sans évolution de conception.

---

# 169. TARIFICATION — extension métier explicitement demandée

La précédente version de cette phase excluait volontairement le moteur de tarification pour rester alignée sur les diagrammes stricts alors disponibles.

Cette exclusion est désormais **annulée par décision métier explicite**.

Le module de tarification fait maintenant partie de la Phase 6.

La documentation générale Tricolis prévoit déjà les concepts suivants :

```text
price_lists
price_rules
price_rule_conditions
price_matrices
price_matrix_rows
customer_price_lists
provider_price_lists
pricing_calculations
```

Dans cette Phase 6, implémenter le périmètre **tarification client** :

```text
PriceList
PriceRule
PriceRuleCondition
PriceMatrix
PriceMatrixRow
CustomerPriceList
PricingCalculation
```

`ProviderPriceList` reste hors scope de cette extension sauf si le décompte fournisseur existant en dépend déjà explicitement.

---

# 169A. Mise à jour de la conception obligatoire

Les classes de tarification ne figuraient pas dans la version stricte précédente du diagramme interne utilisée par les phases backend.

Ne pas créer les migrations en secret.

Avant développement :

1. analyser les tables/tarifs legacy ;
2. analyser la documentation générale ;
3. analyser le backend actuel ;
4. définir les classes/champs/relations exacts ;
5. mettre à jour :

```text
Conception/diagramme/01-diagramme-plateforme-interne.puml
```

avec le package Pricing validé ;
6. seulement ensuite créer migrations/models/API/frontend.

Cette demande utilisateur constitue une **évolution explicite de conception**, donc l’ancienne interdiction sur `PricingRule`, `PriceList`, `PriceMatrix` et `CustomerPriceList` ne s’applique plus.

---

# 169B. Objectif métier

Le transporteur doit pouvoir configurer des tarifs :

```text
GLOBAL
```

qui s’appliquent à tous les Customers,

et des tarifs :

```text
CUSTOMER
```

qui surchargent les tarifs globaux pour un client donné.

Règle fondamentale :

```text
Tarif Customer correspondant trouvé
        ↓ oui
utiliser tarif Customer

        ↓ non
chercher tarif GLOBAL correspondant

        ↓ aucun
Tarif non configuré
```

Ne jamais retourner silencieusement :

```text
0
```

lorsqu’aucune formule n’est trouvée.

`0` doit rester un vrai prix possible uniquement si une formule/règle le produit volontairement.

---

# 169C. La formule est obligatoire, la matrice est optionnelle

Dans le modèle métier demandé :

```text
FORMULE = obligatoire
MATRICE = optionnelle
```

Un tarif peut donc fonctionner uniquement avec une formule :

```text
Service DELIVERY
→ formule par poids
```

sans aucune matrice.

Une matrice sert uniquement lorsque le calcul doit varier selon une dimension telle que :

```text
zone
code postal
```

---

# 169D. Exemple métier simple sans matrice

Exemple :

```text
Service : LIVRAISON
Scope : GLOBAL

Formule :
({P:poids} / {V:100}) * {V:25}
```

Avec :

```text
poids = 350
```

résultat :

```text
(350 / 100) * 25
= 87.50
```

Cette formule peut s’appliquer globalement à la prestation Livraison lorsque aucune règle Customer plus spécifique n’existe.

---

# 169E. Syntaxe des formules

Supporter au minimum la syntaxe métier demandée :

```text
{P:nom_parametre}
{V:valeur}
```

Exemples :

```text
{P:poids}
{P:volume}
{P:distance}
{P:quantite}
{P:nombre_colis}

{V:100}
{V:25}
{V:1.5}
```

Interprétation :

```text
P = paramètre dynamique
V = valeur numérique littérale
```

Exemple :

```text
({P:poids} / {V:100}) * {V:25}
```

---

# 169F. Variables métier autorisées

La documentation générale prévoit notamment :

```text
client
fournisseur
service
poids
volume
distance
nombre de colis
type de véhicule
étage
zone
code postal
durée
jour
créneau
quantité
```

Pour les expressions arithmétiques de cette Phase 6, exposer uniquement une whitelist numérique réellement disponible.

Minimum utile :

```text
poids
volume
distance
nombre_colis
duree
quantite
```

Les dimensions non numériques telles que :

```text
service
zone
code postal
client
```

servent principalement aux **conditions/règles/matrices**, pas directement à une multiplication arbitraire.

---

# 169G. Moteur de formule sécurisé — obligation absolue

Ne jamais exécuter :

```php
eval()
```

Ne jamais exécuter du :

```text
PHP libre
SQL libre
JavaScript libre
shell
```

venant d’une formule.

Créer un moteur sécurisé, par exemple :

```text
PricingFormulaParser
PricingFormulaValidator
PricingFormulaEvaluator
```

Le moteur doit parser uniquement :

```text
nombres
variables whitelistées
+
-
*
/
(
)
)
```

et éventuellement les opérations mathématiques supplémentaires explicitement validées plus tard.

Aucune fonction arbitraire.

---

# 169H. AST / parser

Approche recommandée :

```text
formula string
→ tokenizer
→ parser
→ AST contrôlé
→ validation variables
→ évaluation Decimal
```

Ne pas utiliser un langage de programmation comme moteur.

---

# 169I. Sécurité mathématique

Gérer explicitement :

```text
division par zéro
valeur manquante
NaN
infinity
overflow
formule trop longue
nesting excessif
token invalide
variable inconnue
```

Retourner une erreur métier claire.

Ne pas générer un tarif approximatif en cas d’erreur.

---

# 169J. Précision monétaire

Le moteur doit utiliser une stratégie décimale fiable.

Ne pas utiliser des floats PHP naïfs pour les calculs financiers si le projet utilise une librairie/stratégie Decimal.

Documenter :

```text
scale
rounding
currency precision
```

Le résultat final doit être déterministe et testable.

---

# 169K. Source des valeurs d’un OrderService

Le calcul d’un tarif doit utiliser les données réelles liées au service.

Exemples :

```text
poids
→ OrderService.weight ou donnée métier validée

volume
→ OrderService.volume

nombre_colis
→ OrderService.packageCount

quantite
→ OrderService.quantity

distance
→ distance métier calculée/stockée disponible

code postal
→ Address.postalCode du service concerné
```

Ne pas prendre automatiquement le poids total de toute la commande si la règle concerne uniquement un OrderService, sauf règle explicitement définie.

---

# 169L. Prix calculé au niveau OrderService

La facturation est principalement par service.

Donc le moteur doit calculer le tarif pour :

```text
OrderService
```

et non uniquement au niveau Order.

Le résultat alimente la préparation de :

```text
InvoiceLine.unitPrice
```

et, si les champs existent déjà dans le modèle OrderService validé :

```text
OrderService.customerUnitPrice
OrderService.customerTotalPrice
```

selon la stratégie backend retenue.

---

# 169M. Historiser le calcul

Utiliser :

```text
PricingCalculation
```

pour conserver l’historique du calcul réellement appliqué.

Le calcul doit permettre d’auditer au minimum conceptuellement :

```text
OrderService
Customer
PriceList
PriceRule
PriceMatrix éventuelle
PriceMatrixRow éventuelle
scope utilisé : CUSTOMER ou GLOBAL
formule snapshot
variables snapshot
résultat
date calcul
```

Les champs exacts doivent être ajoutés au diagramme avant migration.

Ne pas dépendre uniquement de la formule actuelle après facturation.

---

# 169N. Snapshot de formule

Si une formule change demain :

```text
ancienne facture
```

ne doit pas être recalculée avec la nouvelle formule.

`PricingCalculation` doit conserver un snapshot suffisant pour expliquer :

```text
pourquoi le prix était 87.50
```

au moment de la facturation.

---

# 169O. Hiérarchie de sélection tarifaire

Appliquer cet ordre de résolution :

```text
1. Customer + Service + Matrix/conditions correspondantes
2. Customer + Service + formule sans Matrix
3. Customer + formule générique compatible si supportée
4. GLOBAL + Service + Matrix/conditions correspondantes
5. GLOBAL + Service + formule sans Matrix
6. GLOBAL + formule générique compatible si supportée
7. aucun tarif -> erreur Tarif non configuré
```

Une règle Customer doit toujours avoir priorité sur une règle globale équivalente.

---

# 169P. Fallback Customer → Global

Exemple :

```text
Customer IKEA
Service DELIVERY
```

Si IKEA possède une formule Livraison :

```text
utiliser formule IKEA
```

Sinon :

```text
utiliser formule GLOBAL Livraison
```

Même règle pour la matrice :

```text
Customer matrix match
→ Customer

sinon
→ chercher GLOBAL
```

---

# 169Q. PriceList global

Le transporteur doit pouvoir avoir au minimum un tarif global actif.

Page :

```text
/billing/pricing/global
```

Afficher :

```text
Formules globales
Matrices globales
Règles
```

La façon exacte d’identifier le PriceList global doit être fixée dans la conception.

Ne pas inventer plusieurs listes globales concurrentes sans règle de priorité.

---

# 169R. Tarification Customer

Dans :

```text
/customers/:id
```

ajouter dans la partie Facturation/Tarification :

```text
Tarification
├── Formules spécifiques
└── Matrices spécifiques
```

L’utilisateur peut définir une formule spécifique au Customer.

Si aucune formule Customer ne correspond :

```text
fallback GLOBAL
```

---

# 169S. Visualiser le fallback

Dans la fiche Customer, afficher clairement :

```text
Tarif spécifique client
```

ou :

```text
Aucun tarif spécifique
→ le tarif global sera utilisé
```

Pour chaque service, permettre de voir :

```text
Source effective : CUSTOMER
```

ou :

```text
Source effective : GLOBAL
```

---

# 169T. PriceRule

Une `PriceRule` représente une règle de calcul.

Elle doit au minimum permettre conceptuellement de définir :

```text
nom
PriceList
Service éventuel
formule obligatoire
priorité
status
conditions éventuelles
```

Les champs exacts sont définis dans le diagramme mis à jour avant migration.

Ne pas ajouter une formule dans chaque InvoiceLine comme source dynamique.

---

# 169U. Formula obligatoire dans PriceRule

Une `PriceRule` active ne doit pas être valide sans formule.

Interdire :

```text
formula = null
formula = ""
```

pour une règle destinée au calcul.

La matrice ne remplace pas la formule.

Elle sélectionne **quelle formule/règle appliquer**.

---

# 169V. PriceRuleCondition

Les conditions permettent de filtrer une formule par :

```text
service
poids
volume
distance
nombre de colis
zone
code postal
quantité
...
```

Exemple :

```text
Service = DELIVERY
Postal code BETWEEN 1144 AND 4000
```

puis appliquer la formule :

```text
({P:poids}/{V:100})*{V:25}
```

---

# 169W. Matrice tarifaire

Une `PriceMatrix` est une table de décision optionnelle.

Elle peut servir à dire :

```text
ZONE 1 -> Formule 1
ZONE 2 -> Formule 3
ZONE 3 -> Formule 2
```

La matrice ne stocke pas du code exécutable.

Elle sélectionne une `PriceRule`/formule.

---

# 169X. Matrice code postal

Exemple :

```text
Matrice : LIVRAISON - ZONES NP

Zone 1
NP 1144 -> 4000
→ règle FORMULE_POIDS_25

Zone 2
NP 4001 -> 6999
→ règle FORMULE_VOLUME

Zone 3
NP 7000 -> 9999
→ règle FORMULE_FIXE
```

Le moteur :

```text
OrderService
→ Address.postalCode
→ trouver ligne de matrice
→ trouver PriceRule
→ calculer formule
```

---

# 169Y. Matrice par Customer ou globale

Une matrice peut être :

```text
Customer-specific
```

ou :

```text
GLOBAL
```

Même fallback :

```text
Customer matrix compatible
→ utiliser

sinon
→ Global matrix
```

---

# 169Z. Matrice non obligatoire

Si aucun `PriceMatrix` n’existe :

le moteur doit continuer à fonctionner avec :

```text
PriceRule.formula
```

directement.

Ne jamais exiger une matrice pour pouvoir calculer un prix.

---

# 169AA. Zone sans nouvelle table obligatoire

Ne pas créer automatiquement :

```text
pricing_zones
postal_zones
```

si `PriceMatrixRow` suffit pour représenter :

```text
zone label
borne min
borne max
règle
```

Si une vraie entité `Zone` est nécessaire à plusieurs modules, elle doit être conçue explicitement.

---

# 169AB. Bornes code postal

Le code postal ne doit pas toujours être traité aveuglément comme un integer universel.

Le besoin actuel utilise des bornes numériques :

```text
1144 -> 4000
```

Supporter ce mode lorsqu’il est adapté au pays/configuration.

Mais concevoir le champ afin de ne pas casser des codes postaux pouvant contenir :

```text
leading zero
lettres
espaces
tirets
```

si Tricolis opère dans d’autres pays.

La stratégie exacte doit être documentée.

---

# 169AC. Exemple complet demandé

Configuration globale :

```text
Service = LIVRAISON
Matrix = ZONE_NP_LIVRAISON

Row 1
NP 1144 -> 4000
Rule = DELIVERY_WEIGHT_ZONE1
```

Rule :

```text
DELIVERY_WEIGHT_ZONE1

formula =
({P:poids}/{V:100})*{V:25}
```

Commande :

```text
Service : Livraison
NP : 2000
Poids : 350
```

Résolution :

```text
NP 2000
→ Row Zone 1
→ DELIVERY_WEIGHT_ZONE1
→ (350 / 100) * 25
→ 87.50
```

---

# 169AD. Exemple Customer override

Global :

```text
DELIVERY / Zone1
→ 25 par 100 kg
```

Customer IKEA :

```text
DELIVERY / Zone1
→ 20 par 100 kg
```

Pour IKEA :

```text
({P:poids}/{V:100})*{V:20}
```

Pour un Customer sans formule spécifique :

```text
({P:poids}/{V:100})*{V:25}
```

global.

---

# 169AE. Priorités et ambiguïtés

Le système doit interdire ou résoudre explicitement les règles ambiguës.

Exemple interdit sans priorité claire :

```text
Customer IKEA
Service DELIVERY
NP 1144-4000
Rule A priority 10

Customer IKEA
Service DELIVERY
NP 2000-3000
Rule B priority 10
```

Deux règles correspondent.

Le backend doit :

- soit imposer des plages non chevauchantes ;
- soit imposer une priorité déterministe.

Documenter la stratégie.

Ne jamais prendre "la première ligne SQL".

---

# 169AF. Testeur de formule

Créer une page/composant :

```text
PricingFormulaEditor
PricingFormulaTester
```

L’utilisateur peut saisir :

```text
({P:poids}/{V:100})*{V:25}
```

et des valeurs de test :

```text
poids = 350
```

Afficher :

```text
Résultat = 87.50
```

Le test utilise **le même moteur backend** que le calcul réel.

Ne pas implémenter un deuxième évaluateur uniquement en JavaScript.

---

# 169AG. Validateur de formule

Pendant Create/Edit :

appeler un endpoint backend conceptuel :

```text
POST /api/v1/pricing/formulas/validate
```

ou une route conforme aux conventions réelles.

Retour :

```text
valid
variablesDetected
errors
```

Cette route n’exécute pas de code arbitraire.

---

# 169AH. Preview tarif sur un vrai service

Permettre :

```text
Tester sur un OrderService
```

si permission.

Retourner :

```text
Customer
Service
variables
scope choisi
PriceList
PriceRule
Matrix éventuelle
MatrixRow éventuelle
formule
résultat
```

Ne pas sauvegarder automatiquement le résultat du preview comme prix définitif.

---

# 169AI. Préfacturation

Ajouter une page :

```text
/billing/prebilling
```

Objectif :

voir les services terminés/facturables avant génération de facture.

Afficher :

```text
Order
Customer
Service
Date
Poids
Volume
Distance
Packages
Formule effective
Scope tarif CUSTOMER/GLOBAL
Prix calculé
Etat calcul
```

Cette page aide à détecter les services sans tarif.

---

# 169AJ. Statut Tarif non configuré

Ne pas inventer un nouveau `OrderServiceStatus`.

Afficher un état UI/calcul :

```text
Tarif non configuré
```

via résultat du moteur de pricing.

Si aucun tarif n’est trouvé :

- ne pas calculer 0 ;
- ne pas produire automatiquement une InvoiceLine avec 0 ;
- bloquer l’ajout automatique à la facture ou demander une action métier validée.

---

# 169AK. Calcul à la création d’une Invoice

Lors de sélection des services facturables :

pour chaque service :

```text
resolve effective pricing
→ calculate
→ create PricingCalculation snapshot
→ use result to prefill InvoiceLine.unitPrice / totals
```

Le backend doit recalculer/valider.

Ne jamais faire confiance au prix calculé uniquement par React.

---

# 169AL. Prix figé après InvoiceLine

Une fois la ligne créée :

```text
InvoiceLine.unitPrice
InvoiceLine.totalExcludingTax
InvoiceLine.totalIncludingTax
```

sont les valeurs de facturation.

Une modification future de la formule ne doit pas modifier automatiquement une InvoiceLine existante.

---

# 169AM. Recalcul avant clôture

Pour une facture DRAFT, proposer éventuellement :

```text
Recalculer les tarifs
```

uniquement si la règle métier le permet.

Cette action doit être explicite.

Ne pas recalculer automatiquement à chaque ouverture.

Afficher les différences avant application si elles modifient des lignes déjà préparées.

---

# 169AN. Facture CLOSED

Une facture :

```text
CLOSED
```

ne peut jamais être recalculée avec une nouvelle formule.

Les snapshots et prix restent historiques.

---

# 169AO. Bouton Modifier dans la liste des factures

Dans :

```text
/billing/invoices
```

la colonne Actions doit contenir :

```text
Voir
Modifier
Clôturer si autorisé
```

Le bouton :

```text
Modifier
```

doit être visible directement dans la liste, pas uniquement dans la fiche Invoice.

Afficher `Modifier` uniquement si :

- permission d’update ;
- Invoice encore modifiable ;
- status différent de CLOSED ou autre état verrouillé.

Pour une facture clôturée :

```text
Modifier
```

est masqué ou disabled avec tooltip :

```text
Une facture clôturée ne peut plus être modifiée.
```

La sécurité finale reste backend.

---

# 169AP. Tarification menu

Compléter le menu :

```text
Facturation
├── Préfacturation
├── Factures clients
├── Tarification
│   ├── Formules globales
│   ├── Matrices globales
│   └── Tarification clients
├── Décomptes fournisseurs
└── Exports / Envois
```

Les routes exactes suivent l’architecture frontend.

---

# 169AQ. Pages Pricing

Créer au minimum :

```text
PricingGlobalPage
PriceRuleListPage
PriceRuleCreatePage
PriceRuleEditPage
PriceMatrixListPage
PriceMatrixCreatePage
PriceMatrixEditPage
PricingFormulaEditor
PricingFormulaTester
PricingCalculationViewer
PrebillingPage
```

Pour Customer :

```text
CustomerPricingTab
CustomerPriceRuleList
CustomerPriceMatrixList
```

---

# 169AR. PriceMatrix Editor

L’éditeur de matrice doit permettre :

```text
Nom matrice
Service éventuel
Rows
```

Chaque row :

```text
Zone / label
From
To
PriceRule
Priority si prévue
```

Pour une matrice code postal :

```text
1144
4000
→ DELIVERY_WEIGHT_ZONE1
```

---

# 169AS. Pas de formule directement dans chaque row si PriceRule existe

Préférer :

```text
PriceMatrixRow
→ PriceRule
→ formula
```

plutôt que dupliquer la formule dans chaque ligne.

Cela permet :

- réutilisation ;
- audit ;
- validation centrale.

Le diagramme final doit fixer cette relation.

---

# 169AT. CustomerPriceList

`CustomerPriceList` lie un Customer à son tarif spécifique.

Il ne doit pas dupliquer toutes les formules dans la table Customer.

Concept :

```text
Customer
→ CustomerPriceList
→ PriceList
→ PriceRule / PriceMatrix
```

Le schéma exact doit être ajouté au diagramme.

---

# 169AU. Global PriceList

Le transporteur doit pouvoir identifier un PriceList global actif.

Le système doit empêcher une ambiguïté de plusieurs globaux de même priorité/période si aucune règle de résolution n’existe.

Ne pas choisir arbitrairement le plus récent.

---

# 169AV. Périodes de validité

Si les tarifs doivent évoluer dans le temps, prévoir lors de la conception :

```text
validFrom
validTo
```

sur le niveau approprié.

Ne pas l’ajouter automatiquement si le besoin n’est pas confirmé par le legacy/diagramme mis à jour.

Mais analyser ce besoin car une facture historique ne doit pas utiliser un futur tarif.

---

# 169AW. Service-specific formula

Permettre :

```text
PriceRule.serviceId = DELIVERY
```

ou relation équivalente.

Une formule peut être spécifique à :

```text
Livraison
Montage
Enlèvement
Retour
...
```

Les prestations viennent du référentiel `Service`.

Ne pas hardcoder les noms de prestations dans React.

---

# 169AX. Formule générique

Une formule générique sans service peut être autorisée uniquement si le design final le prévoit.

Exemple :

```text
tous les services sans règle spécifique
→ prix par quantité
```

Mais une règle spécifique `Service` doit avoir priorité.

---

# 169AY. Matrice par prestation

Une matrice peut cibler une prestation.

Exemple :

```text
Matrice Livraison par NP
```

et une autre :

```text
Matrice Montage par zone
```

Ne pas imposer la même matrice à tous les services.

---

# 169AZ. Calcul par poids

Support obligatoire :

```text
poids
```

Exemple :

```text
({P:poids}/{V:100})*{V:25}
```

---

# 169BA. Calcul par volume

Support obligatoire :

```text
volume
```

Exemple :

```text
{P:volume} * {V:40}
```

si configuré.

---

# 169BB. Calcul mixte

Le moteur doit permettre des formules mixtes :

```text
({P:poids}/{V:100})*{V:25}
+
({P:volume}*{V:5})
```

dans la limite de la grammaire autorisée.

---

# 169BC. Distance

Si une formule utilise :

```text
{P:distance}
```

la source doit être la distance métier réellement disponible pour le service.

Ne pas utiliser automatiquement la distance totale de toute la Tour si le service ne représente qu’un segment.

Documenter la source retenue.

---

# 169BD. Zone / code postal source

Pour un service adressé :

utiliser l’adresse de cet `OrderService`.

Ne pas prendre automatiquement :

```text
Customer billing address
```

pour déterminer la zone de prestation.

Exemple Livraison :

```text
OrderService.addressId
→ Address.postalCode
```

---

# 169BE. Client sans tarif

Comportement obligatoire demandé :

```text
Customer sans tarif spécifique
→ GLOBAL
```

Cela doit être visible dans :

- preview ;
- préfacturation ;
- PricingCalculation ;
- Invoice draft si utile.

---

# 169BF. Customer avec tarif partiel

Si Customer possède une règle spécifique uniquement pour :

```text
MONTAGE
```

mais pas `DELIVERY` :

```text
MONTAGE -> Customer
DELIVERY -> Global
```

Ne pas considérer que la simple présence d’un CustomerPriceList désactive entièrement le fallback global.

---

# 169BG. Matrice Customer partielle

Même logique :

```text
Customer matrix couvre Zone 1
Global matrix couvre Zone 1,2,3
```

Pour Customer :

```text
Zone1 -> Customer
Zone2 -> Global
Zone3 -> Global
```

sauf règle de conception différente explicitement validée.

---

# 169BH. Audit du résultat

Chaque calcul appliqué doit permettre d’expliquer :

```text
Tarif calculé : 87.50

Source : GLOBAL
Service : DELIVERY
Matrix : DELIVERY_ZONE_NP
Zone : 1144-4000
Rule : DELIVERY_WEIGHT_ZONE1
Formula : ({P:poids}/{V:100})*{V:25}
poids : 350
```

Créer/réutiliser :

```text
PricingCalculationViewer
```

---

# 169BI. Permissions Pricing

Analyser/ajouter selon conventions :

```text
pricing.view
pricing.manage

price_lists.view
price_lists.create
price_lists.update
price_lists.delete

price_rules.view
price_rules.create
price_rules.update
price_rules.delete

price_matrices.view
price_matrices.create
price_matrices.update
price_matrices.delete

pricing_calculations.view
pricing_calculations.calculate
```

Ne pas inventer les noms définitifs sans vérifier `PermissionSeeder`.

---

# 169BJ. Multi-organisation Pricing

Tous les tarifs globaux appartiennent au transporteur / Organization active.

Empêcher :

```text
Organization A
→ PriceList Organization B
```

Customer-specific :

```text
Customer.organization
==
PriceList.organization
==
active Organization
```

Protection backend obligatoire.

---

# 169BK. Statuses Pricing

Si les tables conçues possèdent `status` :

```text
price_lists.status
price_rules.status
price_matrices.status
...
```

le status reste textuel.

Ajouter les codes au référentiel :

```text
statuses
```

avec `src` correspondant.

Ne pas créer `status_id`.

Si certaines tables utilisent uniquement `isActive`, ne pas ajouter artificiellement un `status` en plus sans besoin.

---

# 169BL. API Pricing

Après mise à jour de la conception, prévoir des endpoints cohérents tels que :

```text
GET/POST/PATCH/DELETE price-lists
GET/POST/PATCH/DELETE price-rules
GET/POST/PATCH/DELETE price-matrices

GET customer effective pricing
POST pricing calculate
POST pricing formula validate
POST pricing preview
GET pricing calculations
```

Les routes finales doivent suivre les conventions existantes.

---

# 169BM. Backend Services/Actions

Séparer :

```text
ResolvePricingRuleAction
EvaluatePricingFormulaAction
CalculateOrderServicePriceAction
CreatePricingCalculationAction
```

ou architecture équivalente.

Ne pas créer un seul service géant.

---

# 169BN. Algorithme de résolution

Pseudo-code conceptuel :

```text
calculate(OrderService):

  context = buildPricingContext(OrderService)

  candidatesCustomer =
      active rules/matrices linked to Customer
      compatible with service/context

  rule = deterministic best match(candidatesCustomer)

  if no rule:
      candidatesGlobal =
          active GLOBAL rules/matrices
          compatible with service/context

      rule = deterministic best match(candidatesGlobal)

  if no rule:
      return PricingNotConfigured

  validate formula

  result = evaluate(formula, numeric variables)

  save PricingCalculation snapshot

  return result
```

---

# 169BO. Aucun fallback silencieux sur ancien prix

Si un PricingCalculation échoue :

ne pas prendre silencieusement :

```text
OrderService.customerUnitPrice ancien
```

comme nouveau résultat sans règle explicite.

Afficher l’origine du prix.

---

# 169BP. Override manuel

Ne pas inventer automatiquement un système d’override tarifaire manuel.

Si le backend permet déjà de modifier `InvoiceLine.unitPrice` en DRAFT :

conserver cette possibilité selon permissions, mais distinguer clairement :

```text
Prix calculé
Prix facturé
```

et auditer la modification si elle existe.

Ne pas créer une nouvelle table `PriceOverride` sans besoin.

---

# 169BQ. Facture list — action Modifier obligatoire

Exigence UX explicite :

Dans la liste des factures :

```text
Actions
├── Voir
├── Modifier
└── Clôturer
```

`Modifier` doit être directement accessible depuis chaque ligne modifiable.

Ne pas obliger l’utilisateur à ouvrir le détail pour trouver Edit.

---

# 169BR. Tests formules

Tester :

```text
({P:poids}/{V:100})*{V:25}
poids=350
=> 87.50
```

Tester :

```text
variables inconnues
division zéro
parenthèses invalides
tokens interdits
PHP injection
SQL injection
long formula
```

Tout code libre doit être refusé.

---

# 169BS. Tests fallback

Tester :

```text
Customer rule exists
→ Customer

Customer rule absent
→ Global

Customer rule for MONTAGE only
DELIVERY
→ Global DELIVERY

Customer matrix row absent
→ Global compatible
```

---

# 169BT. Tests matrice

Tester :

```text
NP 1144
NP 2000
NP 4000
```

matchent correctement :

```text
1144-4000
```

Tester les bornes.

Tester les overlaps selon stratégie retenue.

---

# 169BU. Test Formula obligatoire

Créer PriceRule sans formule :

```text
422
```

ou erreur de validation équivalente.

Matrix sans règle/formule exploitable :

```text
refusée
```

---

# 169BV. Test Prebilling

Services :

```text
OS1 Customer-specific
OS2 Global fallback
OS3 aucun tarif
```

Afficher :

```text
OS1 prix + CUSTOMER
OS2 prix + GLOBAL
OS3 Tarif non configuré
```

OS3 ne doit pas générer automatiquement une ligne à 0.

---

# 169BW. Test Invoice pricing snapshot

Créer Invoice DRAFT à partir d’une formule.

Modifier ensuite la formule.

Vérifier :

```text
ancienne InvoiceLine ne change pas automatiquement
PricingCalculation historique reste explicable
```

---

# 169BX. E2E Pricing

```text
Login
→ Facturation
→ Tarification
→ créer formule globale DELIVERY
→ formule ({P:poids}/{V:100})*{V:25}
→ tester poids=350
→ résultat 87.50
→ créer matrice NP 1144-4000
→ associer formule
→ Customer sans override
→ Préfacturation
→ prix GLOBAL 87.50
```

---

# 169BY. E2E Customer override

```text
Customer IKEA
→ Tarification
→ créer formule DELIVERY spécifique
→ {P:poids}/{V:100}*{V:20}

Préfacturation IKEA
→ source CUSTOMER

Autre Customer
→ source GLOBAL
```

---

# 169BZ. E2E facture + Modifier list

```text
Liste factures
→ Invoice DRAFT
→ cliquer Modifier directement dans la ligne
→ changer contenu autorisé
→ save

Invoice CLOSED
→ Modifier indisponible
```

---

# 169CA. Rapport d’analyse Pricing obligatoire

Ajouter à :

```text
docs/frontend/phase-6-analysis.md
```

une section complète :

1. legacy tariffs ;
2. diagram update ;
3. schema PriceList ;
4. schema PriceRule ;
5. schema PriceRuleCondition ;
6. schema PriceMatrix ;
7. schema PriceMatrixRow ;
8. schema CustomerPriceList ;
9. schema PricingCalculation ;
10. global fallback ;
11. customer override ;
12. service matching ;
13. formula grammar ;
14. variable whitelist ;
15. formula security ;
16. matrix optional ;
17. postal ranges ;
18. overlaps/priority ;
19. pricing snapshot ;
20. prebilling ;
21. Invoice integration ;
22. tests.

---

# 169CB. Rapport final Pricing

Ajouter au rapport final :

```text
Tarification global
Tarification Customer
Fallback Customer -> Global
Formule obligatoire
Matrice optionnelle
Matrices par zone/NP
Service-specific rules
Formula parser
Formula validator
Formula tester
PricingCalculation history
Prebilling
Invoice pricing integration
Invoice list Edit button
```

---

# 169CC. Interdictions Pricing

Ne pas :

- exécuter `eval()` ;
- exécuter PHP/SQL/JS libre ;
- stocker une formule executable arbitraire ;
- prendre la première règle correspondante sans priorité déterministe ;
- renvoyer 0 lorsqu’aucune formule n’existe ;
- rendre une matrice obligatoire ;
- désactiver le fallback global parce qu’un Customer possède seulement une règle partielle ;
- recalculer une Invoice CLOSED ;
- modifier rétroactivement une InvoiceLine après changement de formule ;
- utiliser un `status_id` ;
- mélanger tarification Customer et coût Provider ;
- créer des tables Pricing sans mettre à jour le diagramme officiel ;
- dupliquer le moteur de formule dans React.

---

# 169CD. Ancienne exclusion annulée

La règle précédente :

```text
Pas de moteur tarifaire
```

est supprimée.

Les concepts suivants sont maintenant **autorisés et requis dans le périmètre client** :

```text
PriceList
PriceRule
PriceRuleCondition
PriceMatrix
PriceMatrixRow
CustomerPriceList
PricingCalculation
```

La documentation générale du projet les prévoyait déjà ; cette demande utilisateur les active maintenant dans la Phase 6.

---

# 170. Interdictions absolues

Ne pas :

- créer InvoiceExport ;
- créer InvoiceDelivery ;
- créer AccountingExport ;
- créer FileTransferLog ;
- créer ApiRequestLog ;
- créer InvoiceStatusHistory ;
- créer Payment ;
- créer CreditNote ;
- créer status_id ;
- envoyer une Invoice DRAFT ;
- déclencher l’export à la création ;
- modifier une Invoice CLOSED ;
- recréer une Invoice pour retry ;
- recréer tous les ExportJob lors d’un retry ;
- stocker le résultat d’envoi dans Invoice ;
- utiliser Invoice.externalReference comme log technique ;
- exposer storagePath ;
- exposer encryptedPassword ;
- stocker bearer token en clair dans settings ;
- détourner apiKeyHash comme secret sortant ;
- créer une table par transport ;
- hardcoder une URL Customer dans React ;
- hardcoder les credentials ;
- exécuter FTP/SFTP/REST dans une transaction DB ;
- générer un export sans les InvoiceLine ;
- facturer deux fois le même OrderService ;
- régler deux fois le même service au Provider ;
- mélanger unitPrice client et unitCost fournisseur ;
- utiliser un moteur de formule non sécurisé ;
- utiliser eval() pour les formules ;
- rendre les matrices obligatoires ;
- supprimer le fallback Customer -> Global ;
- recalculer une facture CLOSED avec un nouveau tarif ;
- inventer des champs absents ;
- inventer des statuses sans les ajouter au référentiel ;
- hardcoder couleurs/labels status ;
- travailler sur main si les phases frontend ne sont pas fusionnées ;
- pousser automatiquement ;
- attribuer le commit à Claude/Anthropic ;
- laisser des TODO.

---

# 171. Branche Git

Identifier la branche qui contient réellement la Phase 5 validée.

Créer :

```bash
git checkout <BRANCHE_PHASE_5_VALIDEE>
git checkout -b feature/frontend-phase-6-billing-exports
```

Si elle existe :

```bash
git checkout feature/frontend-phase-6-billing-exports
```

Ne pas merger automatiquement.

Ne pas pousser automatiquement.

---

# 172. Identité Git

Avant commit :

```bash
git config user.name
git config user.email
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
```

Interdit :

```text
Claude
Anthropic
Co-authored-by: Claude
Co-authored-by: Anthropic
Generated-by: Claude
Generated-by: Anthropic
```

Si nécessaire :

```bash
git log --all --format='%an <%ae>' | sort -u
```

Ne jamais inventer l’e-mail humain.

Commit recommandé :

```bash
git add .
git commit -m "feat(frontend): implement phase 6 pricing billing and invoice exports"
```

---

# 173. Analyse obligatoire

Créer :

```text
docs/frontend/phase-6-analysis.md
```

Documenter au minimum :

1. schéma Invoice ;
2. schéma InvoiceLine ;
3. snapshot ;
4. ProviderSettlement ;
5. ProviderSettlementLine ;
6. cardinalités ;
7. double billing protection ;
8. double settlement protection ;
9. services facturables ;
10. services réglables ;
11. prix client vs coût fournisseur ;
12. statuses Invoice ;
13. code exact Clôturée ;
14. règles d’immutabilité ;
15. route Close ;
16. CustomerExportConfiguration ;
17. ExportJob ;
18. exportType Invoice ;
19. frequency close ;
20. ExportFormat ;
21. ExportTransport ;
22. mapping settings ;
23. REST auth ;
24. FTP ;
25. SFTP ;
26. queue ;
27. retry ;
28. idempotence ;
29. sécurité ;
30. permissions ;
31. multi-org ;
32. tests ;
33. tarification globale ;
34. tarification Customer ;
35. fallback Customer -> Global ;
36. formules sécurisées ;
37. matrices tarifaires ;
38. PricingCalculation ;
39. préfacturation ;
40. bouton Modifier dans InvoiceList.

---

# 174. Audit statuses

Mettre à jour :

```text
docs/backend/statuses-global-audit.md
```

Inclure :

```text
invoices
invoice_lines
provider_settlements
export_jobs
```

Vérifier :

```text
status textuel
code dans statuses
aucun status_id
```

Documenter explicitement le code correspondant à :

```text
Clôturée
```

---

# 175. Rapport final

Créer :

```text
docs/frontend/phase-6-final-report.md
```

Inclure :

1. branche de base ;
2. branche Phase 6 ;
3. Git Author ;
4. Git Committer ;
5. absence Claude/Anthropic ;
6. Invoice pages ;
7. Customer billing tab ;
8. billable services ;
9. InvoiceLine ;
10. snapshots ;
11. totals ;
12. closure ;
13. immutable CLOSED invoice ;
14. statuses ;
15. ProviderSettlements ;
16. settleable services ;
17. CustomerExportConfiguration ;
18. REST API JSON ;
19. FTP ;
20. SFTP ;
21. XML ;
22. JSON ;
23. ExportJob ;
24. queue ;
25. retry ;
26. idempotence ;
27. secrets ;
28. mapping ;
29. query keys ;
30. types ;
31. Zod ;
32. permissions ;
33. tests ;
34. E2E ;
35. limitations ;
36. differences DB/UML ;
37. risks ;
38. tarification globale/client ;
39. formules ;
40. matrices ;
41. fallback ;
42. préfacturation ;
43. bouton Modifier InvoiceList ;
44. next phase.

Conclusion obligatoire :

```text
FRONTEND_PHASE_6_READY
```

ou :

```text
FRONTEND_PHASE_6_NOT_READY
```

Ne pas déclarer READY si les tests échouent.

---

# 176. Vérifications finales

Frontend :

```bash
npm run lint
npm run typecheck
npm run test
npm run build
```

E2E si configuré :

```bash
npm run test:e2e
```

Backend si des adaptations sont nécessaires :

```bash
php artisan optimize:clear
php artisan test
./vendor/bin/pint --test
php artisan migrate:status
php artisan route:list --path=api/v1
```

Vérifier notamment :

```text
Invoice.status clôturée
CustomerExportConfiguration
ExportJob
Queue
FTP/SFTP filesystem
REST client
statuses
```

Git :

```bash
git status
git diff --check
git var GIT_AUTHOR_IDENT
git var GIT_COMMITTER_IDENT
git log -1 --pretty=fuller
```

Ne pas pousser automatiquement.

Ne pas commencer la Phase 7 sans validation explicite de l’utilisateur.
