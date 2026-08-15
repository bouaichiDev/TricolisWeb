# Décisions base de données — Phase 6

Répond au §31. Complète les Phases 1 à 5. Tout est compatible **MySQL 8** :
aucun `JSONB`, `ILIKE`, index partiel ni enum SQL.

---

## 1. Ordre des migrations

```text
1. invoices
2. invoice_lines
3. invoice_line_address_snapshots
4. provider_settlements
5. provider_settlement_lines
```

Ordre du §31. Chaque table ne référence que des tables déjà créées.

## 2. Nullabilité

| Colonne | Nullable | Raison |
|---------|----------|--------|
| `invoices.organization_id`, `customer_id`, `invoice_number`, `invoice_date`, `currency_code`, `status`, `created_at` | non | §6 |
| `invoices.period_from`, `period_to` | **oui** | Absents des obligatoires du §6. Une facture ponctuelle ne couvre pas de période. |
| `invoices.external_reference`, `remark` | oui | Références et notes libres. |
| `invoice_lines.order_service_id` | **oui** | Le §10 demande de l'analyser « selon la création de lignes manuelles ». Une ligne libre — frais de dossier, régularisation — ne correspond à aucune prestation. L'imposer interdirait toute facturation hors prestation. |
| `invoice_lines.order_id` | **oui** | Même raison. |
| `invoice_lines.service_code`, `customer_order_reference`, `service_completed_at` | oui | Absents des obligatoires du §10. |
| Snapshot : tout sauf `invoice_line_id` | **oui** | Un snapshot doit savoir figer une adresse incomplète. Exiger `city` refuserait de copier ce qui existe vraiment. |
| `provider_settlements.period_from`, `period_to` | oui | Symétrie avec `invoices`. |
| `provider_settlement_lines.order_service_id` | **oui** | Symétrie : un décompte peut porter une ligne forfaitaire. |
| Montants et taux | **non, défaut 0** | Précédent `orders.weight`, `order_services.*_price`. Une somme sur `NULL` produirait `NULL`. |

## 3. Suppression

| Clé étrangère | Stratégie | Raison |
|---------------|-----------|--------|
| `invoices.organization_id`, `customer_id` | `RESTRICT` | Une facture est une pièce comptable. |
| `invoice_lines.invoice_id` | `CASCADE` | Composition `Invoice *-- InvoiceLine`. |
| `invoice_lines.order_service_id`, `order_id` | `RESTRICT` | Supprimer une commande facturée effacerait la justification de la ligne. |
| `invoice_line_address_snapshots.invoice_line_id` | `CASCADE` | Composition, exigée au §31. |
| `provider_settlements.organization_id`, `provider_id` | `RESTRICT` | Idem facture. |
| `provider_settlement_lines.settlement_id` | `CASCADE` | Composition. |
| `provider_settlement_lines.order_service_id` | `RESTRICT` | Association. |

### Refus applicatifs

| Ressource | Refus | Code |
|-----------|-------|------|
| `InvoiceLine` | dernière ligne de sa facture | 409 |
| `ProviderSettlementLine` | dernière ligne de son décompte | 409 |

**`Invoice` et `ProviderSettlement` restent supprimables.** Le §34 évoque un
refus si « le statut indique un état final selon une règle existante » : aucune
règle n'existe, et les §6 et §15 interdisent d'inventer les valeurs de `status`.
Les interpréter reviendrait à décider lesquelles sont définitives, ce que
personne n'a arrêté. La protection est donc la permission `invoices.delete`.

Les lignes disparaissent par cascade : c'est le comportement d'un agrégat — la
facture entière est ce que l'appelant a demandé à supprimer — et non une cascade
silencieuse.

## 4. Contraintes uniques

| Table | Contrainte | Portée |
|-------|-----------|--------|
| `invoices` | `(organization_id, invoice_number)` | Même portée que `orders.order_number`. |
| `invoice_lines` | `(invoice_id, line_number)` | Numéro unique dans la facture. |
| `invoice_lines` | **`order_service_id`** | §9 : `OrderService "1" -- "0..1" InvoiceLine`. |
| `invoice_line_address_snapshots` | **`invoice_line_id`** | §12 : au plus un snapshot par ligne. |
| `provider_settlements` | `(organization_id, settlement_number)` | |
| `provider_settlement_lines` | **`order_service_id`** | §18. |

### La double unicité sur `order_service_id`

C'est le point structurant de la phase. Les deux contraintes sont
**indépendantes** :

- `invoice_lines.order_service_id` UNIQUE → un service est facturé au plus une
  fois au client ;
- `provider_settlement_lines.order_service_id` UNIQUE → il est décompté au plus
  une fois au fournisseur.

Le même service peut porter **les deux** : ce sont deux flux distincts, le §22
le dit explicitement. Un test le vérifie.

MySQL traitant chaque `NULL` comme distinct, les lignes libres — sans service —
restent possibles en nombre. La contrainte ne mord que sur les lignes
rattachées, ce qui est exactement l'effet recherché.

## 5. Index

| Table | Index |
|-------|-------|
| `invoices` | `organization_id`, `customer_id`, `invoice_date`, `period_from`, `period_to`, `currency_code`, `status`, `external_reference`, `created_at`, unique `(organization_id, invoice_number)` |
| `invoice_lines` | `invoice_id`, `order_id`, `service_code`, `status`, `service_completed_at`, unique `(invoice_id, line_number)`, unique `order_service_id` |
| `invoice_line_address_snapshots` | unique `invoice_line_id` |
| `provider_settlements` | `organization_id`, `provider_id`, `period_from`, `period_to`, `status`, unique `(organization_id, settlement_number)` |
| `provider_settlement_lines` | `settlement_id`, unique `order_service_id` |

Ceux du §33, moins les deux index `legacy_id` — les colonnes n'existent pas.

## 6. Précision monétaire

Le §32 propose `15,2`, `15,3`, `15,4` et `7,4`. **Non retenus** : ils
créeraient une seconde convention monétaire dans la même base.

| Grandeur | Précision | Précédent |
|----------|-----------|-----------|
| Montant | `DECIMAL(12,2)` | `order_services.customer_unit_price`, `claims.cost` |
| Quantité | `DECIMAL(12,3)` | `order_services.quantity`, `packages.quantity` |
| Taux | `DECIMAL(5,2)` | aucun — décision ci-dessous |

`DECIMAL(5,2)` pour `discount_rate` et `tax_rate` : ce sont des pourcentages,
bornés `0–100` par la validation. Deux décimales couvrent tous les taux réels
(8,1 % ; 20 % ; 2,5 %) et alignent leur précision sur celle des montants. `7,4`
permettrait `999,9999 %`, une amplitude sans usage.

### Aucun flottant

Le §32 interdit `float` et `double` pour l'argent. Tous les calculs passent par
`App\Shared\Support\Money`, qui s'appuie sur **`bcmath`** et manipule des
chaînes. `0.1 + 0.2` ne vaut pas `0.3` en binaire, et une facture fausse d'un
centime est une facture fausse.

`bcmath` **tronque** au lieu d'arrondir : `Money::round()` rétablit l'arrondi
commercial en ajoutant un demi-quantum avant la troncature.

## 7. Calcul des totaux

Le §11 et le §14 demandent de chercher les conventions existantes. **Aucune
n'existe** : la Phase 2 enregistre `customer_total_price` tel que fourni, sans le
calculer. Les formules minimales des §11, §14 et §19 sont donc retenues :

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

### Règle d'arrondi

**Chaque total de ligne est arrondi avant d'être sommé.** Sommer des valeurs non
arrondies puis arrondir produirait un écart d'un centime avec la somme des
lignes affichées : c'est la facture qui doit être juste, pas le calcul
intermédiaire.

`taxTotal` d'une facture est **déduit** (`total − subtotal`), jamais sommé
séparément. Le déduire garantit l'identité `subtotal + taxTotal = total`, qu'une
somme indépendante pourrait violer d'un centime après arrondis. Un test le
vérifie sur des montants à décimales non triviales.

### Les totaux ne sont jamais acceptés en entrée

Le §11 l'exige. Les six champs calculés sont absents des Form Requests ; les
fournir n'a aucun effet. Un test poste `subtotal: 99999` et vérifie que la
facture porte bien le total calculé.

Seul `provider_settlements.tax_total` est **saisi** : le §21 interdit d'inventer
une TVA fournisseur, et `ProviderSettlementLine` ne porte ni `taxRate` ni
`taxAmount` — le §18 interdit de les ajouter. Sans taux, aucune taxe n'est
dérivable.

## 8. Cardinalité minimale `1..*`

`Invoice "1" *-- "1..*" InvoiceLine` et
`ProviderSettlement "1" *-- "1..*" ProviderSettlementLine`.

**Création atomique** : le tableau `lines` est exigé, non vide, et le document
avec ses lignes et leurs snapshots est écrit dans une seule transaction. Si une
ligne échoue, rien ne subsiste. Un test soumet une ligne hors périmètre et
compte les factures créées : zéro.

Les références sont vérifiées **avant** la transaction, pour que l'échec soit un
422 lisible plutôt qu'un rollback muet.

**Suppression** : retirer la dernière ligne renvoie 409.

## 9. Snapshots

`invoice_line_address_snapshots` **ne porte aucune clé étrangère vers
`addresses`**. C'est l'objet même du snapshot : corriger une adresse ne doit
jamais réécrire une facture déjà émise. Le §12 l'exige.

Le snapshot disparaît avec sa ligne — composition, portée par la cascade. Un
test le vérifie.

## 10. Numérotation

Les §7 et §16 interdisent d'imposer un format et d'utiliser `count() + 1`.

Le projet possède `GenerateOrderNumber` + `order_number_sequences`, avec verrou
`lockForUpdate`, mais **propre aux commandes** : préfixe `ORD`, table du module
Orders, absente des diagrammes.

**Décision, identique à celle de la Phase 4 pour `tourNumber`** :
`invoiceNumber` et `settlementNumber` sont fournis par l'appelant, obligatoires,
uniques par organisation. Aucune génération automatique, aucun format imposé.

## 11. Timestamps

| Table | `created_at` | `updated_at` |
|-------|--------------|--------------|
| `invoices` | **oui** | non |
| `invoice_lines` | non | non |
| `invoice_line_address_snapshots` | non | non |
| `provider_settlements` | non | non |
| `provider_settlement_lines` | non | non |

`invoices.created_at` existe parce que le diagramme déclare `createdAt` ;
`ProviderSettlement` n'en déclare aucun et n'en a donc aucun. Le §2 range les
« timestamps absents » et `updated_at` parmi les ajouts interdits.

## 12. Absence de `legacy_id` et d'enums

Le §6 liste `legacyId` sur `Invoice`, le §9 sur `InvoiceLine`, le §33 les indexe.
**Le diagramme n'en contient aucun** : 15 attributs pour `Invoice`, 17 pour
`InvoiceLine`. Les colonnes ne sont pas créées — le §1 donne priorité au
diagramme, le §2 interdit d'ajouter un attribut absent, et c'est la décision
déjà prise en Phases 3 et 5. Un test vérifie leur absence.

**Aucun enum.** `Invoice.status`, `InvoiceLine.status` et
`ProviderSettlement.status` sont des `VARCHAR(32)` : le diagramme les déclare
`string` sans énumérer de valeurs.
