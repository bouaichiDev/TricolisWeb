# Supervision et alertes — portail client

Le projet n'embarque **aucun outil de supervision** (ni Sentry, ni Prometheus,
ni APM) et la Phase 8 n'en ajoute pas (hors périmètre : « nouveau fournisseur
monitoring payant »). Ce document décrit les signaux **qui existent déjà** et
comment les surveiller avec des moyens simples (cron + mail, ou l'outil que
l'équipe choisira).

## Signaux disponibles

| Signal | Source | Ce qu'il dit |
|---|---|---|
| Santé applicative | `GET /up` | 200 si l'application démarre **et** si la base (`select 1`), le stockage local (écriture/suppression) et le cache (écriture/lecture) répondent ; 500 sinon, cause dans le journal (`Health check failed: <composant>`) |
| Journal applicatif | `storage/logs/laravel.log` (canal `stack` → `single`) | erreurs, exceptions ; secrets masqués par `RedactSensitiveContext` |
| Journal du worker | `storage/logs/worker.log` (Supervisor) | jobs traités, échecs |
| Jobs échoués | table `failed_jobs`, `php artisan queue:failed` | imports, envois, communications en échec définitif |
| File en attente | table `jobs` | retard de traitement |
| Envois aux clients | table `export_jobs` (statut `failed`) | flux sortants (statuts Getaz…) en échec |
| Historique des imports | table `customer_import_runs` (statuts `failed`, `rejected`, `completed_with_errors`) ; écrans « Historique » du portail et du back-office | flux entrants (Schmidt REST, IKEA SFTP, dépôts QOQA) en échec ou partiels |
| Audit | `audit_logs`, actions `customer_portal.*` | connexions, refus, actions sensibles du portail |
| Suivi public | `public_tracking_links.access_count`, `last_access_at` | usage et abus éventuel des liens publics |
| Limiteurs de débit | réponses 429 dans le journal d'accès Nginx | tentatives de force brute (connexion, clés API, suivi public) |

## Alertes critiques (à mettre en place avant le go-live)

| # | Alerte | Condition | Sévérité | Première action |
|---|---|---|---|---|
| A1 | API indisponible | `/up` ≠ 200 deux fois de suite à 1 min d'intervalle | critique | runbook § « API en erreur » |
| A2 | Worker arrêté | `supervisorctl status` ≠ `RUNNING`, ou plus vieux job en attente > 15 min | critique | redémarrer le worker, lire `worker.log` |
| A3 | Planificateur arrêté | aucune ligne `schedule:run` dans syslog depuis 5 min | majeure | vérifier `crontab -l` de l'utilisateur de déploiement |
| A4 | Jobs échoués | nouvelle ligne dans `failed_jobs` | majeure | `queue:failed`, identifier le flux |
| A5 | Flux client muet | flux automatique actif sans exécution réussie depuis 2 h | majeure | historique du flux, joignabilité du système client |
| A6 | Envois en échec | hausse des `export_jobs` en `failed` sur 1 h | majeure | message d'erreur de l'envoi, relance depuis le back-office |
| A7 | Erreurs 5xx | > 1 % des réponses `api/v1/customer/*` en 5xx sur 5 min (journal Nginx) | critique | `laravel.log` |
| A8 | Force brute | > 50 réponses 429 sur `auth/login` en 10 min | majeure | identifier l'adresse, bloquer au pare-feu si besoin |
| A9 | Disque | `storage/` > 80 % | majeure | journaux (rotation), fichiers d'import non purgés |
| A10 | Certificat TLS | expiration < 14 jours | majeure | renouveler (certbot) |

### Exemple minimal sans outil tiers (cron toutes les minutes)

```bash
#!/usr/bin/env bash
# /usr/local/bin/tricolis-healthcheck — envoie un mail au deuxième échec consécutif.
STATE=/var/tmp/tricolis-health.fail
if curl -fsS --max-time 10 https://<api>/up >/dev/null; then rm -f "$STATE"; exit 0; fi
if [ -f "$STATE" ]; then echo "/up en échec depuis $(cat "$STATE")" | mail -s "[Tricolis] API indisponible" <équipe>; fi
date -u +%FT%TZ > "$STATE"
```

Le destinataire et le canal (mail, Slack…) sont à choisir par l'équipe :
**non configurés à ce jour**.

## Journaux

- Canal actuel : `single` → un fichier qui grossit sans fin. Passer au canal
  `daily` (rotation) est une décision de conservation :
  `BLOCKED_COMPLIANCE_DECISION` (voir `retention-decisions.md`). En attendant,
  `logrotate` côté système (copytruncate) évite de remplir le disque sans
  décider de durée applicative.
- `LOG_LEVEL` : `warning` ou `error` en production, jamais `debug`
  (`.env.example` contient `debug` pour le développement).
- Ce qui ne doit jamais apparaître : mots de passe, jetons, clés API, en-têtes
  `Authorization`, corps de réponse des systèmes clients. Le masquage
  (`SensitiveData::redact`, `RedactSensitiveContext`) est testé
  (`PortalAuditRedactionTest`, `SecurityHelpersTest`, `RestPullIntegrationTest`).

## Contrôle quotidien (5 min)

```bash
php artisan queue:failed | head
mysql -e "SELECT COUNT(*) FROM jobs" tricolisweb
grep -c ' ERROR' storage/logs/laravel.log
df -h /var/www
```
