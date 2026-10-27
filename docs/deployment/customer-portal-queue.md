# File d'attente — portail client

La file Laravel exécute ce que le portail ne fait pas dans la requête HTTP.
Connexion : `QUEUE_CONNECTION=database` (table `jobs`, échecs dans `failed_jobs`).
Le projet n'utilise **ni Redis ni Horizon** ; ce document n'en introduit pas.

## Jobs concernés par le portail

| Job | Déclenché par | `$tries` | `$timeout` | Effet d'un échec |
|---|---|---|---|---|
| `ProcessUploadedImportJob` | import Excel/CSV/XML depuis le portail (`customer.integrations.import`) | 1 | 1800 s | import marqué en échec : résultat par jeton (24 h) **et** ligne `failed` dans l'historique ; aucun rejeu automatique |
| `RunCustomerPullIntegrationJob` | `customer-integrations:pull` (planificateur, toutes les heures) | `customer_integrations.pull.tries` | 600 s | erreur passagère (5xx, 429, délai) rejouée avec `backoff` ; erreur définitive (401, 403, JSON invalide) arrêtée sans boucle |
| `ProcessExportJob` | changement de statut d'une commande (flux d'envoi, ex. Getaz) | politique `ExportRetryPolicy` (`settings.retry`) | défaut | envoi `failed`, relançable depuis le portail (`customer.integrations.export_jobs.retry`) |
| `SendOrderCommunicationJob` | communications client (mails / SMS) | défaut | défaut | communication restée `queued` |

`RunCustomerPullIntegrationJob` est `ShouldBeUnique` : un même flux n'est jamais
relevé deux fois en parallèle.

## Réglage obligatoire : `retry_after` > plus long `$timeout`

Avec la connexion `database`, un job réservé depuis plus de `retry_after`
secondes est considéré comme perdu et **repris par un autre worker**, même s'il
tourne encore. La valeur par défaut est 90 s ; l'import du portail peut durer
1800 s.

```dotenv
DB_QUEUE_RETRY_AFTER=1900
```

**Corrigé en Phase 8** : 1900 s est désormais la valeur **par défaut** de
`config/queue.php` (avant : 90 s) — elle s'applique au prochain déploiement
sans toucher au `.env` du serveur. `QueueRetryAfterTest` échoue si un job
dépasse un jour cette valeur. Contrepartie : un job dont le worker a planté
n'est repris qu'après ~32 min.

La configuration Supervisor de `docs/DEPLOYMENT_VPS.md` est passée à
`--timeout=1800`, `numprocs=2` : **à appliquer sur le serveur** (accès sudo).

## Configuration Supervisor recommandée

Deux processus, pour qu'un import long ne bloque pas les mails pendant 30 min :

```ini
[program:tricolis-worker]
command=/usr/bin/php /var/www/tricolisba/artisan queue:work database --sleep=3 --tries=3 --timeout=1800 --max-time=3600
numprocs=2
stopwaitsecs=1900
; autres clés : identiques à docs/DEPLOYMENT_VPS.md
```

Avec deux processus, `DB_QUEUE_RETRY_AFTER=1900` devient **indispensable** :
sinon le second worker reprendrait un import en cours.

> Tous les jobs sont publiés sur la file `default`. Isoler les imports sur une
> file dédiée demanderait un `onQueue()` dans le code : **recommandation
> post-production**, non implémentée en Phase 8.

`--max-time` recycle le worker chaque heure (fuites mémoire, code déployé).
`stopwaitsecs` doit dépasser `--timeout` pour qu'un arrêt laisse finir le job.

## Après chaque déploiement

Le workflow lance `php artisan reload`, qui redémarre les workers proprement
(équivalent de `queue:restart`). Sans redémarrage, un worker garde l'ancien code.

## Surveillance

| Signal | Commande | Seuil d'alerte |
|---|---|---|
| Jobs en attente | `SELECT COUNT(*) FROM jobs` | > 100 pendant 10 min |
| Plus vieux job en attente | `SELECT MIN(created_at) FROM jobs WHERE reserved_at IS NULL` | > 15 min |
| Échecs | `php artisan queue:failed` / `SELECT COUNT(*) FROM failed_jobs` | toute nouvelle ligne |
| Worker vivant | `supervisorctl status tricolis-worker:*` | état autre que `RUNNING` |
| Envois en échec | back-office → Intégrations → envois `failed` | toute hausse |

## Rejouer ou purger

```bash
php artisan queue:failed                 # lister (aucune donnée sensible : payload sérialisé)
php artisan queue:retry <uuid>           # rejouer un job précis après correction
php artisan queue:forget <uuid>          # oublier un job qu'on ne veut pas rejouer
```

Ne pas lancer `queue:retry all` sur des imports : un import rejoué après un
succès partiel est protégé contre les doublons par la référence externe, mais
le rejeu doit rester une décision explicite.
Ne jamais lancer `queue:flush` en production sans sauvegarde de `failed_jobs`.

## Hors périmètre

Aucun broker externe, aucune métrique Prometheus : le projet n'en utilise pas.
