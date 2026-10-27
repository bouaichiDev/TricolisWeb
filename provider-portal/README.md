# Tricolis — Portail fournisseur

Application React distincte du back-office (`../frontend`) et du portail client
(`../customer-portal`). Elle parle au même backend Laravel, sous
`/api/v1/provider`.

## Démarrage

```bash
npm install            # .npmrc active legacy-peer-deps (bug npm 11)
cp .env.example .env.local
npm run dev            # http://localhost:5190
```

`VITE_API_URL` désigne le backend (`http://localhost:8000` par défaut). Côté
Laravel, `PROVIDER_PORTAL_URL` doit pointer vers ce portail : les liens
d'invitation et de réinitialisation y mènent. Un build de production refuse de
partir sans `VITE_API_URL`.

En développement (`APP_ENV=local`), `php artisan migrate --seed` pose deux
fournisseurs de démonstration et leurs comptes (mot de passe de développement) :

| Compte | Fournisseur | Rôle |
|---|---|---|
| `admin@transports-alpha.test` | Transports Alpha | Administrateur fournisseur |
| `viewer@transports-alpha.test` | Transports Alpha | Lecture |
| `admin@transports-beta.test` | Transports Beta | Administrateur fournisseur |

## Comptes et accès

Le portail **ne gère ni comptes ni rôles**. Le transporteur les gère au
back-office, fiche du fournisseur → onglet « Accès portail » : inviter,
changer le rôle, désactiver, réactiver, renvoyer l'invitation.

## Sécurité côté interface

- Le jeton vit en `sessionStorage` (clé `tricolis.provider-portal.token`) : il
  meurt avec l'onglet. Un 401 ou un 403 `provider_access_revoked` l'efface,
  vide le cache des requêtes et ramène à la connexion.
- Le menu est celui que le serveur sert (`GET /navigation`) ; une entrée
  `users` ou `roles` ne serait jamais affichée.
- Les permissions servent à masquer, jamais à protéger : le serveur revérifie
  tout, à chaque requête.

## Tests

```bash
npm run test           # Vitest
npm run lint && npm run typecheck && npm run build
npm run test:e2e       # Playwright — préalables ci-dessous
```

Les parcours Playwright tournent contre la base de test (API 8003, web 5191,
`--strictPort`). Préalable, et jamais pendant une autre suite :

```bash
php artisan migrate:fresh --seed --env=testing
php artisan db:seed --class=DemoProviderPortalSeeder --env=testing
```
