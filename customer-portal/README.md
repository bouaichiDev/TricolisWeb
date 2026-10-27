# Tricolis — Portail client

Application React distincte du back-office (`../frontend`). Elle parle au même
backend Laravel, sous `/api/v1/customer`.

## Démarrage

```bash
npm install            # .npmrc active legacy-peer-deps (bug npm 11)
cp .env.example .env.local
npm run dev            # http://localhost:5180
```

`VITE_API_URL` désigne le backend (`http://localhost:8000` par défaut). Côté
Laravel, `CUSTOMER_PORTAL_URL` doit pointer vers ce portail : les liens
d'invitation et de réinitialisation y mènent.

### Démarrage complet, depuis zéro

Prérequis : PHP 8.4 (extensions `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `ftp`, `bcmath`, `dom`, `xmlreader`),
Composer, Node 22, MySQL 8.

```bash
# Backend — depuis TricolisWeb/
cp .env.example .env                 # DB_*, CUSTOMER_PORTAL_URL=http://localhost:5180
composer install
php artisan key:generate
php artisan migrate --seed           # en APP_ENV=local : pose aussi IKEA, QOQA, SCHMIDT, GETAZ
php artisan storage:link
php artisan serve                    # http://localhost:8000
php artisan queue:work               # autre terminal : imports, envois, mails
php artisan schedule:work            # autre terminal : relèves horaires

# Portail — depuis TricolisWeb/customer-portal/
npm ci
npm run dev                          # http://localhost:5180
```

Tests : `php artisan test` (suite complète > 30 min, base `tricolisweb_test`,
voir `.env.testing.example`) ; `npm run test` ; parcours E2E ci-dessous.

## Comptes de démonstration

Semés par `CustomerPortalDemoSeeder` — automatiquement en `local`, à la
demande en `testing` (voir les parcours E2E) —, mot de passe de développement (`password` par défaut) :

| Compte | Client | Rôle |
|---|---|---|
| admin@ikea.test | IKEA | CUSTOMER_ADMIN (propriétaire) |
| orders@ikea.test | IKEA | CUSTOMER_ORDER_MANAGER |
| viewer@ikea.test | IKEA | CUSTOMER_VIEWER |
| admin@qoqa.test | QOQA | CUSTOMER_ADMIN (propriétaire) |

## Commandes (Phase 2)

- Liste filtrable (recherche, statut, service, dates), triée et paginée côté
  serveur ; les filtres vivent dans l'URL.
- Création et modification par un parcours en cinq étapes : informations,
  lignes (articles du catalogue du client), colis, services (adresse du carnet
  ou destinataire ponctuel, créneau, contacts), récapitulatif.
- Détail en onglets : Général, Services, Colis, Articles, Documents, Historique.
- Les boutons Modifier / Dupliquer / Annuler suivent `actions`, calculé par le
  backend (workflow des statuts + commande non planifiée) : aucune règle de
  statut n'est écrite dans le portail.
- Le tableau de bord réutilise le moteur de widgets du back-office ; le
  transporteur le compose sur la fiche des rôles `CUSTOMER_*`.

`DemoCustomerPortalOrderSeeder` pose pour IKEA une adresse, un catalogue
(Billy, Kallax, Malm) et une première commande.

## Modules

| Module | Écrans | Permission d'accès |
|---|---|---|
| Commandes | liste, création, détail, modification, service | `customer.orders.*` |
| Calendrier | vue mois/semaine, vue rapide | `customer.calendar.view` |
| Suivi | suivi authentifié (carte), lien public `/t/:token` | `customer.tracking.view` |
| Réclamations, documents, preuves | depuis la commande et le service | `customer.claims.*`, `customer.orders.documents.*` |
| Stock | articles, soldes, mouvements, réservations, demandes de correction | `customer.stock.*` + module ouvert |
| Intégrations | flux, import de fichier, envois | `customer.integrations.*` |
| Catalogue (Phase 8) | articles, fiche, ajout | `customer.catalog.view` + `customer.articles.view` (+ `.create`) + module ouvert |
| Utilisateurs | membres, invitations, rôles | `customer.users.*` |

Hors connexion et tableau de bord, chaque page se charge à sa première visite
(`src/app/router/lazyPages.ts`).

## Sécurité

- Le client courant est porté par le **jeton** (`customer:<id>`), jamais par
  la requête : le portail n'envoie aucun identifiant de client. Une commande,
  un document ou un membre d'un autre client répond 404.
- Les jetons du portail n'ouvrent pas l'interne, et inversement.
- Menus et boutons suivent les permissions `customer.*` du contexte, mais ce
  n'est qu'un confort : le backend revérifie tout.

## Scripts

| Commande | Rôle |
|---|---|
| `npm run test` | Tests Vitest (MSW) |
| `npm run typecheck` | Vérification TypeScript |
| `npm run lint` | oxlint |
| `npm run build` | Build de production — exige `VITE_API_URL` |
| `npm run test:e2e` | Parcours Playwright |

### Parcours E2E

Ils tournent contre la **base de test** (API sur 8002, portail sur 5182).
Préalable, depuis `TricolisWeb/` :

```bash
php artisan migrate:fresh --seed --env=testing --force
php artisan db:seed --class=CustomerPortalDemoSeeder --env=testing --force
php artisan db:seed --class=DemoCustomerPortalStockSeeder --env=testing --force
php artisan db:seed --class=DemoCustomerPortalIntegrationsSeeder --env=testing --force
php artisan db:seed --class=DemoCustomerPortalCatalogSeeder --env=testing --force
```

Les quatre dernières commandes sont nécessaires (la dernière pose les catalogues
QOQA et GETAZ du parcours d'isolation du catalogue) : en test, le semis par défaut
ne pose ni IKEA ni QOQA, pour ne pas fausser les comptages de la suite back-end.
Après une suite Pest, re-semer avant Playwright.

Ne pas les lancer pendant un `php artisan test` : les deux partagent la base
de test.

## Build de production

```bash
VITE_API_URL=https://<api> npm run build     # sortie : dist/
```

Sans `VITE_API_URL`, le build de production **échoue** volontairement : un
portail qui appellerait `localhost` depuis le navigateur du client ne
fonctionnerait pour personne. `dist/` se sert en statique, avec un repli de
toute route inconnue vers `index.html` (routes React) et un cache long sur
`assets/` (noms à empreinte). Côté Laravel, l'origine du portail doit figurer
dans `CORS_ALLOWED_ORIGINS`, et `CUSTOMER_PORTAL_URL` doit pointer vers lui.

## Documentation

- Fonctionnel, sécurité, matrices, rapports : [`../docs/customer-portal/`](../docs/customer-portal/README.md)
- Exploitation et déploiement : [`../docs/deployment/`](../docs/deployment/customer-portal-runbook.md)
- API : `/docs/api` en local (Scramble) ; `php artisan scramble:export` pour un fichier OpenAPI
