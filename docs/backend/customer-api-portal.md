# Portail client — authentification par clé API

Comment un client appelle Tricolis avec la clé qu'on lui a remise.

> Ajouté après la Phase 8 frontend, pour combler ce que le rapport backend de la
> Phase 8 avait consigné en risques 2 et 3 : la clé était émise, mais **aucun
> point d'entrée ne la lisait**, `allowedIps` et `permissions` n'étaient jamais
> appliqués, et `lastUsedAt` restait vide.

## Ce que le portail est, et ce qu'il n'est pas

C'est une **seconde porte**, distincte de celle des utilisateurs du
transporteur :

| | Administration | Portail client |
|---|---|---|
| Chemin | `/api/v1/*` | `/api/v1/client/*` |
| Authentification | `auth:sanctum` (jeton de session) | `customer-api` (clé API) |
| Identité | un `User` de l'organisation | une `CustomerApiConfiguration` |
| Autorisation | Policies, rôles et permissions RBAC | `permissions` portées par la clé |
| Portée des données | l'organisation | **le seul client de la clé** |

Les deux ne communiquent pas : une clé cliente est refusée sur
`/api/v1/orders`, et un jeton de session est refusé sur `/api/v1/client/me`.
Deux tests le vérifient.

### Pourquoi des routes séparées

C'est le point de conception qui décide de tout le reste.

`OrderListQuery::paginate()` scope par **organisation**, et traite `customerId`
comme un filtre facultatif. Brancher une clé cliente sur les routes existantes
lui aurait donné les commandes de **tous** les clients du transporteur : un
filtre se retire d'une requête, une contrainte non.

Les contrôleurs du portail posent donc l'appartenance en premier, depuis le
contexte authentifié, et ne lisent jamais un `customerId` fourni par
l'appelant. Un test envoie `?customerId=<autre client>` et vérifie que la
réponse reste vide.

## Appeler le portail

Un seul en-tête. Ni `Authorization`, ni `X-Organization-Id` : l'organisation se
déduit du client, et le client de la clé.

```http
GET /api/v1/client/me HTTP/1.1
Host: localhost:8000
X-Api-Key: <la clé remise au client>
Accept: application/json
```

### Points d'entrée

| Route | Droit exigé | Réponse |
|---|---|---|
| `GET /api/v1/client/me` | *aucun* | le client, le nom de l'accès, ses droits |
| `GET /api/v1/client/orders` | `orders.view` | ses commandes, paginées |
| `GET /api/v1/client/orders/{order}` | `orders.view` | une de ses commandes |

`client/me` n'exige aucun droit délibérément : une clé sans permission doit
pouvoir constater qu'elle n'en a aucune, plutôt que de recevoir un 403 muet.

`orders` accepte `page`, `perPage`, `search` — sur le numéro, la référence
externe et la référence client — et `status`.

## Les contrôles, dans l'ordre

L'ordre n'est pas indifférent.

1. **La clé.** Absente → `401`. Hachée en SHA-256, cherchée sur `api_key_hash` ;
   la clé en clair n'est stockée nulle part.
2. **L'existence et l'activité.** Inconnue ou désactivée → `401`, **avec le même
   message**. Distinguer les deux dirait à un appelant qu'il détient une clé
   valide, seulement fermée.
3. **L'adresse.** Hors de `allowedIps` → `403`. Une liste vide n'impose aucune
   restriction — c'est ce que l'écran de création annonce.
4. **Le droit.** Absent de `permissions` → `403`. Une liste vide ne donne
   **rien** : la différence avec les adresses est voulue.
5. **La trace.** `last_used_at` est daté, et seulement pour les appels admis. Un
   test vérifie qu'un appel refusé ne laisse pas de date.

### Adresses : la comparaison se fait sur les octets

`IpOrCidr` vérifie qu'une entrée est bien *écrite* ; `IpAllowList` répond à
l'autre question — cette requête vient-elle d'une adresse permise. Confondre les
deux laisserait passer n'importe qui.

La comparaison passe par `inet_pton`, ce qui traite IPv4 et IPv6 de la même
façon. Comparer des chaînes échouerait dès la première forme abrégée : `::1` et
`0:0:0:0:0:0:0:1` sont la même adresse.

Un réseau IPv4 ne contient pas une adresse IPv6, et un préfixe plus long que
l'adresse — `/64` sur de l'IPv4 — ne correspond à rien plutôt que de déborder.

## Essayer dans Postman

**1. Créer l'accès** depuis l'interface : *Intégrations → Accès API clients →
Nouvel accès*. Choisir le client, cocher au moins `orders.view`, laisser les
adresses vides pour un premier essai. Copier la clé — elle ne sera plus affichée.

**2. Vérifier l'identité.**

```http
GET http://localhost:8000/api/v1/client/me
X-Api-Key: <clé>
Accept: application/json
```

Attendu : `200`, avec le client et `["orders.view"]`.

**3. Lire ses commandes.**

```http
GET http://localhost:8000/api/v1/client/orders
X-Api-Key: <clé>
```

Attendu : `200`, et **uniquement** les commandes de ce client.

**4. Ce qui doit échouer** — c'est là que le portail se prouve :

| Essai | Attendu |
|---|---|
| Sans l'en-tête `X-Api-Key` | `401` |
| Une clé inventée | `401` |
| L'accès désactivé depuis l'interface | `401`, même message |
| `allowedIps` = `203.0.113.7` | `403` |
| Retirer `orders.view` des droits | `403` sur `/client/orders`, `200` sur `/client/me` |
| La clé sur `/api/v1/orders` | `401` |
| `?customerId=<autre client>` | `200`, liste vide |

**5. Rouvrir la fiche de l'accès** : « Dernière utilisation » porte désormais une
date. C'était le champ qui restait vide faute de lecteur.

**6. Renouveler la clé**, puis rappeler `/client/me` avec l'ancienne : `401`.
La rotation change `api_key_hash`, et l'ancienne empreinte n'existe plus.

## Où ce portail devrait vivre — décision ouverte

Ce qui est écrit ici est une **fondation**, pas une architecture arrêtée. Le
portail client et le backoffice sont deux produits : authentification,
autorisation, portée des données, public et exposition diffèrent. Les séparer
est légitime ; reste à choisir *comment*.

| Séparation | Ce qu'elle donne | Ce qu'elle coûte |
|---|---|---|
| **Même code, deux déploiements** | isolation réseau et montée en charge indépendante, **une seule** implémentation des règles métier | un dépôt commun, une release commune |
| **Projet séparé appelant l'API interne** | séparation totale, aucun accès direct à la base | latence, et l'API interne doit exposer ce dont le portail a besoin |
| **Projet séparé sur la même base** | l'impression d'être séparé | la base devient le contrat d'intégration — le plus faible qui soit |

Le troisième mérite un avertissement précis, parce qu'il est tentant. Les règles
du projet vivent dans les Actions, pas dans le schéma :

- `CreateStockMovementAction` verrouille les soldes, contrôle le disponible et
  recalcule, en transaction ;
- `CreateStockReservationAction` prend un `lockForUpdate` avant de promettre ;
- `ExportDispatcher` refuse une facture non clôturée ;
- `CreateOrderAction` écrit commande, lignes, colis et services d'un bloc, en
  résolvant les clés locales ;
- `CheckStatusMachine` filtre les transitions de statut ;
- `WriteAuditLog` trace chaque écriture ;
- les mots de passe d'export sont chiffrés avec `APP_KEY`.

Un second projet écrivant directement en base devrait réimplémenter tout cela à
l'identique et le maintenir en phase. La première divergence ne se verrait qu'au
premier solde de stock faux en production.

Quel que soit le choix, `AuthenticateCustomerApiKey`, `IpAllowList` et
`CustomerApiContext` se reprennent tels quels ; seuls les contrôleurs et le
fichier de routes changeraient de place.

## Ce qui reste ouvert

0. **L'écran des droits promet plus que le portail ne sert.** Le sélecteur
   propose tout le référentiel RBAC — celui des utilisateurs du transporteur —
   alors qu'un seul droit, `orders.view`, a aujourd'hui un point d'entrée
   client. Accorder `stock_items.create` à une clé n'ouvre rien : ce n'est pas
   dangereux, mais c'est trompeur. À corriger en marquant les droits réellement
   servis.
1. **La surface est volontairement étroite** : identité et commandes en lecture.
   Chaque route ajoutée demande de décider comment l'appartenance au client s'y
   exprime — ce n'est pas un réglage, c'est une décision par ressource. Écrire
   une commande, notamment, suppose de résoudre agence, dépôt et services depuis
   des références métier.
2. **Pas de limitation de débit.** Une clé volée peut appeler sans frein. Un
   `throttle` par clé est le complément naturel.
3. **Pas de journal d'appels.** Le §4 de la Phase 8 interdit `ApiRequestLog` ;
   `lastUsedAt` est la seule trace, et elle ne dit pas ce qui a été appelé.
4. **La clé voyage en clair dans l'en-tête.** C'est le cas de tous les schémas à
   jeton porteur, et cela suppose HTTPS en production.
