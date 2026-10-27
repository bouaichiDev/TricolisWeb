# Runbook — portail client

Pour l'astreinte. Chaque section : symptôme → vérifier → agir. Les commandes
supposent `cd /var/www/tricolisba` et l'utilisateur de déploiement.

Documents liés : [file](customer-portal-queue.md) ·
[planificateur](customer-portal-scheduler.md) ·
[supervision](customer-portal-monitoring.md) ·
[sauvegarde](customer-portal-backup-restore.md) ·
[rollback](customer-portal-rollback.md) ·
[smoke tests](customer-portal-smoke-tests.md).

## Architecture déployée

```text
Navigateur ──HTTPS──> Nginx ─┬─> portail React (fichiers statiques, fallback index.html)
                             └─> Laravel (PHP-FPM) ──> MySQL
                                        │            └─> storage/app/private (documents, POD, imports)
                                        ├─> file `database` <── worker Supervisor ──> systèmes clients (REST, SFTP)
                                        └─> cron `schedule:run` (relèves horaires, purge des imports, jetons)
```

Pas de Redis, pas de S3, pas de serveur de mail dédié dans le dépôt.

## API en erreur (`/up` ≠ 200, 500 généralisées)

1. `tail -n 100 storage/logs/laravel.log` — chercher `Health check failed: database|storage|cache`.
2. **database** : `systemctl status mysql`, place disque, identifiants dans `.env`.
3. **storage** : droits sur `storage/app` (propriétaire `www-data`), disque plein (`df -h`).
4. **cache** : `CACHE_STORE=database` → même cause que database ; table `cache` présente ?
5. Après un déploiement : `php artisan config:cache` oublié ou `.env` modifié sans recache →
   `php artisan config:cache && php artisan reload`.
6. Si non résolu en 15 min : [rollback](customer-portal-rollback.md).

## Un client ne peut plus se connecter

| Ce que voit le client (en substance) | Cause probable | Vérifier |
|---|---|---|
| identifiants refusés | mot de passe | proposer « Mot de passe oublié » |
| refus après identifiants corrects | compte global inactif, accès portail désactivé, client inactif | back-office → client → utilisateurs du portail |
| portail indisponible | organisation du transporteur suspendue ou fermée | back-office → organisation → statut |
| 429 | trop d'essais depuis la même adresse | attendre la fenêtre du limiteur (1 min) |
| choix du client demandé | compte rattaché à plusieurs clients | normal ; choisir le client |

Ne jamais réinitialiser un mot de passe à la place du client : utiliser
l'invitation ou la réinitialisation par mail.

## Un client voit une donnée qui n'est pas à lui

**Incident de sécurité, priorité maximale.**

1. Couper l'accès : désactiver le client concerné (back-office), ou
   `php artisan down` si l'origine est inconnue.
2. Révoquer les sessions : `DELETE FROM personal_access_tokens WHERE name = 'customer-portal';`
3. Conserver les preuves : `audit_logs` (actions `customer_portal.*`), journal Nginx, `laravel.log`.
4. Rollback vers le dernier commit sain.
5. Reproduire en local avec un test d'isolation (`tests/Feature/Api/V1/CustomerPortal/Security/PortalCrossCustomerIsolationTest.php`).
6. Informer le responsable du traitement (obligations RGPD/nLPD : décision hors technique).

## Import automatique muet (Schmidt REST, IKEA SFTP)

1. Le flux est-il actif ? back-office → Intégrations.
2. Le planificateur tourne-t-il ? `php artisan schedule:list`, syslog `schedule:run`.
3. Le worker tourne-t-il ? `supervisorctl status tricolis-worker:*`.
4. Erreur du flux : back-office → configuration → « Historique des exécutions » (statut, message, commandes refusées ; jamais de secret).
   - 401/403 / authentification FTP refusée : identifiants du client changés →
     les mettre à jour dans la connexion de la configuration (back-office,
     mot de passe chiffré), **jamais** dans `.env` ni dans Git.
   - 5xx/429/délai : rejoué automatiquement (`tries`, `backoff`) ; si persistant, prévenir le client.
   - « hôte interne refusé » : protection SSRF ; l'adresse doit être publique ou
     listée dans `CUSTOMER_INTEGRATIONS_ALLOWED_PRIVATE_HOSTS` (décision sécurité).
5. Relancer un flux sans attendre l'heure : `php artisan customer-integrations:pull --configuration=<id>`.

## Import de fichier (QOQA Excel) bloqué « en cours »

1. `SELECT COUNT(*) FROM jobs;` — le job attend-il ?
2. Worker arrêté → le redémarrer ; le job repart.
3. L'historique (portail et back-office) montre l'échec ; le détail technique
   est dans `failed_jobs` / `laravel.log`. L'import n'est **pas** rejoué
   automatiquement (`tries = 1`) : le client redépose le fichier, les commandes
   déjà créées sont reconnues comme doublons et ignorées.
4. Vérifier que `DB_QUEUE_RETRY_AFTER`, s'il est défini dans `.env`, reste ≥ 1900 (défaut du code : 1900).

## Envoi de statuts (Getaz) en échec

1. back-office → Intégrations → envois `failed` : code HTTP et message.
2. 4xx : jamais relancé automatiquement ; corriger la configuration avec le client.
3. 5xx : relance selon `settings.retry` ; sinon « Relancer » (back-office ou portail,
   permission `customer.integrations.export_jobs.retry`).

## Lien de suivi public abusé

1. `SELECT order_id, access_count, last_access_at FROM public_tracking_links ORDER BY access_count DESC LIMIT 10;`
2. Révoquer le lien depuis la commande (portail ou back-office) : le jeton répond aussitôt 404.
3. Le limiteur `public-tracking` freine déjà par adresse ; bloquer au pare-feu si besoin.

## Clé API client compromise

back-office → clés API du client → désactiver, puis en créer une nouvelle et la
transmettre au client par un canal sûr. La clé n'est stockée qu'en empreinte :
elle ne peut pas être « retrouvée ».

## Disque plein

`du -sh storage/logs storage/app/private/*` — journaux en canal `single` sans
rotation, fichiers d'import non purgés (`customer-integrations:prune-uploads`
tourne-t-il ?). Ne supprimer **aucun** document client sans décision de
conservation.

## Commandes interdites en production

`migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `db:seed`
(données de démonstration), `queue:flush` sans sauvegarde, `git push --force`.
