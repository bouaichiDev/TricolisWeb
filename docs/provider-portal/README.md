# Portail fournisseur

Spécification : `docs/superpowers/specs/2026-10-27-portail-fournisseur-design.md`.
Plans : `docs/superpowers/plans/2026-10-27-portail-fournisseur-phase-1.md` à `phase-8.md`.

**Statut : `PROVIDER_PORTAL_NOT_READY_FOR_PRODUCTION`** — voir `go-no-go.md`. Aucun déploiement effectué.

| Document | Pour qui |
|---|---|
| `user-guide.md` | les fournisseurs |
| `internal-guide.md` | l'équipe interne (accès, attribution des tournées) |
| `api.md` | les intégrateurs |
| `access-matrix.md`, `security-route-matrix.md` (généré), `security.md` | sécurité, audit |
| `deployment.md`, `go-no-go.md` | exploitation, mise en production |

## Ce qui est livré

**Phase 8 — finalisation** : parcours E2E complet et scénarios d'erreur, CI étendue au portail (lint, typecheck,
tests, build, matrice des routes), documentation utilisateur, interne, API, exploitation, grille GO/NO-GO.

**Phase 7 — sécurité** : inventaire des routes tiré du routeur (`provider-portal:route-matrix`), balayages
automatiques de toutes les routes, IDOR croisé sans oracle, `security.md`.

**Phase 6 — communications et décomptes, en lecture seule** : les communications parties vers l'adresse du
membre connecté (canal externe, envoyées), et les décomptes **clôturés** du fournisseur avec leurs lignes
(service par son numéro) et leurs documents. Aucune route d'écriture ; montants du module financier.

**Phase 5 — suivi, documents, preuves** : chronologie réelle d'une tournée et carte (arrêts géolocalisés,
dernière position enregistrée), documents d'exécution téléchargeables par le jeton, preuves de livraison
(signature et photo) saisies aux arrêts atteints. Incidents : `BLOCKED_DESIGN_DECISION` (aucun modèle
d'incident n'existe ; un événement de suivi n'en tient pas lieu sans décision).

**Phase 4 — exécution** : démarrer et terminer une tournée, faire avancer ses arrêts (arrivé, terminé,
passé avec motif), onglets « À effectuer / En cours / Terminées ».

**Phase 3 — tournées, affectation, calendrier** : tournées attribuées (hors brouillon), détail
opérationnel sans donnée commerciale, affectation contrôlée du chauffeur et du véhicule, calendrier
jour/semaine/mois.

**Phase 2 — chauffeurs et véhicules** : listes filtrées, ajout, modification,
désactivation et réactivation, via les Actions métier existantes. Le compte créé
pour un chauffeur ne donne pas accès au portail. Disponibilités :
`BLOCKED_DESIGN_DECISION` (aucun modèle).

**Phase 1 — fondations :**

- **Rattachement** `provider_users` (+ `provider_user_roles`, `provider_user_invitations`) :
  un `User` ouvre le portail d'un fournisseur s'il y est rattaché, actif.
- **Gestion des accès au back-office uniquement** : fiche fournisseur → « Accès portail ».
- **API** `api/v1/provider` : connexion, choix du fournisseur, activation, mot de
  passe oublié, `me`, navigation, profil, mot de passe, tableau de bord.
- **Application** `provider-portal/` (port 5190).

## Chaîne de sécurité

```
auth:sanctum → provider.portal → provider.permission:<code>
```

`provider.portal` (`EnsureProviderPortalContext`) revérifie à **chaque requête** :

1. jeton à capacité `provider-portal` portant `provider:<id>` (gravé à la connexion) ;
2. compte autorisé à se connecter ;
3. rattachement `provider_users` actif ;
4. fournisseur au statut `active` (tout autre code ferme le portail) ;
5. organisation ni suspendue ni fermée ;
6. permission `provider.portal.access`.

Tout refus : 403 `{"code":"provider_access_revoked"}`, message générique. Le
fournisseur et l'organisation viennent du contexte, jamais de la requête :
`providerId`, `organizationId`, `status`, `id` sont refusés (422).

**Étanchéité des jetons** : un jeton fournisseur est refusé par l'interne
(`internal.token`) et par le portail client ; les jetons interne et client sont
refusés par le portail fournisseur.

**Séparation des rôles** : les rôles `PROVIDER_*` ont la portée `provider`.
`Permission::internal()` exclut `provider.*` : aucun rôle interne ne les
ramasse, même au rejeu de `RoleSeeder`. La liste des rôles internes ne les
montre que sur `?scope=provider`.

## Déploiement de la phase

```bash
php artisan migrate --force                                   # 3 tables nouvelles
php artisan db:seed --class=ProviderPortalSeeder --force      # rejouable
```

Variables : `PROVIDER_PORTAL_URL`, `PROVIDER_PORTAL_INVITATION_HOURS` (72),
`PROVIDER_PORTAL_TOKEN_TTL_MINUTES` (720), `PROVIDER_PORTAL_RATE_LIMIT` (300),
`PROVIDER_PORTAL_LOGIN_RATE_LIMIT` (30), `PROVIDER_PORTAL_WRITE_RATE_LIMIT` (30),
`PROVIDER_PORTAL_EXECUTE_RATE_LIMIT` (120 : exécution et preuves). En production, ajouter l'origine du
portail à `CORS_ALLOWED_ORIGINS`.

## Décisions ouvertes (`BLOCKED_DESIGN_DECISION`)

Disponibilités chauffeurs/véhicules, contrats fournisseur, communications
adressées au fournisseur, domaine et déploiement du portail, durées de
conservation : voir la spécification, §9.

- **Ordre de démarrage** — le portail démarre une tournée `confirmed` (spécification §5.4), alors que le
  référentiel `StatusTransitionSeeder` prévoit `draft → confirmed → planned → in_progress`. Une tournée passée
  à `planned` par l'interne ne peut donc pas démarrer depuis le portail. À trancher avec le propriétaire.
- **Incidents d'exécution** : aucun modèle ; non livrés.
- **Communications adressées au fournisseur en tant qu'entité** : `RecipientRole` n'a pas de destinataire
  « fournisseur » ; le portail ne montre que les envois faits à l'adresse du membre connecté. Accusés de
  réception des canaux externes : aucun modèle.
- **Devise des décomptes** : ni le décompte ni l'organisation ne la portent ; les montants s'affichent sans devise.

## Décisions de conformité (`BLOCKED_COMPLIANCE_DECISION`)

Durée de conservation des preuves de livraison, signatures et photos : non fixée. Aucune purge n'est
programmée ; ne pas en inventer une.
