# Analyse de conception — Backend Tricolis V2

Ce rapport est produit à partir des deux diagrammes PlantUML sources de vérité :

- `Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt`
- `Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt`

Il couvre l’ensemble du modèle détecté, la séparation entre modules partagés et internes, les correspondances SQL/Eloquent, les ambiguïtés et l’ordre de développement recommandé.

---

## 1. Classes détectées

### Module partagé (`Diagramme de classes partagées`)

| # | Classe | Package / Domaine |
|---|--------|-------------------|
| 1 | `User` | Identité et organisation |
| 2 | `Organization` | Identité et organisation |
| 3 | `OrganizationUser` | Identité et organisation |
| 4 | `Subscription` | Identité et organisation |
| 5 | `Role` | Sécurité et autorisations |
| 6 | `Permission` | Sécurité et autorisations |
| 7 | `UserRole` | Sécurité et autorisations |
| 8 | `RolePermission` | Sécurité et autorisations |
| 9 | `Agency` | Structure du transporteur |
| 10 | `Depot` | Structure du transporteur |
| 11 | `Address` | Adresses et contacts |
| 12 | `Contact` | Adresses et contacts |
| 13 | `EntityAddress` | Adresses et contacts (liaison générique) |
| 14 | `EntityContact` | Adresses et contacts (liaison générique) |
| 15 | `AddressContact` | Adresses et contacts |
| 16 | `Document` | Documents et audit |
| 17 | `DocumentLink` | Documents et audit (liaison générique) |
| 18 | `AuditLog` | Documents et audit |

### Module interne (`Diagramme de classes plateforme interne`)

| # | Classe | Package / Domaine |
|---|--------|-------------------|
| 1 | `Customer` | Clients du transporteur |
| 2 | `CustomerSite` | Clients du transporteur |
| 3 | `CustomerUser` | Clients du transporteur |
| 4 | `CustomerCatalog` | Clients du transporteur |
| 5 | `CustomerCatalogItem` | Clients du transporteur |
| 6 | `CustomerImportConfiguration` | Clients du transporteur |
| 7 | `CustomerApiConfiguration` | Clients du transporteur |
| 8 | `CustomerExportConfiguration` | Clients du transporteur |
| 9 | `ExportJob` | Clients du transporteur |
| 10 | `Order` | Commandes, articles et colis |
| 11 | `OrderLine` | Commandes, articles et colis |
| 12 | `PackageType` | Commandes, articles et colis |
| 13 | `GroupingType` | Commandes, articles et colis |
| 14 | `Package` | Commandes, articles et colis |
| 15 | `PackageOrderLine` | Commandes, articles et colis |
| 16 | `Service` | Services de commande |
| 17 | `OrderService` | Services de commande |
| 18 | `OrderServiceContact` | Services de commande |
| 19 | `OrderServicePackage` | Services de commande |
| 20 | `StockItem` | Stock client chez le transporteur |
| 21 | `StockLocation` | Stock client chez le transporteur |
| 22 | `StockBalance` | Stock client chez le transporteur |
| 23 | `StockMovement` | Stock client chez le transporteur |
| 24 | `StockReservation` | Stock client chez le transporteur |
| 25 | `Provider` | Fournisseurs et ressources |
| 26 | `Driver` | Fournisseurs et ressources |
| 27 | `VehicleType` | Fournisseurs et ressources |
| 28 | `Vehicle` | Fournisseurs et ressources |
| 29 | `Tour` | Planification des services |
| 30 | `TourStop` | Planification des services |
| 31 | `TourStopService` | Planification des services |
| 32 | `TourPeriod` | Planification des services |
| 33 | `TourPeriodAssignment` | Planification des services |
| 34 | `TrackingEvent` | Suivi, POD et réclamations |
| 35 | `ProofOfDelivery` | Suivi, POD et réclamations |
| 36 | `Claim` | Suivi, POD et réclamations |
| 37 | `CommunicationTemplate` | Communication et templates |
| 38 | `CommunicationRule` | Communication et templates |
| 39 | `OrderCommunication` | Communication et templates |
| 40 | `CommunicationAttachment` | Communication et templates |
| 41 | `Invoice` | Facturation client et fournisseur |
| 42 | `InvoiceLine` | Facturation client et fournisseur |
| 43 | `InvoiceLineAddressSnapshot` | Facturation client et fournisseur |
| 44 | `ProviderSettlement` | Facturation client et fournisseur |
| 45 | `ProviderSettlementLine` | Facturation client et fournisseur |

**Total : 18 classes partagées + 45 classes internes = 63 classes.**

---

## 2. Enums détectés

### Module partagé

| Enum | Valeurs |
|------|---------|
| `OrganizationStatus` | `PENDING`, `ACTIVE`, `SUSPENDED`, `CLOSED` |
| `UserStatus` | `INVITED`, `ACTIVE`, `SUSPENDED`, `DISABLED` |
| `ContactRole` | `LOAD`, `DELIVERY`, `BILLING`, `OPERATIONS`, `EMERGENCY`, `OTHER` |

### Module interne

| Enum | Valeurs |
|------|---------|
| `CustomerStatus` | `ACTIVE`, `INACTIVE`, `BLOCKED` |
| `OrderSource` | `INTERNAL`, `CUSTOMER_PORTAL`, `REST_API`, `CSV_IMPORT`, `EXCEL_IMPORT`, `XML_IMPORT`, `STOCK`, `CATALOG` |
| `OrderStatus` | `DRAFT`, `CONFIRMED`, `READY`, `PARTIALLY_PLANNED`, `PLANNED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, `PARTIALLY_INVOICED`, `INVOICED` |
| `OrderServiceStatus` | `DRAFT`, `PENDING`, `READY_TO_PLAN`, `PLANNED`, `IN_PROGRESS`, `COMPLETED`, `FAILED`, `CANCELLED`, `INVOICED` |
| `TourStatus` | `DRAFT`, `PLANNED`, `CONFIRMED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED` |
| `TourStopStatus` | `PENDING`, `ARRIVED`, `IN_PROGRESS`, `COMPLETED`, `SKIPPED`, `CANCELLED` |
| `ExportFormat` | `XML`, `CSV`, `JSON`, `PDF` |
| `ExportTransport` | `FTP`, `SFTP`, `REST_API`, `EMAIL`, `MANUAL` |
| `CommunicationChannel` | `EMAIL`, `SMS`, `WHATSAPP`, `PUSH_NOTIFICATION`, `INTERNAL_NOTIFICATION` |
| `CommunicationTemplateType` | `APPOINTMENT_REQUEST`, `APPOINTMENT_CONFIRMATION`, `APPOINTMENT_REMINDER`, `DRIVER_ASSIGNED`, `DRIVER_DEPARTED`, `ARRIVAL_ESTIMATE`, `ARRIVAL_SOON`, `DELIVERY_CONFIRMATION`, `DELIVERY_FAILED`, `POD_AVAILABLE`, `ORDER_CANCELLED`, `CUSTOM` |
| `CommunicationEventType` | `ORDER_CREATED`, `ORDER_CONFIRMED`, `ORDER_CANCELLED`, `SERVICE_PLANNED`, `APPOINTMENT_REQUESTED`, `APPOINTMENT_CONFIRMED`, `DRIVER_ASSIGNED`, `TOUR_STOP_APPROACHING`, `SERVICE_COMPLETED`, `POD_CREATED`, `CLAIM_CREATED` |
| `CommunicationStatus` | `DRAFT`, `SCHEDULED`, `QUEUED`, `SENDING`, `SENT`, `DELIVERED`, `READ`, `FAILED`, `CANCELLED` |
| `RecipientRole` | `CUSTOMER`, `LOAD_CONTACT`, `DELIVERY_CONTACT`, `BILLING_CONTACT`, `INTERNAL_USER`, `CUSTOM` |

---

## 3. Relations et cardinalités

### Partagées

| Relation | Cardinalité | Remarque |
|----------|-------------|----------|
| `User` → `OrganizationUser` | 1 — 0..* | Un utilisateur peut appartenir à plusieurs organisations. |
| `Organization` → `OrganizationUser` | 1 — 1..* | Une organisation a au moins un rattachement (son créateur). |
| `Organization` → `Subscription` | 1 — 0..1 | Une organisation a zéro ou un abonnement actif. |
| `OrganizationUser` → `UserRole` | 1 — 0..* | Les rôles sont attachés au rattachement, non à l’utilisateur global. |
| `Role` → `UserRole` | 1 — 0..* | |
| `Organization` → `Role` | 1 — 0..* | Les rôles sont propres à une organisation. |
| `Role` → `RolePermission` | 1 — 0..* | |
| `Permission` → `RolePermission` | 1 — 0..* | |
| `Organization` → `Agency` | 1 — 0..* | |
| `Agency` → `Depot` | 1 — 0..* | Le dépôt appartient au transporteur via l’agence. |
| `Address` → `EntityAddress` | 1 — 0..* | Liaison polymorphe générique. |
| `Contact` → `EntityContact` | 1 — 0..* | Liaison polymorphe générique. |
| `Address` → `AddressContact` | 1 — 0..* | |
| `Contact` → `AddressContact` | 1 — 0..* | |
| `Document` → `DocumentLink` | 1 — 0..* | Liaison polymorphe. |
| `Organization` → `AuditLog` | 1 — 0..* | |
| `User` → `AuditLog` | 0..1 — 0..* | L’auteur d’un audit peut être inconnu (système). |

### Internes

| Relation | Cardinalité | Remarque |
|----------|-------------|----------|
| `Organization` → `Customer` | 1 — 0..* | |
| `Customer` → `CustomerSite` | 1 — 0..* | |
| `Address` → `CustomerSite` | 1 — 0..* | |
| `Customer` → `CustomerUser` | 1 — 0..* | |
| `User` → `CustomerUser` | 1 — 0..* | |
| `Customer` → `CustomerCatalog` | 1 — 0..* | |
| `CustomerCatalog` → `CustomerCatalogItem` | 1 — 0..* | |
| `Customer` → `CustomerImportConfiguration` | 1 — 0..* | |
| `Customer` → `CustomerApiConfiguration` | 1 — 0..* | |
| `Customer` → `CustomerExportConfiguration` | 1 — 0..* | |
| `CustomerExportConfiguration` → `ExportJob` | 1 — 0..* | |
| `Customer` → `Order` | 1 — 0..* | |
| `Agency` → `Order` | 1 — 0..* | |
| `Depot` → `Order` | 0..1 — 0..* | |
| `Order` → `Order` (parent) | 0..1 — 0..* | Relation récursive. |
| `Order` → `OrderLine` | 1 — 1..* | Composition forte. |
| `CustomerCatalogItem` → `OrderLine` | 0..1 — 0..* | |
| `Order` → `Package` | 1 — 0..* | Composition. |
| `Package` → `Package` (parent) | 0..1 — 0..* | Relation récursive. |
| `PackageType` → `Package` | 1 — 0..* | |
| `GroupingType` → `Package` | 1 — 0..* | |
| `Package` → `PackageOrderLine` | 1 — 0..* | |
| `OrderLine` → `PackageOrderLine` | 1 — 0..* | |
| `StockLocation` → `Package` | 0..1 — 0..* | Localisation courante. |
| `Order` → `OrderService` | 1 — 1..* | Composition. |
| `Service` → `OrderService` | 1 — 0..* | |
| `Address` → `OrderService` | 1 — 0..* | |
| `OrderService` → `OrderServiceContact` | 1 — 0..* | Composition. |
| `Contact` → `OrderServiceContact` | 0..1 — 0..* | |
| `OrderService` → `OrderServicePackage` | 1 — 0..* | |
| `Package` → `OrderServicePackage` | 1 — 0..* | |
| `Customer` → `StockItem` | 1 — 0..* | |
| `CustomerCatalogItem` → `StockItem` | 0..1 — 0..* | |
| `Depot` → `StockLocation` | 1 — 0..* | |
| `StockLocation` → `StockLocation` (parent) | 0..1 — 0..* | Relation récursive. |
| `StockItem` → `StockBalance` | 1 — 0..* | |
| `StockLocation` → `StockBalance` | 1 — 0..* | |
| `StockItem` → `StockMovement` | 1 — 0..* | |
| `StockItem` → `StockReservation` | 1 — 0..* | |
| `StockLocation` → `StockReservation` | 1 — 0..* | |
| `OrderLine` → `StockReservation` | 1 — 0..* | |
| `Organization` → `Provider` | 1 — 0..* | |
| `Provider` → `Address` | 0..* — 0..1 | |
| `Provider` → `Contact` | 0..* — 0..1 | |
| `Provider` → `Driver` | 1 — 0..* | |
| `Provider` → `Vehicle` | 1 — 0..* | |
| `VehicleType` → `Vehicle` | 1 — 0..* | |
| `Organization` → `Tour` | 1 — 0..* | |
| `Agency` → `Tour` | 1 — 0..* | |
| `Depot` → `Tour` | 0..1 — 0..* | |
| `Provider` → `Tour` | 0..1 — 0..* | |
| `Driver` → `Tour` | 0..1 — 0..* | |
| `Vehicle` → `Tour` | 0..1 — 0..* | |
| `Driver` → `Address` | 0..* — 0..1 | |
| `Driver` → `Contact` | 0..* — 0..1 | |
| `Tour` → `TourStop` | 1 — 0..* | Composition. |
| `Address` → `TourStop` | 1 — 0..* | |
| `TourStop` → `TourStopService` | 1 — 1..* | Composition. |
| `OrderService` → `TourStopService` | 1 — 0..* | |
| `Tour` → `TourPeriod` | 1 — 0..* | Composition. |
| `TourStop` → `TourPeriod` | 0..1 — 0..* | |
| `TourPeriod` → `TourPeriodAssignment` | 1 — 0..* | Composition. |
| `TourStopService` → `TourPeriodAssignment` | 1 — 0..* | |
| `Package` → `TourPeriodAssignment` | 0..1 — 0..* | |
| `Organization` → `CommunicationTemplate` | 1 — 0..* | |
| `Organization` → `CommunicationRule` | 1 — 0..* | |
| `Organization` → `OrderCommunication` | 1 — 0..* | |
| `Service` → `CommunicationTemplate` | 0..1 — 0..* | |
| `Service` → `CommunicationRule` | 0..1 — 0..* | |
| `CommunicationTemplate` → `CommunicationRule` | 1 — 0..* | |
| `CommunicationTemplate` → `OrderCommunication` | 0..1 — 0..* | |
| `CommunicationRule` → `OrderCommunication` | 0..1 — 0..* | |
| `Order` → `OrderCommunication` | 1 — 0..* | |
| `User` → `OrderCommunication` (createdBy) | 0..1 — 0..* | |
| `OrderCommunication` → `CommunicationAttachment` | 1 — 0..* | Composition. |
| `Document` → `CommunicationAttachment` | 1 — 0..* | |
| `Order` → `TrackingEvent` | 1 — 0..* | |
| `OrderService` → `TrackingEvent` | 0..1 — 0..* | |
| `Tour` → `TrackingEvent` | 0..1 — 0..* | |
| `TourStop` → `TrackingEvent` | 0..1 — 0..* | |
| `Order` → `ProofOfDelivery` | 1 — 0..* | |
| `OrderService` → `ProofOfDelivery` | 0..1 — 0..* | |
| `TourStop` → `ProofOfDelivery` | 0..1 — 0..* | |
| `Document` → `ProofOfDelivery` | 0..1 — 0..* | |
| `Customer` → `Claim` | 1 — 0..* | |
| `Order` → `Claim` | 0..1 — 0..* | |
| `OrderService` → `Claim` | 0..1 — 0..* | |
| `Tour` → `Claim` | 0..1 — 0..* | |
| `Customer` → `Invoice` | 1 — 0..* | |
| `Invoice` → `InvoiceLine` | 1 — 1..* | Composition. |
| `OrderService` → `InvoiceLine` | 1 — 0..1 | |
| `InvoiceLine` → `InvoiceLineAddressSnapshot` | 1 — 0..1 | Composition. |
| `Provider` → `ProviderSettlement` | 1 — 0..* | |
| `ProviderSettlement` → `ProviderSettlementLine` | 1 — 1..* | Composition. |
| `OrderService` → `ProviderSettlementLine` | 1 — 0..1 | |

---

## 4. Séparation entités partagées / entités internes

### Partagées (fondation de toute la plateforme)

Ces entités sont utilisées par les deux diagrammes et doivent être développées en premier :

- `User`, `Organization`, `OrganizationUser`, `Subscription`
- `Role`, `Permission`, `UserRole`, `RolePermission`
- `Agency`, `Depot`
- `Address`, `Contact`, `EntityAddress`, `EntityContact`, `AddressContact`
- `Document`, `DocumentLink`, `AuditLog`

### Internes (métier opérationnel Tricolis)

Ces entités dépendent des entités partagées et seront développées dans des modules dédiés, en commençant par la première étape :

**Première étape (scope actuel) :**

- `Customer`, `CustomerSite`

**Étapes futures :**

- `CustomerUser`, `CustomerCatalog`, `CustomerCatalogItem`, `CustomerImportConfiguration`, `CustomerApiConfiguration`, `CustomerExportConfiguration`, `ExportJob`
- `Order`, `OrderLine`, `PackageType`, `GroupingType`, `Package`, `PackageOrderLine`
- `Service`, `OrderService`, `OrderServiceContact`, `OrderServicePackage`
- `StockItem`, `StockLocation`, `StockBalance`, `StockMovement`, `StockReservation`
- `Provider`, `Driver`, `VehicleType`, `Vehicle`
- `Tour`, `TourStop`, `TourStopService`, `TourPeriod`, `TourPeriodAssignment`
- `TrackingEvent`, `ProofOfDelivery`, `Claim`
- `CommunicationTemplate`, `CommunicationRule`, `OrderCommunication`, `CommunicationAttachment`
- `Invoice`, `InvoiceLine`, `InvoiceLineAddressSnapshot`, `ProviderSettlement`, `ProviderSettlementLine`

---

## 5. Correspondance classes PlantUML → tables SQL

Chaque classe devient une table MySQL. Toutes les clés primaires et étrangères sont de type `CHAR(26)` (ULID).

| Classe | Table SQL | Remarque |
|--------|-----------|----------|
| `User` | `users` | |
| `Organization` | `organizations` | |
| `OrganizationUser` | `organization_users` | Table de jonction enrichie. |
| `Subscription` | `subscriptions` | |
| `Role` | `roles` | |
| `Permission` | `permissions` | Référentiel global. |
| `UserRole` | `user_roles` | |
| `RolePermission` | `role_permissions` | |
| `Agency` | `agencies` | |
| `Depot` | `depots` | |
| `Address` | `addresses` | |
| `Contact` | `contacts` | |
| `EntityAddress` | `entity_addresses` | Polymorphe. |
| `EntityContact` | `entity_contacts` | Polymorphe. |
| `AddressContact` | `address_contacts` | |
| `Document` | `documents` | |
| `DocumentLink` | `document_links` | Polymorphe. |
| `AuditLog` | `audit_logs` | |
| `Customer` | `customers` | |
| `CustomerSite` | `customer_sites` | |
| `CustomerUser` | `customer_users` | |
| `CustomerCatalog` | `customer_catalogs` | |
| `CustomerCatalogItem` | `customer_catalog_items` | |
| `CustomerImportConfiguration` | `customer_import_configurations` | |
| `CustomerApiConfiguration` | `customer_api_configurations` | |
| `CustomerExportConfiguration` | `customer_export_configurations` | |
| `ExportJob` | `export_jobs` | |
| `Order` | `orders` | |
| `OrderLine` | `order_lines` | |
| `PackageType` | `package_types` | |
| `GroupingType` | `grouping_types` | |
| `Package` | `packages` | |
| `PackageOrderLine` | `package_order_lines` | |
| `Service` | `services` | |
| `OrderService` | `order_services` | |
| `OrderServiceContact` | `order_service_contacts` | |
| `OrderServicePackage` | `order_service_packages` | |
| `StockItem` | `stock_items` | |
| `StockLocation` | `stock_locations` | |
| `StockBalance` | `stock_balances` | |
| `StockMovement` | `stock_movements` | |
| `StockReservation` | `stock_reservations` | |
| `Provider` | `providers` | |
| `Driver` | `drivers` | |
| `VehicleType` | `vehicle_types` | |
| `Vehicle` | `vehicles` | |
| `Tour` | `tours` | |
| `TourStop` | `tour_stops` | |
| `TourStopService` | `tour_stop_services` | |
| `TourPeriod` | `tour_periods` | |
| `TourPeriodAssignment` | `tour_period_assignments` | |
| `TrackingEvent` | `tracking_events` | |
| `ProofOfDelivery` | `proof_of_deliveries` | |
| `Claim` | `claims` | |
| `CommunicationTemplate` | `communication_templates` | |
| `CommunicationRule` | `communication_rules` | |
| `OrderCommunication` | `order_communications` | |
| `CommunicationAttachment` | `communication_attachments` | |
| `Invoice` | `invoices` | |
| `InvoiceLine` | `invoice_lines` | |
| `InvoiceLineAddressSnapshot` | `invoice_line_address_snapshots` | |
| `ProviderSettlement` | `provider_settlements` | |
| `ProviderSettlementLine` | `provider_settlement_lines` | |

---

## 6. Correspondance classes → modèles Eloquent

| Classe | Modèle Eloquent | Namespace proposé |
|--------|-----------------|-------------------|
| `User` | `User` | `App\Modules\Identity\Models` |
| `Organization` | `Organization` | `App\Modules\Organizations\Models` |
| `OrganizationUser` | `OrganizationUser` | `App\Modules\Organizations\Models` |
| `Subscription` | `Subscription` | `App\Modules\Organizations\Models` |
| `Role` | `Role` | `App\Modules\Identity\Models` ou `Security` |
| `Permission` | `Permission` | `App\Modules\Identity\Models` |
| `UserRole` | `UserRole` | `App\Modules\Identity\Models` |
| `RolePermission` | `RolePermission` | `App\Modules\Identity\Models` |
| `Agency` | `Agency` | `App\Modules\Agencies\Models` |
| `Depot` | `Depot` | `App\Modules\Agencies\Models` |
| `Address` | `Address` | `App\Modules\Addresses\Models` |
| `Contact` | `Contact` | `App\Modules\Contacts\Models` |
| `EntityAddress` | `EntityAddress` | `App\Modules\Addresses\Models` |
| `EntityContact` | `EntityContact` | `App\Modules\Contacts\Models` |
| `AddressContact` | `AddressContact` | `App\Modules\Contacts\Models` |
| `Document` | `Document` | `App\Modules\Documents\Models` |
| `DocumentLink` | `DocumentLink` | `App\Modules\Documents\Models` |
| `AuditLog` | `AuditLog` | `App\Modules\Audit\Models` |
| `Customer` | `Customer` | `App\Modules\Customers\Models` |
| `CustomerSite` | `CustomerSite` | `App\Modules\Customers\Models` |

Les modèles futurs suivront la même convention dans leurs namespaces respectifs.

---

## 7. Clés étrangères

### Partagées

| Table | Colonne | Référence | Stratégie de suppression |
|-------|---------|-----------|--------------------------|
| `organization_users` | `organization_id` | `organizations.id` | `CASCADE` |
| `organization_users` | `user_id` | `users.id` | `RESTRICT` |
| `subscriptions` | `organization_id` | `organizations.id` | `CASCADE` |
| `roles` | `organization_id` | `organizations.id` | `CASCADE` |
| `user_roles` | `organization_user_id` | `organization_users.id` | `CASCADE` |
| `user_roles` | `role_id` | `roles.id` | `CASCADE` |
| `role_permissions` | `role_id` | `roles.id` | `CASCADE` |
| `role_permissions` | `permission_id` | `permissions.id` | `CASCADE` |
| `agencies` | `organization_id` | `organizations.id` | `CASCADE` |
| `depots` | `agency_id` | `agencies.id` | `RESTRICT` |
| `entity_addresses` | `organization_id` | `organizations.id` | `CASCADE` |
| `entity_addresses` | `address_id` | `addresses.id` | `RESTRICT` |
| `entity_contacts` | `organization_id` | `organizations.id` | `CASCADE` |
| `entity_contacts` | `contact_id` | `contacts.id` | `RESTRICT` |
| `address_contacts` | `address_id` | `addresses.id` | `CASCADE` |
| `address_contacts` | `contact_id` | `contacts.id` | `CASCADE` |
| `documents` | `organization_id` | `organizations.id` | `CASCADE` |
| `documents` | `created_by` | `users.id` | `SET NULL` |
| `document_links` | `document_id` | `documents.id` | `CASCADE` |
| `audit_logs` | `organization_id` | `organizations.id` | `CASCADE` |
| `audit_logs` | `user_id` | `users.id` | `SET NULL` |

### Internes (première étape)

| Table | Colonne | Référence | Stratégie de suppression |
|-------|---------|-----------|--------------------------|
| `customers` | `organization_id` | `organizations.id` | `CASCADE` |
| `customer_sites` | `customer_id` | `customers.id` | `CASCADE` |
| `customer_sites` | `address_id` | `addresses.id` | `RESTRICT` |

### Internes (futures)

- `customer_*` tables → `customers.id`
- `orders` → `organizations`, `customers`, `agencies`, `depots`
- `order_lines` → `orders`, `customer_catalog_items`, `parent_line`
- `packages` → `orders`, `parent_package`, `package_types`, `grouping_types`, `stock_locations`
- `package_order_lines` → `packages`, `order_lines`
- `order_services` → `orders`, `services`, `addresses`
- `order_service_contacts` → `order_services`, `contacts`
- `order_service_packages` → `order_services`, `packages`
- `stock_*` tables → `customers`, `depots`, `catalog_items`, `stock_items`, `stock_locations`
- `providers`, `drivers`, `vehicles` → `organizations`, `providers`, `addresses`, `contacts`
- `tours`, `tour_stops`, `tour_periods` → `organizations`, `agencies`, `depots`, `providers`, `drivers`, `vehicles`, `addresses`, `orders`, `order_services`, `packages`
- `tracking_events`, `proof_of_deliveries`, `claims` → `organizations`, `customers`, `orders`, `order_services`, `tours`, `tour_stops`, `documents`
- `communications` → `organizations`, `services`, `templates`, `rules`, `orders`, `users`
- `invoices`, `provider_settlements` → `organizations`, `customers`, `providers`, `order_services`

---

## 8. Relations polymorphes

Les relations suivantes utilisent des clés polymorphes (`entity_type` + `entity_id`) avec une morph map Laravel stable :

| Table | Colonnes | Entités supportées |
|-------|----------|--------------------|
| `entity_addresses` | `entity_type`, `entity_id` | `organization`, `customer`, `customer_site`, `agency`, `depot`, `provider`, `driver` |
| `entity_contacts` | `entity_type`, `entity_id` | `organization`, `customer`, `customer_site`, `agency`, `depot`, `provider`, `driver` |
| `document_links` | `entity_type`, `entity_id` | `organization`, `customer`, `customer_site`, `agency`, `depot`, `order`, `order_service`, `claim`, `invoice`, `provider`, `driver`, `vehicle`, `tour` |
| `audit_logs` | `entity_type`, `entity_id` | Toutes les entités métier auditables. |

**Décision :** utiliser une morph map centralisée dans `App\Shared\Database\MorphMap` ou un service provider, avec des valeurs métier contrôlées. Aucun nom de classe PHP ne sera stocké en base.

---

## 9. Relations récursives

| Entité | Relation | Signification |
|--------|----------|---------------|
| `Order` | `parentOrderId` | Commande parente / sous-commandes. |
| `Package` | `parentPackageId` | Colis parent / sous-colis. |
| `StockLocation` | `parentLocationId` | Emplacement parent / sous-emplacements. |

---

## 10. Relations plusieurs-à-plusieurs

Avec table intermédiaire explicite :

| Entité A | Table intermédiaire | Entité B | Description |
|----------|---------------------|----------|-------------|
| `OrganizationUser` | `user_roles` | `Role` | Rôles d’un rattachement. |
| `Role` | `role_permissions` | `Permission` | Permissions d’un rôle. |
| `User` | `organization_users` | `Organization` | Appartenance multi-organisation. |

Relations de composition avec table de liaison :

| Entité A | Table intermédiaire | Entité B |
|----------|---------------------|----------|
| `Package` | `package_order_lines` | `OrderLine` |
| `OrderService` | `order_service_contacts` | `Contact` |
| `OrderService` | `order_service_packages` | `Package` |
| `TourPeriod` | `tour_period_assignments` | `TourStopService` / `Package` |

---

## 11. Agrégats métier identifiés

Un agrégat regroupe une entité racine et ses entités dépendantes.

### Partagés

1. **Identité** : `User` (racine) → `OrganizationUser` → `UserRole` → `Role` → `RolePermission`.
2. **Organisation** : `Organization` (racine) → `Subscription`, `Agency`, `Depot`, `Role`, `AuditLog`.
3. **Adresses** : `Address` (racine) → `EntityAddress`, `AddressContact`.
4. **Contacts** : `Contact` (racine) → `EntityContact`, `AddressContact`.
5. **Documents** : `Document` (racine) → `DocumentLink`.

### Internes

6. **Client** : `Customer` (racine) → `CustomerSite`, `CustomerUser`, `CustomerCatalog`, `CustomerCatalogItem`, `CustomerImportConfiguration`, `CustomerApiConfiguration`, `CustomerExportConfiguration`, `ExportJob`.
7. **Commande** : `Order` (racine) → `OrderLine`, `Package`, `PackageOrderLine`, `OrderService`, `OrderServiceContact`, `OrderServicePackage`.
8. **Stock** : `StockItem` / `StockLocation` (racines) → `StockBalance`, `StockMovement`, `StockReservation`.
9. **Tournée** : `Tour` (racine) → `TourStop`, `TourStopService`, `TourPeriod`, `TourPeriodAssignment`.
10. **Communication** : `OrderCommunication` (racine) → `CommunicationAttachment`.
11. **Facturation** : `Invoice` (racine) → `InvoiceLine` → `InvoiceLineAddressSnapshot`.
12. **Fournisseur** : `Provider` (racine) → `Driver`, `Vehicle`, `ProviderSettlement`.

---

## 12. Ambiguïtés et incohérences éventuelles

### A. Statuts sous forme de chaînes

Plusieurs entités définissent un attribut `status` de type `string` sans enum explicite :

- `Subscription.status`
- `Role.status`
- `Agency.status`
- `Depot.status`
- `Address.status`
- `Document.status`
- `CustomerSite.status`
- `Service.status`
- etc.

**Décision :** implémenter ces statuts comme des enums PHP natifs dès la première étape lorsqu’ils sont utilisés, ou comme colonnes `string` validées par des enums créés au besoin. Le document `docs/backend/phase-1-database-decisions.md` détaillera chaque choix.

### B. `User.status` vs `OrganizationUser.status`

Le diagramme partagé réutilise `UserStatus` pour `User` et `OrganizationUser`. Cependant, un statut global (`SUSPENDED`) et un statut de rattachement peuvent avoir des sens différents. Nous conservons `UserStatus` pour les deux conformément au diagramme, en documentant que le statut du rattachement peut restreindre l’accès à une organisation spécifique.

### C. `Subscription` : absence de relation inverse claire

`Organization 1 — 0..1 Subscription` est unidirectionnel dans le diagramme. Nous conservons la relation telle quelle.

### D. `Document` et `createdBy`

`createdBy` référence `User.id`. Nous le nommerons `created_by` en base.

### E. Liaisons polymorphes `EntityAddress` et `EntityContact`

Ces tables contiennent `organizationId` en plus de `entityId`. Cela permet d’isoler les adresses/contacts au niveau organisationnel, même si l’entité liée est elle-même déjà liée à une organisation. Cette redondance est volontaire pour faciliter les requêtes et l’isolation.

### F. `CustomerSite` : un seul site par défaut ?

Le diagramme indique `isDefault: boolean` mais ne précise pas la contrainte d’unicité. La documentation métier mentionne « un seul site par défaut par client si cette règle est compatible avec le diagramme ». Nous ajouterons une contrainte d’unicité partielle sur `(customer_id, isDefault)` uniquement lorsque `isDefault = true`, en la justifiant dans `phase-1-database-decisions.md`.

### G. `Address.isDefault`

`Address` possède un champ `isDefault`. Il n’est pas clair si cette valeur est globale ou propre à une liaison `EntityAddress`. Le diagramme la place directement sur `Address`. Nous la conservons sur `Address` mais privilégions `EntityAddress.isDefault` pour le marquage par entité liée.

### H. `Order` sans `organizationId` explicite dans certaines lectures

Le diagramme interne place bien `organizationId` sur `Order`. Aucune ambiguïté.

### I. `DocumentLink` : pas de `organizationId`

`DocumentLink` n’a pas de `organizationId` propre. L’isolation organisationnelle passe par `Document.organizationId`.

---

## 13. Ordre recommandé de création des migrations

### Phase 1 — Fondations partagées

1. `users`
2. `organizations`
3. `organization_users`
4. `subscriptions`
5. `permissions`
6. `roles`
7. `user_roles`
8. `role_permissions`
9. `agencies`
10. `depots`
11. `addresses`
12. `contacts`
13. `entity_addresses`
14. `entity_contacts`
15. `address_contacts`
16. `documents`
17. `document_links`
18. `audit_logs`

### Phase 2 — Première étape métier interne

19. `customers`
20. `customer_sites`

### Phase 3 — Modules futurs (à planifier, non développés maintenant)

21. `customer_users`, `customer_catalogs`, `customer_catalog_items`
22. `customer_import_configurations`, `customer_api_configurations`, `customer_export_configurations`, `export_jobs`
23. `services`, `package_types`, `grouping_types`
24. `orders`, `order_lines`
25. `packages`, `package_order_lines`
26. `order_services`, `order_service_contacts`, `order_service_packages`
27. `stock_items`, `stock_locations`, `stock_balances`, `stock_movements`, `stock_reservations`
28. `providers`, `vehicle_types`, `vehicles`, `drivers`
29. `tours`, `tour_stops`, `tour_stop_services`, `tour_periods`, `tour_period_assignments`
30. `tracking_events`, `proof_of_deliveries`, `claims`
31. `communication_templates`, `communication_rules`, `order_communications`, `communication_attachments`
32. `invoices`, `invoice_lines`, `invoice_line_address_snapshots`, `provider_settlements`, `provider_settlement_lines`

---

## 14. Ordre recommandé de développement des modules

### Itération 1 — Socle identité et sécurité

1. `Shared` : `HasUlid`, `MorphMap`, base des réponses API.
2. `Identity` : authentification Sanctum, utilisateurs, profil, sessions.
3. `Organizations` : organisations, rattachements, rôles, permissions, contexte actif.

### Itération 2 — Structure et référentiels partagés

4. `Agencies` : agences et dépôts.
5. `Addresses` : adresses et liaisons polymorphes.
6. `Contacts` : contacts et liaisons polymorphes.
7. `Documents` : documents et liaisons polymorphes.
8. `Audit` : journal d’audit et mécanisme générique.

### Itération 3 — Premier module métier interne

9. `Customers` : clients et sites clients.

### Itérations futures (hors scope actuel)

10. `Catalogs`, `Orders`, `Packages`, `Services`, `Stock`, `Providers`, `Fleet`, `Tours`, `Tracking`, `Claims`, `Communications`, `Billing`.

---

## 15. Décisions techniques préliminaires

- **Framework** : Laravel 13.23.0 (déjà installé) ; PHP 8.4.2.
- **Base de données** : MySQL.
- **Identifiants** : ULID (`CHAR(26)`) pour toutes les clés primaires et étrangères.
- **Auth** : Laravel Sanctum.
- **Tests** : PHPUnit (déjà configuré dans `phpunit.xml`).
- **Architecture** : monolithe modulaire sous `app/Modules/` et `app/Shared/`.
- **Enums** : enums PHP natifs, stockés en `string` en base.
- **Relations polymorphes** : morph map métier stable, pas de nom de classe PHP en base.
- **Isolation** : contexte organisationnel explicite (`CurrentOrganizationContext`) et `X-Organization-Id` header.
- **Audit** : mécanisme explicite dans les Actions, pas d’observer global automatique.

---

## 16. Fichiers attendus dans la première étape

- `docs/backend/conception-analysis.md` (ce fichier)
- `docs/backend/phase-1-database-decisions.md`
- `docs/backend/local-development.md`
- `app/Shared/Database/Concerns/HasUlid.php`
- `app/Shared/Database/MorphMap.php`
- `app/Modules/Identity/...`
- `app/Modules/Organizations/...`
- `app/Modules/Agencies/...`
- `app/Modules/Addresses/...`
- `app/Modules/Contacts/...`
- `app/Modules/Documents/...`
- `app/Modules/Audit/...`
- `app/Modules/Customers/...`
- Migrations, seeders, factories, tests feature/unit correspondants.
