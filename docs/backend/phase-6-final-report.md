# Rapport final — Phase 6 : facturation client et décomptes fournisseurs

Répond au §39 du prompt.

---

## 1. Branche

```text
feature/backend-phase-6-billing-settlements
```

Créée depuis `feature/backend-phase-5-tracking-pod-claims` (commit `641451e`), et
non depuis `main` — resté au squelette vide `c97dc0d`. `invoice_lines` référence
`order_services` (Phase 2), `provider_settlements` référence `providers`
(Phase 3) : brancher depuis `main` rendrait les migrations inexécutables. Même
écart assumé qu'aux Phases 3 à 5.

Aucune fusion, aucun rebase, aucun push.

## 2. Diagrammes et conflits

Les deux `.puml` du §1 n'existent pas ; les `.txt` disponibles font foi. Classes
lignes 753-823, relations lignes 931-938.

### Deux conflits, même arbitrage

| # | Le prompt dit | Le diagramme dit | Décision |
|---|---------------|------------------|----------|
| A | `Invoice.legacyId: bigint` (§6, §33) | 15 attributs, sans `legacyId` | colonne **non créée** |
| B | `InvoiceLine.legacyId: bigint` (§9, §33) | 16 attributs, sans `legacyId` | colonne **non créée** |

Le §1 donne priorité au diagramme, le §2 interdit d'ajouter un attribut absent,
et c'est la décision déjà prise en Phase 3 (`providers`, `drivers`, `vehicles`)
puis en Phase 5 (`claims`). Les filtres `legacyId` du §8 et les index du §33 sont
sans objet. Un test vérifie l'absence.

Conformité vérifiée par les colonnes créées :

```text
invoices                         15 colonnes  (15 attributs)
invoice_lines                    16 colonnes  (16 attributs)
invoice_line_address_snapshots    9 colonnes  (9 attributs)
provider_settlements             10 colonnes  (10 attributs)
provider_settlement_lines         7 colonnes  (7 attributs)
```

## 3. Classes et relations implémentées

```text
Invoice   InvoiceLine   InvoiceLineAddressSnapshot
ProviderSettlement   ProviderSettlementLine
```

```text
Invoice                belongsTo Organization, Customer ; hasMany InvoiceLine
InvoiceLine            belongsTo Invoice, OrderService, Order ; hasOne InvoiceLineAddressSnapshot
InvoiceLineAddressSnapshot  belongsTo InvoiceLine
ProviderSettlement     belongsTo Organization, Provider ; hasMany ProviderSettlementLine
ProviderSettlementLine belongsTo ProviderSettlement, OrderService
```

Trois compositions (`*--`) → cascades ; quatre associations (`--`) → aucune
destruction.

**Aucun enum** : les trois `status` sont des `VARCHAR(32)`, le diagramme ne les
énumérant pas.

## 4. Migrations, modèles, Actions

**Migrations (5)** — `invoices`, `invoice_lines`,
`invoice_line_address_snapshots`, `provider_settlements`,
`provider_settlement_lines`. Aucune migration existante modifiée.

**Actions (12)** :

```text
CreateInvoiceAction  UpdateInvoiceAction  DeleteInvoiceAction
AddInvoiceLineAction UpdateInvoiceLineAction RemoveInvoiceLineAction
CalculateInvoiceLineTotals  RecalculateInvoiceTotals

CreateProviderSettlementAction  UpdateProviderSettlementAction  DeleteProviderSettlementAction
AddProviderSettlementLineAction UpdateProviderSettlementLineAction RemoveProviderSettlementLineAction
CalculateProviderSettlementLineTotal  RecalculateProviderSettlementTotals
```

**Services (2)** — `BillingScopeGuard` (client, commande, service, cohérence
commande ↔ service), `SettlementScopeGuard` (fournisseur, service, cohérence
fournisseur ↔ tournée).

**Support (1)** — `App\Shared\Support\Money` : arithmétique `bcmath`, arrondi
commercial. Aucun `float` ne touche un montant.

**DTOs (9)**, **exceptions (2)**, **Query Objects (2)**.

## 5. Requests, Resources, Policies

**Form Requests (10)** — les huit du §26, plus `ListInvoiceRequest` et
`ListProviderSettlementRequest`.

**Resources (9)** — celles du §28.

**Policies (4)** — `InvoicePolicy`, `InvoiceLinePolicy`,
`ProviderSettlementPolicy`, `ProviderSettlementLinePolicy`.

## 6. Permissions et routes

16 permissions ; total du projet : **138**.

**22 routes**, aucun doublon sur les 257 du projet :

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

Aucun `/pay`, `/validate`, `/approve`, `/send`, `/export`, `/credit-note`.

## 7. Tests

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `Billing/InvoiceTest` | 17 | Création avec ligne, **refus sans ligne**, **atomicité vérifiée** (aucune facture orpheline après rejet), client hors organisation, période inversée, devise vide, numéro dupliqué, même numéro ailleurs, **calcul des totaux**, **totaux envoyés ignorés**, **remise avant taxe**, **`subtotal + taxTotal = total` après arrondis**, absence de `legacy_id` et des colonnes de paiement, CRUD, IDOR, recherche, filtres, tri, pagination, audit |
| `Billing/InvoiceLineTest` | 16 | Ligne liée à un service du bon client, service d'un autre client refusé, commande incohérente refusée, numéro de ligne dupliqué, **même service facturé deux fois refusé**, quantité négative, taux hors bornes, snapshot facultatif, **un seul snapshot par ligne**, **snapshot supprimé avec sa ligne**, recalcul en cascade sur modification, **refus de retirer la dernière ligne**, IDOR, audit |
| `ProviderSettlements/ProviderSettlementTest` | 19 | Création avec ligne, refus sans ligne, fournisseur hors organisation, période inversée, numéro dupliqué, route fournisseur, **subtotal calculé + taxe saisie**, recalcul sur changement de taxe, `totalCost = quantity × unitCost`, **même service décompté deux fois refusé**, **service planifié chez un autre fournisseur refusé**, **service planifié chez le bon fournisseur accepté**, **service à la fois facturé et décompté accepté**, refus de retirer la dernière ligne, recalcul sur modification de ligne, montants négatifs, absence de colonnes inventées, IDOR, liste, audit |
| `Billing/BillingPermissionTest` | 6 | Lecture, création, modification et suppression refusées sans permission ; accès accordé après attribution du rôle ; en-tête requis ; non authentifié refusé |

**58 tests ajoutés.**

## 8. Résultats

```text
composer validate                                valid
php artisan migrate:fresh --seed --env=testing   OK
php artisan test                                 458 passed (1450 assertions)
./vendor/bin/pint --test                         PASS
php artisan route:list                           257 routes, aucun doublon
fichiers > 200 lignes                            aucun
TODO / classes vides                             aucun
constructions PostgreSQL                         aucune
```

400 tests des Phases 1 à 5, 58 de la Phase 6. **Aucune régression** : aucun test
existant modifié, désactivé ni marqué `skip`.

## 9. Décisions structurantes

### La double unicité sur `order_service_id`

Deux contraintes **indépendantes** : `invoice_lines.order_service_id` UNIQUE et
`provider_settlement_lines.order_service_id` UNIQUE. Un service est facturé au
plus une fois au client, décompté au plus une fois au fournisseur — et peut
porter les deux, ce sont deux flux distincts (§22).

MySQL traitant chaque `NULL` comme distinct, les lignes libres — frais de
dossier, forfaits — restent possibles en nombre.

### Les totaux ne sont jamais acceptés en entrée

Le §11 l'exige. Les six champs calculés sont absents des Form Requests. Un test
poste `subtotal: 99999` et vérifie que la facture porte le total calculé.

Seul `provider_settlements.tax_total` est saisi : le §21 interdit d'inventer une
TVA fournisseur, et la ligne de décompte ne porte aucun taux.

### Arithmétique `bcmath`

Le §32 interdit `float` pour l'argent. Tous les calculs passent par
`Money`, sur des chaînes. Chaque total de ligne est arrondi **avant** sommation,
et `taxTotal` d'une facture est **déduit** (`total − subtotal`) pour garantir
l'identité comptable après arrondis. Un test le vérifie sur `33,33 × 3` et
`1,11 × 7` à 7,7 %.

### Aucun statut d'`OrderService` modifié

Le §23 l'interdit sans règle existante. `OrderServiceStatus` possède `INVOICED`
mais **aucun moteur de transition**, contrairement à `OrderStatus`. Le fait
« facturé » se lit à l'existence de la ligne — garantie unique — pas à un statut
recopié qui pourrait diverger.

## 10. Ambiguïtés levées

| # | Ambiguïté | Traitement |
|---|-----------|------------|
| A | `legacyId` sur `Invoice` et `InvoiceLine` | Absents du diagramme : non créés |
| B | Nullabilité de `order_service_id` (§10) | **Nullable** : sinon aucune ligne libre ne serait facturable |
| C | Nullabilité de `order_id` (§10) | Nullable ; raccourci de lecture, redondant avec `orderService.order_id` |
| D | Formules de calcul (§11, §14, §19) | Aucune convention existante ; formules minimales du prompt retenues et documentées |
| E | Précision décimale (§32) | Convention existante (`12,2`, `12,3`) plutôt que les exemples `15,x` qui créeraient une seconde convention |
| F | Précision des taux | Aucun précédent : `DECIMAL(5,2)`, bornés `0–100` par validation |
| G | Numérotation (§7, §16) | Fournie par l'appelant, unique par organisation ; le mécanisme existant est propre aux commandes |
| H | Suppression d'une facture (§34) | Autorisée : aucune règle de conservation n'existe et les valeurs de `status` ne doivent pas être interprétées |
| I | Cohérence service ↔ fournisseur (§18) | Contrôle **conditionnel** : appliqué seulement si le service est planifié sur une tournée ayant un fournisseur |
| J | Statut `INVOICED` (§23) | Aucun statut modifié automatiquement |

## 11. Fichiers

**52 créés** : migrations (5), modèles (5), DTOs (9), Actions (12), services (2),
exceptions (2), support (1), Query Objects (2), Form Requests (10), Resources
(9), Policies (4), Controllers (4), factories (5), tests (4), documentation (4).

**4 modifiés**, tous par ajout : `routes/api.php`, `PermissionSeeder`,
`AuthServiceProvider`, `MorphMap`. Aucune ligne des Phases 1 à 5 supprimée ni
réécrite.

## 12. Éléments exclus

```text
InvoicePayment  Payment  CreditNote  CreditNoteLine  InvoiceStatusHistory
InvoiceValidation  InvoiceApproval  InvoiceDocument  InvoiceExport
AccountingExport  PricingRule  PriceList  PriceMatrix  PriceCalculation
CustomerPriceList  ProviderPriceList  ProviderSettlementPayment
ProviderSettlementDocument  ProviderSettlementStatusHistory  TaxRule
Currency  BillingBatch  BillingRun  InvoiceLineSource
```

Attributs non ajoutés : `due_date`, `paid_at`, `validated_at`, `validated_by`,
`issued_by`, `billing_address_id`, `payment_terms`, `payment_status`,
`accounting_reference`, `abacus_reference`, `invoice_type`, `credit_note_id`,
`discount_amount`, `tax_amount`, `unit`, `pricing_snapshot`, `metadata`,
`settings`, `softDeletes`, `updated_at`, `legacy_id`.

Sur le snapshot : ni `addressLine3`, ni `canton`, ni `latitude`, ni `longitude`,
ni `customerCode`, ni `companyName`. Sur la ligne de décompte : ni `taxRate`, ni
`taxAmount`, ni `status`, ni `serviceDate`, ni `providerContractId`, ni
`pricingRuleId`, ni `sourceType`.

## 13. Risques

1. **Une facture reste supprimable quel que soit son statut.** C'est délibéré —
   aucune règle de conservation n'est définie — mais une facture émise et
   envoyée peut disparaître si quelqu'un détient `invoices.delete`. Dès que les
   statuts métier seront arrêtés, un refus doit être posé.
2. **Rien ne relie une facture à un paiement.** Le diagramme ne porte ni
   `Payment` ni `paid_at` : le suivi des encaissements est hors modèle.
3. **`taxTotal` d'un décompte est déclaratif.** Sans taux au niveau ligne, rien
   ne le vérifie. Une saisie erronée fausse `total` sans alerte.
4. **Les statuts sont des chaînes libres.** Rien n'empêche `draft` et `DRAFT`.
5. **Aucune contrainte sur la devise.** `currency_code` est un `CHAR(3)` libre :
   une facture peut porter une devise qui n'existe pas. Le §2 interdit une table
   `Currency`.
6. **Le point de la Phase 4 reste ouvert** : `DeleteTourAction` ne refuse
   toujours pas la suppression d'une tournée référencée par un `TrackingEvent`,
   une `ProofOfDelivery` ou une `Claim` — tables créées en Phase 5. S'y ajoute
   désormais le cas d'un `OrderService` facturé.

## 14. Prochaine phase

**Non commencée** : la Phase 7 (stock client) attend une validation explicite.
