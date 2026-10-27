# Retour arrière (rollback) — portail client

## Comment le déploiement actuel fonctionne

`deploy-production.yml` (push sur `main`, après les jobs `backend` et `frontend`
verts) : `rsync --delete` du code **sur place** dans `/var/www/tricolisba`,
`composer install --no-dev`, `migrate --force`, caches, `reload`.

Conséquence : **il n'y a pas de dossier de release précédente** sur le serveur.
Revenir en arrière, c'est redéployer un commit antérieur.

> Le workflow ne déploie pas encore le portail client (`customer-portal/dist`) :
> domaine et emplacement de publication non définis (BLOCKER d'infrastructure,
> voir go-live report). La procédure ci-dessous s'applique au backend et, une
> fois ce point tranché, au portail.

## Décider

| Symptôme après déploiement | Action |
|---|---|
| `/up` en échec, 500 généralisées | rollback du code immédiatement |
| Une fonction du portail cassée, le reste sain | correctif rapide (`fix/…` → PR → CI) si < 1 h, sinon rollback |
| Fuite de données entre clients, faille d'authentification | **désactiver l'accès** (voir plus bas) puis rollback |
| Migration en échec à mi-course | ne rien relancer ; diagnostiquer (`migrate:status`), restaurer la sauvegarde si l'état est incohérent |

## Rollback du code

```bash
git revert --no-edit <sha_du_merge_fautif>     # jamais de push --force sur main
git push origin main                            # relance CI puis déploiement
```

Le `revert` passe par la même CI que tout autre changement : tests et build
doivent être verts, ce qui protège d'un rollback vers un état lui-même cassé.

En urgence, sans attendre la CI (accès SSH requis, à tracer dans le runbook) :

```bash
cd /var/www/tricolisba
git -C <clone_de_travail> archive <sha_sain> | tar -x -C /var/www/tricolisba   # ou rsync depuis un clone propre
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan event:cache && php artisan view:cache
php artisan reload
```

## Migrations : ne pas les annuler en production

Les 10 migrations du portail sont **additives** (6 tables créées ; 4 colonnes nullables et 1 index ajoutés) :

| Migration | Effet | `down()` |
|---|---|---|
| `2026_09_27_100000_create_customer_users_table` | table | supprime la table |
| `2026_09_27_100001_create_customer_user_roles_table` | table | supprime la table |
| `2026_09_27_100002_create_customer_user_invitations_table` | table | supprime la table |
| `2026_09_28_100000_add_default_agency_to_customers_table` | colonne nullable | supprime la colonne |
| `2026_09_29_100000_create_public_tracking_links_table` | table | supprime la table |
| `2026_09_30_100000_add_color_to_statuses_table` | colonne nullable | supprime la colonne |
| `2026_09_30_120000_create_stock_adjustment_requests_table` | table | supprime la table |
| `2026_10_10_100000_add_file_connection_to_customer_import_configurations_table` | 2 colonnes nullables (connexion FTP/SFTP) | supprime les connexions enregistrées |
| `2026_10_10_100001_create_customer_import_runs_table` | table (historique des imports) | supprime la table |
| `2026_10_10_100002_add_customer_external_reference_index_to_orders_table` | index | supprime l'index |

L'ancien code ignore ces tables et colonnes : **un rollback du code ne demande
pas de rollback de schéma**. `migrate:rollback` détruirait les comptes du
portail, les invitations et les liens publics créés depuis : ne jamais le lancer
en production. `migrate:fresh`, `migrate:reset` et `db:wipe` sont interdits.

Si une donnée doit être retrouvée : restaurer la sauvegarde **dans une base à
part** et récupérer les lignes utiles (voir `customer-portal-backup-restore.md`).

## Couper l'accès sans rollback

| Besoin | Moyen existant |
|---|---|
| Fermer le portail d'un client | back-office → client → statut inactif (`EnsureCustomerPortalContext` refuse à la requête suivante) |
| Fermer un module | désactiver `catalog_enabled` / `stock_enabled` / `tracking_enabled` du client |
| Couper un utilisateur | désactiver son accès au portail ou son compte |
| Révoquer toutes les sessions du portail | `DELETE FROM personal_access_tokens WHERE name = 'customer-portal'` (les utilisateurs se reconnectent) |
| Couper une clé API client | back-office → clés API → désactiver |
| Couper un flux d'intégration | back-office → Intégrations → désactiver le flux |
| Tout le site | `php artisan down --secret=<jeton>` (maintenance) |

## Après un rollback

1. `curl -f https://<api>/up` → 200.
2. Smoke tests (`customer-portal-smoke-tests.md`).
3. `php artisan queue:failed` : rejouer seulement ce qui doit l'être.
4. Consigner dans le go-live report : heure, cause, commit revenu, données touchées.
