# Sauvegarde et restauration — portail client

## Constat Phase 8

- **Aucune sauvegarde n'est prise aujourd'hui** : ni par le workflow
  `deploy-production.yml` (qui lance `migrate --force` directement), ni par une
  tâche planifiée, ni dans `docs/DEPLOYMENT_VPS.md`.
- La spécification classe « backup non disponible avant changement risqué » en
  **BLOCKER**. Le premier déploiement du portail ajoute 10 migrations : c'est un
  changement à couvrir par une sauvegarde.

Ce document décrit la procédure ; sa mise en place sur le serveur (cron,
stockage hors machine) demande un accès et une décision d'infrastructure qui
ne sont pas dans le dépôt.

## Ce qu'il faut sauvegarder

| Élément | Où | Pourquoi |
|---|---|---|
| Base MySQL `tricolisweb` | serveur MySQL | commandes, comptes du portail, rôles, audit, liens publics, flux |
| Fichiers privés | `storage/app/private` (disque `local`) | documents, BL, preuves de livraison, pièces de réclamation, imports en cours |
| Fichiers publics | `storage/app/public` | logos d'organisation |
| `.env` de production | `/var/www/tricolisba/.env` | `APP_KEY` (sans elle, les données chiffrées — secrets des flux — sont illisibles), accès BDD, mail |

> `.env` contient des secrets : sa copie va dans un coffre (gestionnaire de
> mots de passe de l'équipe), **jamais** dans Git ni dans la même archive que
> les dumps.

## Sauvegarde

```bash
# 1. Base : instantané cohérent sans verrouiller les tables InnoDB
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
mysqldump --single-transaction --routines --triggers --hex-blob --no-tablespaces \
  -u <user> -p tricolisweb | gzip > /var/backups/tricolis/db-$STAMP.sql.gz

# 2. Fichiers
tar -czf /var/backups/tricolis/storage-$STAMP.tar.gz -C /var/www/tricolisba storage/app

# 3. Empreintes, pour vérifier la copie distante
sha256sum /var/backups/tricolis/*-$STAMP.* > /var/backups/tricolis/$STAMP.sha256
```

Copier ensuite les trois fichiers **hors du serveur** (autre machine, stockage
de l'hébergeur) : une sauvegarde sur le même disque ne protège pas d'une perte
du VPS.

**Avant chaque déploiement qui contient une migration**, prendre une sauvegarde
manuelle et noter son horodatage dans la checklist de production.

Fréquence et durée de conservation des sauvegardes : `BLOCKED_COMPLIANCE_DECISION`
(voir [`retention-decisions.md`](../customer-portal/retention-decisions.md)).
Aucune durée n'est fixée ici.

## Restauration

Toujours restaurer **d'abord dans une base neuve**, jamais par-dessus la base
de production :

```bash
mysql -u <user> -p -e "CREATE DATABASE tricolisweb_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c db-<STAMP>.sql.gz | mysql -u <user> -p tricolisweb_restore
DB_DATABASE=tricolisweb_restore php artisan migrate:status   # aucune « Pending » attendue
```

Puis, après vérification, basculer `DB_DATABASE` dans `.env`,
`php artisan config:cache`, `php artisan reload`.
Les fichiers : `tar -xzf storage-<STAMP>.tar.gz -C /var/www/tricolisba`, puis
`chown -R www-data:www-data storage/app`.

## Test de restauration — exécuté le 2026-10-10 (hors production)

Fait sur la base de **développement** (aucun accès à la production depuis cet
environnement) :

| Étape | Résultat |
|---|---|
| `mysqldump --single-transaction` de `tricolisweb` | OK — 6,5 Mo, 2 s |
| Restauration dans une base neuve `tricolisweb_restore_check` | OK — 12 s |
| Tables | 97 / 97 |
| Lignes, table par table (`COUNT(*)`) | identiques sur les 97 tables (dont `orders` 1988, `audit_logs` 534, `migrations` 121) |
| `migrate:status` sur la base restaurée | 121 migrations « Ran », 0 « Pending » |
| Application branchée sur la base restaurée | `/customer/context`, `/customer/orders`, `/customer/catalog/items` → 200 |
| Nettoyage | base temporaire supprimée, dump effacé |

**Non testé** : la restauration des fichiers `storage/app`, et toute
restauration sur le serveur de production. Ces deux tests restent à faire en
préproduction (checklist).

## Vérifier une sauvegarde sans restaurer

```bash
sha256sum -c <STAMP>.sha256
gunzip -t db-<STAMP>.sql.gz && zcat db-<STAMP>.sql.gz | tail -1   # « -- Dump completed »
```
