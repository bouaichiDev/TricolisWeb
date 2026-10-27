# Smoke tests — portail client

À lancer **après chaque déploiement** (préproduction puis production) et après
tout rollback. Durée : moins d'une minute. Aucun test n'écrit de donnée métier.

## Script automatique

```bash
API_URL=https://<api> \
PORTAL_URL=https://<portail> \
SMOKE_EMAIL=<compte lecteur dédié> SMOKE_PASSWORD=<…> \
bash scripts/customer-portal-smoke.sh
```

`PORTAL_URL` et le compte sont facultatifs ; sans compte, seules les
vérifications anonymes tournent. Le compte doit être un **lecteur**
(`CUSTOMER_VIEWER`) d'un client de test, créé pour cet usage : la connexion
écrit une ligne d'audit `login` et un jeton aussitôt révoqué.
Le mot de passe se passe par variable d'environnement, jamais en argument
(il apparaîtrait dans l'historique et `ps`).

Code retour : `0` si tout passe, `1` sinon — utilisable dans un pipeline.

| # | Vérification | Attendu |
|---|---|---|
| 1 | `GET /up` (base, stockage, cache) | 200 |
| 2 | contexte, commande, API client sans authentification | 401 |
| 3 | suivi public avec un jeton inconnu | aucune trace, SQL ni classe interne |
| 4 | en-têtes `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, HSTS en HTTPS | présents |
| 5 | portail : `/` et `/orders` servis en HTML (fallback SPA) | 200 |
| 6 | `index.html` du portail | sans `localhost` |
| 7 | pré-vol CORS depuis l'origine du portail | `Access-Control-Allow-Origin` = portail |
| 8 | connexion du compte lecteur | jeton |
| 9 | contexte, liste des commandes | 200 |
| 10 | commande inexistante | 404 générique |
| 11 | `?customerId=` imposé | 422 |
| 12 | déconnexion puis réutilisation du jeton | 204 puis 401 |

### Exécution de référence — 2026-10-10, environnement de développement

`API_URL=http://localhost:8000 PORTAL_URL=http://localhost:5180`, compte
`viewer@ikea.test` (démo) : **SMOKE OK**, 19 vérifications sur 19.
HSTS non vérifié (HTTP local). **Le script n'a jamais été lancé contre la
préproduction ni la production** : aucun accès depuis cet environnement.

## Vérifications manuelles (navigateur, 5 min)

Avec un compte administrateur d'un client de test :

1. Connexion → tableau de bord affiché, nom du client correct en en-tête.
2. Commandes → liste, ouverture d'une commande, onglet suivi.
3. Calendrier → les commandes du mois apparaissent ; un clic ouvre la vue rapide.
4. Catalogue → liste des articles (si le module est ouvert au client).
5. Stock → liste (si le module est ouvert au client).
6. Intégrations → flux du client, sans aucun secret affiché.
7. Lien de suivi public d'une commande de test → page sans compte, sans
   donnée interne (pas de chauffeur nominatif, pas de prix).
8. Déconnexion → retour à l'écran de connexion ; « Précédent » ne réaffiche rien.
9. Console du navigateur : aucune erreur rouge, aucun appel vers `localhost`.

Ne **pas** créer de commande réelle en production pour un smoke test.

## Si un test échoue

| Échec | Piste |
|---|---|
| `/up` ≠ 200 | `storage/logs/laravel.log` : « Health check failed: database / storage / cache » |
| 401 attendu, 200 reçu | middleware d'authentification contourné : **rollback immédiat** |
| détail interne dans une erreur | `APP_DEBUG=true` en production ou `ExternalApiErrors` non chargé |
| CORS | `CORS_ALLOWED_ORIGINS` ne contient pas l'origine exacte du portail (schéma, domaine, pas de `/` final) ; `php artisan config:cache` oublié |
| `/orders` 404 sur le portail | fallback SPA absent du vhost (`try_files $uri /index.html`) |
| `localhost` dans `index.html` | build sans `VITE_API_URL` (le build de production l'exige désormais) |
| connexion refusée | compte inactif, client inactif, organisation suspendue, ou compte rattaché à plusieurs clients |
