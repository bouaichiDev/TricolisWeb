# Tricolis Web

Backend API multi-organisation pour la gestion d'une activité de transport et de logistique.

Le projet permet actuellement de gérer l'authentification, les organisations de transporteurs,
les agences, les dépôts, les clients, leurs sites, les adresses et les contacts. La conception
prévoit ensuite les commandes, colis, services, stocks, ressources, tournées, suivis et factures.

## Stack technique

- PHP 8.3 ou supérieur ;
- Laravel 13 ;
- MySQL 8 ;
- Laravel Sanctum pour les tokens API ;
- Pest pour les tests ;
- Scramble pour la documentation OpenAPI ;
- Opcodes Log Viewer pour la consultation sécurisée des logs ;
- Vite et Tailwind CSS pour les ressources frontend.

## Installation locale

```bash
composer install
copy .env.example .env
php artisan key:generate
```

Créer les bases MySQL :

```sql
CREATE DATABASE tricolisweb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE tricolisweb_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Configurer `.env` :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tricolisweb
DB_USERNAME=root
DB_PASSWORD=
```

Installer la base et les données de développement :

```bash
php artisan migrate --seed
npm install
npm run build
```

Lancer l'application :

```bash
composer run dev
```

L'API est alors disponible sous `http://localhost:8000/api/v1`.

## Inscription d'un transporteur

L'inscription crée dans une transaction unique :

1. le compte utilisateur ;
2. l'organisation du transporteur ;
3. l'appartenance propriétaire principale ;
4. le rôle administrateur et ses permissions ;
5. le token Sanctum.

```http
POST /api/v1/auth/register
Accept: application/json
Content-Type: application/json
```

```json
{
  "firstName": "Sara",
  "lastName": "Amrani",
  "email": "sara@example.com",
  "phone": "+212600000000",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "organization": {
    "name": "Atlas Transport",
    "legalName": "Atlas Transport SARL",
    "registrationNumber": "RC-12345",
    "taxNumber": "ICE-98765",
    "timezone": "Africa/Casablanca",
    "currencyCode": "MAD"
  }
}
```

## Authentification et organisation active

Les routes suivantes sont publiques :

- `POST /auth/register` ;
- `POST /auth/login` ;
- `POST /auth/forgot-password` ;
- `POST /auth/reset-password`.

Toutes les autres routes utilisent un token :

```http
Authorization: Bearer VOTRE_TOKEN
```

Les routes métier exigent également l'organisation active :

```http
X-Organization-Id: 01JABCDEFGHJKMNPQRSTVWXYZ
```

La liste `GET /organizations` ne demande pas cet en-tête afin que le frontend puisse d'abord
présenter les organisations accessibles à l'utilisateur.

## Documentation API

La documentation interactive est disponible ici :

```text
http://localhost:8000/docs/api
```

Elle permet de configurer le Bearer token et documente automatiquement l'en-tête
`X-Organization-Id` sur les routes concernées.

## Compte de développement

Après `php artisan migrate:fresh --seed` :

```text
E-mail : admin@tricolis.dev
Mot de passe : password
```

Ces identifiants ne doivent jamais être utilisés en production.

## Consultation des logs

Activer le visualiseur uniquement pour les administrateurs techniques :

```dotenv
LOG_VIEWER_ENABLED=true
LOG_VIEWER_ALLOWED_EMAILS=admin@tricolis.dev
```

Puis ouvrir :

```text
http://localhost:8000/log-viewer
```

L'accès utilise HTTP Basic avec le compte Laravel. Les utilisateurs absents de la liste blanche
sont refusés et la suppression des fichiers de logs est désactivée. HTTPS est obligatoire en
production.

Pour suivre les logs depuis le terminal :

```bash
php artisan pail
```

## Tests et qualité

La base de test est définie dans `.env.testing` et doit rester distincte de la base locale.

```bash
php artisan test
vendor/bin/pint --test
```

Sous Windows :

```powershell
php artisan test
vendor\bin\pint --test
```

## Structure métier

```text
app/
├── Modules/       Modèles et actions par domaine
├── Policies/      Autorisations multi-organisation
├── Shared/        Composants transverses
├── Http/          Contrôleurs, middleware, requests et resources
└── OpenApi/       Extensions de la documentation API
```

Les diagrammes de référence se trouvent dans `Conception/diagramme` et les décisions backend
dans `docs/backend`.

## Règles de sécurité

- ne jamais commiter `.env` ;
- utiliser une base distincte pour les tests ;
- ne jamais exposer Log Viewer sans authentification ;
- ne jamais envoyer un token dans l'URL ;
- vérifier le Bearer token et `X-Organization-Id` sur toute route métier ;
- conserver les logs techniques séparés du journal d'audit métier.
