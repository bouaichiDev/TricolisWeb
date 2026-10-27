# Portail fournisseur — Phase 3 : tournées, affectation, calendrier — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Le fournisseur voit les tournées que le transporteur lui a attribuées (à partir de « planifiée »), leur détail opérationnel, un calendrier, et y affecte **ses** chauffeurs et véhicules sous contrôle strict.

**Architecture:** `ProviderTourScope` (fournisseur + organisation, statut ≠ `draft`) ; ressources minimisées (aucun prix, montant, facture ni référence commerciale) ; affectation via `AssignProviderTourResources` qui vérifie appartenance, statut, réservation, chevauchement et capacité sous verrou, puis délègue à `UpdateTourAction` (audit `tour.updated`). Front : liste, détail, calendrier (FullCalendar, déjà utilisé par le portail client).

**Tech Stack:** Laravel 13 / Pest ; React 19, TanStack Query, FullCalendar 6, Vitest/MSW, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-27-portail-fournisseur-design.md` §5.3, §4 (minimisation)

## Global Constraints

- Fichiers ≤ 200 lignes. Aucune migration.
- Tournée visible si `provider_id` = fournisseur courant, même organisation, statut ∈ `planned|confirmed|in_progress|completed|cancelled`. Brouillon, autre fournisseur, autre organisation, inexistante : **même 404**.
- Détail exposé : numéro, date, statut, agence/dépôt (nom), chauffeur, véhicule, horaires prévus et réels, consignes, totaux (poids, volume, colis, distance, durées). Arrêts : séquence, statut, adresse opérationnelle (lignes, code postal, ville, pays, coordonnées, consignes, créneau), horaires prévus/réels. Services de l'arrêt : numéro de service, nom du service, créneau demandé, poids, volume, colis, consignes, contacts sur place (nom, téléphone). **Jamais** : prix client, coût fournisseur, facture, numéro de commande, référence externe, client.
- Affectation (`PATCH tours/{id}/resources` {driverId?, vehicleId?}, nul = retirer) : tournée `planned|confirmed`, `locked_by` nul, chauffeur/véhicule du fournisseur et `active`, pas de chevauchement (même jour, créneaux prévus qui se recouvrent, autre tournée non annulée du même chauffeur ou véhicule), capacité véhicule ≥ `total_weight` et `total_volume`. Refus : 422 sur le champ concerné.
- Calendrier : `GET tours/calendar?from&to` (≤ 62 jours, ≤ 500 événements).
- Commit à la fin de la phase.

## Review Focus

- Deux affectations concurrentes du même chauffeur à deux tournées qui se chevauchent : la seconde est refusée (verrou sur le chauffeur) — Task 3.
- Tournée réservée par un planificateur (`locked_by`) : 422 « en cours de planification » — Task 3.
- Tournée multi-clients : aucun champ commercial dans la réponse (assertion sur les clés JSON) — Task 2.
- Fenêtre de calendrier démesurée ou inversée : 422 — Task 2.
- Une tournée réattribuée par l'interne à un autre fournisseur disparaît aussitôt du portail (404) — Task 2.

---

### Task 1: Permissions, rôles, navigation
`provider.tours.view`, `provider.tours.assign-resources`, `provider.calendar.view`. ADMIN tout ; DISPATCHER base + flotte en lecture + tours.view + assign + calendar ; FLEET_MANAGER + tours.view + calendar ; VIEWER + tours.view + calendar. Navigation `tours` → `/tours`, `calendar` → `/calendar`. Tests : seeder (rejeu), navigation.

### Task 2: Lecture des tournées
Create `ProviderTourScope`, `ProviderTourController` (index filtré date/statut/recherche numéro, show), `ProviderTourCalendarController`, ressources `ProviderTourResource`, `ProviderTourStopResource`, `ProviderStopServiceResource`. Tests `Tours/ProviderToursTest.php` : isolation (B, autre organisation, brouillon → 404), liste filtrée, détail ordonné, **liste blanche des clés** du détail (aucune clé `price|cost|invoice|order|customer|reference`), calendrier borné.

### Task 3: Affectation
Create `AssignProviderTourResources` (Action), `ProviderTourResourcesController`, `AssignTourResourcesRequest`. Tests `Tours/ProviderTourAssignmentTest.php` : succès (audit `tour.updated`), chauffeur/véhicule de B ou du transporteur → 422, inactif → 422, statut `in_progress|completed|cancelled` → 422, réservé → 422, chevauchement → 422, capacité insuffisante → 422, retrait (null) accepté, sans permission → 403, champs imposés → 422.

### Task 4: Front
Modules `tours` (liste, détail avec arrêts et services, dialogue d'affectation listant chauffeurs/véhicules actifs) et `calendar` (FullCalendar jour/semaine/mois, clic → détail). Ajouter `@fullcalendar/*` au `package.json`. Tests Vitest : liste, détail sans champ commercial, affectation envoie `{driverId, vehicleId}`, refus 422 affiché, bouton masqué sans `actions.canAssign`.

### Task 5: Démo, E2E, docs, vérification, commit
`DemoProviderPortalTourSeeder` (local/testing : une tournée confirmée pour Alpha, une pour Beta, avec un arrêt et un service), E2E `tours.spec.ts` (Alpha affecte son chauffeur et son véhicule ; tournée de Beta → 404), docs, suites complètes, commit « feat(provider-portal): phase 3 — tournées, affectation, calendrier ».
