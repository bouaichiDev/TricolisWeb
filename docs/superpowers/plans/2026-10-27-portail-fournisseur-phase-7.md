# Portail fournisseur — Phase 7 : sécurité, audit, isolation

Consigne : `PortailFournisseur/Phase7_07_Securite_Audit_Permissions_Isolation.md`. Base : commit de la phase 6.

## Approche

Les contrôles d'isolation existent depuis les phases 1 à 6, chacun testé par ressource. La phase 7 ajoute ce qui
manquait pour les **garantir dans la durée** : un inventaire tiré du routeur réel, des balayages automatiques de
toutes les routes, un test d'IDOR croisé sans oracle, et la synthèse documentaire.

## Tâches

1. `ProviderRouteSecurity` (lecture du routeur : auth, contexte, permission, isolation, débit, lacune) et commande
   `provider-portal:route-matrix [--check|--output]` ; matrice générée `docs/provider-portal/security-route-matrix.md`.
2. `Security/ProviderRouteGuardsTest` : inventaire sans lacune ; 401 anonyme ; 403 jetons interne et client ;
   403 pour un membre qui n'a que l'accès au portail, sur chaque route à permission ; aucune route
   users/roles/permissions. Vérifié par mutation (route sans permission → rouge).
3. `Security/ProviderIdorTest` : Beta sur chaque route paramétrée avec les identifiants réels d'Alpha → même statut,
   message, code et erreurs qu'un identifiant inexistant. Vérifié par mutation (scope sans fournisseur → rouge).
4. Correctifs de la relecture de la phase 6.
5. `docs/provider-portal/security.md` : chaîne, tests, chemins indirects, contrôles transverses, audit,
   rétention (`BLOCKED_COMPLIANCE_DECISION`), constats ouverts, non-régression.
6. Suite complète, Playwright des deux portails, commit.
