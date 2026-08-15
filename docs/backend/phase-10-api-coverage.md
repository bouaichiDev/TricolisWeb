# Phase 10 — Couverture de l'API

Document exigé par le §35.

---

## 1. Résultat

```text
routes api/v1                308
documentées par annotation   308
endpoints sans permission      4   (routes publiques d'authentification)
endpoints non fonctionnels     0
```

Chaque méthode de contrôleur porte un bloc de documentation indiquant l'action,
la permission requise et les refus métier attendus. **Scramble** produit la
spécification OpenAPI à partir de ces annotations, des Form Requests et des API
Resources : elle ne peut pas diverger du code, puisqu'elle en est dérivée.

## 2. Ce que documente chaque endpoint

| Élément | Source |
|---|---|
| Méthode et URL | `routes/api.php` |
| Permission requise | bloc de documentation du contrôleur |
| En-têtes | `Authorization: Bearer`, `X-Organization-Id` |
| Payload | Form Request — règles, types, champs conditionnels |
| Filtres, tri, pagination | `ListRequest` et le Query Object du module |
| Réponses | API Resource |
| Erreurs | codes documentés ci-dessous |
| Exemples | `docs/backend/phase-*-api-examples.md`, un par phase |

## 3. Couverture par domaine

| Domaine | Routes | Documentation d'exemples |
|---|:-:|---|
| Authentification | 11 | `phase-1-api-examples.md` |
| Organisations, abonnements, utilisateurs, rôles | 24 | `phase-1-api-examples.md` |
| Agences et dépôts | 11 | `phase-1-api-examples.md` |
| Adresses, contacts, documents | 26 | `phase-2-api-examples.md` |
| Clients, sites, catalogues | 24 | `phase-2-api-examples.md` |
| Référentiels (services, colis, véhicules) | 21 | `phase-2-api-examples.md` |
| Commandes, lignes, services, colis | 45 | `phase-3-api-examples.md` |
| Fournisseurs, chauffeurs, flotte | 22 | `phase-3-api-examples.md` |
| Tournées, arrêts, périodes, affectations | 33 | `phase-4-api-examples.md` |
| Suivi, preuves de livraison, litiges | 14 | `phase-5-api-examples.md` |
| Factures et décomptes | 22 | `phase-6-api-examples.md` |
| Stock | 21 | `phase-7-api-examples.md` |
| Intégrations et exports | 26 | `phase-8-api-examples.md` |
| Communication | 24 | `phase-9-api-examples.md` |
| Audit | 1 | `phase-1-api-examples.md` |

## 4. Format de réponse — unique sur les 308 routes

### Ressource

```json
{ "data": { }, "meta": [] }
```

### Collection paginée

```json
{
  "data": [],
  "meta": { "currentPage": 1, "perPage": 25, "total": 0, "lastPage": 1 },
  "links": { "first": null, "last": null, "prev": null, "next": null }
}
```

Ce format est celui du §16, à la lettre. Il est produit par un point unique —
`ApiResponse::paginated()` — jamais recomposé endpoint par endpoint.

### Erreur de validation — 422

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "champ": ["message"] }
}
```

### Erreur métier — 409

```json
{ "message": "Une phrase expliquant ce que l'état du système interdit." }
```

Le §16 propose un champ `code: "BUSINESS_CONFLICT"`. **Il n'a pas été ajouté** :
les Phases 3 à 9 ont livré leurs 409 sans lui, le format est déjà consommé par
les tests, et le §16 précise « Ne pas changer le format si les phases
précédentes ont déjà validé un format différent ; harmoniser selon la convention
principale ». La convention principale est `{ "message": … }`, et elle est
uniforme sur les 308 routes.

## 5. Codes de statut

| Code | Emploi | Uniforme ? |
|---|---|:-:|
| `200` | Lecture, modification, transition | ✓ |
| `201` | Création | ✓ |
| `204` | Suppression | ✓ |
| `401` | Non authentifié | ✓ |
| `403` | Membre de l'organisation, **sans la permission** | ✓ |
| `404` | Ressource hors périmètre, ou enfant sous un mauvais parent | ✓ **depuis la Phase 10** |
| `405` | Verbe non exposé sur une ressource historique | ✓ |
| `422` | Validation, ou référence hors périmètre dans un payload | ✓ |
| `409` | Refus métier lié à l'état | ✓ |

La distinction 403 / 404 est le point qui a été corrigé par cette phase : cinq
ressources des Phases 1 et 2 renvoyaient 403 là où vingt-et-une renvoyaient 404.

## 6. Conventions transversales

| Convention | Règle |
|---|---|
| Nommage | **camelCase** dans les payloads et les réponses ; `snake_case` en base uniquement |
| Identifiants | ULID, 26 caractères, jamais un entier |
| Dates | **ISO 8601 avec fuseau** en sortie (`2026-08-07T09:00:00+00:00`) ; toute date acceptée en entrée |
| Montants | **chaînes**, jamais des nombres flottants (`"450.00"`) |
| Pagination | `page`, `perPage` (défaut 25, max 100) |
| Recherche | `search`, sur les colonnes déclarées par le module |
| Tri | `sort` + `direction`, liste blanche par module, 422 hors liste |
| Modification | `PATCH` partiel : un champ absent n'est jamais effacé |

## 7. Endpoints délibérément absents

| Endpoint | Décision | Documenté dans |
|---|---|---|
| `POST /communications/provider-callback/{channel}` | Non créé : aucune intégration fournisseur n'existe (Phase 9 §28) | `phase-9-final-report.md` |
| Génération de fichier d'export | Non créée : aucune règle de contenu n'est définie (Phase 8 §30) | `phase-8-final-report.md` |
| CRUD `CustomerUser` | Classe du diagramme non implémentée ; le §2 de la Phase 10 interdit de créer une table | `phase-10-uml-inventory.md` |
| Tableau de bord | Aucun écran n'existe ; la permission `dashboard.view` est en attente | `phase-10-permissions-audit.md` |

## 8. Collection Postman

**Non créée.** Le §37 la conditionne : « uniquement si cela correspond aux outils
du projet ». Le projet n'utilise ni Postman ni Bruno — aucun fichier de
collection n'existe dans le dépôt, aucune dépendance ne les mentionne.

La spécification OpenAPI produite par Scramble couvre le même besoin et
s'importe dans les deux outils. Elle a l'avantage décisif d'être **dérivée du
code** : une collection écrite à la main diverge à la première route ajoutée.

```bash
php artisan scramble:export     # produit la spécification OpenAPI
```
