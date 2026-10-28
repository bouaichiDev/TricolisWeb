# Portail fournisseur — Phase 5 : suivi, preuves de livraison, documents — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pour ses seules tournées, le fournisseur consulte une chronologie fidèle et une carte des positions réellement connues, enregistre et consulte les preuves de livraison de ses arrêts, et télécharge les documents d'exécution autorisés — sans jamais atteindre un document d'une autre tournée, d'un autre client ou de la facturation.

**Architecture:** `ProviderTourTimeline` assemble la chronologie à partir des horodatages réels (tournée, arrêts) et des `TrackingEvent` de la tournée — aucune donnée inventée. `ProviderDocumentAccess` définit l'ensemble des documents accessibles (liés à la tournée, aux services confirmés de ses arrêts, aux preuves de ses arrêts) ; tout téléchargement passe par lui (404 sinon) et par le flux protégé existant (`CustomerDocumentFiles::download`, générique). `RecordProviderProofOfDelivery` dépose signature/photo via `AttachEntityDocument` puis appelle `CreateProofOfDeliveryAction`.

**Spec:** `docs/superpowers/specs/2026-10-27-portail-fournisseur-design.md` §5.5

## Global Constraints

- Fichiers ≤ 200 lignes. Aucune migration.
- Permissions : `provider.tracking.view`, `provider.documents.view`, `provider.pod.view`, `provider.pod.create`. ADMIN et DISPATCHER : toutes ; FLEET_MANAGER et VIEWER : les trois `*.view`.
- Chronologie `GET tours/{id}/tracking` : entrées `{type, label, occurredAt, stopSequence, serviceNumber, latitude, longitude}` ; jamais la `description` libre des événements (peut porter des remarques client). Positions seulement si réellement enregistrées ; aucune ETA.
- Preuves : `GET tours/{tour}/stops/{stop}/proofs` ; `POST …/proofs` multipart `{orderServiceId, recipientName (2..120), remark? (≤500), signature (png/jpeg réel, ≤ 2 Mo), photo? (jpeg/png réel, ≤ 8 Mo)}`. Exige tournée `in_progress|completed`, arrêt `arrived|completed`, service **confirmé et actif** de cet arrêt. `deliveredAt` = heure serveur.
- Documents : `GET tours/{id}/documents`, `GET documents/{id}` (téléchargement). Jamais un document lié seulement à une commande (factures, BL d'autres services) ni à une autre tournée. Nom de fichier assaini, `nosniff`.
- Incidents : le motif de passage d'arrêt (phase 4) en tient lieu ; aucune table `ProviderIncident` — `BLOCKED_DESIGN_DECISION` documenté. Rétention des preuves : `BLOCKED_COMPLIANCE_DECISION`.
- Commit à la fin de la phase.

## Review Focus
- Identifiant de document d'une autre tournée, d'une facture de la même commande, d'une autre organisation : 404.
- Fichier `.png` contenant du PHP/HTML ou un SVG : refusé (422).
- Preuve sur un service d'un autre arrêt de la même tournée : 422.
- Preuve sur un arrêt `pending` : 422.
- Chronologie d'une tournée multi-clients : aucune `description` libre.

### Task 1 : permissions (rejeu), navigation inchangée (onglets du détail). Tests seeder.
### Task 2 : `ProviderTourTimeline` + route + tests `Tours/ProviderTrackingTest.php`.
### Task 3 : `ProviderDocumentAccess` + routes documents + tests `Tours/ProviderDocumentsTest.php`.
### Task 4 : `RecordProviderProofOfDelivery` + routes preuves + tests `Tours/ProviderProofsTest.php` (Storage::fake).
### Task 5 : Front — onglets du détail de tournée (Arrêts, Chronologie, Documents), carte (Leaflet, composants recopiés du portail client), dialogue de preuve (signature par fichier image), téléchargements authentifiés ; Vitest.
### Task 6 : E2E `proofs.spec.ts`, docs (dont les deux décisions bloquées), suites complètes, commit.
