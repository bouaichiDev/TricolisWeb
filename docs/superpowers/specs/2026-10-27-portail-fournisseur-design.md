# Portail externe fournisseur — conception

**Date :** 2026-10-27 · **Branche :** `feature/provider-portal` (depuis `feature/customer-portal-phase-8-finalization`)
**Consignes source :** `PFE/Project/PortailFournisseur/Phase1…Phase8` · **Statut :** validée en conversation, à relire.

## 1. Objectif

Un portail fournisseur (transporteur sous-traitant) de même niveau que le portail client : application React séparée, même structure d'interface (tableaux, filtres, actions en icônes), API Laravel `api/v1/provider`, isolation stricte entre fournisseurs, organisations et clients.

**Succès :** un fournisseur A gère ses chauffeurs et véhicules, affecte ses ressources aux tournées que l'interne lui a attribuées, les exécute et consulte suivi, preuves, documents et décomptes, sans jamais voir ni toucher une donnée de B, d'une autre organisation ou au-delà du besoin opérationnel des clients transportés.

## 2. Décisions prises

| # | Décision | Choix |
|---|---|---|
| D1 | Rattachement utilisateur → fournisseur | Nouvelle table `provider_users` (modèle `customer_users`), gérée **uniquement** depuis le back-office |
| D2 | Cycle de vie piloté par le fournisseur | Cycle encadré (§5.4) ; annulation et planification restent internes |
| D3 | Organisation du code React | App séparée `provider-portal/`, socle recopié depuis `customer-portal/` (convention du dépôt) |

## 3. Accès et rattachement (backend)

**Tables nouvelles** (aucun ALTER de table existante) :
- `provider_users` : `id`, `provider_id`, `user_id`, `status` (`invited|active|disabled`), `invited_by`, `invited_at`, `activated_at`, `disabled_at`, timestamps ; unique `(provider_id, user_id)` ; index `user_id`, `(provider_id, status)`.
- `provider_user_roles` : `provider_user_id` → `role_id` du RBAC partagé (pas de RBAC parallèle).
- `provider_user_invitations` : jeton **haché**, usage unique, expiration ; l'invité choisit son mot de passe.

**Gestion des comptes :** onglet « Accès portail » de la fiche fournisseur du back-office (inviter, changer de rôle, désactiver, réactiver), sous permission interne dédiée. Le portail n'expose **aucun** écran ni route de gestion d'utilisateurs ou de rôles.

**Contexte :** middleware `EnsureProviderPortalContext` → `CurrentProviderContext` : jeton → `User` actif → `provider_users` actif → `Provider` actif → `Organization` active. Tout échec : 403 générique (compte) ou 401 (jeton). Un compte rattaché à plusieurs fournisseurs choisit à la connexion ; le jeton porte ce fournisseur ; changer impose une reconnexion.

**Jetons :** capacité Sanctum propre au portail fournisseur. Jeton fournisseur refusé sur l'API interne et l'API client ; jetons client et interne refusés sur `api/v1/provider`.

**Champs interdits :** `providerId`, `provider_id`, `organizationId`, `organization_id`, `status`, `id` → 422 sur toutes les écritures (`ServerControlledFields::prohibit`).

**Rôles par défaut** (seeder rejouable, toutes organisations, permissions ajoutées aux rôles existants au rejeu) :

| Rôle | Périmètre |
|---|---|
| Administrateur fournisseur | toutes les permissions `provider.*` |
| Exploitant | tournées, affectation, calendrier, exécution, arrêts, suivi, documents, POD |
| Gestionnaire flotte | chauffeurs, véhicules |
| Lecture | toutes les permissions `*.view` |

**Permissions :** `provider.portal.access`, `provider.dashboard.view`, `provider.profile.view`, `provider.drivers.view|create|update`, `provider.vehicles.view|create|update`, `provider.tours.view`, `provider.tours.assign-resources`, `provider.calendar.view`, `provider.tours.execute`, `provider.tour-stops.update-status`, `provider.tracking.view`, `provider.documents.view`, `provider.pod.view|create`, `provider.communications.view`, `provider.settlements.view`, `provider.settlements.documents.view`.

## 4. Isolation

- `ProviderScope` : toute lecture filtre `provider_id = CurrentProvider` **et** `organization_id = CurrentProvider.organization` ; toute écriture relit sous `lockForUpdate`. Aucune liaison de route implicite par ULID.
- Ressource étrangère et inexistante : **même 404**.
- Tournées en `draft` invisibles.
- Chemins indirects scopés par la tournée : `Tour → TourStop → TourStopService → OrderService → Order`, `POD → Document`, `ProviderSettlement → lines`.
- **Minimisation multi-clients :** le fournisseur voit adresse d'arrêt, contact sur place, consignes, colis, poids, volume, créneaux. Jamais prix, montants clients, factures, références commerciales ni données d'autres services non affectés à la tournée.
- E-mail de chauffeur déjà utilisé : message neutre (pas de révélation de compte), limiteur sur la création.
- Limiteurs : connexion, invitation, écritures. Erreurs externes génériques (`ExternalApiErrors`). Audit (`WriteAuditLog`) des écritures sensibles sans donnée personnelle inutile.

## 5. Fonctionnalités par phase

### 5.1 Phase 1 — Fondations
Connexion, déconnexion, choix du fournisseur, activation par invitation, mot de passe oublié. `GET me`, `GET dashboard` (tournées du jour, en cours, terminées, chauffeurs actifs, véhicules actifs — données réelles), `GET navigation` filtrée par permission. Profil fournisseur en lecture seule ; mot de passe personnel modifiable.

### 5.2 Phase 2 — Chauffeurs et véhicules
- Chauffeurs : liste paginée, recherche, filtres, détail, ajout, modification, désactivation, réactivation via `CreateDriverAction` (crée le compte chauffeur invité, sans permission), `UpdateDriverAction`.
- Véhicules : liste, détail, ajout, modification via `CreateVehicleAction`, `UpdateVehicleAction` ; type de véhicule de l'organisation ; capacités ≥ 0 avec maxima.
- `provider_id` toujours imposé par le contexte ; aucun transfert. Aucune suppression. Désactivation refusée (422) si la ressource est affectée à une tournée `planned|confirmed|in_progress`.
- Disponibilités / indisponibilités : `BLOCKED_DESIGN_DECISION` (aucun mécanisme existant).

### 5.3 Phase 3 — Tournées, affectation, calendrier
- Liste et détail des tournées du fournisseur (statut ≥ `planned`) : date, agence, dépôt, statut, chauffeur, véhicule, progression, arrêts ordonnés, périodes, consignes, totaux.
- `PATCH tours/{id}/resources` via `UpdateTourAction`, sous verrou, si : chauffeur et véhicule du fournisseur et actifs ; tournée `planned|confirmed` ; non réservée (`locked_by` nul) ; pas de chevauchement de créneau avec une autre tournée du même chauffeur ou véhicule ; capacité véhicule ≥ poids et volume de la tournée.
- Calendrier jour, semaine, mois. Aucune création de tournée, d'arrêt, de période ni réordonnancement.

### 5.4 Phase 4 — Exécution (cycle encadré)
```
planned ─(interne)─► confirmed ─Démarrer─► in_progress ─Terminer─► completed
cancelled : interne uniquement
Arrêt : pending ─Arrivé─► arrived ─Terminé─► completed ; pending|arrived ─Passé (motif)─► skipped
```
- Nouvelles Actions : `StartProviderTour` (chauffeur et véhicule requis, `actual_start_at` serveur), `CompleteProviderTour` (tous les arrêts `completed|skipped|cancelled`, `actual_end_at`), `ArriveAtStop` (`actual_arrival_at`), `CompleteStop` (`actual_departure_at`), `SkipStop` (motif obligatoire, conservé dans l'audit).
- Idempotence : rejouer une transition déjà faite renvoie l'état actuel (200), sans doublon d'audit. Transition interdite : 422.
- Vue « À effectuer / En cours / Terminées », chronologie.

### 5.5 Phase 5 — Suivi, POD, documents
Chronologie `TrackingEvent` de la tournée ; carte uniquement avec positions réelles (pas d'ETA inventée). POD : consultation, création via `CreateProofOfDeliveryAction` sur un arrêt du fournisseur ; contrôle du type réel et de la taille. Documents des services de la tournée en flux protégé ; aucun lien public permanent.

### 5.6 Phase 6 — Communications, décomptes
Décomptes `ProviderSettlement` en lecture seule (périodes, lignes, montants, statut, document) ; aucune action financière. Communications liées à ses tournées si le modèle `OrderCommunication` le permet sans fuite ; sinon `BLOCKED_DESIGN_DECISION`.

### 5.7 Phases 7 et 8
Audit transversal, matrice des routes vérifiée en CI (`provider-portal:route-matrix --check`), `docs/provider-portal/security.md`, E2E, documentation utilisateur et exploitation, checklist GO/NO-GO. **Aucun déploiement.**

## 6. Application React `provider-portal/`

- Pile du portail client : React 19, Vite, TanStack Query, Tailwind, shadcn, Vitest + MSW, Playwright. Port de dev 5190 (5173-5174 back-office, 5180-5182 portail client), build `provider-portal/dist`.
- Socle recopié puis adapté : `DataTable` (ligne de filtres, 10 à 100 lignes), `RowActions`, `SearchInput` (350 ms), `PageHeader`, états chargement, vide, erreur, 401, 403, 404, responsive.
- Navigation : Tableau de bord, Chauffeurs, Véhicules, Tournées, Calendrier, Exécution, Documents, Décomptes, Profil — chaque entrée visible seulement si la permission et le module existent.

**Sécurité de l'interface :**
- Gardes de route (non connecté → connexion ; sans permission → 403) ; actions sans droit **masquées**.
- L'UI affiche les capacités calculées par le serveur (`actions.canAssign`, `canStart`, …) ; aucune règle métier recalculée côté client.
- Jeton en `sessionStorage`, clé propre au portail ; purgé avec le cache TanStack sur déconnexion, 401 ou compte désactivé.
- Aucun `dangerouslySetInnerHTML` ; CSP et en-têtes de sécurité ; CORS par `.env`.
- Aucun menu Utilisateurs ou Rôles (vérifié par test).

## 7. Tests

- **Pest :** IDOR A/B et X/Y sur chaque route, champs falsifiés, compte suspendu, rattachement désactivé, jeton révoqué, jeton client ou interne sur l'API fournisseur (et inverse), transitions interdites, idempotence, concurrence d'affectation, tournée multi-clients (champs masqués), seeder rejouable, seeders de démo inertes en production.
- **Vitest :** pages, gardes, permissions, absence de menu d'administration.
- **Playwright :** parcours de la phase 8 (§ consignes).
- **Non-régression :** suite Pest complète, Vitest portail client et back-office, Playwright portail client.

## 8. Contraintes de réalisation

Fichiers ≤ 200 lignes ; Actions métier existantes réutilisées, aucune écriture directe depuis un Controller ; seules les trois tables du §3 sont créées ; seeders de démo gardés `local|testing` ; aucun secret en dépôt ni en log ; aucun déploiement, aucune commande destructive ; commit uniquement sur demande ; rapport final en français à chaque phase.

## 9. Décisions ouvertes (`BLOCKED_DESIGN_DECISION`)

- Disponibilités chauffeurs et véhicules (aucun modèle).
- Contrats fournisseur (`ProviderContractDriver`, `ProviderContractVehicle` absents).
- Communications adressées au fournisseur, selon l'audit du modèle en phase 6.
- Domaine, vhost et déploiement du portail fournisseur (phase 8).
- Durées de conservation des POD et documents fournisseur (aucune durée inventée).
