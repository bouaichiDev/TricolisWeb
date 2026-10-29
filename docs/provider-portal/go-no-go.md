# Portail fournisseur — GO / NO-GO

## Statut : `PROVIDER_PORTAL_NOT_READY_FOR_PRODUCTION`

Le code des phases 1 à 8 est livré et vérifié localement, mais la mise en production n'est **pas** prête : plusieurs
prérequis d'infrastructure et de décision ne sont pas vérifiés. **Aucun déploiement n'a été fait.**

## Blocages

| # | Blocage | Pour lever |
|---|---|---|
| 1 | Aucun domaine ni vhost pour `provider-portal/` ; le workflow de déploiement ne le publie pas | choisir le domaine, ajouter build + publication + origine CORS + `PROVIDER_PORTAL_URL` |
| 2 | La fusion sur `main` **déclenche** le déploiement de production (migrations comprises) **sans sauvegarde préalable** | sauvegarde vérifiée et restauration testée avant toute fusion |
| 3 | CI jamais exécutée sur GitHub pour ce portail (aucun push) | pousser la branche, ouvrir une PR, CI verte |
| 4 | Protections de branche, secrets, environnements GitHub : non vérifiables d'ici | à contrôler dans les réglages du dépôt |
| 5 | Observabilité : aucune alerte (5xx, échecs de connexion, 429), aucune sonde du domaine du portail | à mettre en place (`deployment.md`) |
| 6 | Décisions ouvertes (ci-dessous) | arbitrage du propriétaire |

## Décisions ouvertes

- `BLOCKED_DESIGN_DECISION` : démarrage depuis `confirmed` (spécification) ou `planned` (référentiel des statuts) ;
  incidents d'exécution (aucun modèle) ; disponibilités des ressources ; communications adressées au fournisseur en
  tant qu'entité ; devise des décomptes.
- `BLOCKED_COMPLIANCE_DECISION` : conservation des preuves, signatures et photos (EXIF conservés), des journaux
  d'audit du portail, des comptes désactivés ; téléphone des chauffeurs dans l'audit.
- **ALTER à approuver** : index unique `(tour_stop_id, order_service_id)` sur les preuves ; `recipient_user_id` sur
  les communications ; provenance des preuves.
- **Back-office** : décompte `closed` encore modifiable ; `hasOrganizationAccess` ignore le statut de l'appartenance ;
  `TourScopeGuard` accepte des ressources inactives.

## Vérifié (local, 2026-10-28/29)

| Contrôle | Résultat |
|---|---|
| Pest complet (back-office, portail client, portail fournisseur) | 2521/2521 |
| Vitest | back-office 839/839, portail client 118/118, portail fournisseur 63/63 |
| Lint, typecheck, build | verts pour les trois applications (bundle fournisseur 514 Ko, avertissement de taille) |
| Playwright fournisseur | 17/17 (parcours complet, isolation, preuves, décomptes, réseau coupé, session expirée) |
| Playwright client | 16/16 (non-régression) |
| `provider-portal:route-matrix --check` | 45 routes, toutes protégées |
| `customer-portal:route-matrix --check` | 92 routes externes, toutes autorisées |
| `pint --test` | vert |
| Seeders | `ProviderPortalSeeder` rejouable ; seeders `Demo*` sans effet hors `local`/`testing` (testé) |
| Migrations | 3 créations de tables avec `down()` ; aucune table existante modifiée |

## Matrice E2E (consigne phase 8)

| Parcours | Couverture |
|---|---|
| 1. Connexion → tableau de bord → chauffeur → véhicule → affectation → démarrage → arrêt → preuve → fin | `journey.spec.ts` |
| 2. Beta sur les ressources d'Alpha, fichiers compris | `journey.spec.ts`, `tours.spec.ts`, `resources.spec.ts`, `isolation.spec.ts` ; toutes routes : `ProviderIdorTest` |
| 3. Tournée multi-clients : nécessaire visible, étranger masqué | `journey.spec.ts` ; liste blanche des clés : `ProviderToursTest` |
| 4. Ressource indisponible, conflit, transition interdite | Pest `Tours/*`, `Resources/*` ; refus affichés : Vitest |
| 4. Jeton expiré, réseau interrompu | `resilience.spec.ts` |
| 5. Décomptes en lecture seule | `settlements.spec.ts`, `Finance/ProviderSettlementsTest` |
| 6. Back-office et portail client verts | Pest complet, Vitest, Playwright client |

## Pour passer à GO

Lever les blocages 1 à 5, arbitrer les décisions ouvertes, puis suivre `deployment.md` **avec approbation explicite**.
