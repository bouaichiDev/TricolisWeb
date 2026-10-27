# Planificateur — portail client

Le planificateur Laravel (`routes/console.php`) est lancé chaque minute par cron :

```cron
* * * * * cd /var/www/tricolisba && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Fuseau : `config/app.php` → `timezone = UTC`. Les heures ci-dessous sont donc
des heures **UTC** (`daily()` = minuit UTC = 01:00 ou 02:00 à Lausanne/Paris).
Voir [`phase-8-timezone-strategy.md`](../customer-portal/phase-8-timezone-strategy.md).

## Tâches

Relevé `php artisan schedule:list` (Phase 8) :

| Expression | Commande | Rôle portail | Chevauchement |
|---|---|---|---|
| `* * * * *` | `communications:process-scheduled` | met en file les communications programmées échues | `withoutOverlapping` |
| `0 * * * *` | `customer-integrations:pull` | met en file une passe de chaque import automatique actif (REST pull — Schmidt —, FTP/SFTP XML — IKEA) | `withoutOverlapping` |
| `0 * * * *` | `customer-integrations:prune-uploads` | supprime les fichiers d'import prévisualisés dont le jeton (30 min) a expiré | `withoutOverlapping` |
| `0 0 * * *` | `sanctum:prune-expired --hours=24` | supprime les jetons du portail échus depuis plus de 24 h | `withoutOverlapping` |

`withoutOverlapping` s'appuie sur le cache (`CACHE_STORE=database`) : un seul
serveur exécute le planificateur, `onOneServer()` n'est donc pas nécessaire.
S'il fallait un jour plusieurs serveurs, ajouter `onOneServer()` à chaque tâche.

## Ce que le planificateur ne fait pas

- **Il n'exécute pas** les relèves : il les **met en file**. Sans worker
  (`queue:work`), les imports automatiques restent en attente — voir
  [`customer-portal-queue.md`](customer-portal-queue.md).
- Il ne purge ni les journaux d'audit, ni les documents, ni les traces d'accès
  au suivi public : leur durée de conservation est une
  `BLOCKED_COMPLIANCE_DECISION` (voir
  [`retention-decisions.md`](../customer-portal/retention-decisions.md)).
  Aucune durée n'a été inventée.

## Lancer une tâche à la main

```bash
php artisan customer-integrations:pull --configuration=<id>   # un seul flux, sans attendre l'heure
php artisan customer-integrations:prune-uploads
php artisan schedule:list                                       # vérifier l'enregistrement
```

`customer-integrations:pull` ne fait que publier des jobs : la commande rend la
main tout de suite, le résultat se lit dans l'historique du flux.

## Après un déploiement

Le workflow lance `php artisan schedule:interrupt` : une tâche longue en cours
s'arrête proprement et la minute suivante reprend avec le nouveau code.

## Surveillance

| Signal | Comment | Alerte |
|---|---|---|
| Cron vivant | `grep CRON /var/log/syslog \| grep schedule:run \| tail` (aucune trace applicative dédiée n'existe) | aucune exécution depuis 5 min |
| Relève horaire | dernière exécution d'un flux automatique (portail ou back-office → Intégrations → « Dernière exécution ») | flux actif sans relève depuis 2 h |
| Verrou bloqué | `SELECT * FROM cache_locks WHERE \`key\` LIKE '%schedule%'` | verrou plus vieux que 24 h (tâche tuée sans libérer) |

Un verrou `withoutOverlapping` survivant à un crash expire après 24 h. Pour le
libérer plus tôt : `php artisan schedule:clear-cache`.

## Recommandation (hors périmètre Phase 8)

Ajouter un « heartbeat » externe (ping d'URL à chaque passe) si un service de
supervision est retenu : aucun n'est configuré dans le projet aujourd'hui.
