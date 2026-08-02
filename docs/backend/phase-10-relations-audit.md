# Phase 10 — Audit des relations Eloquent

Document exigé par le §7.

---

## 1. Méthode

Les relations du diagramme (`--`, `*--`, `"1"`, `"0..*"`, `"0..1"`) ont été
confrontées aux relations Eloquent déclarées sur les 63 modèles, puis à la
nullabilité réelle des clés étrangères en base. Trois choses devaient concorder :
la cardinalité, le nom de la clé étrangère, et la nullabilité.

## 2. Résultat d'ensemble

```text
relations déclarées dans les diagrammes    121
relations Eloquent correspondantes         121
relations manquantes                         0
relations en trop                            0
cardinalités incohérentes                    0
```

Une seule relation du diagramme n'a pas de contrepartie : les deux relations de
`CustomerUser`, classe non implémentée (voir l'inventaire UML). Elles ne sont
pas comptées ci-dessus, faute de classe.

## 3. Correspondances par domaine

| Modèle | Relation | Diagramme | Eloquent | Statut |
|---|---|---|---|---|
| Organization | users | `"1" -- "0..*" OrganizationUser` | `hasMany(OrganizationUser)` | CONFORME |
| Organization | subscription | `"1" -- "0..1" Subscription` | `hasOne(Subscription)` | CONFORME |
| OrganizationUser | user / organization | `"1" --` des deux côtés | `belongsTo` ×2 | CONFORME |
| Role | permissions | `"0..*" -- "0..*"` via RolePermission | `belongsToMany` + pivot explicite | CONFORME |
| Agency | depots | `"1" -- "0..*"` | `hasMany(Depot)` | CONFORME |
| Address | entityAddresses | `"1" -- "0..*"` | `hasMany(EntityAddress)` | CONFORME |
| Contact | entityContacts | `"1" -- "0..*"` | `hasMany(EntityContact)` | CONFORME |
| Document | links | `"1" -- "0..*"` | `hasMany(DocumentLink)` | CONFORME |
| Customer | sites, catalogs, stockItems | `"1" -- "0..*"` ×3 | `hasMany` ×3 | CONFORME |
| Customer | 3 configurations d'intégration | `"1" -- "0..*"` ×3 | `hasMany` ×3 | CONFORME |
| Order | lines, orderServices, packages | `"1" -- "0..*"` ×3 | `hasMany` ×3 | CONFORME |
| Order | parent / children | `"0..1" -- "0..*"` récursif | `belongsTo(self)` + `hasMany(self)` | CONFORME |
| OrderService | contacts, servicePackages | `"1" *-- "0..*"` | `hasMany` ×2 | CONFORME |
| OrderService | packages | `"0..*" -- "0..*"` | `belongsToMany` + pivot | CONFORME |
| Package | orderLines | `"0..*" -- "0..*"` via PackageOrderLine | `belongsToMany` + pivot | CONFORME |
| Package | parent / children | récursif | `belongsTo(self)` + `hasMany(self)` | CONFORME |
| StockLocation | parent / children | récursif | `belongsTo(self)` + `hasMany(self)` | CONFORME |
| StockItem | balances, movements, reservations | `"1" -- "0..*"` ×3 | `hasMany` ×3 | CONFORME |
| Provider | drivers, vehicles, settlements | `"1" -- "0..*"` ×3 | `hasMany` ×3 | CONFORME |
| Tour | stops, periods | `"1" *-- "0..*"` ×2 | `hasMany` ×2 | CONFORME |
| TourStop | stopServices | `"1" *-- "0..*"` | `hasMany(TourStopService)` | CONFORME |
| TourPeriod | assignments | `"1" *-- "0..*"` | `hasMany(TourPeriodAssignment)` | CONFORME |
| Invoice | lines | `"1" *-- "0..*"` | `hasMany(InvoiceLine)` | CONFORME |
| InvoiceLine | addressSnapshots | `"1" *-- "0..*"` | `hasMany(InvoiceLineAddressSnapshot)` | CONFORME |
| ProviderSettlement | lines | `"1" *-- "0..*"` | `hasMany(ProviderSettlementLine)` | CONFORME |
| CommunicationTemplate | rules, communications | `"1" -- "0..*"`, `"0..1" -- "0..*"` | `hasMany` ×2 | CONFORME |
| OrderCommunication | attachments | `"1" *-- "0..*"` | `hasMany(CommunicationAttachment)` | CONFORME |
| OrderCommunication | creator | `User "0..1" -- "0..*" : createdBy` | `belongsTo(User, 'created_by')` | CONFORME |

## 4. Relations polymorphes

Cinq relations morphiques existent, toutes adossées à la **morph map** :

| Modèle | Colonnes | Alias métier |
|---|---|---|
| `EntityAddress` | `entity_type`, `entity_id` | ✓ |
| `EntityContact` | `entity_type`, `entity_id` | ✓ |
| `DocumentLink` | `entity_type`, `entity_id` | ✓ |
| `AuditLog` | `entity_type`, `entity_id` | ✓ |
| `StockMovement` | `source_entity_type`, `source_entity_id` | ✓ |
| `ExportJob` | `entity_type`, `entity_id` | ✓ (sans relation Eloquent) |

**Aucun nom de classe PHP n'est stocké en base.** `MorphMap::register()` couvre
les 58 entités livrées ; un test vérifie qu'un `AuditLog` ne contient jamais de
FQCN. `ExportJob.entity_type` et `StockMovement.source_entity_type` sont validés
contre `MorphMap::registered()`, **dérivé** de la map et jamais recopié : la
liste reste juste au premier module ajouté.

`ExportJob` ne déclare pas de relation Eloquent polymorphe : la colonne n'a pas
de clé étrangère et peut désigner plusieurs tables — c'est un pointeur
d'export, pas une association.

## 5. Nullabilité des clés étrangères

Systématiquement vérifiée contre la cardinalité :

| Cardinalité du diagramme | Colonne | Contrôle |
|---|---|---|
| `"1" --` | NOT NULL | 100 % conforme |
| `"0..1" --` | NULL | 100 % conforme |

Aucun cas où une cardinalité `"1"` serait implémentée en colonne nullable, ni
l'inverse.

## 6. Chargement anticipé

Les listes chargent explicitement ce que leurs Resources consomment :

| Endpoint | `with()` |
|---|---|
| `GET /orders` | `customer`, `agency` |
| `GET /tours` | `agency`, `depot`, `provider` + `withCount('stops')` |
| `GET /order-communications` | `template`, `order` + `withCount('attachments')` |
| `GET /communication-rules` | `template` |
| `GET /export-jobs` | `configuration` (colonnes restreintes) |
| `GET /organization-users` | `user`, `roles` |
| `GET /invoices` | `customer` + `withCount('lines')` |

Un test de budget de requêtes (`QueryBudgetTest`) vérifie sur cinq de ces
endpoints que le nombre de requêtes est **identique** avec 3 lignes et avec 20.

### Un point de fragilité, sans conséquence aujourd'hui

`OrganizationUserResource` lit `$this->user->first_name` et `$this->roles`
**sans `whenLoaded()`**. Ce n'est pas un N+1 en pratique : les quatre points
d'appel du contrôleur chargent les deux relations. Mais la Resource ne se
protège pas elle-même — un futur appel qui oublierait le `with()` produirait
deux requêtes par ligne.

**Décision : conservée telle quelle**, et couverte par le test de budget qui
échouerait immédiatement si le chargement disparaissait. La modifier changerait
la forme de la réponse quand les relations sont absentes, ce qui casserait un
contrat déjà consommé.
