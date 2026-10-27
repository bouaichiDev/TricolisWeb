# Checklist mise en production — portail client

Prérequis : [préproduction](customer-portal-preproduction-checklist.md)
entièrement cochée, et le [go-live report](customer-portal-go-live-report.md)
conclut `CUSTOMER_PORTAL_READY_FOR_PRODUCTION`. Sinon : **ne pas déployer**.

Deux personnes : l'une exécute, l'autre relit chaque étape et note l'heure.

## J-1

- [ ] Fenêtre de déploiement annoncée aux clients pilotes (heure creuse)
- [ ] Compte lecteur de smoke test créé sur un client de test en production
- [ ] Destinataires des alertes joignables pendant la fenêtre
- [ ] Commit à déployer identifié : `________` (CI verte sur ce commit)
- [ ] Dernier commit sain connu (cible de rollback) : `________`

## Configuration de production (une fois)

- [ ] `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`
- [ ] `CORS_ALLOWED_ORIGINS=https://tricolis.bouaichibadr.com,https://<portail>`
- [ ] `CUSTOMER_PORTAL_URL=https://<portail>` (sinon les invitations pointent vers `localhost`)
- [ ] `CUSTOMER_PORTAL_TOKEN_TTL_MINUTES` revu (720 par défaut)
- [ ] Supervisor mis à jour selon `DEPLOYMENT_VPS.md` §5 (`--timeout=1800`, `numprocs=2`) ; `retry_after` 1900 s est déjà le défaut du code
- [ ] Connexions FTP/SFTP réelles saisies dans le back-office (mot de passe chiffré par `APP_KEY`) ; `APP_KEY` sauvegardée hors du serveur — sans elle, les mots de passe sont illisibles
- [ ] Secret GitHub / variable `VITE_API_URL` du portail = URL publique de l'API
- [ ] Vhost Nginx du portail en place (HTTPS, fallback SPA)
- [ ] Workflow : étape de publication de `customer-portal/dist` ajoutée (absente aujourd'hui)
- [ ] Après déploiement : `php artisan db:seed --class=CustomerPortalSeeder --force` (crée `customer.articles.update` et l'accorde aux rôles par défaut ; rejouable, ne retire rien)

## Jour J

1. [ ] Sauvegarde base + `storage/app` (`customer-portal-backup-restore.md`), empreintes vérifiées, copie hors serveur — horodatage : `________`
2. [ ] `php artisan migrate:status` en production : noter les migrations « Pending » attendues (les 7 du portail si première mise en production)
3. [ ] Fusion de la PR dans `main` → le workflow lance `backend`, `frontend`, puis `deploy`
4. [ ] Suivre le job `deploy` jusqu'à « Check backend health » vert
5. [ ] `php artisan migrate:status` : aucune « Pending »
6. [ ] `supervisorctl status` : workers `RUNNING` (redémarrés par `reload`)
7. [ ] `php artisan schedule:list` : 4 tâches listées
8. [ ] `bash scripts/customer-portal-smoke.sh` (API, portail, compte lecteur) → `SMOKE OK`
9. [ ] Parcours manuels de `customer-portal-smoke-tests.md` avec un client pilote de test
10. [ ] Ouvrir le portail aux clients pilotes : activer les comptes, envoyer les invitations

## Critères d'arrêt (→ rollback)

- smoke test en échec sur une vérification d'authentification ou d'isolation ;
- `/up` ≠ 200 plus de 5 min après le déploiement ;
- migration en échec ;
- toute donnée d'un client visible par un autre.

Procédure : [rollback](customer-portal-rollback.md). Ne jamais lancer
`migrate:rollback`.

## J+1 à J+7

- [ ] Contrôle quotidien (`customer-portal-monitoring.md`)
- [ ] `failed_jobs` vide ou expliqué
- [ ] Relèves horaires actives pour chaque flux automatique
- [ ] Retour des clients pilotes consigné
- [ ] Go-live report complété (heure de mise en production, incidents)
