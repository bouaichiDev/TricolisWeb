# Portail fournisseur — guide d'exploitation et de déploiement

**Aucun déploiement n'a été fait.** Ce guide décrit la procédure ; elle exige une approbation explicite
(`go-no-go.md`).

## Composants

- **API** : dans le backend Laravel existant (`/api/v1/provider`), aucun service nouveau.
- **Application** : `provider-portal/` (React, Vite), fichiers statiques. `npm ci && npm run build` avec
  `VITE_API_URL=<URL publique de l'API>` ; servir `provider-portal/dist/` en SPA (repli sur `index.html`).
- **Base** : 3 tables nouvelles (`provider_users`, `provider_user_roles`, `provider_user_invitations`), aucune table
  existante modifiée.
- **Fichiers** : signatures et photos sur le disque privé `local`, sous `storage/app/private/documents/{organisation}`,
  servies uniquement par l'API.
- **E-mails** (invitation, réinitialisation) : envoyés de façon synchrone par la boîte d'envoi de l'organisation ;
  aucun worker de file d'attente ni tâche planifiée propre au portail.

## Variables d'environnement (backend)

| Variable | Défaut | Rôle |
|---|---|---|
| `PROVIDER_PORTAL_URL` | — | URL publique du portail, utilisée dans les liens d'invitation et de réinitialisation |
| `PROVIDER_PORTAL_INVITATION_HOURS` | 72 | validité d'une invitation |
| `PROVIDER_PORTAL_TOKEN_TTL_MINUTES` | 720 | durée d'un jeton de session |
| `PROVIDER_PORTAL_RATE_LIMIT` | 300 | requêtes/min par appelant |
| `PROVIDER_PORTAL_LOGIN_RATE_LIMIT` | 30 | connexions/min par IP |
| `PROVIDER_PORTAL_WRITE_RATE_LIMIT` | 30 | créations/min (flotte, affectation) |
| `PROVIDER_PORTAL_EXECUTE_RATE_LIMIT` | 120 | exécution et preuves/min |
| `CORS_ALLOWED_ORIGINS` | — | **ajouter l'origine du portail** (les origines locales ne valent qu'hors production) |

Aucun secret propre au portail. Upload : vérifier `upload_max_filesize` / `post_max_size` (PHP) et
`client_max_body_size` (nginx) ≥ 12 Mo (signature + photo de 5 Mo chacune).

## Procédure (à exécuter seulement après GO)

1. **Sauvegarde vérifiée** de la base et de `storage/` ; restauration testée sur une copie.
2. Déployer le code (le workflow `deploy-production.yml` part sur `main` : la fusion **est** le déploiement).
3. `php artisan migrate --force` (3 créations de tables).
4. `php artisan db:seed --class=ProviderPortalSeeder --force` — rejouable : permissions `provider.*` et 4 rôles par
   organisation. **Jamais** les seeders `Demo*` (ils ne font rien hors `local`/`testing`, garde testée).
5. `php artisan provider-portal:route-matrix --check`.
6. Publier `provider-portal/dist/` sur le domaine du portail (vhost nginx, HTTPS).
7. Smoke tests : `GET /up` ; `POST /api/v1/provider/auth/login` avec un compte invité ; `GET auth/me`, `navigation`,
   `tours` ; un jeton interne sur `GET /api/v1/provider/auth/me` → 403.

## Rollback

- **Code** : redéployer le commit précédent.
- **Données** : si les trois migrations du portail sont les dernières appliquées, `php artisan migrate:rollback --step=3`
  les retire (leurs `down()` suppriment les tables : comptes portail et invitations perdus) ; vérifier d'abord avec
  `php artisan migrate:status`. Les écritures faites par les fournisseurs
  (affectations, statuts, preuves, documents) vont dans des tables existantes : seul le retour à la sauvegarde les
  annule. Les permissions `provider.*` restent, sans effet sans le code.

## Observabilité

- Journaux Laravel : aucune donnée d'authentification journalisée ; erreurs API rendues sans trace hors debug.
- Audit métier : `audit_logs` (`provider_portal.*`, codes métier).
- À mettre en place (non existant) : alerte sur taux de 5xx de `/api/v1/provider/*`, sur pics de `login_failed`,
  sur 429 ; sonde de disponibilité du domaine du portail ; procédure d'incident (révoquer : désactiver le rattachement
  ou le fournisseur, effet à la requête suivante).
