# Phase 10 — Inventaire UML

Document exigé par le §3. Aucune correction n'a été entreprise avant sa
production.

---

## 1. Méthode

Les classes ont été extraites **mécaniquement** des deux diagrammes, pas
recopiées à la main : un script lit chaque bloc `class X { … }`, en tire les
attributs, et les compare aux colonnes réelles de la base. Le comptage est donc
reproductible et non déclaratif.

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Les `.puml` cités au §1 n'existent pas ; les `.txt` font foi depuis la Phase 4,
sur instruction de l'utilisateur.

Le diagramme interne redéclare sept classes du diagramme partagé sous forme de
souches sans attribut (`Organization`, `User`, `Agency`, `Depot`, `Address`,
`Contact`, `Document`) : elles ne sont comptées qu'une fois.

## 2. Comptage

```text
classes du diagramme partagé            18
classes propres au diagramme interne    45
─────────────────────────────────────────
total des classes UML                   63

classes CONFORMES                       62
classes MANQUANTES                       1   (CustomerUser)
classes EN_TROP                          0
classes INCOHÉRENTES                     0
```

## 3. Inventaire détaillé

Colonnes : `M` modèle Eloquent · `T` table · `Mig` migration · `F` factory ·
`P` policy · `R` API Resource · `Test` couverture par test.

### Diagramme partagé

| Classe UML | M | T | Mig | F | P | R | Test | Statut |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|---|
| User | ✓ | `users` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Organization | ✓ | `organizations` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| OrganizationUser | ✓ | `organization_users` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Subscription | ✓ | `subscriptions` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Role | ✓ | `roles` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Permission | ✓ | `permissions` | ✓ | — | — | ✓ | ✓ | **CONFORME** |
| UserRole | ✓ | `user_roles` | ✓ | — | — | — | ✓ | **CONFORME** |
| RolePermission | ✓ | `role_permissions` | ✓ | — | — | — | ✓ | **CONFORME** |
| Agency | ✓ | `agencies` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Depot | ✓ | `depots` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Address | ✓ | `addresses` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Contact | ✓ | `contacts` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| EntityAddress | ✓ | `entity_addresses` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| EntityContact | ✓ | `entity_contacts` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| AddressContact | ✓ | `address_contacts` | ✓ | ✓ | — | ✓ | ✓ | **CONFORME** |
| Document | ✓ | `documents` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| DocumentLink | ✓ | `document_links` | ✓ | ✓ | — | ✓ | ✓ | **CONFORME** |
| AuditLog | ✓ | `audit_logs` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |

`Permission`, `UserRole` et `RolePermission` n'ont ni factory ni policy propres :
`permissions` est un référentiel en lecture seule alimenté par un seeder ; les
deux tables de liaison sont créées et contrôlées par `RoleController` et
`OrganizationUserController`, sous leurs policies respectives.

`AddressContact` et `DocumentLink` n'ont pas de policy propre : leur périmètre
est celui de leur parent, vérifié par `AddressPolicy` et `DocumentPolicy`.

### Diagramme interne

| Classe UML | M | T | Mig | F | P | R | Test | Statut |
|---|:-:|:-:|:-:|:-:|:-:|:-:|:-:|---|
| Customer | ✓ | `customers` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CustomerSite | ✓ | `customer_sites` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| **CustomerUser** | — | — | — | — | — | — | — | **MANQUANT** |
| CustomerCatalog | ✓ | `customer_catalogs` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CustomerCatalogItem | ✓ | `customer_catalog_items` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CustomerImportConfiguration | ✓ | `customer_import_configurations` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CustomerApiConfiguration | ✓ | `customer_api_configurations` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CustomerExportConfiguration | ✓ | `customer_export_configurations` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| ExportJob | ✓ | `export_jobs` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Order | ✓ | `orders` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| OrderLine | ✓ | `order_lines` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| PackageType | ✓ | `package_types` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| GroupingType | ✓ | `grouping_types` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Package | ✓ | `packages` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| PackageOrderLine | ✓ | `package_order_lines` | ✓ | ✓ | — | ✓ | ✓ | **CONFORME** |
| Service | ✓ | `services` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| OrderService | ✓ | `order_services` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| OrderServiceContact | ✓ | `order_service_contacts` | ✓ | ✓ | — | ✓ | ✓ | **CONFORME** |
| OrderServicePackage | ✓ | `order_service_packages` | ✓ | ✓ | — | ✓ | ✓ | **CONFORME** |
| StockItem | ✓ | `stock_items` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| StockLocation | ✓ | `stock_locations` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| StockBalance | ✓ | `stock_balances` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| StockMovement | ✓ | `stock_movements` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| StockReservation | ✓ | `stock_reservations` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Provider | ✓ | `providers` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Driver | ✓ | `drivers` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| VehicleType | ✓ | `vehicle_types` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Vehicle | ✓ | `vehicles` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Tour | ✓ | `tours` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| TourStop | ✓ | `tour_stops` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| TourStopService | ✓ | `tour_stop_services` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| TourPeriod | ✓ | `tour_periods` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| TourPeriodAssignment | ✓ | `tour_period_assignments` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| TrackingEvent | ✓ | `tracking_events` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| ProofOfDelivery | ✓ | `proofs_of_delivery` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Claim | ✓ | `claims` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CommunicationTemplate | ✓ | `communication_templates` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CommunicationRule | ✓ | `communication_rules` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| OrderCommunication | ✓ | `order_communications` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| CommunicationAttachment | ✓ | `communication_attachments` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| Invoice | ✓ | `invoices` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| InvoiceLine | ✓ | `invoice_lines` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| InvoiceLineAddressSnapshot | ✓ | `invoice_line_address_snapshots` | ✓ | ✓ | — | ✓ | ✓ | **CONFORME** |
| ProviderSettlement | ✓ | `provider_settlements` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |
| ProviderSettlementLine | ✓ | `provider_settlement_lines` | ✓ | ✓ | ✓ | ✓ | ✓ | **CONFORME** |

---

## 4. La seule classe manquante — `CustomerUser`

Le diagramme interne la déclare, lignes 128-134, avec deux relations :

```text
class CustomerUser {
  +id: ULID
  +customerId: ULID
  +userId: ULID
  +status: string
  +isAdmin: boolean
}

Customer "1" -- "0..*" CustomerUser
User "1" -- "0..*" CustomerUser
```

Elle n'a jamais été implémentée : aucune table, aucun modèle, aucune référence
dans le code — vérifié par recherche du nom et de la table sur l'ensemble de
`app/` et `database/`.

**Elle n'est pas créée par cette phase**, et c'est un choix, pas un oubli. Le §2
est catégorique : « Ne créer aucune nouvelle classe métier persistée. Ne créer
aucune nouvelle table métier. » Le §41 le répète. Le §3 prévoit précisément le
statut `MANQUANT` pour ce cas : constater, documenter, ne pas construire.

Ce qu'elle apporterait est identifiable : elle rattache un `User` à un
`Customer`, avec un indicateur d'administrateur — c'est le socle d'un **portail
client**, où un contact du client se connecterait pour suivre ses commandes.
L'enum `OrderSource` contient d'ailleurs déjà `CUSTOMER_PORTAL`, et
`CustomerApiConfiguration` (Phase 8) couvre l'accès machine du même client.

Aucune fonctionnalité livrée n'en dépend : rien, dans les Phases 1 à 9, ne la
référence. Son absence ne casse rien ; elle laisse un pan du diagramme non
réalisé.

**Reporté à une phase ultérieure**, avec son module : table, modèle, policy,
routes et — surtout — le garde-fou qui empêcherait un utilisateur client de voir
les commandes d'un autre client.

---

## 5. Tables sans classe UML

Une seule table métier n'a pas de classe correspondante :

| Table | Origine | Nature | Décision |
|---|---|---|---|
| `order_number_sequences` | Phase 3 | **technique** | **Conservée** |

Elle porte le compteur par organisation et par année qui produit
`Order.orderNumber`. Ce n'est pas une entité métier : elle n'est jamais exposée,
n'a ni route ni resource, et sert uniquement de point de verrouillage
(`lockForUpdate`) pour que deux commandes simultanées n'obtiennent pas le même
numéro. Le §2 autorise explicitement les classes techniques non persistées ; le
raisonnement vaut pour une table de séquence, dont l'alternative — un
`MAX(order_number) + 1` — serait sujette à collision.

Les neuf tables techniques de Laravel sont conformes à la liste du §6 :

```text
cache · cache_locks · failed_jobs · job_batches · jobs
migrations · password_reset_tokens · personal_access_tokens · sessions
```

```text
72 tables au total
 −  9 techniques Laravel
 −  1 technique projet (order_number_sequences)
─────────────────────────────────────────────
   62 tables métier = 62 classes UML implémentées
```
