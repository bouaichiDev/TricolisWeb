# Portail client — catalogue modifiable, demandes de correction, tableaux alignés

Date : 2026-10-11. Statut : design validé section par section, spec à relire.

## Besoin (dit par l'utilisateur)

1. Le client doit pouvoir **modifier** ses articles du catalogue.
2. Le stock : **garder la règle** (le client ne touche jamais une quantité),
   mais l'utilisateur ne trouvait pas où créer une demande de correction.
3. Les tableaux du portail doivent avoir **la même structure** que ceux du
   back-office.
4. **Sécurité à 100 %** : un client ne doit jamais pouvoir modifier l'article
   d'un autre client (ex. IKEA ne modifie jamais un article de GETAZ).

Décisions prises pendant le cadrage :

| Sujet | Décision |
|---|---|
| Catalogue | modifier + désactiver/réactiver ; **pas de suppression** (historique des commandes et du stock) |
| Stock | règle Phase 5 conservée ; ajouter un point d'entrée « Nouvelle demande » |
| Tableaux | même **composant** que le back-office (pas les mêmes colonnes) |
| Approche tableaux | A : copier le composant du back-office dans le portail |

Hors périmètre : modification de quantité par le client, suppression
d'article, composant partagé entre les deux applications, colonnes internes.

## Lot 1 — Modifier et désactiver un article

**Permission** : `customer.articles.update` (nouvelle, déclarée dans
`CustomerPortalPermissions`), donnée par défaut à `CUSTOMER_ADMIN` et
`CUSTOMER_STOCK_MANAGER` ; bornée par `catalog_enabled` (`CustomerFeatureGate`,
préfixe `customer.articles.` déjà couvert). Les rôles par défaut existants la
reçoivent par la synchronisation des rôles par défaut (`CustomerPortalRoles`).

**Routes** (fichier `routes/customer-catalog.php`, groupe déjà protégé par
`auth:sanctum` + `customer.portal` + limiteur `customer-portal`) :

| Méthode | URI | Permissions |
|---|---|---|
| PATCH | `catalog/items/{item}` | `customer.catalog.view` + `customer.articles.update` |
| POST | `catalog/items/{item}/deactivate` | idem |
| POST | `catalog/items/{item}/activate` | idem |

**Règles de modification** — celles du back-office (`UpdateCatalogItemRequest`) :
`articleCode` (unique dans **son** catalogue), `barcode`, `name`,
`description`, `weight`, `volume`, `length`, `width`, `height`,
`assemblyTimeMinutes`. Refusés en 422 (`prohibited`) : `status`, `catalogId`,
`customerId`, `organizationId`, `id`. Changer le code ne réécrit ni les lignes
de commande ni le stock (ils portent leur propre `article_code`).

**Désactivation** : `status = inactive`. L'article reste listé (filtre par
statut) mais n'est plus proposé par la recherche d'articles du formulaire de
commande (`customer/catalog-items`) ni à la création d'un article de stock —
à vérifier dans ces deux requêtes, et à filtrer si ce n'est pas déjà fait. Les
commandes et le stock existants ne changent pas. Activer/désactiver un article
déjà dans cet état répond 200 sans écriture ni audit.

**Audit** : `updated` (`WriteAuditLog`, anciennes/nouvelles valeurs) et
`customer_portal.catalog_item_updated|deactivated|activated`
(`CustomerPortalAudit`).

**Écran** : fiche article → « Modifier » (dialogue de l'ajout, pré-rempli) et
« Désactiver »/« Réactiver » (confirmation), selon la permission ; mêmes actions
en icônes dans la liste (lot 3).

## Sécurité d'isolation du catalogue (exigence « 100 % »)

Principe : **l'identifiant dans l'URL ne désigne jamais seul un article** ; il
n'est cherché que parmi les articles du client de la session.

1. **Résolution unique par la portée du client** : toute écriture passe par
   `CustomerCatalogScope::item($id)`, qui ne cherche que dans
   `catalogs.customer_id = client de la session` **et**
   `customers.organization_id = organisation de la session`. Pas de liaison de
   modèle implicite (`Route::bind`), pas de `CustomerCatalogItem::find()` dans
   le portail.
2. **Relecture sous verrou, par la même portée** : dans la transaction,
   l'article est relu avec `lockForUpdate()` **via la portée du client** (pas par
   son seul identifiant) ; l'unicité du code se vérifie dans le catalogue de
   cet article, sous verrou du catalogue.
3. **Aucun champ de rattachement modifiable** : `catalogId`, `customerId`,
   `organizationId` refusés en 422 — un article ne peut pas être « déplacé »
   vers le catalogue d'un autre client.
4. **Réponse identique** : un article d'un autre client et un identifiant
   inexistant répondent le même 404 générique (`ExternalApiErrors`) — le portail
   ne confirme pas qu'un article existe chez un autre client.
5. **Barrières empilées** : jeton du portail (`customer:{id}`) → contexte client
   actif → organisation non suspendue → module catalogue ouvert → permission →
   portée. Chacune refuse seule.
6. **Matrice de routes** : les 3 nouvelles routes héritent de l'isolation
   déclarée pour le préfixe `api/v1/customer/catalog` dans
   `PortalRouteSecurity` (`CustomerCatalogScope`) ;
   `customer-portal:route-matrix --check` doit passer et la matrice générée
   est régénérée.

Tests de sécurité obligatoires (Pest) :

- IKEA tente `PATCH`, `activate`, `deactivate` sur un article de **GETAZ**, de
  **QOQA**, et d'un client d'une **autre organisation** → 404, et **instantané
  en base inchangé** (nom, code, statut, `updated_at` de l'article visé) ;
- IKEA tente de déplacer son propre article : `catalogId` d'un catalogue GETAZ
  → 422, article inchangé ;
- IKEA modifie son article avec le code d'un article GETAZ → **accepté**
  (l'unicité est par catalogue, le code GETAZ n'est ni lu ni révélé) ;
- même réponse (statut et corps) pour article étranger et identifiant inexistant ;
- lecteur IKEA → 403 ; module catalogue fermé → 403 ; organisation suspendue
  → refus ;
- `PortalCrossCustomerIsolationTest` (balaye **toutes** les routes à
  identifiant) couvre automatiquement les nouvelles routes : son instantané est
  étendu à l'article QOQA visé ;
- aucun audit écrit pour une tentative refusée.

## Lot 2 — « Nouvelle demande » de correction

API inchangée : `POST customer/stock/adjustment-requests` accepte déjà
`stockItemId` et refuse en 404 l'article d'un autre client (testé :
`PortalStockAdjustmentRequestTest`).

Écran : bouton « Nouvelle demande » sur la page « Demandes de correction »
(`customer.stock.adjustment_requests.create`). Le dialogue existant
(`AdjustmentRequestDialog`) gagne une étape « article » : recherche serveur
`stock/items?search=` (donc seulement les articles du client), puis
emplacement facultatif, quantité constatée, motif, commentaire. Sans article de
stock : message explicatif au lieu du bouton.

Données de dev : `DemoCustomerPortalStockSeeder` lancé sur la base de dev après
vérification qu'il est rejouable sans doublon (corrigé sinon).

## Lot 3 — Tableaux alignés sur le back-office

Copie adaptée dans `customer-portal/src/shared/components/data/` de
`DataTable`, `DataTablePagination`, `DataTableParts`, `RowActions` du
back-office : textes en français en dur (pas d'i18n dans le portail), erreurs au
format du portail. Comportement commun : tri serveur, ligne de filtres sous les
en-têtes, choix 10/25/50/100 lignes, colonne d'actions en icônes à droite
(permission par action).

Règle : un filtre sous une colonne n'existe que si le serveur l'accepte déjà
pour cette liste, et sa valeur part au serveur. La recherche globale reste
au-dessus du tableau.

| Écran | Filtres sous colonnes | Actions en icônes |
|---|---|---|
| Commandes | référence, réf. client, statut, prestation, dates | voir, suivre, modifier, dupliquer, annuler (capacités serveur) |
| Réclamations | statut, type, état, dates | voir |
| Stock — articles | code, code-barres, statut | voir, modifier, demander une correction |
| Stock — mouvements / réservations | type, dates | voir la commande liée |
| Demandes de correction | statut, dates | voir |
| Catalogue | statut, catalogue | voir, modifier, désactiver/réactiver |
| Utilisateurs | statut | voir, modifier, désactiver |
| Intégrations — envois / imports | statut, flux | relancer / détail |
| Flux, lignes, colis | — | inchangées, même composant |

Inchangés : colonnes (jamais de donnée interne), permissions (le serveur
revérifie), filtres dans l'URL là où ils y sont déjà.

## Tests et vérification

- Pest : tests du lot 1 et de la section sécurité ; suite complète détachée à
  la fin.
- Vitest : test du composant porté du back-office ; tests d'écrans mis à jour
  (emplacement des filtres, icônes) sans retirer d'assertion ; dialogue de
  modification, « Nouvelle demande ».
- Playwright : catalogue (modifier, désactiver), demande de correction depuis
  la page des demandes, isolation (IKEA → article GETAZ par URL et API) ; les
  14 parcours existants adaptés aux icônes.
- `pint --test`, `route-matrix --check`, lint, typecheck, builds.
- Fichiers de 200 lignes au plus ; colonnes longues dans `xxxColumns.tsx`.
- Documents Phase 8 mis à jour : inventaire, matrice des permissions, matrice
  d'accès, matrice E2E, rapport final.

## Ordre de livraison

Lot 1 (avec la section sécurité) → lot 2 → lot 3, chacun testé et vérifié
avant le suivant.
