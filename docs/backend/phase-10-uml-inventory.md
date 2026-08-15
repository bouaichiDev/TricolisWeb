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
classes MANQUANTES                       1   (CustomerUser — hors périmètre)
classes EN_TROP                          0
classes INCOHÉRENTES                     0
```

`CustomerUser` porte le statut `MANQUANT` parce que l'inventaire décrit ce qui
existe : la classe est absente du code. Mais son absence **n'est pas un oubli des
Phases 1 à 10** — elle relève du second backend, celui des portails. Voir §4.

**Les dix phases livrées couvrent la plateforme interne**, et elles la couvrent
intégralement : 62 classes sur 62.

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

## 4. `CustomerUser` — hors du périmètre interne

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

### Ce n'est pas un oubli, c'est une frontière

Les dix phases livrées constituent le **backend de la plateforme interne** : ce
qu'utilisent les collaborateurs du transporteur. `CustomerUser` n'y a pas sa
place, parce qu'elle ne sert à personne en interne — elle rattache un `User` à
un `Customer` pour que ce contact **se connecte lui-même**.

Elle appartient donc au **second backend, celui des portails**, qui reste à
construire :

```text
portail client        CustomerUser  ← seule classe déjà décrite au diagramme
portail fournisseur   à définir
portail chauffeur     à définir
```

Deux éléments livrés la préfigurent déjà : l'enum `OrderSource` contient
`CUSTOMER_PORTAL`, et `CustomerApiConfiguration` (Phase 8) couvre l'accès
**machine** du même client — le portail en sera l'accès **humain**.

Le §2 de cette phase confirme la décision indépendamment : « Ne créer aucune
nouvelle classe métier persistée. Ne créer aucune nouvelle table métier. » Le
§3 prévoit le statut `MANQUANT` pour exactement ce cas : constater, documenter,
ne pas construire.

Aucune fonctionnalité livrée n'en dépend : rien, dans les Phases 1 à 10, ne la
référence.

### Ce que les portails demanderont — et que les diagrammes ne disent pas encore

`CustomerUser` est la **seule** classe de liaison entre un `User` et un tiers
présente dans les diagrammes. Ni `Provider` ni `Driver` ne portent de `userId`,
et aucune classe `ProviderUser` ou `DriverUser` n'est déclarée :

```text
class Provider {  id · organizationId · addressId · contactId · code · name · status  }
class Driver   {  id · organizationId · providerId · addressId · contactId · code · name · status  }
```

Les portails fournisseur et chauffeur exigeront donc **d'abord une extension des
diagrammes**, puis le module correspondant. Le portail client, lui, peut
démarrer immédiatement : sa classe est déjà spécifiée.

Dans les trois cas, le point délicat sera le même — et il ne ressemble à rien de
ce que les dix phases ont traité. L'isolation actuelle repose sur une question :
« cet utilisateur appartient-il à l'organisation active ? ». Un portail en pose
une autre : « cet utilisateur a-t-il le droit de voir **cette commande-ci**,
parmi celles de son propre client ? ». `BaseOrganizationPolicy` ne sait pas y
répondre.

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
