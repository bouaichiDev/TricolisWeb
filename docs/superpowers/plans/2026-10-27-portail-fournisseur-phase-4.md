# Portail fournisseur — Phase 4 : exécution des tournées — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Le fournisseur démarre et termine ses tournées et fait avancer leurs arrêts selon le cycle encadré validé (spec §5.4), avec horodatage serveur, audit, idempotence et verrous.

**Architecture:** Une Action par transition (`StartProviderTour`, `CompleteProviderTour`, `ArriveAtProviderStop`, `CompleteProviderStop`, `SkipProviderStop`), toutes sous `DB::transaction` avec verrou de la tournée puis de l'arrêt, déléguant l'écriture à `UpdateTourAction` / `UpdateTourStopAction` (audit `tour.status_changed`, `tour_stop.updated`). Rejouer une transition déjà faite rend l'état courant (200) sans nouvel audit. Front : onglets « À effectuer / En cours / Terminées » sur la liste, boutons d'exécution sur le détail.

**Spec:** `docs/superpowers/specs/2026-10-27-portail-fournisseur-design.md` §5.4

## Global Constraints

- Fichiers ≤ 200 lignes. Aucune migration : `actual_*` existent déjà.
- Transitions tournée : `confirmed → in_progress` (chauffeur **et** véhicule requis, `actual_start_at = now()`), `in_progress → completed` (tous les arrêts `completed|skipped|cancelled`, `actual_end_at = now()`). Toute autre : 422 `status`.
- Transitions arrêt (tournée `in_progress` obligatoire) : `pending → arrived` (`actual_arrival_at`), `arrived → completed` (`actual_departure_at`), `pending|arrived → skipped` avec `reason` (3..500 caractères) consigné dans l'audit `tour_stop.skipped`.
- Rejeu idempotent : même transition sur un objet déjà dans l'état cible → 200, état courant, aucun audit ajouté.
- L'heure vient toujours du serveur ; aucun champ de date accepté (`missing`).
- Ordre des arrêts non imposé (un chauffeur peut inverser deux arrêts sur le terrain) — décision consignée.
- Arrêt d'une tournée étrangère ou inexistante : 404 (résolu via la tournée du fournisseur).
- Permissions : `provider.tours.execute` (démarrer/terminer), `provider.tour-stops.update-status` (arrêts). ADMIN et DISPATCHER les reçoivent.
- Commit à la fin de la phase.

## Review Focus
- Double clic / réseau rejoué sur « Démarrer » : un seul `tour.status_changed`.
- Terminer une tournée avec un arrêt encore `arrived` : 422.
- Arrêt d'une tournée de Beta via son identifiant : 404.
- Motif de passage vide ou démesuré : 422.
- Tournée `confirmed` sans véhicule : démarrage refusé.

### Task 1 : permissions + navigation inchangée (exécution depuis le détail). Tests seeder.
### Task 2 : Actions tournée (start/complete) + routes `POST tours/{id}/start|complete` + tests `Tours/ProviderTourExecutionTest.php`.
### Task 3 : Actions arrêt (arrive/complete/skip) + routes `POST tours/{tour}/stops/{stop}/arrive|complete|skip` + tests `Tours/ProviderStopExecutionTest.php`.
### Task 4 : ressource `actions.canStart|canComplete` et `stops[].actions.canArrive|canComplete|canSkip` ; front (onglets, boutons, dialogue de motif) ; Vitest.
### Task 5 : E2E `execution.spec.ts` (démarrer, arriver, terminer l'arrêt, terminer la tournée), docs, suites complètes, commit.
