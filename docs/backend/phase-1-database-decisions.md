# Décisions base de données — Backend Tricolis V2

Ce document explique les choix techniques appliqués aux migrations MySQL.

## 1. Identifiants

- Toutes les clés primaires et étrangères métier utilisent des **ULID** (`CHAR(26)`).
- Génération côté Laravel via le trait `App\Shared\Database\Concerns\HasUlid`.
- Aucun identifiant auto-incrémenté exposé.

## 2. Stratégies de suppression

| Relation | Stratégie | Justification |
|----------|-----------|---------------|
| `organization_users.organization_id → organizations.id` | `CASCADE` | Si une organisation est supprimée, ses rattachements le sont aussi. |
| `organization_users.user_id → users.id` | `RESTRICT` | Empêcher la suppression d’un utilisateur lié à une organisation. |
| `subscriptions.organization_id → organizations.id` | `CASCADE` | Donnée dépendante de l’organisation ; contrainte unique : une organisation porte au plus un abonnement. |
| `roles.organization_id → organizations.id` | `CASCADE` | Rôles propres à l’organisation. |
| `user_roles.*` | `CASCADE` | Table de jonction. |
| `role_permissions.*` | `CASCADE` | Table de jonction. |
| `agencies.organization_id → organizations.id` | `CASCADE` | |
| `depots.agency_id → agencies.id` | `RESTRICT` | Protéger les dépôts référencés. |
| `entity_addresses.address_id → addresses.id` | `RESTRICT` | Empêcher la suppression d’une adresse utilisée. |
| `entity_contacts.contact_id → contacts.id` | `RESTRICT` | Empêcher la suppression d’un contact utilisé. |
| `documents.created_by → users.id` | `SET NULL` | Conserver l’historique documentaire. |
| `audit_logs.user_id → users.id` | `SET NULL` | Conserver l’audit si l’utilisateur est supprimé. |
| `customers.organization_id → organizations.id` | `CASCADE` | |
| `customer_sites.customer_id → customers.id` | `CASCADE` | |
| `customer_sites.address_id → addresses.id` | `RESTRICT` | |

## 3. Relations polymorphes

Utilisation de colonnes `entity_type` (`VARCHAR(64)`) + `entity_id` (`CHAR(26)`).

Aucun nom de classe PHP stocké. Les valeurs sont définies dans `App\Shared\Database\MorphMap` :

- `organization`, `subscription`, `user`, `organization_user`, `role`, `agency`, `depot`, `address`, `contact`, `customer`, `customer_site`, `order`, `document`

Seules les entités réellement livrées figurent dans la morph map. Les alias des
modules futurs (fournisseurs, chauffeurs, véhicules, réclamations, factures)
seront ajoutés en même temps que leur module, pour éviter des valeurs orphelines.

Ces valeurs sont utilisées pour `EntityAddress`, `EntityContact`, `DocumentLink` et `AuditLog`.

## 4. Contraintes uniques

- `organizations.code` : unique (code organisationnel).
- `users.email` : unique (email normalisé en minuscules).
- `organization_users(organization_id, user_id)` : unique.
- `roles(organization_id, code)` : unique.
- `permissions.code` : unique (référentiel global).
- `agencies(organization_id, code)` : unique.
- `depots(agency_id, code)` : unique.
- `customers(organization_id, code)` : unique.
- `customer_sites(customer_id, code)` : unique.
- `entity_addresses(entity_type, entity_id, address_id, address_type)` : éviter les doublons.
- `entity_contacts(entity_type, entity_id, contact_id, contact_role)` : éviter les doublons.
- `address_contacts(address_id, contact_id, contact_role)` : éviter les doublons.

## 5. Colonnes JSON

Les colonnes JSON sont utilisées uniquement pour des structures potentiellement évolutives :

- `organizations.settings`
- `entity_addresses.metadata` (si besoin futur)
- `audit_logs.old_values`, `audit_logs.new_values`
- `customer_export_configurations.settings` (futur)
- `customer_api_configuration.permissions` (futur)

Validation au niveau des Form Requests ; les colonnes utilisent le type JSON natif de MySQL.

## 6. Enums

Les enums sont implémentés comme des **enums PHP natifs** et stockés en `VARCHAR` en base.

Cela facilite l’évolution des valeurs sans migration complexe de type ENUM MySQL.

Enums créés dans la première étape :

- `OrganizationStatus`
- `UserStatus`
- `ContactRole`
- `CustomerStatus`
- `SubscriptionStatus`
- `OrderSource`, `OrderStatus`, `OrderServiceStatus` (module Commandes)

`SubscriptionStatus` mérite une note : le diagramme déclare `Subscription.status`
comme une chaîne libre sans en énumérer les valeurs. Les cinq valeurs retenues
(`trialing`, `active`, `suspended`, `cancelled`, `expired`) sont une **hypothèse
documentée**, pas une règle lue dans la conception. La colonne restant un
`VARCHAR(20)`, ajouter ou renommer une valeur ne demandera qu'un changement
d'enum PHP, sans migration.

## 7. Index

Index créés sur :

- toutes les clés étrangères ;
- `users.email` (unique) ;
- `organizations.code` (unique) ;
- `customers(organization_id, code)` (unique composite) ;
- `agencies(organization_id, code)` ;
- `depots(agency_id, code)` ;
- `entity_addresses(entity_type, entity_id)` pour les requêtes polymorphes ;
- `audit_logs(organization_id, created_at)` pour les listes d’audit filtrées ;
- `documents.deleted_at` pour exclure efficacement les documents supprimés logiquement.

## 8. Nullabilité

- Les champs obligatoires du diagramme sont `NOT NULL`.
- Les champs optionnels (`addressLine2`, `addressLine3`, `floor`, `receivedAt`, `trialEndsAt`, etc.) sont `NULL`.
- `entity_addresses.organization_id` est `NOT NULL` pour préserver l’isolation, même si l’entité liée en possède déjà une.

## 9. Timestamps

- `created_at` / `updated_at` ajoutés conformément au diagramme.
- Les tables de jonction sans champs métier (`user_roles`, `role_permissions`) n’ont pas de timestamps pour rester légères, sauf si le diagramme les précise.

## 10. Suppression logique et rétention documentaire

Une seule table utilise la suppression logique : **`documents`** (`deleted_at`).

Justification : un document est une pièce justificative. Une suppression
accidentelle ou malveillante ne doit pas détruire immédiatement le fichier, et
l’entrée d’audit correspondante doit rester rattachable à une ligne existante.

Cycle de vie retenu :

1. `DELETE /api/v1/documents/{document}` effectue une **suppression logique**.
   Le document disparaît des listes, du détail et du téléchargement (404), mais
   la ligne et le fichier restent en place. L’opération est auditée.
2. Le fichier est conservé pendant `tricolis.document_retention_days`
   (30 jours par défaut, surchargeable via `DOCUMENT_RETENTION_DAYS`).
3. La commande `php artisan documents:purge` supprime définitivement les
   documents dont `deleted_at` dépasse la rétention : liaisons `DocumentLink`,
   ligne en base, puis fichier sur le disque privé.

Les autres entités de la première étape utilisent une suppression physique,
protégée en amont :

- suppression refusée en `409` lorsqu’une entité est encore référencée
  (agence possédant des dépôts, client possédant des sites) ;
- `restrictOnDelete()` au niveau SQL pour les adresses et contacts utilisés ;
- `OrganizationUser` n’est jamais supprimé : `DELETE` bascule son statut à
  `disabled` pour préserver l’historique des rattachements.
