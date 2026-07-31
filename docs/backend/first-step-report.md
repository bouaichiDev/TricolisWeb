# Rapport final — Première étape du backend Tricolis V2

Ce document répond au §31 du cahier des charges. Il fait le point sur le socle
backend livré : ce qui a été analysé, décidé, construit, testé, et ce qui reste.

---

## 1. Résumé de l'analyse des diagrammes

Les deux diagrammes PlantUML de `Conception/Diagramme/Classe` ont été dépouillés
intégralement avant tout code. L'analyse complète est dans
[`conception-analysis.md`](conception-analysis.md).

- **Diagramme partagé** — socle transverse : `User`, `Organization`,
  `OrganizationUser`, `Subscription`, `Role`, `Permission`, `UserRole`,
  `RolePermission`, `Agency`, `Depot`, `Address`, `Contact`, `EntityAddress`,
  `EntityContact`, `AddressContact`, `Document`, `DocumentLink`, `AuditLog`.
- **Diagramme plateforme interne** — métier opérationnel : clients et sites,
  catalogues, configurations d'import/export, commandes et lignes, colis,
  services, stock, fournisseurs, flotte, tournées, suivi, réclamations,
  communications, facturation.

Points structurants retenus :

- les rôles sont portés par le rattachement `OrganizationUser`, jamais par
  `User` — c'est la clé de l'isolation multi-organisation ;
- `EntityAddress` / `EntityContact` / `DocumentLink` sont des liaisons
  génériques, traitées en polymorphisme Laravel avec morph map métier ;
- `Organization` (entité organisationnelle) et `Customer` (profil client du
  transporteur) sont deux concepts distincts, jamais fusionnés.

## 2. Ambiguïtés identifiées

Documentées en détail au §12 de `conception-analysis.md`. Les principales :

| # | Ambiguïté | Décision prise |
|---|-----------|----------------|
| A | Statuts sous forme de chaînes libres dans le diagramme | Enums PHP natifs + colonnes `VARCHAR` |
| B | `User.status` et `OrganizationUser.status` coexistent | `User.status` gouverne la connexion ; `OrganizationUser.status` gouverne l'accès à une organisation |
| C | `Subscription` sans relation inverse claire | `Organization 1 — 0..1 Subscription`, exposé en ressource singleton ; aucune logique de facturation à ce stade |
| C bis | Valeurs de `Subscription.status` non énumérées | Enum `SubscriptionStatus` (`trialing`, `active`, `suspended`, `cancelled`, `expired`) — **hypothèse à confirmer** ; la colonne reste `VARCHAR` pour rester ouverte |
| D | `Document.createdBy` sans cardinalité | FK nullable vers `users` en `SET NULL` |
| E | Type de l'entité cible dans les liaisons génériques | Morph map à valeurs métier, aucun FQCN en base |
| F | Unicité du site par défaut par client | Non contraint en base, faute de règle explicite dans le diagramme — signalé plutôt qu'inventé |
| G | `Address.isDefault` en doublon avec `EntityAddress.isDefault` | `EntityAddress.isDefault` fait foi dans un contexte donné |
| H | `Order` sans `organizationId` explicite sur certaines lectures | Colonne `organization_id` ajoutée, indispensable à l'isolation |
| I | `DocumentLink` sans `organizationId` | Isolation portée par `Document`, jamais par la liaison |

## 3. Décisions techniques

- **Identifiants** : ULID `CHAR(26)` partout, générés côté Laravel via
  `App\Shared\Database\Concerns\HasUlid`. Aucun entier auto-incrémenté métier
  exposé, validation `ulid` sur toutes les entrées d'API.
- **Base de données** : MySQL 8, conformément à la stack déjà installée. Les
  recherches utilisent `LIKE`, insensible à la casse avec la collation par défaut.
- **Architecture** : monolithe modulaire — `app/Modules/<Domaine>` pour le
  métier, `app/Shared` pour le transverse, `app/Http` pour la couche HTTP.
  Actions pour les opérations métier, Form Requests pour la validation, API
  Resources pour les réponses, Policies pour l'autorisation, Query Objects
  seulement là où le filtrage le justifie (`CustomerListQuery`).
- **Contexte organisationnel** : en-tête `X-Organization-Id`, validé par
  `EnsureOrganizationContext` et centralisé dans `CurrentOrganizationContext`.
  Aucun Global Scope masquant silencieusement des données.
- **Polymorphisme** : morph map limitée aux entités réellement livrées.
- **Enums** : enums PHP natifs, colonnes `VARCHAR` pour rester évolutives.
- **Suppression** : choix explicite par relation entre `restrictOnDelete()`,
  `nullOnDelete()` et `cascadeOnDelete()`. Suppression logique réservée aux
  documents, avec purge différée. Détail dans
  [`phase-1-database-decisions.md`](phase-1-database-decisions.md).

## 4. Migrations créées

Dans l'ordre de dépendance :

| # | Migration | Table |
|---|-----------|-------|
| 1 | `create_organizations_table` | `organizations` |
| 2 | `create_organization_users_table` | `organization_users` |
| 3 | `create_subscriptions_table` | `subscriptions` |
| 4 | `create_permissions_table` | `permissions` |
| 5 | `create_roles_table` | `roles` |
| 6 | `create_user_roles_table` | `user_roles` |
| 7 | `create_role_permissions_table` | `role_permissions` |
| 8 | `create_agencies_table` | `agencies` |
| 9 | `create_depots_table` | `depots` |
| 10 | `create_addresses_table` | `addresses` |
| 11 | `create_contacts_table` | `contacts` |
| 12 | `create_entity_addresses_table` | `entity_addresses` |
| 13 | `create_entity_contacts_table` | `entity_contacts` |
| 14 | `create_address_contacts_table` | `address_contacts` |
| 15 | `create_documents_table` | `documents` |
| 16 | `create_document_links_table` | `document_links` |
| 17 | `create_audit_logs_table` | `audit_logs` |
| 18 | `create_customers_table` | `customers` |
| 19 | `create_customer_sites_table` | `customer_sites` |
| 20 | `create_personal_access_tokens_table` | `personal_access_tokens` |
| 21 | `create_orders_table` | `orders` |
| 22 | `create_order_lines_table` | `order_lines` |
| 23 | `create_services_table` | `services` |
| 24 | `create_order_services_table` | `order_services` |
| 25 | `add_deleted_at_to_documents_table` | `documents` (rétention) |

La table `users` provient de la migration de base Laravel, étendue aux colonnes
du diagramme.

## 5. Modèles créés

| Module | Modèles |
|--------|---------|
| Identity | `User`, `Role`, `Permission`, `UserRole`, `RolePermission` |
| Organizations | `Organization`, `OrganizationUser`, `Subscription` (+ enum `SubscriptionStatus`) |
| Agencies | `Agency`, `Depot` |
| Addresses | `Address`, `EntityAddress` |
| Contacts | `Contact`, `EntityContact`, `AddressContact` |
| Documents | `Document`, `DocumentLink` |
| Audit | `AuditLog` |
| Customers | `Customer`, `CustomerSite` |
| Orders | `Order`, `OrderLine`, `Service`, `OrderService` |

## 6. Endpoints créés

90 routes sous `/api/v1`.

**Authentification** — `POST auth/register`, `POST auth/login`,
`POST auth/logout`, `POST auth/logout-all`, `GET auth/me`,
`PATCH auth/profile`, `PATCH auth/password`, `POST auth/forgot-password`,
`POST auth/reset-password`, `GET auth/sessions`,
`DELETE auth/sessions/{tokenId}`.

**Organisations** — CRUD `organizations`.

**Abonnements** — ressource singleton de l'organisation active
(`Organization 1 — 0..1 Subscription`) : `GET|POST|PATCH|DELETE subscription`.

**Identité** — CRUD `users`, CRUD `organization-users`, CRUD `roles`,
`GET permissions` et `GET permissions/{permission}` (référentiel en lecture seule).

**Agences et dépôts** — CRUD `agencies`, CRUD `agencies/{agency}/depots`.

**Adresses** — CRUD `addresses`, plus les liaisons génériques
`GET|POST addresses/{address}/links`, `DELETE addresses/{address}/links/{link}`
et les contacts d'adresse `GET|POST addresses/{address}/contacts`,
`DELETE addresses/{address}/contacts/{addressContact}`.

**Contacts** — CRUD `contacts`, plus `GET|POST contacts/{contact}/links` et
`DELETE contacts/{contact}/links/{link}`.

**Documents** — `GET|POST documents`, `GET documents/{document}`,
`GET documents/{document}/download`, `DELETE documents/{document}`, plus les
liaisons `GET|POST documents/{document}/links` et
`DELETE documents/{document}/links/{link}`.

**Audit** — `GET audit-logs` (lecture seule).

**Clients** — CRUD `customers`, `PATCH customers/{customer}/status`,
CRUD `customers/{customer}/sites`.

**Commandes** — `GET|POST orders`, `GET orders/{order}`,
`DELETE orders/{order}`.

## 7. Permissions ajoutées

51 permissions seedées par `PermissionSeeder`, couvrant l'intégralité de la
liste du §12 du cahier des charges :

`dashboard.view` · `organizations.view|create|update` ·
`subscriptions.view|create|update|delete` ·
`users.view|create|update|disable|assign_roles` ·
`roles.view|create|update|delete|assign_permissions` ·
`agencies.view|create|update|delete` · `depots.view|create|update|delete` ·
`customers.view|create|update|delete|block` ·
`customer_sites.view|create|update|delete` ·
`addresses.view|create|update|delete` · `contacts.view|create|update|delete` ·
`documents.view|upload|delete` · `audit.view` ·
`orders.view|create|update|delete`.

## 8. Policies créées

`OrganizationPolicy`, `SubscriptionPolicy`, `OrganizationUserPolicy`, `RolePolicy`, `AgencyPolicy`,
`DepotPolicy`, `AddressPolicy`, `EntityAddressPolicy`, `ContactPolicy`,
`EntityContactPolicy`, `CustomerPolicy`, `CustomerSitePolicy`,
`DocumentPolicy`, `AuditLogPolicy`, `OrderPolicy` — 15 policies, toutes dérivées de
`BaseOrganizationPolicy` qui porte la vérification d'appartenance et de
permission.

## 9. Tests ajoutés

**134 tests Pest**, répartis ainsi :

| Fichier | Couverture |
|---------|-----------|
| `Auth/LoginTest` | connexion valide, mot de passe invalide, compte suspendu, audit des échecs de connexion, email inconnu non audité, profil, déconnexion simple, déconnexion globale, refus sans authentification |
| `Auth/RegisterTest` | inscription atomique, doublons et données invalides |
| `Api/V1/ComplianceTest` | compte désactivé, contexte renvoyé à la connexion, révocation de session, pagination bornée, tri interdit, ULID malformé, permission manquante |
| `OrganizationContextTest` | en-tête requis, identifiant malformé, listing accessible sans organisation active |
| `Organizations/OrganizationTest` | CRUD complet, propriétaire automatique, organisation étrangère masquée, suppression réservée au propriétaire |
| `Organizations/SubscriptionTest` | souscription, unicité (409), dates incohérentes, statut inconnu, période d'essai, abonnement expiré, audit du changement de statut, résiliation, isolation, en-tête requis |
| `Identity/IdentityManagementTest` | création de rôle, affectation au bon `OrganizationUser`, rôle d'une autre organisation refusé |
| `Identity/UserTest` | liste isolée, recherche, création avec rôles, hash jamais exposé, rôle étranger refusé, email dupliqué, modification auditée, désactivation avec révocation des jetons, utilisateur d'une autre organisation masqué, tri interdit |
| `Identity/PermissionTest` | catalogue, filtre par module, détail, absence d'endpoint d'écriture, contexte requis |
| `Documents/DocumentLinkTest` | rattachement, liste, doublon, entité étrangère, type inconnu, détachement, liaison d'un autre document, isolation |
| `Agencies/AgencyTest` | liste, création, modification, suppression, refus si dépôts rattachés, isolation |
| `Agencies/DepotTest` | CRUD complet, dépôt d'une agence étrangère refusé, tri interdit |
| `Addresses/AddressTest` | CRUD complet, isolation, payload invalide |
| `Addresses/AddressLinkTest` | liaisons `EntityAddress` et contacts d'adresse, doublons, entité étrangère, dernière liaison protégée |
| `Contacts/ContactTest` | CRUD complet, isolation, payload invalide |
| `Contacts/ContactLinkTest` | liaisons `EntityContact`, rôle métier invalide, dernière liaison protégée |
| `Customers/CustomerTest` | liste, création, recherche, doublon de code, isolation, représentation compacte |
| `Customers/CustomerSiteTest` | CRUD complet, adresse hors périmètre, site d'un autre client, client d'une autre organisation |
| `Documents/DocumentTest` | téléversement, téléchargement, suppression logique, purge après rétention, exclusion des documents supprimés, isolation |
| `Audit/AuditLogTest` | audit de création, masquage des données sensibles, audit d'attribution de rôle, audit de changement de statut |
| `Orders/OrderTest` | création avec lignes et services, validations, client d'une autre organisation, isolation des listes |
| `LogViewerAccessTest` | accès au visualiseur de logs restreint à une liste blanche |

## 10. Résultat des tests

```text
composer validate                                   ./composer.json is valid
php artisan optimize:clear                          OK
php artisan migrate:fresh --seed --env=testing      OK
php artisan test                                   134 passed (357 assertions)
./vendor/bin/pint --test                            PASS
```

PHPStan / Larastan ne sont pas installés dans le projet ; conformément au §30,
ils n'ont pas été ajoutés.

## 11. Fichiers créés et modifiés

**Documentation** — `conception-analysis.md`, `phase-1-analysis.md`, `phase-1-database-decisions.md`,
`local-development.md`, `first-step-report.md`.

**Configuration** — `config/tricolis.php` (mot de passe de développement,
rétention documentaire), `bootstrap/app.php` (alias `organization`),
`AppServiceProvider` (morph map, transformers OpenAPI, commande de purge),
`AuthServiceProvider` (14 policies).

**Transverse** — `HasUlid`, `MorphMap`, `EntityLinkResolver`, `ApiResponse`,
`ListRequest`, `InputMapper`, `CurrentOrganizationContext`,
`EnsureOrganizationContext`, enums partagés.

**Par module** — modèles, actions (`LoginUser`, `RegisterTransporter`,
`UpdateUserProfile`, `WriteAuditLog`), query object (`CustomerListQuery`),
commande (`PurgeDeletedDocuments`).

**Couche HTTP** — 20 contrôleurs API, 34 Form Requests, 18 API Resources.

**Base de données** — 25 migrations métier (plus les 3 migrations de base
Laravel), 5 seeders orchestrés par `DatabaseSeeder`, 13 factories.

**OpenAPI** — `AddOrganizationHeader`, `DocumentStandardErrors`.

**Tests** — 18 fichiers Pest, `tests/Pest.php` (helpers `authUser`,
`authOrganization`).

## 12. Modules non encore développés

Conformément au §4, les modules suivants restent à faire :

catalogues clients et articles · configurations d'import, d'API et d'export ·
traitements d'export · types de colis et de regroupement · colis et liaison
colis-lignes · contacts de service et colis de service · stock, emplacements,
soldes, mouvements, réservations · fournisseurs · chauffeurs · types de
véhicules et véhicules · tournées, arrêts et services affectés · périodes et
affectations · suivi et preuves de livraison · réclamations · communications,
modèles, règles et pièces jointes · factures, lignes de facture, snapshots
d'adresses et décomptes fournisseurs.

**Écart assumé** : le module Commandes (`Order`, `OrderLine`, `Service`,
`OrderService`) a été livré alors que le §4 le plaçait hors de la première
étape. Il est fonctionnel, testé et isolé par organisation.

## 13. Prochaine étape recommandée

1. **Compléter le module Commandes** déjà amorcé : mise à jour et changement de
   statut d'une commande, transitions de `OrderStatus` et `OrderServiceStatus`,
   `ServiceContact` et contrôle des services exigeant une adresse ou un contact.
2. **Colis** ensuite : `PackageType`, `GroupingType`, `Package` et la liaison
   colis-lignes, qui dépendent des commandes et de rien d'autre.
3. **Puis le stock**, si les clients concernés ont `stockEnabled`.

Deux points à trancher avant d'avancer, hérités de la première étape :

- **PostgreSQL ou MySQL** : le cahier des charges cite PostgreSQL, le projet
  tourne sur MySQL. Le portage se complique à chaque module ajouté.
- **Route des dépôts** : `/api/v1/agencies/{agency}/depots` a été retenu, alors
  que le §14 proposait `/api/v1/depots`. À confirmer avec le frontend avant que
  le contrat ne se fige.
