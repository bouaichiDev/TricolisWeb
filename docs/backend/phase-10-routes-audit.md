# Phase 10 — Audit des routes

Document exigé par le §13.

---

## 1. Résultat d'ensemble

```text
routes api/v1                308
routes nommées               308 / 308
doublons méthode+URI           0
sans auth:sanctum              4   (publiques, justifiées)
sans middleware organization  12   (self-service, justifiées)
routes non fonctionnelles      0
endpoints fictifs              0
```

Le versionnement est porté par `apiPrefix: 'api/v1'` dans `bootstrap/app.php` :
aucune route ne peut échapper au préfixe, il n'est pas répété route par route.

## 2. Middleware

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('organization')->group(function () {
        // 296 routes métier
    });
    // 12 routes self-service
});
// 4 routes publiques
```

### Les quatre routes publiques

```text
POST /api/v1/auth/register          POST /api/v1/auth/login
POST /api/v1/auth/forgot-password   POST /api/v1/auth/reset-password
```

Aucune ne peut exiger un jeton : ce sont celles qui en produisent un, ou qui
permettent de le récupérer.

### Les douze routes sans contexte organisationnel

```text
POST   /auth/logout            POST   /auth/logout-all
GET    /auth/me                PATCH  /auth/profile
PATCH  /auth/password          GET    /auth/sessions
DELETE /auth/sessions/{tokenId}
GET|POST         /organizations
GET|PATCH|DELETE /organizations/{organization}
```

Les sept premières sont **strictement personnelles** : elles agissent sur
l'utilisateur authentifié, pas sur une donnée d'organisation. Exiger un
`X-Organization-Id` pour consulter son propre profil n'ajouterait aucune
protection.

Les cinq suivantes traitent l'organisation **comme ressource**. Les filtrer par
l'organisation active serait circulaire : `GET /organizations` doit retourner
celles auxquelles l'utilisateur appartient, ce que la requête fait par jointure
sur `organization_users`. La création est un parcours d'inscription.

**Toutes les autres — 296 — passent par `EnsureOrganizationContext`**, qui
valide le format de l'en-tête (422 si malformé) puis l'appartenance (403 si
l'utilisateur n'est pas membre).

## 3. Nommage

Les 308 routes sont nommées, sans exception, selon la convention
`ressource.action` avec imbrication par points :

```text
customers.index · customers.store · customers.show · customers.update · customers.destroy
customers.status
orders.services.index · orders.services.store · orders.services.show
tours.stops.services.reorder
order-communications.attachments.index
customer-api-configurations.rotate-key
```

## 4. Cohérence parent-enfant

Toute route imbriquée vérifie **les deux liens** — le parent appartient à
l'organisation, et l'enfant appartient à ce parent — avant d'autoriser :

| Chaîne | Trait |
|---|---|
| `tours/{tour}/stops/{stop}/services/{service}` | `ResolvesTourScope` |
| `tours/{tour}/periods/{period}/assignments/{assignment}` | `ResolvesTourScope` |
| `orders/{order}/services/{service}/contacts/{contact}` | contrôleur dédié |
| `orders/{order}/packages/{package}/lines/{line}` | contrôleur dédié |
| `invoices/{invoice}/lines/{line}` | contrôleur dédié |
| `provider-settlements/{settlement}/lines/{line}` | contrôleur dédié |
| `customers/{customer}/catalogs/{catalog}/items/{item}` | contrôleur dédié |
| `order-communications/{communication}/attachments/{attachment}` | `ResolvesCommunicationScope` |

Un enfant valide sous un mauvais parent renvoie **404**, jamais 403 : l'existence
de la ressource ne se révèle pas davantage par son rattachement.

## 5. Ordre de déclaration

Trois routes littérales précèdent leur `apiResource`, sans quoi le paramètre les
capterait comme identifiants :

```text
GET  /stock-locations/tree                      avant  /stock-locations/{stockLocation}
GET  /orders/{order}/packages/tree              avant  /packages/{package}
POST /customer-api-configurations/{id}/rotate-key   avant  l'apiResource
POST /order-communications/{id}/queue|cancel|retry  avant  l'apiResource
POST /tours/{tour}/stops/reorder                avant  /stops/{tourStop}
```

## 6. Verbes HTTP

| Verbe | Emploi | Contrôle |
|---|---|---|
| `GET` | lecture, jamais d'effet de bord | ✓ |
| `POST` | création, et transitions d'état | ✓ |
| `PATCH` | modification **partielle** — le projet n'expose aucun `PUT` sémantique | ✓ |
| `DELETE` | suppression, refusée en 409 sur l'historique | ✓ |

`PUT` apparaît dans `route:list` parce qu'`apiResource` l'enregistre avec
`PATCH`, mais les contrôleurs traitent toujours une modification partielle via
`PartialAttributes` : un champ absent n'est pas effacé.

Les ressources historiques n'exposent délibérément ni `PATCH` ni `DELETE`, et
répondent **405** :

```text
export-jobs                        (index, store, show, retry)
tracking-events                    (index, store, show)
proofs-of-delivery                 (index, store, show)
stock-movements                    (index, store, show)
order-communications/{id}/attachments  (pas de PATCH — snapshots immuables)
stock-balances                     (lecture seule — produits par les mouvements)
```

## 7. Endpoints prévus mais non livrés

Aucun endpoint fictif n'existe. Deux endpoints envisagés par les prompts ont été
**délibérément non créés**, et leur absence est documentée :

| Endpoint | Prompt | Raison |
|---|---|---|
| `POST /communications/provider-callback/{channel}` | Phase 9 §28 | Conditionné à des intégrations fournisseur existantes. Il n'y en a aucune, et « ne pas inventer de protocole fournisseur » interdit d'écrire une validation de signature contre un fournisseur imaginaire. |
| Génération et transport de fichier d'export | Phase 8 §30 | Aucune règle de contenu n'est définie nulle part ; le §30 interdit d'inventer un schéma métier. |

## 8. Répartition par domaine

| Domaine | Routes | Domaine | Routes |
|---|:-:|---|:-:|
| Authentification | 11 | Planification | 33 |
| Organisations et identité | 24 | Suivi et litiges | 14 |
| Réseau (agences, dépôts) | 11 | Facturation | 22 |
| Tiers (clients, fournisseurs) | 38 | Stock | 21 |
| Référentiels | 39 | Intégrations | 26 |
| Commandes | 45 | Communication | 24 |
