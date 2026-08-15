# Phase 10 — Audit des décimaux

Document exigé par le §25.

---

## 1. Résultat

```text
colonnes float / double / real     0
colonnes DECIMAL                  62
précisions distinctes              6
```

**Aucun montant, aucune quantité, aucune dimension n'est stockée en virgule
flottante.** Vérifié sur les 72 tables par lecture d'`information_schema`.

## 2. Précisions retenues

| Précision | Emploi | Colonnes |
|---|---|:-:|
| `DECIMAL(12,2)` | **Montants** — prix, coûts, taxes, remises, totaux | 18 |
| `DECIMAL(12,3)` | **Quantités et masses** — kg, unités | 31 |
| `DECIMAL(12,4)` | **Volumes** — m³ | 7 |
| `DECIMAL(5,2)` | **Taux** — remise, TVA (0 à 100 %) | 2 |
| `DECIMAL(10,8)` | **Latitude** — −90 à +90, ~1 mm | 2 |
| `DECIMAL(11,8)` | **Longitude** — −180 à +180, ~1 mm | 2 |

## 3. Détail par famille

### Montants — `DECIMAL(12,2)`

```text
invoices.subtotal · tax_total · total
invoice_lines.unit_price · total_excluding_tax · total_including_tax
provider_settlements.subtotal · tax_total · total
provider_settlement_lines.unit_cost · total_cost
orders (prix client et coût fournisseur)
order_services.customer_unit_price · customer_total_price
order_services.provider_unit_cost · provider_total_cost
claims.cost
tours.total_cost
```

Dix chiffres avant la virgule, deux après : jusqu'à 9 999 999 999,99 dans la
devise de l'organisation. Deux décimales suffisent au dirham comme à l'euro.

### Quantités et masses — `DECIMAL(12,3)`

```text
order_lines.quantity · weight
order_services.quantity · weight
invoice_lines.quantity
provider_settlement_lines.quantity
packages.weight · length · width · height
customer_catalog_items.weight · length · width · height
stock_balances.quantity · reserved
stock_movements.quantity
stock_reservations.quantity
tours.total_weight
vehicles.payload_capacity
```

Trois décimales : le gramme sur une masse en kilogrammes, le millimètre sur une
dimension en mètres.

### Volumes — `DECIMAL(12,4)`

```text
orders.volume · order_lines.volume · order_services.volume
packages.volume · customer_catalog_items.volume
tours.total_volume · vehicles.volume_capacity
```

Quatre décimales parce qu'un volume est un produit de trois dimensions : les
erreurs d'arrondi s'y composent, et un colis de 0,0001 m³ existe.

### Coordonnées — `DECIMAL(10,8)` / `DECIMAL(11,8)`

```text
addresses.latitude · longitude
tracking_events.latitude · longitude
```

Huit décimales ≈ 1,1 mm à l'équateur. La longitude prend un chiffre entier de
plus, son domaine allant jusqu'à ±180.

## 4. Calcul des montants

Le §25 interdit `float` en base ; le projet va plus loin et **interdit aussi
l'arithmétique flottante en PHP**.

`App\Shared\Support\Money` (Phase 6) enveloppe **bcmath** :

```text
SCALE       = 10   précision de travail interne
MONEY_SCALE =  2   précision de restitution
```

Aucun `+`, `*` ou `round()` PHP n'est appliqué à un montant. L'arrondi est
explicite et symétrique autour de zéro :

```php
public static function round(string $value, int $scale = self::MONEY_SCALE): string
{
    $half = '0.'.str_repeat('0', $scale).'5';

    return bccomp($value, '0', self::SCALE) >= 0
        ? bcadd($value, $half, $scale)
        : bcsub($value, $half, $scale);
}
```

Les montants circulent **en chaînes**, de la base à la réponse JSON : `'450.00'`,
jamais `450.0`. C'est ce qui garantit qu'aucune conversion flottante ne s'insère
en chemin, y compris côté client.

## 5. Cohérence des totaux

Deux règles, vérifiées par test :

- **`InvoiceLine.totalExcludingTax`** = `quantity × unitPrice`, remise appliquée,
  arrondi une seule fois à la fin ;
- **`Invoice.taxTotal`** = `total − subtotal`, **déduit** et non sommé. Sommer
  les taxes de chaque ligne accumulerait les arrondis ligne à ligne, et le total
  affiché ne correspondrait plus à la somme des deux autres.

Même règle pour `ProviderSettlement`.

## 6. Casts Eloquent

Chaque colonne décimale porte un cast de précision identique à celle de la
colonne :

```php
'weight' => 'decimal:3',
'volume' => 'decimal:4',
'customer_unit_price' => 'decimal:2',
'latitude' => 'decimal:8',
```

Un cast plus court tronquerait à la lecture ; un cast plus long afficherait des
décimales que la base ne stocke pas. Aucun écart trouvé entre cast et colonne.

## 7. Le cas `delayValue`

`CommunicationRule.delayValue` est un `INT` **signé**, alors que la validation
refuse les valeurs négatives (`min:0`). C'est délibéré : la colonne reste
tolérante, la règle métier est portée là où elle produit un message lisible.

C'est l'inverse du choix fait en Phase 4 pour `TourStop.sequence`, où
l'`UNSIGNED` était nécessaire — le réordonnancement en deux passes y ajoute un
décalage de 1 000 000, et un dépassement silencieux aurait corrompu l'ordre.
