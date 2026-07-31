# Analyse Phase 1 — Fondations backend Tricolis V2

Ce document répond au §2 du cahier des charges « Phase 1 — Fondations Backend ».
Il décrit ce que la Phase 1 couvre, l'état réel du code au moment de l'analyse,
ce qui a été complété, et ce qui reste volontairement hors périmètre.

L'analyse détaillée des diagrammes (classes, attributs, cardinalités, agrégats)
se trouve dans [`conception-analysis.md`](conception-analysis.md) ; les choix de
schéma sont dans [`phase-1-database-decisions.md`](phase-1-database-decisions.md).

---

## 0. État des sources de vérité

> **Correction.** Une version antérieure de ce document affirmait que les
> fichiers PlantUML avaient disparu du dépôt. C'était faux : ils n'avaient pas
> été cherchés au bon endroit. Ils se trouvent dans le projet Laravel, et non à
> la racine `PFE/Conception/`.

Les deux diagrammes sources sont :

```text
Project/TricolisWeb/Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Project/TricolisWeb/Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Leurs titres PlantUML correspondent exactement à ceux attendus par le cahier des
charges. Ce sont eux qui font foi.

Il existe par ailleurs `PFE/Conception/Diagramme/Classe/GlobalDiagramme.png`,
intitulé « Classes partagées **vérifiées** ». Ce n'est pas le même artefact : il
ajoute des colonnes de reprise de l'ancienne plateforme (`legacyId`, `legacyUid`,
`srcObject`, `srcId`, `isDeleted`…), un enum `OrganizationType`, et des champs
`Agency.phone1/phone2/agencyTypeId/defaultDepotId` absents du `.txt`. Son titre
ne correspond pas à celui attendu par le cahier des charges.

**À trancher** : ces champs de reprise legacy font-ils partie du périmètre ?
Ils ne sont pas implémentés aujourd'hui, la Phase 1 s'étant alignée sur le
`.txt`. S'ils sont nécessaires à la migration depuis l'ASP.NET, ils devront
faire l'objet d'une itération dédiée.

Vérification faite : le schéma livré en Phase 1 correspond au `.txt` partagé,
attribut par attribut.

## 1. Classes concernées par la Phase 1

| Domaine | Classes |
|---------|---------|
| Identité | `User`, `Role`, `Permission`, `UserRole`, `RolePermission` |
| Organisation | `Organization`, `OrganizationUser`, `Subscription` |
| Structure | `Agency`, `Depot` |
| Adresses | `Address`, `EntityAddress` |
| Contacts | `Contact`, `EntityContact`, `AddressContact` |
| Documents | `Document`, `DocumentLink` |
| Audit | `AuditLog` |
| Clients | `Customer`, `CustomerSite` |

## 2. Tables correspondantes

`users`, `organizations`, `organization_users`, `subscriptions`, `permissions`,
`roles`, `user_roles`, `role_permissions`, `agencies`, `depots`, `addresses`,
`contacts`, `entity_addresses`, `entity_contacts`, `address_contacts`,
`documents`, `document_links`, `audit_logs`, `customers`, `customer_sites`,
plus `personal_access_tokens` (Sanctum).

## 3. Relations et cardinalités

| Relation | Cardinalité |
|----------|-------------|
| `User` → `OrganizationUser` | 1 — 0..* |
| `Organization` → `OrganizationUser` | 1 — 1..* |
| `Organization` → `Subscription` | 1 — 0..1 |
| `OrganizationUser` → `UserRole` | 1 — 0..* |
| `Role` → `UserRole` | 1 — 0..* |
| `Organization` → `Role` | 1 — 0..* |
| `Role` → `RolePermission` | 1 — 0..* |
| `Permission` → `RolePermission` | 1 — 0..* |
| `Organization` → `Agency` | 1 — 0..* |
| `Agency` → `Depot` | 1 — 0..* |
| `Address` → `EntityAddress` | 1 — 0..* |
| `Contact` → `EntityContact` | 1 — 0..* |
| `Address` → `AddressContact` ← `Contact` | 1 — 0..* — 1 |
| `Document` → `DocumentLink` | 1 — 0..* |
| `Organization` → `Customer` | 1 — 0..* |
| `Customer` → `CustomerSite` | 1 — 0..* |
| `Address` → `CustomerSite` | 1 — 0..* |

Les rôles sont portés par `OrganizationUser`, **jamais** par `User` : c'est le
pivot de l'isolation multi-organisation.

## 4. Enums

| Enum | Valeurs | Origine |
|------|---------|---------|
| `UserStatus` | `invited`, `active`, `suspended`, `disabled` | diagramme |
| `OrganizationStatus` | diagramme | diagramme |
| `ContactRole` | `load`, `delivery`, `billing`, `operations`, `emergency`, `other` | diagramme |
| `CustomerStatus` | `active`, `inactive`, `blocked` | diagramme |
| `SubscriptionStatus` | `trialing`, `active`, `suspended`, `cancelled`, `expired` | **hypothèse** — le diagramme ne les énumère pas |

Tous sont des enums PHP natifs stockés en `VARCHAR`, jamais en `ENUM` SQL.

## 5. Ordre des migrations

1. `organizations`
2. `organization_users`
3. `subscriptions`
4. `permissions`
5. `roles`
6. `user_roles`
7. `role_permissions`
8. `agencies`
9. `depots`
10. `addresses`
11. `contacts`
12. `entity_addresses`
13. `entity_contacts`
14. `address_contacts`
15. `documents`
16. `document_links`
17. `audit_logs`
18. `customers`
19. `customer_sites`
20. `personal_access_tokens`
21. `add_deleted_at_to_documents_table`

`users` provient de la migration de base Laravel, étendue aux colonnes du
diagramme.

## 6. État du code au moment de l'analyse

Le projet n'était pas vierge : l'essentiel de la Phase 1 était déjà en place et
vert (108 tests). L'analyse a donc porté sur **ce qui manquait**, pas sur une
reconstruction.

### Déjà terminé et conservé tel quel

- Laravel 13.8 / PHP 8.4, Sanctum 4, Pest 5, Pint — stack conforme, non touchée.
- ULID sur toutes les entités métier (`HasUlid`), validation `ulid` sur l'API.
- Les 11 endpoints d'authentification, avec throttle, refus des comptes
  suspendus ou désactivés, et révocation de sessions.
- Contexte organisationnel (`X-Organization-Id`, `EnsureOrganizationContext`,
  `CurrentOrganizationContext`) et isolation vérifiée par des tests dédiés.
- Rôles, permissions et leurs affectations, sur le rattachement.
- CRUD agences, dépôts, adresses, contacts, clients, sites clients,
  organisations, abonnements, rattachements.
- Documents : upload, téléchargement, suppression logique, purge différée.
- Journal d'audit en lecture seule, avec masquage des valeurs sensibles.
- Liaisons génériques adresses ↔ entités et contacts ↔ entités.
- Format d'API `data` / `meta` / `links`, pagination bornée, tri en liste
  blanche, documentation OpenAPI générée par Scramble.

### Éléments manquants, complétés dans cette itération

| # | Manque constaté | Traitement |
|---|-----------------|------------|
| 1 | Aucune ressource `utilisateurs` autonome (§10) — les comptes n'étaient accessibles qu'au travers de `organization-users` | CRUD `/api/v1/users` : liste isolée par organisation, recherche, création, modification, désactivation |
| 2 | Création d'un membre dupliquée entre contrôleurs | Action partagée `CreateOrganizationMember`, utilisée par `/users` **et** `/organization-users` |
| 3 | « Liaison des documents aux entités » (§3, module 16) non pilotable : une seule liaison, posée à l'upload | `GET/POST /documents/{document}/links`, `DELETE .../{link}` |
| 4 | Référentiel des permissions exposé sans ressource ni détail | `PermissionController` dédié : `GET /permissions`, `GET /permissions/{permission}`, filtre `module`, `PermissionResource` |
| 5 | Connexions échouées journalisées en fichier uniquement, pas auditées (§7) | Action `login_failed` dans `audit_logs`, avec le motif, sans jamais le mot de passe |
| 6 | Résolution des cibles polymorphes dupliquée entre documents et adresses/contacts | `EntityLinkResolver` unifié, seul endroit décidant de l'appartenance organisationnelle d'une cible |

### Incohérences détectées et corrigées

| # | Incohérence | Gravité | Correction |
|---|-------------|---------|------------|
| A | `AuditLog.entity_type` stockait le nom de classe PHP complet pour `Subscription`, `EntityAddress`, `EntityContact`, `AddressContact` et `DocumentLink` — contraire au §11 | élevée | Alias métier ajoutés à la morph map |
| B | `CurrentOrganizationContext` était `scoped` **et** recevait la `Request` par constructeur : l'instance mémorisait la requête et relisait l'en-tête de l'appel précédent | élevée | Requête et garde résolus à chaque appel ; fuite de contexte impossible |
| C | `CustomerSiteController` ne vérifiait pas que le site appartenait au client de l'URL (§14) | élevée | Garde `ensureSiteBelongsToCustomer`, renvoie 404 |
| D | Filtre `isActive` des contacts absent des règles de validation, donc silencieusement inopérant | moyenne | `ListContactRequest` dédiée |
| E | Filtres d'audit (`userId`, `action`, `entityType`, `entityId`) présents sur **toutes** les listes | moyenne | `ListAuditLogRequest` dédiée |
| F | `Address::contacts()` déclarait `withTimestamps()` sur une table pivot sans timestamps | moyenne | Retiré ; les liaisons passent par le modèle `AddressContact` |
| G | `RoleSeeder` orphelin, sa logique recopiée dans le seeder de développement | moyenne | Seeders éclatés, un rôle par seeder, tous idempotents |
| H | `PasswordResetController` validait en ligne au lieu d'utiliser des Form Requests | faible | `ForgotPasswordRequest`, `ResetPasswordRequest` |

### Incohérences signalées, non corrigées

| # | Point | Pourquoi ce n'est pas corrigé |
|---|-------|-------------------------------|
| I | Le §4 impose PostgreSQL ; le projet tourne sur **MySQL 8** | Choix confirmé explicitement par le porteur du projet. La recherche `LIKE` reste insensible à la casse grâce à la collation MySQL par défaut, mais un portage PostgreSQL demanderait de revoir ces requêtes |
| J | Le §12 demande de conserver des « métadonnées » de document | Aucune colonne `metadata` dans le diagramme : l'ajouter serait inventer une propriété métier, ce que le §1 interdit |
| K | Le §10 range « permissions » parmi les CRUD complets | Les codes de permission sont le contrat entre l'API et les Policies : une permission créée à l'exécution ne serait vérifiée par aucun code, et en supprimer une ouvrirait un accès silencieusement. Le référentiel reste en lecture seule, alimenté par seeder ; ce sont les **rôles** qui se pilotent à l'exécution |
| L | Le module Commandes est développé alors qu'il est hors Phase 1 | Livré, testé et isolé ; le retirer détruirait du travail fonctionnel. Signalé pour arbitrage |
| M | Les dépôts sont exposés en `/agencies/{agency}/depots` | Cohérent avec `Agency 1 — 0..* Depot`, mais divergent d'un `/depots` à plat. À figer avec le frontend |

## 7. Décisions techniques de la Phase 1

- **Isolation** : aucun Global Scope. L'organisation active est explicite, portée
  par un en-tête validé, et chaque Policy la revérifie. Un contexte implicite
  serait intestable et masquerait les fuites au lieu de les empêcher.
- **Utilisateurs** : un compte n'existe pour l'API que s'il possède un
  rattachement dans l'organisation active. La liste est donc isolée par
  construction, sans filtre à ne pas oublier.
- **Suppression d'un compte** : jamais physique. `DELETE /users/{user}` passe le
  statut à `disabled` et révoque les jetons — un compte est référencé par
  l'audit et les documents.
- **Permissions** : référentiel versionné avec le code, en lecture seule.
- **Documents** : suppression logique + purge différée (`documents:purge`), type
  MIME déduit du contenu et non de l'en-tête client, chemin de stockage jamais
  exposé.
- **Polymorphisme** : morph map à valeurs métier, incluant les tables de liaison
  parce qu'elles sont auditées.
- **Audit** : écrit explicitement par les Actions et contrôleurs, jamais par un
  observer global qui produirait du bruit sans intention métier.

## 8. Périmètre non développé

Conformément au §3 : catalogues clients, arrêts de commande, colis, stock,
fournisseurs, véhicules, chauffeurs, tournées, planning, tarification,
facturation, réclamations, communications, imports métier, application chauffeur.

Le module Commandes (`Order`, `OrderLine`, `Service`, `OrderService`) est
présent, contrairement à ce périmètre — voir le point L ci-dessus.
