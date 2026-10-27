# Audit de performance — portail client (Phase 8, §26)

Mesures faites le 2026-10-10 sur la base de **développement** (MySQL 8.0 local,
PHP 8.4, `APP_ENV=local`, sans cache de configuration), requêtes traitées
dans le noyau HTTP sans serveur web. Ce ne sont pas des temps de production ;
elles servent à repérer les N+1 et les requêtes excessives.

## Méthode

Pour chaque endpoint : une requête de chauffe, puis une requête mesurée avec
`DB::enableQueryLog()`. `Model::preventLazyLoading()` est activé avec un
gestionnaire qui **collecte** toute relation chargée paresseusement sur une
collection (signature d'un N+1). Deux comptes : `admin@ikea.test`,
`admin@qoqa.test`.

## Résultats (admin IKEA)

| Endpoint | Requêtes SQL | Temps |
|---|---:|---:|
| `GET /customer/context` | 14 | 15 ms |
| `GET /customer/dashboard` | 19 | 22 ms |
| `GET /customer/orders?perPage=20` | 18 | 23 ms |
| `GET /customer/orders?perPage=100` | 18 | 36 ms |
| `GET /customer/orders/{id}` | 25 | 36 ms |
| `GET /customer/orders/{id}/tracking` | 22 | 30 ms |
| `GET /customer/orders/{id}/history` | 19 | 21 ms |
| `GET /customer/calendar/orders` (60 jours) | 24 | 44 ms |
| `GET /customer/claims?perPage=20` | 15 | 19 ms |
| `GET /customer/catalog/items?perPage=20` | 17 | 20 ms |
| `GET /customer/catalog/items/{id}` | 18 | 19 ms |
| `GET /customer/stock/items?perPage=20` | 17 | 20 ms |
| `GET /customer/stock/movements?perPage=20` | 15 | 19 ms |
| `GET /customer/users` | 18 | 21 ms |
| `GET /customer/integrations` | 19 | 32 ms |
| `GET /customer/integrations/export-jobs` | 15 | 30 ms |
| `GET /customer/integrations/import-configurations` | 16 | 21 ms |

QOQA : mêmes ordres de grandeur (14 à 24 requêtes, 16 à 53 ms).
**Chargements paresseux détectés : aucun** (IKEA et QOQA).

Les ~14 premières requêtes sont fixes : jeton Sanctum, utilisateur, rattachement
au client, client, organisation, rôles et permissions, limiteur de débit (cache
`database`). Elles sont payées une fois par requête, quel que soit l'endpoint.

## Indépendance au volume

Les volumes de démonstration sont faibles (2 commandes IKEA). Pour vérifier que
le nombre de requêtes ne croît pas avec le nombre de lignes, un test a été
ajouté : `tests/Feature/Api/V1/CustomerPortal/Performance/PortalQueryBudgetTest.php`.
Il compte les requêtes d'une page, ajoute 12 commandes (ou 15 articles) par
l'API, recompte, et tolère au plus 3 requêtes de plus (une relation vide avant
l'ajout peut en coûter une après). Un N+1 en ajouterait au moins 12.

## Pagination et plafonds

| Liste | Plafond |
|---|---|
| Listes paginées (`perPage`) | 100 maximum (commandes, réclamations, stock, catalogue, utilisateurs) |
| Calendrier | 100 jours (`CUSTOMER_PORTAL_CALENDAR_MAX_DAYS`), 500 événements (`…_MAX_EVENTS`) |
| Import de fichier | 5 Mo, 5000 lignes ; au-delà de 200 commandes, traitement en file |
| Relève REST | 50 pages, réponse ≤ 5 Mo, délai 30 s |

## Frontend

| Mesure | Avant | Après |
|---|---:|---:|
| Chunk principal (`index-*.js`) | 802,6 ko (234,3 ko gzip) | **472,3 ko (142,6 ko gzip)** |
| Alerte Vite « chunk > 500 ko » | oui | non |

Correction : chaque page de module se charge à sa première visite
(`app/router/lazyPages.ts`, `React.lazy`). Le premier écran (connexion, tableau
de bord) ne télécharge plus les commandes, le stock, les intégrations… Le
calendrier (FullCalendar, 275 ko) et la carte (Leaflet, 155 ko) étaient déjà
séparés. Un `Suspense` dans `PortalLayout` garde la barre latérale pendant le
chargement d'une page.

Effet de bord traité : la vue rapide du calendrier (un `Sheet`) restait ouverte
pendant le chargement de la page visée ; elle se ferme désormais au clic sur
ses liens (`OrderEventSheet`).

## Recommandations (non implémentées)

- Mesurer en préproduction avec `config:cache`, `route:cache` et OPcache
  activés, et un volume réaliste (≥ 10 000 commandes par client).
- (Fait le 2026-10-10) index `orders(customer_id, external_reference)` : la
  recherche de doublon de chaque commande importée n'est plus un parcours des
  commandes du client.
