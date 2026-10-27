# Stratégie dates et fuseaux horaires — portail client (Phase 8, §27)

## Règle

| Couche | Fuseau | Format |
|---|---|---|
| PHP / Laravel | `UTC` (`config/app.php`) | — |
| MySQL | colonnes `timestamp` écrites et lues en UTC par Laravel | — |
| API — horodatages (`createdAt`, `updatedAt`, `cancelledAt`, …) | UTC | ISO 8601 avec `Z` : `2026-10-12T15:30:00.000000Z` |
| API — dates seules (date demandée, date de tournée, journée entière) | **aucun** : c'est un jour du calendrier | `2026-10-12` |
| API — créneaux de livraison (`slot.start`, `slot.end`) | **heure murale** du transporteur, sans décalage | `2026-10-12T09:00:00` |
| Portail React | fuseau du navigateur pour les horodatages ; aucune conversion pour les dates seules et les heures murales | `Intl.DateTimeFormat('fr-CH')` |
| Planificateur | UTC (`daily()` = minuit UTC) | — |

Pourquoi l'heure murale pour les créneaux : « livraison entre 9 h et 11 h » est
une promesse faite au destinataire **sur place**. Un client consultant le portail
depuis un autre fuseau doit lire 9 h – 11 h, pas une heure convertie.

## Où c'est appliqué

- **Backend** : `ServiceSlot::of()` produit `AAAA-MM-JJ` (journée entière) ou
  `AAAA-MM-JJTHH:MM:SS` (heure murale) ; les ressources sérialisent les
  horodatages Eloquent en ISO 8601 UTC (comportement Laravel par défaut).
- **Calendrier** : `CalendarOrdersRequest` reçoit `from` / `to` en
  `Y-m-d` ; `CustomerCalendarEvents::within()` compare des jours, sans fuseau.
  Côté React, `toDateOnly()` (`modules/calendar/utils/range.ts`) construit ces
  jours en heure **locale** — pas `toISOString()`, qui ferait glisser la période
  d'un jour à l'est de Greenwich.
- **Affichage** : `shared/utils/format.ts` et `shared/utils/slot.ts`.

## Défaut corrigé en Phase 8

`formatDate('2026-10-12')` passait la chaîne à `new Date()`, qui lit une date
seule comme **minuit UTC** : dans un navigateur à l'ouest de Greenwich (UTC−5),
le jour affiché reculait au 11. Invisible pour les clients actuels (Suisse,
France, Maroc : UTC+0 à +2), mais faux par construction.

Correction : une date seule `AAAA-MM-JJ` est construite en heure locale
(`new Date(année, mois − 1, jour)`). Tests : `shared/utils/format.test.ts`
(Vitest), qui recharge le module dans `America/New_York` et `Europe/Zurich` et
vérifie que le piège existe bien dans le fuseau de test avant de vérifier la
correction.

## Points d'attention (non corrigés, documentés)

| Point | Effet | Décision |
|---|---|---|
| Fuseau par défaut d'une organisation incohérent : `Europe/Paris` à la création par le back-office, `Africa/Casablanca` au provisionnement | le champ `organizations.timezone` n'est **lu nulle part** dans le portail aujourd'hui : aucun effet visible | à unifier si le fuseau d'organisation devient utilisé (`BLOCKED_DESIGN_DECISION` mineure) |
| Les heures murales des créneaux ne portent pas leur fuseau | un client consultant depuis un autre fuseau lit l'heure locale du transporteur, sans mention | voulu ; mention du fuseau à envisager si des clients hors Europe arrivent |
| Planificateur en UTC | `sanctum:prune-expired` tourne à 01:00 ou 02:00 heure suisse selon la saison | sans effet métier |
| Changement d'heure (DST) | les créneaux en heure murale ne sont jamais convertis : pas de décalage d'une heure | — |

## Tests existants

- `modules/calendar/utils/range.test.ts` — bornes de période en jours locaux.
- `shared/utils/format.test.ts` — dates seules à l'est et à l'ouest de Greenwich,
  horodatage UTC converti en heure locale (17:30 à Zurich pour 15:30 Z en octobre).
- `tests/Feature/Api/V1/CustomerPortal/Tracking/CustomerCalendarTest.php` —
  période, borne de fin exclue, plafond de jours.
