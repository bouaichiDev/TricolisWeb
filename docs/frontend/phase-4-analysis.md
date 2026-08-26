# Frontend Phase 4 — Analyse préalable

Fournisseurs, chauffeurs, véhicules, et normalisation des statuts par le
référentiel `statuses`.

Relevé sur le backend réel le 26 août 2026, avant toute ligne de code, selon
l'ordre de priorité du prompt : **schéma réel > backend réel > UML > document de
phase**.

---

## 1. Provider — schéma réel

Table `providers`, `ProviderListResource` / `ProviderDetailResource` :

```text
id, organizationId, addressId, contactId, code, name, status
driverCount, vehicleCount        (comptes, quand chargés)
organization, address, contact   (relations, quand chargées)
```

## 2. Driver — schéma réel

```text
id, organizationId, providerId, addressId, contactId, code, name, status
providerName                     (liste)
provider, address, contact       (détail)
```

## 3. « VehicleType » — n'existe plus comme entité

Le référentiel des types de véhicule a été fusionné avec ceux des colis et du
groupage dans `types` / `type_items`, à la demande explicite du propriétaire du
projet, le 26 août 2026 — commit `5417ecd`. Un type de véhicule est désormais
une **valeur de la source `vehicle`** :

```text
GET /type-items?type=vehicle
```

L'écran `/types` livré avec cette fusion les administre déjà : sources à gauche,
valeurs à droite, création, modification, suppression protégée.

## 4. Vehicle — schéma réel

```text
id, providerId, vehicleTypeId, code, registrationNumber,
payloadCapacity, volumeCapacity, palletCapacity, status
providerName, vehicleTypeName    (liste)
provider, vehicleType            (détail)
```

`vehicleTypeId` désigne une ligne de `type_items` de la source `vehicle` ; la
règle `IsTypeItemOf('vehicle')` le vérifie côté serveur.

---

## 5. Écarts entre le document de phase et la base réelle

| Attendu au prompt | Réel | Décision |
|---|---|---|
| `Provider.providerType` | absent | Ni colonne ni filtre. La liste et le formulaire s'en passent |
| `Provider.legacyId`, `Driver.legacyId`, `Vehicle.legacyId` | absents | Rien à afficher en lecture seule |
| `Driver.firstName`, `lastName`, `phone`, `email`, `userId` | un seul `name` ; pas de lien `User` | Le prompt prévoit explicitement cette variante (§25, « version schéma avec nom unique ») |
| Entité `VehicleType` + pages dédiées | fusionnée dans `type_items` | Aucune page `/resources/vehicle-types` : elle recréerait ce qui vient d'être supprimé. L'écran `/types` tient ce rôle |
| Permissions `vehicle_types.*` | `types.*` | Un module unique couvre toutes les sources, y compris celles qu'un organisme ajoute |
| `statuses.src` | `statuses.source` | Voir `docs/backend/statuses-schema-audit.md` |
| `statuses.color`, `background_color` | absents ; `icon` seul | Les couleurs restent celles du système de design, comme le §40 l'autorise |
| `statuses` scopé par organisation | portée plateforme | Un statut décrit le cycle de vie du domaine, pas la préférence d'un organisme |
| Préfixe de routes `/resources/...` | routes à plat (`/providers`, `/customers`…) | Le prompt autorise le préfixe existant (§20). Ajouter `/resources` ferait cohabiter deux conventions |

Aucun de ces écarts n'est corrigé en silence : le contrat réel est suivi, et la
divergence est consignée ici comme au rapport final.

---

## 6. Endpoints réels

```text
GET|POST      /providers            GET|PATCH|DELETE /providers/{id}
GET|POST      /drivers              GET|PATCH|DELETE /drivers/{id}
GET|POST      /vehicles             GET|PATCH|DELETE /vehicles/{id}
GET|POST      /type-items           GET|PATCH|DELETE /type-items/{id}
GET           /statuses             GET /statuses/sources
```

## 7. Permissions réelles (`PermissionSeeder`)

```text
providers.view   .create .update .delete
drivers.view     .create .update .delete
vehicles.view    .create .update .delete
types.view       .create .update .delete
statuses.view    (écriture réservée à la plateforme)
```

## 8. Filtres réellement acceptés

| Liste | Filtres |
|---|---|
| Providers | `search`, `status`, `addressId`, `contactId` |
| Drivers | `search`, `status`, `providerId`, `addressId`, `contactId` |
| Vehicles | `search`, `status`, `providerId`, `vehicleTypeId`, `payloadCapacityMin`, `volumeCapacityMin`, `palletCapacityMin` |
| Statuses | `source`, `active`, `search` |

Aucun autre filtre ne sera envoyé : un paramètre inconnu revient en 422.

## 9. Tris autorisés

Providers et Drivers : `code`, `name`, `status`. Vehicles : `code`,
`registration_number`, `status` (relevé sur `VehicleListQuery`). `perPage` est
plafonné à **100**.

## 10. Référentiel `statuses`

Colonnes réelles et écarts : `docs/backend/statuses-schema-audit.md`.
Inventaire des 38 entités portant un `status` :
`docs/backend/statuses-global-audit.md`.

Aucune entrée n'existe aujourd'hui pour `provider`, `driver`, `vehicle`, `type`
ni `type_item` — cette phase les crée.

## 11. API statuses

`GET /statuses?source=provider&active=1`. Existe déjà, permission
`statuses.view`, rien à créer.

## 12. Sources de statut utilisées par cette phase

```text
provider   driver   vehicle   type_item
```

## 13. Composants réutilisés

`DataTable`, `PageHeader`, `SearchInput`, `StatusBadge`, `AsyncSelect`,
`ControlledField`, `TextField`, `ConfirmDialog`, `EmptyState`, `ErrorState`,
`FormErrorSummary`, `PermissionGuard`, `SectionCard`, `Tabs`.

Deux ajouts partagés, faute d'équivalent : un hook `useStatuses(source)` et un
sélecteur de statut branché sur le référentiel. Le `StatusSelect` existant
reçoit ses valeurs de l'appelant — utile pour les listes qui ne viennent pas du
référentiel (type d'adresse, rôle de contact), insuffisant ici.

## 14. Hors périmètre

`ProviderContract`, `ProviderPriceList`, `DriverAvailability`,
`VehicleAvailability`, `VehicleCapacity` séparée, `DriverSkill`, `VehicleSkill`,
`VehicleMaintenance`, GPS, optimisation de tournée, Planning, `Tour`,
`TourStop` — aucun n'est touché.

La validation des statuts n'est activée que sur les entités de cette phase ;
l'audit global explique pourquoi et ce qui reste.
