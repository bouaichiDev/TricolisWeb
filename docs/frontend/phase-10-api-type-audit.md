# Audit de contrat d'API et de types — Phase 10

Relevé du 2 septembre 2026. La comparaison n'est pas faite à l'œil : un script
extrait les URL réellement appelées par le frontend et les confronte à
`php artisan route:list --path=api/v1`.

## 1. Méthode

```text
serveur   → route:list --json, paramètres normalisés : /orders/{order}/lines → /orders/{}/lines
frontend  → toutes les URL littérales passées aux sept méthodes du client HTTP
            (get, post, patch, put, delete, upload, blob), interpolations normalisées
```

Deux angles morts corrigés en cours d'audit, et qui valent d'être dits parce
qu'ils faussaient le résultat :

- le générique peut être imbriqué — `api.get<ApiCollection<Customer>>(…)` — et
  une expression s'arrêtant au premier `>` ratait l'appel ;
- `api` et sa méthode sont souvent séparés par un retour à la ligne, si bien que
  s'accrocher à `api.` ratait `/auth/login` ;
- `upload` et `blob` sont des méthodes du client au même titre que les cinq
  verbes, et les oublier faisait passer le téléchargement d'un document pour une
  route jamais appelée.

Un audit qui se serait arrêté à la première version aurait annoncé 151 routes
mortes. Il y en a 41, et la plupart ne le sont pas.

## 2. Résultat — aucun endpoint fantôme

```text
201 routes servies
160 routes appelées
  0 fantôme
```

C'est la condition du §27 : **aucun appel frontend ne vise une route que le
serveur n'expose pas.**

## 3. Les 41 routes servies sans appel — analyse

Le §27 ne les interdit pas ; les laisser sans explication serait néanmoins
laisser croire à du code mort. Quatre catégories.

### 3.1 Le portail client — hors périmètre du Backoffice

```text
/client/me   /client/orders   /client/orders/{}
```

Trois routes authentifiées par **clé API client**, pas par session. Elles
servent le futur Customer Portal, et le §53 place celui-ci après la Phase 10.
Aucun écran du Backoffice ne doit les appeler : elles scopent par client, pas
par organisation.

### 3.2 Ressources imbriquées, lues par leur parent

```text
/tours/{}/stops et ses six sous-routes
/tours/{}/stops/{}/services et ses trois sous-routes
/orders/{}/services/{}/contacts
/documents/{}/links
/contacts/{}/links
```

Le détail d'une tournée **embarque** ses arrêts — `Tour.stops?: TourStop[]` — et
la planification les modifie par `/tours/{id}/plan` et `/unplan`, un appel pour
tout un glisser-déposer. Les sous-routes CRUD existent et fonctionnent ; l'écran
n'en a pas besoin, et les appeler une par une multiplierait les allers-retours.

Servi sans être consommé n'est pas mort : c'est une API utilisable par une
intégration.

### 3.3 Écrans non livrés — **un vrai manque**

```text
/tours/{}/periods                       (index, store)
/tours/{}/periods/reorder
/tours/{}/periods/{}                    (show, update, destroy)
/tours/{}/periods/{}/assignments        (index, store)
/tours/{}/periods/{}/assignments/{}     (destroy)
```

`TourPeriod` et `TourPeriodAssignment` sont deux entités que le §8 déclare
strictes, avec neuf routes servies, des politiques, des ressources et six
fichiers de tests backend. **Le frontend n'en dit pas un mot** : aucune
occurrence de `TourPeriod` dans `frontend/src/`.

C'est le seul écart fonctionnel que cet audit révèle. Il est reporté au rapport
final ; le combler est un écran de planification par période, pas une
correction.

### 3.4 Parcours d'authentification et divers

```text
/auth/register  /auth/forgot-password  /auth/reset-password
/auth/profile   /auth/password         /auth/sessions
/subscription   /permissions/{}        /users
/proofs-of-delivery et /tracking-events en accès direct
```

`/auth/login` et `/auth/me` **sont** appelés. Les autres relèvent d'écrans de
compte non livrés (mot de passe oublié, sessions actives) ou d'accès directs
doublonnant une route imbriquée déjà employée — un POD se lit par
`/orders/{}/proofs-of-delivery` dans l'écran de commande.

## 4. Types — aucune échappatoire

| Recherche | Résultat |
|---|---|
| `as any` | **0** |
| `as unknown as` | **0** |
| `npm run typecheck` | **0 erreur** |

Le §27 interdit ces deux formes « comme cache-misère ». Aucune n'est employée,
y compris dans les tests.

## 5. Champs fantômes

Le §27 nomme quatre suspects. Vérification :

| Champ | Verdict |
|---|---|
| `priority` | **réel** — `price_rules.priority` et `price_matrix_rows.priority`, créés par `2026_08_29_100000`. Absent de `CommunicationRule` et `OrderCommunication`, ce que le §113 de la Phase 9 exige. |
| `billingStatus` | **absent** partout |
| `OrderStop` | **absent** — cinq occurrences, toutes des commentaires disant qu'il n'existe pas |
| champs spéculatifs hérités | aucun trouvé |

## 6. Conclusion

Une seule action en découle, et ce n'est pas une correction de contrat :

```text
0 endpoint fantôme
0 échappatoire de typage
0 champ fantôme
1 écart fonctionnel — TourPeriod servi, sans écran
```
