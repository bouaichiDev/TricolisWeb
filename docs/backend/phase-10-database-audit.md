# Phase 10 — Audit de la base

Documents exigés par les §4, §5 et §6.

---

## 1. Méthode

Le schéma réel a été extrait d'`information_schema` — colonnes, types,
nullabilité, clés étrangères, règles de suppression, index — puis comparé
mécaniquement aux attributs lus dans les diagrammes. Aucune vérification n'est
déclarative : chaque ligne ci-dessous vient d'une comparaison exécutée.

## 2. Résultat d'ensemble

```text
62 tables métier comparées à 62 classes UML

colonnes manquantes      0
colonnes en trop         0   (hors timestamps et colonnes Laravel, voir §4)
écarts de nom            1   (users.password, voir §4)
types incompatibles      0
FK non ULID              0
PK non ULID              0
float / double           0
```

**Aucune table ne s'écarte du diagramme.** Les 62 tables portent exactement le
nombre d'attributs de leur classe, aux exceptions documentées ci-dessous.

## 3. Vérifications par table

| Contrôle | Résultat |
|---|---|
| Nom de table conforme à la convention | 62 / 62 |
| Clé primaire `CHAR(26)` ULID | 62 / 62 |
| Clés étrangères en `CHAR(26)` | 100 % |
| Colonnes du diagramme présentes | 100 % |
| Timestamps conformes au diagramme | 62 / 62 |
| Aucun `ENUM` SQL | 62 / 62 |
| JSON en type natif MySQL 8 | 100 % — aucun `JSONB` |
| Décimaux sans `float`/`double` | 60 colonnes, toutes en `DECIMAL` |

Les tables dont le diagramme ne donne pas `updatedAt` ont bien `$timestamps`
désactivé ou une seule colonne `created_at` : `communication_attachments`,
`document_links`, `tour_stop_services`, `entity_addresses`, `entity_contacts`,
`address_contacts`, `export_jobs`, `user_roles`, `role_permissions`.

---

## 4. Colonnes supplémentaires détectées

Quatre colonnes seulement n'apparaissent dans aucun diagramme. Toutes sont
imposées par le framework ou par une convention Laravel non négociable.

| Table | Colonne | Raison | Risque | Décision |
|---|---|---|---|---|
| `users` | `password` | Le diagramme dit `passwordHash`. **Même colonne, autre nom.** Laravel résout le mot de passe par `getAuthPassword()`, qui retourne `password` par défaut. | **Nul.** La colonne stocke bien une empreinte bcrypt, jamais un mot de passe en clair. | **Conservée.** Renommer imposerait de surcharger `getAuthPassword()` et de reconfigurer Sanctum, les réinitialisations et les tentatives de connexion — pour un gain purement lexical. |
| `users` | `remember_token` | Contrat `Illuminate\Contracts\Auth\Authenticatable`. | Nul : masquée par `#[Hidden]`, jamais exposée. | **Conservée.** |
| toutes | `created_at` / `updated_at` | Les diagrammes les déclarent sous la forme `createdAt` / `updatedAt`. | Nul. | **Conservées.** Ce ne sont pas des colonnes en trop mais la forme `snake_case` des attributs du diagramme. |
| `documents` | `deleted_at` | `SoftDeletes`, décidé en Phase 2 : un document supprimé reste référencé par des `DocumentLink` et par l'audit. | Faible. Documenté au rapport de la Phase 2. | **Conservée.** |

### Un écart de nommage, pas de structure

Le diagramme écrit `addressLine1` ; la colonne s'appelle `address_line_1`. Le
comparateur mécanique les distingue, mais il s'agit de la même donnée : la
conversion `camelCase → snake_case` place ou non un tiret bas avant le chiffre
selon la règle retenue. Les 23 attributs d'`Address` sont présents, ni plus ni
moins. **Aucune correction** : renommer trois colonnes casserait les requêtes,
les factories et les Resources sans rien apporter.

---

## 5. Tables inventées

Aucune table métier n'existe hors diagramme. Une seule table technique du projet
s'ajoute aux neuf de Laravel :

| Table | Nature | Décision |
|---|---|---|
| `order_number_sequences` | Compteur par organisation et par année, verrouillé pour la numérotation des commandes (Phase 3). | **Conservée.** Jamais exposée, sans route ni resource. L'alternative — `MAX(order_number) + 1` — produirait des doublons sous concurrence. |

Aucune des tables interdites par le §2 n'existe. Vérifié table par table, par
test automatisé :

```text
communication_recipients · notifications · notification_templates
notification_preferences · email_logs · sms_logs · whatsapp_logs
push_notification_logs · internal_notifications · communication_queues
communication_providers · communication_status_histories · communication_deliveries
webhooks · webhook_deliveries · message_threads · conversations · messages
scheduled_communications · export_templates · api_tokens · status_definitions
status_transitions · payments · credit_notes · pricing_rules · price_lists
warehouses · stock_zones · contracts · availabilities · imports · import_rows
import_errors · planning_boards · route_optimizations · incidents · driver_notes
```

---

## 6. Stratégies de suppression

Répartition réelle des 138 clés étrangères :

| Stratégie | Emploi | Justification |
|---|---|---|
| `RESTRICT` | Majoritaire | Toute association `--` du diagramme, et tout rattachement à `Organization`. Une donnée référencée ne disparaît pas sous celle qui la référence. |
| `CASCADE` | Compositions `*--` uniquement | `communication_attachments → order_communications`, `invoice_line_address_snapshots → invoice_lines`, `tour_stop_services → tour_stops`, `order_service_*`, `package_order_lines`, `entity_*`, les trois configurations client → `customers`. |
| `SET NULL` | `created_by`, `updated_by`, et les références facultatives dont le contenu est figé ailleurs | `order_communications.template_id` et `.communication_rule_id` : le contenu est un snapshot, perdre le lien ne perd rien. |

**Aucune cascade ne touche un historique.** Les tables `tracking_events`,
`proofs_of_delivery`, `stock_movements`, `export_jobs`, `audit_logs`,
`invoices` et `provider_settlements` ne sont la cible d'aucun `CASCADE`.

Les refus métier doublent les contraintes SQL partout où le message importe :
supprimer un modèle de communication utilisé, une configuration ayant produit un
export, une facture finalisée ou une communication envoyée renvoie **409** avec
une phrase explicite, avant que la contrainte ne se déclenche.

---

## 7. Index

Chaque colonne servant de filtre, de tri ou de clé étrangère porte un index.
Les colonnes listées au §23 ont été vérifiées une par une :

| Colonne | Indexée partout où elle existe |
|---|---|
| `organization_id` | ✓ |
| `customer_id`, `provider_id`, `order_id`, `order_service_id` | ✓ |
| `tour_id`, `tour_stop_id` | ✓ |
| `status` | ✓ |
| `created_at`, `updated_at` | ✓ (`created_at` systématique) |
| `code`, `barcode`, `invoice_number`, `settlement_number` | ✓, en unique composite avec leur périmètre |
| `occurred_at`, `scheduled_at`, `sent_at` | ✓ |
| `legacy_id` | *n'existe pas* — voir plus bas |

**Aucun index sur un `TEXT`, `LONGTEXT` ou `JSON`.** Ces colonnes sont
cherchables par `LIKE`, mais un index MySQL y imposerait une longueur de préfixe
arbitraire pour un gain nul sur un motif `%terme%`.

**Aucun index ajouté par cette phase** : la comparaison entre les filtres
exposés par l'API et les index existants n'a fait apparaître aucun filtre non
couvert.

### `legacyId` n'existe nulle part

Le §9 et le §23 mentionnent `legacyId`. **Aucune colonne de ce nom n'existe dans
le projet**, et c'est délibéré : les Phases 5, 6 et 7 ont chacune rencontré un
prompt réclamant un `legacyId` que le diagramme ne déclarait pas. L'arbitrage
retenu — le diagramme prime — a conduit à ne pas le créer, avec un test
vérifiant son absence à chaque fois.

---

## 8. Contraintes uniques

Toutes portent un périmètre, jamais une valeur globale :

```text
(organization_id, code)              customers, agencies, services, providers,
                                     communication_templates, package_types,
                                     grouping_types, vehicle_types, roles
(customer_id, article_code)          stock_items
(depot_id, location_code)            stock_locations
(customer_id, name)                  les trois configurations client
(communication_id, document_id)      communication_attachments
(invoice_id, line_number)            invoice_lines
(tour_id, sequence)                  tour_stops, tour_periods
(tour_stop_id, sequence_within_stop) tour_stop_services
(order_id, sequence)                 order_services
order_service_id                     invoice_lines · provider_settlement_lines
api_key_hash                         customer_api_configurations
```

Les deux `UNIQUE` **indépendants** sur `order_service_id` (Phase 6) garantissent
qu'un service n'est facturé qu'une fois et décompté qu'une fois — sans les lier
l'un à l'autre, un service pouvant être les deux.
