# Portail fournisseur — Phase 8 : finalisation, E2E, CI, documentation, GO/NO-GO

Consigne : `PortailFournisseur/Phase8_08_Finalisation_E2E_Documentation_Deploiement.md`. Base : commit de la phase 7.
**Aucun déploiement, aucune fusion.**

## Tâches

1. **Démonstration** : `ProviderDemoOrder` porte deux services (S1, S2) ; `DemoProviderPortalProofSeeder` prépare
   DEMO-ALPHA-3 (en cours, preuve) et DEMO-ALPHA-4 (confirmée, sans ressource, parcours complet), remises à zéro à
   chaque semis. Test des seeders.
2. **E2E** :
   - `journey.spec.ts` — parcours 1 (chauffeur et véhicule créés, affectés, démarrage, arrivée, preuve, fin) et
     parcours 3 (tournée multi-clients : ni client ni commande à l'écran) ; parcours 2 (Beta refusé sur tournée,
     chronologie, documents, fichiers, décomptes d'Alpha).
   - `resilience.spec.ts` — parcours 4 : réseau coupé puis « Réessayer », session expirée ou révoquée.
   - Conflit d'affectation, ressource indisponible, transition interdite : couverts par Pest (`Tours/*`,
     `Resources/*`) et Vitest (refus affichés), non dupliqués en E2E.
   - Parcours 5 : `settlements.spec.ts` ; parcours 6 : suites back-office et portail client.
3. **CI** : `provider-portal` dans la matrice front (lint, typecheck, test, build) ; étape
   `provider-portal:route-matrix --check` ; `provider-portal/` exclu du rsync backend du déploiement.
4. **Documentation** : `api.md`, `user-guide.md`, `internal-guide.md`, `deployment.md`, `go-no-go.md` ; README.
5. **Vérifications** : Pest complet, Vitest des trois applications, typecheck, lint, builds, Playwright des deux
   portails, `pint`, matrices de routes.
6. Statut final : `PROVIDER_PORTAL_NOT_READY_FOR_PRODUCTION` tant que les prérequis de production ne sont pas
   vérifiés ; commit.
