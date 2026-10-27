# Matrice d'accès — portail client

Qui peut faire quoi dans le portail, et sur quel périmètre. Les colonnes de rôles
reflètent `CustomerPortalRoles::defaults()` (rôles fournis à chaque transporteur) ;
un administrateur client peut composer d'autres rôles, **jamais au-delà des
permissions qu'il détient lui-même** (`CustomerPermissions::delegableFor`).

Le détail route par route (méthode, URI, middleware, limiteur) est dans
[`security-route-matrix.md`](security-route-matrix.md), généré depuis le routeur.

## Rôles par défaut

| Code | Libellé | Portée |
|---|---|---|
| `CUSTOMER_ADMIN` | Administrateur client | toutes les permissions `customer.*` |
| `CUSTOMER_ORDER_MANAGER` | Gestionnaire de commandes | commandes, suivi, réclamations, import de fichiers |
| `CUSTOMER_STOCK_MANAGER` | Gestionnaire de stock | articles, stock, demandes de correction |
| `CUSTOMER_VIEWER` | Lecteur | lecture des commandes, du suivi, du stock |

« Administrateur du Customer » (`CustomerUser.isAdmin` du diagramme) est porté dans le
code par le rôle `CUSTOMER_ADMIN` ; le **propriétaire** du compte (`is_owner`) ne peut
être ni désactivé ni rétrogradé depuis le portail (`ManagedMemberGuard`).

## Matrice fonctionnelle

Isolation : *Customer* = client de la session **et** son organisation (jamais un
identifiant fourni par la requête).

| Module | Action | Admin | Commandes | Stock | Lecteur | Permission | Isolation |
|---|---|:-:|:-:|:-:|:-:|---|---|
| Tableau de bord | voir | oui | oui | oui | oui | aucune ; chaque widget exige la sienne | Customer |
| Commandes | voir | oui | oui | — | oui | `customer.orders.view` | Customer |
| Commandes | créer | oui | oui | — | — | `customer.orders.create` | Customer |
| Commandes | modifier | oui | oui | — | — | `customer.orders.update` + statut + non engagée | Customer |
| Commandes | annuler | oui | oui | — | — | `customer.orders.cancel` + non planifiée | Customer |
| Commandes | dupliquer | oui | oui | — | — | `customer.orders.duplicate` | Customer |
| Commandes | historique | oui | oui | — | oui | `customer.orders.history.view` | Customer |
| Documents | voir / télécharger | oui | oui | — | oui | `customer.orders.documents.view` | Customer (rattachement) |
| Documents | joindre | oui | oui | — | — | `customer.orders.documents.upload` | Customer |
| Preuves de livraison | voir | oui | oui | — | oui | `customer.orders.proofs_of_delivery.view` | Customer |
| Suivi | voir | oui | oui | — | oui | `customer.tracking.view` (+ `orders.track`) | Customer |
| Lien public | voir / gérer | oui | — | — | — | `customer.tracking.public_link.view` / `.manage` | Customer |
| Calendrier | voir | oui | oui | — | oui | `customer.calendar.view` | Customer |
| Réclamations | voir / créer / modifier | oui | oui | — | voir | `customer.claims.view` / `.create` / `.update` | Customer |
| Stock | voir | oui | — | oui | oui | `customer.stock.view` + module ouvert | Customer |
| Stock | mouvements / réservations | oui | — | oui | oui | `customer.stock.movements.view` / `.reservations.view` | Customer |
| Stock | articles (métadonnées) | oui | — | oui | — | `customer.stock.items.create` / `.update` | Customer |
| Stock | modifier une quantité | non | non | non | non | aucune route | — |
| Stock | demander une correction | oui | — | oui | — | `customer.stock.adjustment_requests.create` | Customer, validation interne |
| Catalogue | voir catalogues et articles | oui | oui | oui | oui | `customer.catalog.view` + `customer.articles.view` + module ouvert | Customer (`CustomerCatalogScope`) |
| Catalogue | ajouter un article | oui | — | oui | — | `customer.articles.create` | Customer, catalogue actif du client |
| Catalogue | modifier / désactiver / réactiver un article | oui | — | oui | — | `customer.articles.update` (2026-10-11) | Customer — article résolu dans la portée du client, jamais par son seul identifiant |
| Catalogue | supprimer un article | non | non | non | non | aucune route (historique des commandes et du stock) | — |
| Utilisateurs | voir | oui | — | — | — | `customer.users.view` | Customer |
| Utilisateurs | inviter / modifier / désactiver | oui | — | — | — | `customer.users.invite` / `.update` / `.disable` | Customer |
| Utilisateurs | attribuer des rôles | oui | — | — | — | `customer.users.assign_role` ⊆ permissions de l'acteur | Customer |
| Intégrations | voir les flux | oui | oui | — | — | `customer.integrations.view` | Customer |
| Intégrations | voir la correspondance | oui | — | — | — | `customer.integrations.mapping.view` | Customer |
| Intégrations | importer un fichier | oui | oui | — | — | `customer.integrations.import` | Customer |
| Intégrations | voir les envois | oui | — | — | — | `customer.integrations.logs.view` | Customer |
| Intégrations | relancer un envoi | oui | — | — | — | `customer.integrations.export_jobs.retry` | Customer |
| Intégrations | configurer, secrets | non | non | non | non | back-office du transporteur | — |
| Clés API | créer / renouveler | non | non | non | non | back-office du transporteur | — |

## Correspondance avec les codes de la spécification Phase 7

Les codes existants ont été **réutilisés** ; aucun code parallèle n'a été créé.

| Code de la spécification | Code réel | Note |
|---|---|---|
| `customer.dashboard.view` | — | le tableau de bord est ouvert ; chaque widget exige sa permission |
| `customer.orders.*` | identiques | + `documents.*`, `history.view`, `proofs_of_delivery.view` |
| `customer.users.create` | `customer.users.invite` | un membre se crée par invitation ; `users.create` est réservé |
| `customer.catalog.manage` | `customer.articles.create` + `customer.articles.update` | ajout (Phase 8), modification et (dés)activation (2026-10-11) ; la suppression reste au transporteur |
| `customer.stock.articles.manage` | `customer.stock.items.create` / `.update` | métadonnées seulement |
| `customer.stock.adjustment.request` | `customer.stock.adjustment_requests.create` | + `.view` |
| `customer.integrations.manage` | — (non ouvert) | configuration réservée au back-office ; `create/update/disable/test` réservés |
| `customer.api_credentials.manage` | — (non ouvert) | clés gérées par le transporteur |
| `customer.exports.view` | `customer.integrations.logs.view` | les envois sont la trace d'exécution |
| `customer.exports.retry` | `customer.integrations.export_jobs.retry` | |

Permissions déclarées mais sans route portail à ce jour (réservées) :
`customer.users.create`, `customer.roles.assign`, `customer.integrations.create`,
`customer.integrations.update`, `customer.integrations.disable`,
`customer.integrations.mapping.manage`, `customer.integrations.status_mapping.manage`,
`customer.integrations.test`.

## Ce qu'aucun utilisateur du portail ne peut faire

- choisir son client ou son organisation (`customerId`, `organizationId` refusés en 422) ;
- recevoir une permission interne : seules les permissions `customer.*` comptent,
  et seuls les rôles de portée `customer` de **son** organisation s'attribuent ;
- créer une permission, modifier un rôle système, une organisation ;
- lire ou écrire l'audit ;
- atteindre une route du back-office : un jeton du portail y est refusé (403),
  et réciproquement.

Le catalogue, annoncé en Phase 1 mais livré par aucune des Phases 2 à 7, a été
ouvert en Phase 8 avec les permissions existantes (`customer.catalog.view`,
`customer.articles.view`, `customer.articles.create`).
