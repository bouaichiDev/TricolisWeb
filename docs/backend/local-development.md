# Développement local — Backend Tricolis V2

## Identifiants de développement

Les seeders de développement ne s'exécutent qu'en environnement `local` ou `testing`.
Aucun mot de passe de production n'est présent dans le dépôt.

| Ressource | Valeur |
|-----------|--------|
| Organisation | `Tricolis Dev` (`code: tricolis-dev`) |
| Utilisateur admin | `admin@tricolis.dev` |
| Mot de passe | `password` (surchargeable via `DEV_ADMIN_PASSWORD`) |
| Agence de démo | `main` — Agence principale |
| Dépôt de démo | `central` — Dépôt central |
| Client de démo | `demo-client` — Client démo |

Le mot de passe est lu depuis `config('tricolis.development_password')`, alimenté
par la variable d'environnement `DEV_ADMIN_PASSWORD`.

## Seeders

Chaque seeder est autonome et idempotent : il peut être rejoué sans créer de doublon.

| Seeder | Rôle | Environnements |
|--------|------|----------------|
| `PermissionSeeder` | Référentiel global des permissions | tous |
| `DevelopmentOrganizationSeeder` | Organisation Tricolis + utilisateur admin + rattachement | local, testing |
| `RoleSeeder` | Rôle système `admin` de chaque organisation, ses permissions et son attribution aux propriétaires | tous |
| `DemoAgencySeeder` | Agence et dépôt de démonstration | local, testing |
| `DemoCustomerSeeder` | Client de démonstration | local, testing |

`DatabaseSeeder` les appelle dans cet ordre : les rôles sont créés après les
organisations, afin que `RoleSeeder` puisse itérer sur les organisations existantes.

Lancer un seeder isolément :

```bash
php artisan db:seed --class=PermissionSeeder
```

## Configuration requise

- PHP 8.4.2
- Laravel 13.23.0
- MySQL 8.0 ou MariaDB compatible
- Composer

## Commandes utiles

```bash
# Installation des dépendances
composer install

# Génération de la clé si nécessaire
php artisan key:generate

# Reset complet de la base de test
php artisan migrate:fresh --seed --env=testing

# Exécution des tests
php artisan test

# Vérifications de style
./vendor/bin/pint --test

# Purge des documents supprimés au-delà de la rétention
php artisan documents:purge
php artisan documents:purge --days=7
```

## Documentation de l'API

La documentation OpenAPI est générée par Scramble à partir des Form Requests,
des API Resources et des annotations des contrôleurs.

```bash
# Interface interactive
php artisan serve   # puis ouvrir /docs/api

# Export du document OpenAPI
php artisan scramble:export --path=storage/app/api.json
```

Chaque opération documente sa méthode, son URL, la permission requise,
l'en-tête `X-Organization-Id` lorsqu'il s'applique, ses paramètres, son corps de
requête avec ses règles de validation, un exemple de réponse et les erreurs
possibles (401, 403, 404, 409, 422).

## File d'attente

`QUEUE_CONNECTION=database` : les travaux différés attendent dans la table
`jobs` jusqu'à ce qu'un ouvrier les prenne.

```bash
php artisan queue:work
```

**Sans cet ouvrier, rien ne se géocode et aucun itinéraire ne se calcule.** Les
deux partent en file délibérément : le service GPS est distant, et l'appeler
pendant la requête ferait attendre le formulaire à chaque adresse enregistrée,
ou le glisser à chaque commande planifiée. Les travaux concernés :

| Travail | Déclenché par |
|---|---|
| `GeocodeAddressJob` | adresse créée sans point, adresse déplacée, commande créée sur une adresse non située |
| `RecalculateTourRouteJob` | planification, déplanification, ajout / retrait / déplacement / réordonnancement d'un arrêt |
| `SendOrderCommunicationJob` | communication mise en file |

Les deux premiers exigent une configuration active dans
`organization_api_configurations`, de code `gps_geocoding` et `gps_routing`. Sans
elle, le travail s'exécute, journalise un avertissement et ne change rien — la
carte reste vide et la distance affiche « non calculé ».

Le stock d'adresses antérieur à la mise en place ne se rattrape pas tout seul :

```bash
php artisan tricolis:geocode-addresses --dry-run   # compter
php artisan tricolis:geocode-addresses --limit=50  # traiter un lot
```

Le quota du service est limité : `--limit` borne le lot, et les adresses
introuvables sont listées en fin de traitement plutôt que tues.

## Tests

Les tests utilisent **Pest** (déjà installé) et le trait `RefreshDatabase`.

La base de test MySQL est configurée dans `.env.testing`. Utiliser une base dédiée
(`tricolisweb_test` par défaut) afin de ne jamais réinitialiser les données locales.

`.env.testing` n'est **pas versionné** : il contient une `APP_KEY` et des
identifiants de base. Le copier depuis le modèle après le clone :

```bash
cp .env.testing.example .env.testing
php artisan key:generate --env=testing
```

Les helpers `authUser()` et `authOrganization()` (définis dans `tests/Pest.php`)
renvoient le compte et l'organisation créés par les seeders de développement.
