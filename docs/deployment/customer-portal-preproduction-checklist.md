# Checklist préproduction — portail client

À dérouler sur un environnement **distinct de la production** (base, domaine,
`.env`, stockage), avec une copie anonymisée ou des données de démonstration.
Chaque case cochée porte la date et l'initiale de qui l'a vérifiée.

État au 2026-10-10 : **aucun environnement de préproduction n'existe** dans le
dépôt ni dans le workflow. Les cases marquées « fait (dev) » ont été vérifiées
en développement pendant la Phase 8 ; les autres restent à faire.

## Code et tests

- [x] Branche dédiée `feature/customer-portal-phase-8-finalization`, pas de travail sur `main` — fait
- [ ] Revue de code et fusion dans `main` par PR (CI verte)
- [x] Backend : suite Pest complète — fait (dev), voir `phase-8-regression-report.md`
- [x] Backend : `pint --test` vert — fait (dev)
- [x] Backend : `customer-portal:route-matrix --check` — fait (dev), 89 routes externes autorisées
- [x] Frontend portail : lint, typecheck, Vitest, build — fait (dev)
- [x] E2E Playwright portail : 14 parcours — fait (dev, base de test)
- [ ] CI GitHub Actions verte sur la PR (jobs `backend`, `frontend`) — jamais exécutée : workflow modifié en Phase 8

## Configuration

- [ ] `.env` de préproduction créé à partir de `.env.example`, `APP_ENV=staging`, `APP_DEBUG=false`
- [ ] `APP_KEY` propre à la préproduction (jamais celle de la production)
- [ ] `CORS_ALLOWED_ORIGINS` contient l'origine exacte du portail de préproduction
- [ ] `CUSTOMER_PORTAL_URL` pointe vers le portail de préproduction (liens d'invitation, de réinitialisation, de suivi public) — le défaut est `http://localhost:5180`
- [x] `retry_after` 1900 s — défaut de `config/queue.php` depuis la Phase 8
- [ ] Connexions FTP/SFTP de test saisies dans le back-office (Intégrations → configuration → connexion), aucun identifiant réel de client
- [ ] Mail : `MAIL_MAILER` vers une boîte de capture (Mailpit…), jamais vers de vrais clients
- [ ] Portail construit avec `VITE_API_URL=https://<api-preprod>` (le build échoue sans)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan event:cache` sans erreur — fait (dev), voir rapport final

## Données

- [ ] Sauvegarde prise et **restaurée** sur la préproduction (procédure `customer-portal-backup-restore.md`) — restauration de la base testée en dev le 2026-10-10 ; fichiers `storage/app` non testés
- [ ] `php artisan migrate --force` : 10 migrations additives du portail appliquées, `migrate:status` sans « Pending »
- [ ] Clients de test SCHMIDT / GETAZ / IKEA / QOQA configurés **sans secret réel** (hôtes `*.example.invalid` ou bouchons)

## Infrastructure

- [ ] Vhost Nginx du portail : HTTPS, `try_files $uri /index.html`, cache long sur `/assets/*`, pas de cache sur `index.html`
- [ ] Vhost de l'API : HTTPS, HSTS, `client_max_body_size` ≥ taille d'import (5 Mo par défaut)
- [ ] Worker Supervisor (`--timeout=1800`, `numprocs=2`, `stopwaitsecs=3600` — `DEPLOYMENT_VPS.md` §5), cron `schedule:run`
- [ ] Certificats TLS valides

## Validation fonctionnelle

- [ ] `bash scripts/customer-portal-smoke.sh` avec `PORTAL_URL` et un compte lecteur → `SMOKE OK`
- [ ] Parcours manuels de `customer-portal-smoke-tests.md`
- [ ] Isolation : un utilisateur IKEA ne voit aucune commande, stock, réclamation, intégration QOQA (URL directe et API)
- [ ] Import QOQA Excel sur fichier de démonstration
- [ ] Relève Schmidt contre un bouchon REST (aucune API réelle)
- [ ] Envoi de statut Getaz contre un bouchon REST
- [ ] Lien de suivi public : création, consultation sans compte, révocation → 404
- [ ] Alertes A1 à A4 de `customer-portal-monitoring.md` déclenchées volontairement au moins une fois

## Décisions à obtenir avant la production

- [ ] Domaine du portail client et emplacement de publication (BLOCKER infra)
- [ ] Durées de conservation (`retention-decisions.md`, 8 points) — `BLOCKED_COMPLIANCE_DECISION`
- [x] Décisions ouvertes Phase 6 — levées le 2026-10-10 (connexion FTP en base, historique, index)
- [ ] Destinataires des alertes
