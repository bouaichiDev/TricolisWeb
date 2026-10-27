# API du portail client — guide (Phase 8, §35)

La référence endpoint par endpoint (méthode, URL, paramètres, corps, règles de
validation, réponses, erreurs) est la documentation **OpenAPI générée par
Scramble** depuis le code — l'outil déjà utilisé par le projet ; aucun outil
concurrent n'a été ajouté.

```bash
# en local : http://localhost:8000/docs/api (fermé hors environnement local)
php artisan scramble:export --path=storage/app/openapi.json   # fichier importable dans Postman, Bruno, Insomnia…
```

Le fichier exporté couvre les **89 opérations** externes (portail, lien public,
API par clé). Depuis la Phase 8, il ne présente plus `customerId` /
`organizationId` comme filtres (ils sont refusés) et documente `X-Api-Key`
(`ExternalApiDocumentationTest`).

Ce guide donne les conventions communes et un exemple par famille.
Toutes les valeurs ci-dessous sont **fictives**.

## Trois accès

| Accès | Préfixe | Authentification | Pour qui |
|---|---|---|---|
| Portail | `/api/v1/customer` | `Authorization: Bearer <jeton du portail>` (Sanctum, 12 h) | l'application React du portail |
| Lien public | `/api/v1/public/tracking/{token}` | aucune : le jeton aléatoire du lien | le destinataire final |
| API par clé | `/api/v1/client` | `X-Api-Key: <clé>` | le système informatique du client |

En-têtes communs : `Accept: application/json` ; `Content-Type: application/json`
pour un corps JSON. **Jamais** d'en-tête ni de champ désignant le client ou
l'organisation : ils viennent du jeton ou de la clé.

## Variables d'environnement (collection)

| Variable | local | préproduction | production |
|---|---|---|---|
| `{{base_url}}` | `http://localhost:8000` | `https://<api-preprod>` | `https://<api>` |
| `{{customer_token}}` | obtenu par `auth/login` | idem | idem |
| `{{customer_api_key}}` | clé de test du back-office | clé de test | **jamais dans une collection partagée** |

## Réponses

| Cas | Statut | Corps |
|---|---|---|
| Lecture | 200 | `{"data": {…}}` |
| Liste | 200 | `{"data": […], "meta": {"currentPage", "perPage", "total", "lastPage"}, "links": {…}}` |
| Création | 201 | `{"data": {…}}` |
| Suppression / déconnexion | 204 | vide |
| Non authentifié | 401 | `{"message": "…"}` |
| Permission ou module manquant | 403 | `{"message": "Vous n’avez pas la permission requise pour cette action."}` |
| Introuvable **ou appartenant à un autre client** | 404 | `{"message": "Ressource introuvable."}` |
| Conflit métier | 409 | `{"message": "…", "code": "ORDER_VERSION_CONFLICT", "errors": {}}` (aussi `ORDER_NOT_CANCELLABLE`…) |
| Validation | 422 | `{"message": "…", "errors": {"champ": ["…"]}}` |
| Débit dépassé | 429 | `{"message": "…"}` + `Retry-After` |
| Erreur serveur | 500 | `{"message": "Une erreur interne est survenue. Réessayez plus tard ; …"}` — jamais de détail technique |

## Exemples

### Connexion

```http
POST {{base_url}}/api/v1/customer/auth/login
Content-Type: application/json

{"email": "lecteur@client.example", "password": "<mot de passe>", "deviceName": "integration-doc"}
```

`200` → `data.token` (à envoyer ensuite en `Bearer`) et `data.context`
(client, utilisateur, permissions). Un compte rattaché à plusieurs clients
reçoit `409` `{"code": "customer_selection_required", "customers": [{"id", "name"}]}`
et rappelle `login` avec `customerId` — **seul endroit** où ce champ est
accepté, et seulement parmi les clients du compte.

### Liste des commandes

```http
GET {{base_url}}/api/v1/customer/orders?status[]=draft&search=IKEA-45&perPage=20
Authorization: Bearer {{customer_token}}
```

Permission `customer.orders.view`. `perPage` ≤ 100. `?customerId=…` → 422.

### Créer un article du catalogue

```http
POST {{base_url}}/api/v1/customer/catalog/items
Authorization: Bearer {{customer_token}}
Content-Type: application/json

{"catalogId": "01J…", "articleCode": "HEMNES-3", "name": "Commode Hemnes", "weight": 12.5}
```

Permissions `customer.catalog.view` + `customer.articles.create`, module
catalogue ouvert. `201`. Code déjà présent dans le catalogue, catalogue d'un
autre client ou inactif, champ `status` fourni → 422.

### Calendrier

```http
GET {{base_url}}/api/v1/customer/calendar/orders?from=2026-10-01&to=2026-11-01
```

`from` / `to` au format `AAAA-MM-JJ`, `to` exclu, 100 jours au plus. Les
créneaux sont en heure murale (voir `phase-8-timezone-strategy.md`).

### Suivi public

```http
GET {{base_url}}/api/v1/public/tracking/<jeton du lien>
```

`200` → suivi minimal ; jeton inconnu, expiré ou révoqué → toujours le même
`404`. Débit limité par adresse.

### API par clé

```http
GET {{base_url}}/api/v1/client/orders?status=delivered&perPage=50
X-Api-Key: {{customer_api_key}}
```

Filtres lus : `search`, `status`, `perPage`, `page`. La clé ne voit que les
commandes de son client ; l'adresse appelante peut être restreinte (liste ou
bloc CIDR) par le transporteur.

## Ce que l'API ne fait pas

Pas de création de clé API, pas de configuration de flux, pas de modification
de quantité de stock, pas de modification ni suppression d'article, pas de
webhook entrant : voir `phase-8-feature-inventory.md`.
