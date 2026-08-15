# Analyse Phase 6 — Facturation client et décomptes fournisseurs

Répond au §3. Aucune migration n'a été écrite avant que le tableau du §5 soit
terminé.

---

## 1. Sources de vérité et conflits

Le §1 désigne deux `.puml` qui **n'existent pas**. Les `.txt` disponibles font
foi, comme aux Phases 3 à 5. Classes lignes 753-823, relations lignes 931-938.

### Deux conflits relevés, même arbitrage

| # | Le prompt dit | Le diagramme dit | Décision |
|---|---------------|------------------|----------|
| A | `Invoice.legacyId: bigint` (§6, §33) | `Invoice` compte **15 attributs**, de `id` à `createdAt`, sans `legacyId` | colonne **non créée** |
| B | `InvoiceLine.legacyId: bigint` (§9, §33) | `InvoiceLine` compte **17 attributs**, sans `legacyId` | colonne **non créée** |

Le §1 pose que « les diagrammes ont priorité sur les anciens prompts », le §2
interdit d'ajouter un attribut absent des diagrammes, et c'est la décision déjà
prise en Phase 3 (retrait de `legacy_id` sur `providers`, `drivers`, `vehicles`)
puis en Phase 5 (`claims`). Les filtres `legacyId` du §8 et les index du §33 sont
sans objet. Deux tests vérifient l'absence.

Hors ces deux points, prompt et diagramme concordent exactement.

## 2. État du code et dépendances

Phases 1 à 5 livrées : **400 tests**, 1222 assertions, 235 routes.
Laravel 13.23.0, PHP 8.4.2, MySQL 8, Sanctum 4, Pest 5, Pint.

| Table | Phase | Usage |
|-------|-------|-------|
| `organizations` | 1 | `invoices.organization_id`, `provider_settlements.organization_id` |
| `customers` | 1 | `invoices.customer_id` |
| `orders` | 2 | `invoice_lines.order_id` |
| `order_services` | 2 | `invoice_lines.order_service_id`, `provider_settlement_lines.order_service_id` |
| `providers` | 3 | `provider_settlements.provider_id` |

Branche partie de la Phase 5 et non de `main`, resté au squelette vide `c97dc0d`.
Même écart assumé qu'aux Phases 3 à 5.

## 3. Classes et relations

```text
Invoice   InvoiceLine   InvoiceLineAddressSnapshot
ProviderSettlement   ProviderSettlementLine
```

```text
Customer           "1" --  "0..*" Invoice
Invoice            "1" *-- "1..*" InvoiceLine
OrderService       "1" --  "0..1" InvoiceLine
InvoiceLine        "1" *-- "0..1" InvoiceLineAddressSnapshot
Provider           "1" --  "0..*" ProviderSettlement
ProviderSettlement "1" *-- "1..*" ProviderSettlementLine
OrderService       "1" --  "0..1" ProviderSettlementLine
```

Trois compositions (`*--`) : les lignes n'existent pas hors de leur document, le
snapshot hors de sa ligne. Elles dictent les cascades. Les associations (`--`)
ne détruisent rien.

**Aucun enum.** `Invoice.status`, `InvoiceLine.status` et
`ProviderSettlement.status` sont des `string` sans valeurs énumérées. Le §6, le
§10 et le §15 interdisent d'en inventer.

### Isolation organisationnelle

| Classe | `organizationId` | Isolation |
|--------|------------------|-----------|
| `Invoice` | oui | condition directe |
| `InvoiceLine` | non | via `invoice.organization_id` |
| `InvoiceLineAddressSnapshot` | non | via `invoiceLine.invoice` |
| `ProviderSettlement` | oui | condition directe |
| `ProviderSettlementLine` | non | via `settlement.organization_id` |

## 4. La double cardinalité `0..1` sur OrderService

Le point structurant de la phase. Le diagramme pose **deux** relations
distinctes :

```text
OrderService "1" -- "0..1" InvoiceLine
OrderService "1" -- "0..1" ProviderSettlementLine
```

Un service est donc facturé **au plus une fois** au client, et décompté **au
plus une fois** au fournisseur — mais les deux flux sont indépendants : le même
service peut porter une ligne de facture *et* une ligne de décompte. Le §22 le
dit explicitement.

Traduction : `UNIQUE` sur `invoice_lines.order_service_id` et sur
`provider_settlement_lines.order_service_id`, **séparément**. MySQL traite chaque
`NULL` comme distinct, ce qui autorise les lignes libres — celles qui ne
correspondent à aucun service, saisies à la main. C'est exactement ce qu'il
faut : la contrainte ne mord que sur les lignes rattachées.

## 5. Tableau de correspondance

### Invoice → `invoices`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `organizationId` | ULID | `organization_id` CHAR(26) | non | index + unique composite | FK `organizations.id` RESTRICT |
| `customerId` | ULID | `customer_id` CHAR(26) | non | index | FK `customers.id` RESTRICT |
| `invoiceNumber` | string | `invoice_number` VARCHAR(255) | non | unique `(organization_id, invoice_number)` | — |
| `invoiceDate` | date | `invoice_date` DATE | non | index | — |
| `periodFrom` | date | `period_from` DATE | **oui** | index | — |
| `periodTo` | date | `period_to` DATE | **oui** | index | — |
| `currencyCode` | string | `currency_code` CHAR(3) | non | index | — |
| `subtotal` | decimal | `subtotal` DECIMAL(12,2) | non, défaut 0 | — | calculé |
| `taxTotal` | decimal | `tax_total` DECIMAL(12,2) | non, défaut 0 | — | calculé |
| `total` | decimal | `total` DECIMAL(12,2) | non, défaut 0 | — | calculé |
| `externalReference` | string | `external_reference` VARCHAR(255) | **oui** | index | — |
| `remark` | text | `remark` TEXT | **oui** | — | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |
| `createdAt` | datetime | `created_at` DATETIME | non | index | — |

### InvoiceLine → `invoice_lines`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `invoiceId` | ULID | `invoice_id` CHAR(26) | non | index + unique composite | FK `invoices.id` CASCADE |
| `orderServiceId` | ULID | `order_service_id` CHAR(26) | **oui** | **unique** | FK `order_services.id` RESTRICT |
| `orderId` | ULID | `order_id` CHAR(26) | **oui** | index | FK `orders.id` RESTRICT |
| `lineNumber` | int | `line_number` INT UNSIGNED | non | unique `(invoice_id, line_number)` | — |
| `serviceCode` | string | `service_code` VARCHAR(64) | **oui** | index | — |
| `description` | string | `description` VARCHAR(255) | non | — | — |
| `customerOrderReference` | string | `customer_order_reference` VARCHAR(255) | **oui** | — | — |
| `quantity` | decimal | `quantity` DECIMAL(12,3) | non | — | — |
| `unitPrice` | decimal | `unit_price` DECIMAL(12,2) | non | — | — |
| `discountRate` | decimal | `discount_rate` DECIMAL(5,2) | non, défaut 0 | — | — |
| `taxRate` | decimal | `tax_rate` DECIMAL(5,2) | non, défaut 0 | — | — |
| `totalExcludingTax` | decimal | `total_excluding_tax` DECIMAL(12,2) | non, défaut 0 | — | calculé |
| `totalIncludingTax` | decimal | `total_including_tax` DECIMAL(12,2) | non, défaut 0 | — | calculé |
| `serviceCompletedAt` | datetime | `service_completed_at` DATETIME | **oui** | index | — |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### InvoiceLineAddressSnapshot → `invoice_line_address_snapshots`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `invoiceLineId` | ULID | `invoice_line_id` CHAR(26) | non | **unique** | FK `invoice_lines.id` CASCADE |
| `addressCode` | string | `address_code` VARCHAR(64) | **oui** | — | — |
| `name` | string | `name` VARCHAR(255) | **oui** | — | — |
| `addressLine1` | string | `address_line1` VARCHAR(255) | **oui** | — | — |
| `addressLine2` | string | `address_line2` VARCHAR(255) | **oui** | — | — |
| `postalCode` | string | `postal_code` VARCHAR(32) | **oui** | — | — |
| `city` | string | `city` VARCHAR(255) | **oui** | — | — |
| `country` | string | `country` VARCHAR(255) | **oui** | — | — |

**Aucune clé étrangère vers `addresses`.** Le §12 l'exige : le snapshot est une
copie figée. Une modification d'adresse ne doit jamais remonter dans une facture
déjà émise.

### ProviderSettlement → `provider_settlements`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `organizationId` | ULID | `organization_id` CHAR(26) | non | index + unique composite | FK `organizations.id` RESTRICT |
| `providerId` | ULID | `provider_id` CHAR(26) | non | index | FK `providers.id` RESTRICT |
| `settlementNumber` | string | `settlement_number` VARCHAR(255) | non | unique `(organization_id, settlement_number)` | — |
| `periodFrom` | date | `period_from` DATE | **oui** | index | — |
| `periodTo` | date | `period_to` DATE | **oui** | index | — |
| `subtotal` | decimal | `subtotal` DECIMAL(12,2) | non, défaut 0 | — | calculé |
| `taxTotal` | decimal | `tax_total` DECIMAL(12,2) | non, défaut 0 | — | fourni |
| `total` | decimal | `total` DECIMAL(12,2) | non, défaut 0 | — | calculé |
| `status` | string | `status` VARCHAR(32) | non | index | — |

### ProviderSettlementLine → `provider_settlement_lines`

| Attribut | Type | Colonne | Nullable | Index | Relation |
|---|---|---|---|---|---|
| `id` | ULID | `id` CHAR(26) | non | PK | — |
| `settlementId` | ULID | `settlement_id` CHAR(26) | non | index | FK `provider_settlements.id` CASCADE |
| `orderServiceId` | ULID | `order_service_id` CHAR(26) | **oui** | **unique** | FK `order_services.id` RESTRICT |
| `description` | string | `description` VARCHAR(255) | non | — | — |
| `quantity` | decimal | `quantity` DECIMAL(12,3) | non | — | — |
| `unitCost` | decimal | `unit_cost` DECIMAL(12,2) | non | — | — |
| `totalCost` | decimal | `total_cost` DECIMAL(12,2) | non, défaut 0 | — | calculé |

Ni `taxRate`, ni `taxAmount`, ni `status`, ni `serviceDate` : le §18 les interdit.

## 6. Nullabilité

| Colonne | Choix | Raison |
|---------|-------|--------|
| `invoices.period_from`, `period_to` | **nullable** | Absents des obligatoires du §6, qui liste `organization_id`, `customer_id`, `invoice_number`, `invoice_date`, `currency_code`, `status`. Une facture ponctuelle ne couvre pas une période. |
| `invoices.external_reference`, `remark` | nullable | Références et notes libres. |
| `invoice_lines.order_service_id` | **nullable** | Le §10 demande de l'analyser « selon la création de lignes manuelles ». Une ligne libre — frais de dossier, régularisation — ne correspond à aucun service. La rendre obligatoire interdirait toute facturation hors prestation. |
| `invoice_lines.order_id` | **nullable** | Même raison. Le §9 note que « `orderId` existe explicitement même si la relation finale n'est pas dessinée séparément » : c'est un raccourci de lecture vers la commande, redondant avec `orderService.order_id` quand le service est renseigné, seul utile quand il ne l'est pas. |
| `invoice_lines.service_code`, `customer_order_reference` | nullable | Absents des obligatoires du §10. |
| `invoice_lines.service_completed_at` | nullable | Une ligne libre n'a pas de date d'exécution. |
| `invoice_lines.description`, `line_number`, `status` | non | §10. Une ligne sans libellé n'est pas facturable. |
| Snapshot : tous les champs sauf `invoice_line_id` | **nullable** | C'est une copie d'adresse : rien ne garantit que l'original était complet. Exiger `city` refuserait de figer une adresse incomplète, ce qui est exactement ce qu'un snapshot doit savoir faire. |
| `provider_settlements.period_from`, `period_to` | nullable | Symétrie avec `invoices`. |
| `provider_settlement_lines.order_service_id` | **nullable** | Symétrie avec `invoice_lines` : un décompte peut porter une ligne forfaitaire. |
| Montants et taux | **non nullable, défaut 0** | Précédent : `orders.weight`, `order_services.*_price` sont `default(0)`. Une somme sur des `NULL` produirait `NULL`. |

## 7. Suppression

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `invoices.organization_id`, `customer_id` | `RESTRICT` | Une facture est une pièce comptable : ni l'organisation ni le client ne disparaissent sous elle. |
| `invoice_lines.invoice_id` | `CASCADE` | Composition `Invoice *-- InvoiceLine`. |
| `invoice_lines.order_service_id`, `order_id` | `RESTRICT` | Association. Supprimer une commande facturée effacerait la justification de la ligne. |
| `invoice_line_address_snapshots.invoice_line_id` | `CASCADE` | Composition, exigée au §31. |
| `provider_settlements.organization_id`, `provider_id` | `RESTRICT` | Idem facture. |
| `provider_settlement_lines.settlement_id` | `CASCADE` | Composition. |
| `provider_settlement_lines.order_service_id` | `RESTRICT` | Association. |

### Refus applicatifs

| Ressource | Refus | Code |
|-----------|-------|------|
| `InvoiceLine` | dernière ligne de sa facture | 409 |
| `ProviderSettlementLine` | dernière ligne de son décompte | 409 |

Les cardinalités `1..*` l'imposent : retirer la dernière ligne laisserait un
document que le diagramme n'autorise pas. Pour le vider, il faut le supprimer.

`Invoice` et `ProviderSettlement` restent supprimables. Le §34 évoque des
« contraintes de conservation existantes » et un « statut final selon une règle
existante » : **aucune n'existe**, et le §6 interdit d'inventer les valeurs de
`status`. Les interpréter reviendrait à décider lesquelles sont définitives, ce
que personne n'a arrêté. La suppression reste donc protégée par la seule
permission `invoices.delete`, et emporte les lignes par cascade — ce qui est le
comportement d'un agrégat, pas une cascade silencieuse.

## 8. Cardinalité minimale `1..*`

`Invoice "1" *-- "1..*" InvoiceLine` et
`ProviderSettlement "1" *-- "1..*" ProviderSettlementLine`.

**Création atomique.** `POST /invoices` exige un tableau `lines` d'au moins un
élément ; la facture, ses lignes et leurs snapshots sont écrits dans la même
transaction. Si une ligne échoue, rien ne subsiste — ni facture vide, ni ligne
partielle, ni snapshot orphelin. Un test le vérifie en comptant les factures
après un rejet.

Symétriquement, `DELETE` de la dernière ligne renvoie 409.

## 9. Calcul des totaux

Le §11 et le §14 interdisent d'inventer une formule complexe et demandent de
chercher les conventions existantes. **Aucune n'existe** : la Phase 2 enregistre
`customer_total_price` tel que fourni, sans le calculer.

Les formules minimales des §11, §14 et §19 sont donc retenues, et documentées
comme telles :

```text
ligne de facture
  base              = quantity × unitPrice
  totalExcludingTax = base × (1 − discountRate / 100)
  totalIncludingTax = totalExcludingTax × (1 + taxRate / 100)

facture
  subtotal = Σ totalExcludingTax
  total    = Σ totalIncludingTax
  taxTotal = total − subtotal

ligne de décompte
  totalCost = quantity × unitCost

décompte
  subtotal = Σ totalCost
  total    = subtotal + taxTotal
```

**Les totaux ne sont jamais acceptés en entrée.** Le §11 est explicite : « ne
jamais faire confiance aux totaux envoyés sans validation ». Les six champs
calculés sont absents des Form Requests ; les fournir n'a aucun effet.

Seul `provider_settlements.tax_total` est **saisi** : le §21 interdit d'inventer
une TVA fournisseur, et aucune règle fiscale n'est définie. `total` en découle
(`subtotal + taxTotal`).

**Arrondi** : `round(…, 2)` à la demi-unité supérieure, appliqué à chaque total
de ligne *avant* sommation. Sommer des valeurs non arrondies puis arrondir
produirait un écart d'un centime avec la somme des lignes affichées — c'est la
facture qui doit être juste, pas le calcul intermédiaire.

Les calculs passent par `bcmath` via des chaînes, jamais par des flottants PHP :
le §32 l'exige pour l'argent.

## 10. Numérotation

Les §7 et §16 interdisent d'imposer un format et de générer avec `count() + 1`.

Le projet possède `GenerateOrderNumber` + `order_number_sequences`, avec verrou
`lockForUpdate` — mais **propre aux commandes** : préfixe `ORD`, table du module
Orders, absente des diagrammes.

**Décision, identique à celle de la Phase 4 pour `tourNumber`** :
`invoiceNumber` et `settlementNumber` sont **fournis par l'appelant**,
obligatoires, uniques par organisation — `unique(organization_id, invoice_number)`,
exactement la contrainte de `orders.order_number`. Aucune génération automatique,
aucun format imposé.

## 11. Statuts d'OrderService

Le §23 demande de documenter quand un service est considéré facturé, et
d'éviter d'inventer des transitions.

`OrderStatus` et `OrderServiceStatus` possèdent bien `INVOICED`, mais
`OrderServiceStatus` **n'a aucun moteur de transition** — contrairement à
`OrderStatus`, il ne déclare que ses cas.

**Décision : aucun statut n'est modifié automatiquement.** Créer une ligne de
facture ne fait pas passer le service à `INVOICED`. Le §23 l'interdit sans règle
existante explicite, et aucune n'existe.

Ce qui est en revanche **structurellement garanti** par la contrainte unique :

- un service est facturé si une `InvoiceLine` le référence ;
- il est décompté si une `ProviderSettlementLine` le référence ;
- l'annulation d'un lien se fait en supprimant la ligne, ce qui libère
  immédiatement le service pour une nouvelle facturation.

La lecture du fait « facturé » passe donc par l'existence de la ligne, pas par un
statut recopié qui pourrait diverger.

## 12. Précision décimale

Le §32 propose `15,2`, `15,4` et `7,4`. **Non retenus** : ils créeraient une
seconde convention monétaire dans la même base.

| Grandeur | Précision | Précédent |
|----------|-----------|-----------|
| Montant | `DECIMAL(12,2)` | `orders.currency_code`-adossés, `order_services.customer_unit_price`, `claims.cost` |
| Quantité | `DECIMAL(12,3)` | `order_services.quantity`, `packages.quantity` |
| Taux | `DECIMAL(5,2)` | **aucun précédent** — décision documentée ci-dessous |

`DECIMAL(5,2)` pour `discount_rate` et `tax_rate` : ce sont des pourcentages,
bornés à `0–100` par la validation. Deux décimales couvrent tous les taux réels
(8,1 % ; 20 % ; 2,5 %) et alignent la précision des taux sur celle des montants.
`7,4` permettrait `999,9999 %`, une amplitude sans usage.

## 13. Permissions et endpoints

16 permissions :

```text
invoices.view / create / update / delete
invoice_lines.view / create / update / delete
provider_settlements.view / create / update / delete
provider_settlement_lines.view / create / update / delete
```

22 routes :

```text
GET|POST          /invoices
GET|PATCH|DELETE  /invoices/{invoice}
GET|POST          /invoices/{invoice}/lines
GET|PATCH|DELETE  /invoices/{invoice}/lines/{line}

GET|POST          /provider-settlements
GET|PATCH|DELETE  /provider-settlements/{providerSettlement}
GET|POST          /provider-settlements/{providerSettlement}/lines
GET|PATCH|DELETE  /provider-settlements/{providerSettlement}/lines/{line}
GET|POST          /providers/{provider}/settlements
```

Aucun endpoint `/pay`, `/validate`, `/approve`, `/send`, `/export`,
`/credit-note` : les §8 et §17 les écartent.

## 14. Ordre des migrations

```text
1. invoices
2. invoice_lines
3. invoice_line_address_snapshots
4. provider_settlements
5. provider_settlement_lines
```

**Aucun soft delete. Aucun `updated_at`.** `invoices.created_at` existe seul,
parce que le diagramme déclare `createdAt` ; `ProviderSettlement` n'en déclare
aucun et n'en a donc aucun.

## 15. Éléments exclus

```text
InvoicePayment      Payment              CreditNote          CreditNoteLine
InvoiceStatusHistory                     InvoiceValidation   InvoiceApproval
InvoiceDocument     InvoiceExport        AccountingExport    PricingRule
PriceList           PriceMatrix          PriceCalculation    CustomerPriceList
ProviderPriceList   ProviderSettlementPayment                TaxRule
ProviderSettlementDocument               ProviderSettlementStatusHistory
Currency            BillingBatch         BillingRun          InvoiceLineSource
```

Attributs non ajoutés :

```text
due_date        paid_at         validated_at    validated_by    issued_by
billing_address_id              payment_terms   payment_status  invoice_type
accounting_reference            abacus_reference                credit_note_id
discount_amount tax_amount      unit            pricing_snapshot
metadata        settings        softDeletes     updated_at
legacy_id (invoices et invoice_lines)
```

Sur le snapshot : ni `addressLine3`, ni `canton`, ni `latitude`, ni `longitude`,
ni `customerCode`, ni `companyName`. Sur la ligne de décompte : ni `taxRate`, ni
`taxAmount`, ni `status`, ni `serviceDate`, ni `providerContractId`, ni
`pricingRuleId`, ni `sourceType`.
