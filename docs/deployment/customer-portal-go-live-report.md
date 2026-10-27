# Go / No-Go — portail client (Phase 8, §63)

Date : 2026-10-10. Branche : `feature/customer-portal-phase-8-finalization`
(base `a52dd1e`, non commitée à ce jour). Évaluation faite depuis
l'environnement de développement : **aucun accès** à la production, ni à une
préproduction (qui n'existe pas).

## Décision

```text
CUSTOMER_PORTAL_NOT_READY_FOR_PRODUCTION
```

Le code est prêt (tests verts, isolation vérifiée, build valide), mais **deux
BLOCKERS d'infrastructure** sont ouverts : le portail n'a aucun mécanisme de
déploiement, et aucune sauvegarde n'est prise avant les migrations de
production. La spécification interdit de conclure READY dans ce cas.

## Problèmes ouverts

| # | Sévérité | Problème | Pourquoi ce n'est pas corrigé ici | Pour lever |
|---|---|---|---|---|
| B1 | **BLOCKER** | Le portail client n'est déployé par aucun mécanisme : ni domaine, ni vhost, ni étape de publication de `customer-portal/dist` dans `deploy-production.yml` | domaine et emplacement de publication sont une décision d'infrastructure ; « ne jamais déployer automatiquement en production sans mécanisme déjà autorisé » | choisir le domaine ; vhost Nginx (fallback SPA) ; étape rsync du `dist` dans le workflow ; `CORS_ALLOWED_ORIGINS`, `CUSTOMER_PORTAL_URL`, `VITE_API_URL` |
| B2 | **BLOCKER** | Aucune sauvegarde avant `migrate --force` (10 migrations du portail à appliquer) ; aucune sauvegarde planifiée sur le serveur | demande un accès serveur et un stockage hors machine | appliquer `customer-portal-backup-restore.md` ; tester la restauration (base **et** fichiers) en préproduction |
| M1 | MAJOR | Workflow CI modifié en Phase 8 (tests avant déploiement) mais **jamais exécuté** sur GitHub | pas d'exécution GitHub Actions depuis ce poste | ouvrir la PR : les jobs `backend` et `frontend` doivent être verts |
| M2 | MAJOR | Aucune préproduction | infrastructure | `customer-portal-preproduction-checklist.md` |
| M3 | MAJOR | File : la configuration Supervisor du serveur (`--timeout=120`, un seul worker) n'a pas encore été mise à jour — `retry_after` est corrigé dans le code | édition de `/etc/supervisor` : accès sudo | appliquer le bloc de `docs/DEPLOYMENT_VPS.md` §5 (`reread`, `update`) |
| M4 | MAJOR | Alertes non branchées (aucun destinataire, aucun outil) | choix d'équipe | alertes A1–A4 de `customer-portal-monitoring.md` |
| M5 | MAJOR | Durées de conservation (données personnelles, audit, journaux, sauvegardes) non décidées | `BLOCKED_COMPLIANCE_DECISION` — aucune durée inventée | `retention-decisions.md`, 8 décisions |
| M6 | MAJOR | Journal en canal `single`, sans rotation | lié à M5 | `logrotate` système en attendant |
| M7 | — | ~~Décisions Phase 6 ouvertes~~ — **levées le 2026-10-10** : connexion FTP/SFTP en base (chiffrée), historique `customer_import_runs`, index `orders(customer_id, external_reference)` | modification de table : accord préalable requis | décision produit + ALTER validé |
| m1 | MINOR | `laravel/framework` < 13.30 : XSS dans la page de débogage (GHSA-jh5r-qr3c-85q8, gravité faible), inactive avec `APP_DEBUG=false` ; `npm audit` du portail : 0 vulnérabilité | montée de version hors périmètre de finalisation | `composer update laravel/framework` dans une PR dédiée |
| m2 | MINOR | Fuseau par défaut d'une organisation incohérent (`Europe/Paris` / `Africa/Casablanca`), champ non utilisé par le portail | sans effet visible | unifier le jour où le champ sert |
| m3 | MINOR | 3 avertissements oxlint `only-export-components` (composants UI générés) | confort de rechargement à chaud seulement | — |
| i1 | INFO | Parcours E2E non automatisés en navigateur : relève Schmidt, envoi Getaz, preuve de livraison, compte désactivé en cours de session | couverts par l'API | `phase-8-e2e-matrix.md` |

## Corrigé pendant la Phase 8

| Sévérité avant | Problème | Correction |
|---|---|---|
| CRITICAL | Le workflow déployait en production à chaque push sur `main` **sans aucun test** | jobs `backend` (Pint, matrice de routes, Pest) et `frontend` (lint, typecheck, Vitest, build) ; `deploy` en dépend ; PR testées sans déploiement |
| MAJOR | Catalogue annoncé en Phase 1, jamais livré (entrée de menu désactivée) | 4 routes, 2 écrans, tests, E2E |
| MAJOR | Le portail de production ne pouvait pas appeler l'API : origines CORS figées dans le code | `CORS_ALLOWED_ORIGINS` ; `localhost` retiré en production |
| MAJOR | Un build sans `VITE_API_URL` appelait `localhost:8000` depuis le navigateur du client | le build de production échoue sans `VITE_API_URL` |
| MAJOR | Erreurs du périmètre externe : classe interne dans les 404, SQL et trace dans les 500 en débogage | `ExternalApiErrors` : 404 et 500 génériques, quel que soit `APP_DEBUG` |
| MAJOR | `/up` ne vérifiait que le démarrage | base, stockage et cache vérifiés |
| MAJOR | Ajout d'un article sans poids/volume → erreur SQL (trouvé par l'E2E) | colonne laissée à son défaut |
| MINOR | Date seule affichée la veille à l'ouest de Greenwich | construction en heure locale + tests de fuseau |
| MAJOR | File : `retry_after` 90 s, sous l'import de 1800 s → double exécution possible | `retry_after` 1900 s par défaut (`config/queue.php`) + `QueueRetryAfterTest` |
| MINOR | Chunk JS principal de 803 ko | 472 ko : pages chargées à la demande |
| MINOR | OpenAPI : `customerId` présenté comme filtre, filtres ignorés de l'API par clé, `X-Api-Key` absent | transformateur `DocumentExternalPerimeter` + test |
| MINOR | `.env.example` sans les variables du portail et des intégrations | complété, sans secret |

## Vérifié

| Contrôle | Résultat |
|---|---|
| Fuite inter-client | aucune : chaque route à identifiant testée avec une ressource QOQA (`PortalCrossCustomerIsolationTest`) + E2E `isolation` |
| Fuite inter-organisation | aucune : scopes client **et** organisation (`PortalCustomerIsolationTest`, organisation suspendue → portail fermé) |
| Authentification | 401 sans jeton, jeton du back-office refusé, jeton expiré ou révoqué refusé |
| Suivi public | aucune donnée interne, même 404 pour tout jeton inutilisable, débit limité |
| Secrets dans Git | aucun (`.env*` réels ignorés ; exemples sans valeur) |
| Migrations | 10, toutes additives ; aucune destructive |
| Build production | backend : caches `config` / `route` / `event` générés ; portail : build OK, sans `localhost` |
| Restauration de base | testée sur copie de développement : 97 tables, comptages identiques |
| Tests | voir [`phase-8-regression-report.md`](../customer-portal/phase-8-regression-report.md) |

## Pour passer à READY

1. Lever B1 et B2.
2. Exécuter la checklist de préproduction, dont les smoke tests.
3. Obtenir M1 (CI verte sur la PR).
4. Mettre à jour ce rapport : nouvelle décision, date, signataire.
