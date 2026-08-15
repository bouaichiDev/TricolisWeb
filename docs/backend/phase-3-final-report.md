# Rapport final — Phase 3 : fournisseurs et ressources

Répond au §37 du prompt « Tricolis V2 — Backend Phase 3 ».

---

## 1. Branche Git

```text
feature/backend-phase-3-providers-resources
```

Créée depuis `livrable2PartieMetier` (Phases 1 et 2), et non depuis `main`.

**Écart assumé au §0, validé par le porteur du projet** : `main` est resté au
commit initial `c97dc0d`, un squelette Laravel vide. Brancher depuis lui aurait
privé la Phase 3 des organisations, adresses, contacts, audit et policies dont
`Provider` et `Driver` dépendent directement.

Aucune fusion, aucun rebase, aucune suppression de branche, aucun push
automatique.

## 2. Diagrammes utilisés

Le §1 désigne comme sources officielles deux `.puml` qui **n'existent pas** et
n'ont jamais été produits. Les diagrammes réellement disponibles sont :

```text
Conception/diagramme/Tricolis V2 — Diagramme de classes partagées.txt
Conception/diagramme/Tricolis V2 — Diagramme de classes plateforme interne.txt
```

Leur modèle **contredit** celui décrit par le prompt sur trois des quatre
classes — `Driver` n'a quasiment aucun attribut commun entre les deux versions.

**Arbitrage tranché par le porteur du projet le 1er août 2026 : les deux `.txt`
sont la dernière version et font foi.** Le prompt Phase 3 reste la référence
pour tout ce qu'il décrit hors modèle de données — endpoints, permissions,
règles de suppression, exclusions, exigences de test.

Une première livraison suivait le prompt ; elle a été réalignée sur les `.txt` :

| Table | Retiré | Ajouté |
|-------|--------|--------|
| `providers` | `legacy_id`, `provider_type` | `address_id`, `contact_id` |
| `drivers` | `legacy_id`, `user_id`, `first_name`, `last_name`, `phone`, `email` | `organization_id`, `address_id`, `contact_id`, `name` |
| `vehicles` | `legacy_id` | — |
| `vehicle_types` | — | — |

Détail dans [`phase-3-analysis.md`](phase-3-analysis.md) §1.

## 3. Classes implémentées

```text
Provider
Driver
VehicleType
Vehicle
```

## 4. Attributs implémentés

| Classe | Attributs |
|--------|-----------|
| `Provider` | `id`, `organizationId`, `addressId`, `contactId`, `code`, `name`, `status` |
| `Driver` | `id`, `organizationId`, `providerId`, `addressId`, `contactId`, `code`, `name`, `status` |
| `VehicleType` | `id`, `organizationId`, `code`, `name`, `status` |
| `Vehicle` | `id`, `providerId`, `vehicleTypeId`, `code`, `registrationNumber`, `payloadCapacity`, `volumeCapacity`, `palletCapacity`, `status` |

Strictement les attributs des diagrammes, ni plus ni moins. Tableau de
correspondance complet — type, colonne MySQL, nullabilité, index, relation — au
§6 de `phase-3-analysis.md`.

## 5. Relations implémentées

```text
Provider    belongsTo Organization ; belongsTo Address ; belongsTo Contact
            hasMany Driver ; hasMany Vehicle
Driver      belongsTo Organization ; belongsTo Provider
            belongsTo Address ; belongsTo Contact
VehicleType belongsTo Organization ; hasMany Vehicle
Vehicle     belongsTo Provider     ; belongsTo VehicleType
```

`Provider` et `Driver` portent `address_id` et `contact_id` en clé étrangère
directe — c'est ce que pose le diagramme (`Provider "0..*" --> "0..1" Address`),
et c'est déjà la convention de `customer_sites` et `order_services`. Les deux
liens sont facultatifs.

Aucune relation vers `Document` : le diagramme n'en définit pas. Aucun lien
`Driver → User` non plus.

## 6. Migrations créées

| # | Migration |
|---|-----------|
| 1 | `2026_08_01_200001_create_providers_table` |
| 2 | `2026_08_01_200002_create_drivers_table` |
| 3 | `2026_08_01_200003_create_vehicle_types_table` |
| 4 | `2026_08_01_200004_create_vehicles_table` |

Aucune migration existante modifiée.

## 7. Modèles créés

`App\Modules\Providers\Models\Provider`,
`App\Modules\Drivers\Models\Driver`,
`App\Modules\Fleet\Models\VehicleType`,
`App\Modules\Fleet\Models\Vehicle`.

## 8. Actions créées

| Module | Actions |
|--------|---------|
| Providers | `CreateProviderAction`, `UpdateProviderAction`, `DeleteProviderAction` |
| Drivers | `CreateDriverAction`, `UpdateDriverAction`, `DeleteDriverAction` |
| Fleet | `CreateVehicleTypeAction`, `UpdateVehicleTypeAction`, `DeleteVehicleTypeAction`, `CreateVehicleAction`, `UpdateVehicleAction`, `DeleteVehicleAction` |

Aucune ne dépend de la couche HTTP : elles reçoivent un `AuditContext`
(organisation, utilisateur, adresse IP), pas une `Request`. Toutes sont
transactionnelles et produisent leur audit.

Services de garde : `ProviderScopeGuard` (appartenance des clés étrangères),
exceptions métier `ProviderStillInUse` et `VehicleTypeStillInUse`.

## 9. Requests créées

`StoreProviderRequest`, `UpdateProviderRequest`, `ListProviderRequest`,
`StoreDriverRequest`, `UpdateDriverRequest`, `ListDriverRequest`,
`StoreVehicleTypeRequest`, `UpdateVehicleTypeRequest`,
`StoreVehicleRequest`, `UpdateVehicleRequest`, `ListVehicleRequest`.

Règle réutilisable `BelongsToActiveOrganization` : `exists:providers,id` seul
laisserait passer le fournisseur d'une autre organisation.

## 10. Resources créées

12, trois niveaux par entité : `ProviderListResource`, `ProviderDetailResource`,
`ProviderCompactResource`, et l'équivalent pour `Driver`, `VehicleType` et
`Vehicle`.

Les listes ne chargent jamais les collections enfants — seulement des compteurs
via `withCount`.

## 11. Policies créées

`ProviderPolicy`, `DriverPolicy`, `VehicleTypePolicy`, `VehiclePolicy`, toutes
dérivées de `BaseOrganizationPolicy`.

`DriverPolicy` et `VehiclePolicy` évaluent la permission dans l'organisation du
fournisseur : ces deux entités n'ont pas d'organisation propre.

## 12. Permissions ajoutées

Seize, exactement celles du §22 :

```text
providers.view      drivers.view       vehicle_types.view      vehicles.view
providers.create    drivers.create     vehicle_types.create    vehicles.create
providers.update    drivers.update     vehicle_types.update    vehicles.update
providers.delete    drivers.delete     vehicle_types.delete    vehicles.delete
```

Total du projet : **91 permissions**.

## 13. Routes créées

Vingt, toutes sous `/api/v1`, protégées par `auth:sanctum` + middleware
`organization` + Policies :

```text
GET|POST        /api/v1/providers
GET|PATCH|DELETE /api/v1/providers/{provider}
GET|POST        /api/v1/drivers
GET|PATCH|DELETE /api/v1/drivers/{driver}
GET|POST        /api/v1/vehicle-types
GET|PATCH|DELETE /api/v1/vehicle-types/{vehicleType}
GET|POST        /api/v1/vehicles
GET|PATCH|DELETE /api/v1/vehicles/{vehicle}
```

Total du projet : **163 routes**, aucun doublon.

Routes imbriquées `providers/{provider}/drivers` et `.../vehicles` **non
créées** : les §10 et §14 les rendent facultatives. Le projet nidifie quand
l'enfant n'a pas de sens seul ; un chauffeur se cherche par nom ou par
immatriculation, pas en naviguant par fournisseur. Un filtre `providerId` couvre
le besoin sans dupliquer la logique.

## 14. Tests ajoutés

| Fichier | Tests | Couverture |
|---------|-------|-----------|
| `Providers/ProviderTest` | 17 | CRUD, adresse et contact facultatifs, création sans adresse ni contact, adresse ou contact inconnus refusés, suppression refusée avec chauffeurs puis avec véhicules, code dupliqué, même code dans une autre organisation, IDOR sur `GET`/`PATCH`/`DELETE`, isolation de liste, recherche code et nom, filtres `status` et `addressId`, pagination, tri interdit, compteurs sans chargement, audit création/modification/suppression, audit limité aux champs modifiés |
| `Drivers/DriverTest` | 15 | CRUD, héritage de l'organisation du fournisseur, adresse et contact facultatifs, fournisseur hors périmètre, déplacement hors périmètre refusé, adresse ou contact inconnus refusés, code dupliqué par fournisseur, même code chez un autre fournisseur, IDOR, filtre et recherche, tri interdit, audit |
| `Fleet/FleetTest` | 15 | CRUD des deux entités, suppression de type refusée si utilisé, code dupliqué, IDOR, recherche et filtres, fournisseur hors périmètre, type hors périmètre, organisations différentes, code dupliqué par fournisseur, immatriculation dupliquée globalement, capacités négatives, filtres de capacité minimale, tri interdit, audit |
| `Providers/FleetPermissionTest` | 6 | Lecture, création, modification et suppression refusées sans permission ; accès accordé après attribution du rôle ; en-tête d'organisation requis ; accès non authentifié refusé |

**53 tests ajoutés.**

## 15. Résultats des tests

```text
composer validate                                ./composer.json is valid
php artisan optimize:clear                       OK
php artisan migrate:fresh --seed --env=testing   OK (6 seeders)
php artisan test                                 242 passed (705 assertions)
```

189 tests des Phases 1 et 2, 53 de la Phase 3. **Aucune régression** : aucun
test des Phases 1 et 2 n'a été modifié, désactivé ni marqué `skip`.

## 16. Résultat de Pint

```text
./vendor/bin/pint --test    PASS
```

## 17. Incohérences trouvées

| # | Incohérence | Traitement |
|---|-------------|------------|
| A | Les deux `.puml` désignés au §1 n'existent pas et n'ont jamais été produits | Signalée ; les `.txt` disponibles font foi sur décision du porteur |
| B | Les `.txt` contredisent le prompt sur `Provider`, `Driver` et `Vehicle` | Tranchée en faveur des `.txt` ; code réaligné, écarts documentés dans `phase-3-analysis.md` §1 |
| C | Le §5 place `Http/` dans chaque module ; le projet le place dans `app/Http` depuis la Phase 1 | Convention existante conservée, comme le demandent le §5 (« respecter l'architecture déjà utilisée ») et le §26 |
| D | Le §24 exige de refuser la suppression d'un chauffeur ou d'un véhicule référencé par une tournée | Le module Tours n'existe pas : contrôle reporté et signalé au §23 |
| E | `apiResource('vehicle-types')` génère le paramètre `vehicle_type`, incompatible avec la liaison implicite vers `$vehicleType` | Corrigé par `->parameters(['vehicle-types' => 'vehicleType'])` |
| F | Le §7 interdit `addressId` et `contactId` sur `Provider` ; le diagramme retenu les impose | Le diagramme l'emporte sur ce point, le prompt restant la référence hors modèle de données |
| G | Deux mécanismes d'adresse coexistent : FK directe et `entity_addresses` polymorphe | Ce sont deux besoins distincts (« a une » vs « a des »), documenté dans `phase-3-database-decisions.md` §8 bis |

## 18. Décisions de nullabilité

| Colonne | Choix | Raison |
|---------|-------|--------|
| `providers.address_id`, `providers.contact_id` | nullable | `Provider "0..*" --> "0..1" Address` : le `0..1` est explicite au diagramme |
| `drivers.address_id`, `drivers.contact_id` | nullable | Même relation `0..1` |
| Tout le reste | non nullable | Le diagramme ne marque aucune autre optionnalité |

## 19. Décisions de suppression

| Clé étrangère | Stratégie |
|---------------|-----------|
| `providers.organization_id` | `RESTRICT` |
| `providers.address_id`, `providers.contact_id` | `RESTRICT` |
| `drivers.organization_id` | `RESTRICT` |
| `drivers.provider_id` | `RESTRICT` |
| `drivers.address_id`, `drivers.contact_id` | `RESTRICT` |
| `vehicle_types.organization_id` | `RESTRICT` |
| `vehicles.provider_id` | `RESTRICT` |
| `vehicles.vehicle_type_id` | `RESTRICT` |

Aucun `cascadeOnDelete()`. Refus applicatifs en 409 avant que SQL n'intervienne :
fournisseur avec ressources, type de véhicule utilisé.

Détail complet dans [`phase-3-database-decisions.md`](phase-3-database-decisions.md).

## 20. Fichiers créés

**34 fichiers.**

Migrations (4), modèles (4), DTOs (8), Actions (12), exceptions (2), services
(`ProviderScopeGuard`), Query Objects (4), Form Requests (11), Resources (12),
Policies (4), Controllers (4), factories (4), seeder (`DemoFleetSeeder`),
supports partagés (`AuditContext`, `PartialAttributes`,
`BelongsToActiveOrganization`, `BuildsAuditContext`), tests (4), documentation
(`phase-3-analysis.md`, `phase-3-database-decisions.md`,
`phase-3-api-examples.md`, `phase-3-final-report.md`).

## 21. Fichiers modifiés

**6 fichiers**, tous en ajout, sans réécriture des Phases 1 et 2 :

| Fichier | Modification |
|---------|--------------|
| `routes/api.php` | 20 routes ajoutées |
| `database/seeders/PermissionSeeder.php` | 16 permissions ajoutées |
| `database/seeders/DatabaseSeeder.php` | `DemoFleetSeeder` enregistré |
| `app/Providers/AuthServiceProvider.php` | 4 policies enregistrées |
| `app/Shared/Database/MorphMap.php` | 4 alias ajoutés |
| `app/Modules/Audit/Actions/WriteAuditLog.php` | Paramètre optionnel `$ipAddress`, pour que les Actions journalisent sans dépendre d'une `Request` |

## 22. Éléments volontairement non implémentés car absents du diagramme officiel

```text
ProviderContract
ProviderContractVersion
ProviderContractDriver
ProviderContractVehicle
ProviderContractDocument
ProviderPriceList
DriverAvailability
VehicleAvailability
VehicleCapacity
```

Au niveau des attributs et conventions :

- `ProviderStatus`, `DriverStatus`, `VehicleStatus`, `VehicleTypeStatus` — ces
  propriétés sont des `string` ;
- `providerType`, `legacyId` — absents du diagramme retenu ;
- `settings`, `metadata`, `taxNumber` ;
- `registrationNumber`, `phone`, `email` sur `Provider` ;
- permis de conduire, `employee_number`, `license_number`,
  `license_expiration`, `active`, `availability` sur `Driver` ;
- `name`, `emission_type`, `gps_provider`, `gps_external_id`, `availability`,
  capacité séparée, documents spécifiques, dates d'entretien sur `Vehicle` ;
- capacité par défaut, description, `emission_type` sur `VehicleType` ;
- `created_at`, `updated_at`, `deleted_at` sur les quatre tables.

Vérifié : aucune table `contract` ni `availability` dans les migrations.

## 23. Risques restants

1. **Les `.puml` officiels sont absents.** Le modèle livré suit le prompt, pas un
   diagramme vérifiable. Toute divergence entre les deux ne sera découverte
   qu'à la lecture humaine. C'est le risque principal de cette phase.
2. **Suppression de chauffeur et de véhicule non protégée par les tournées.** Le
   §24 l'exige, le module Tours n'existe pas. Le contrôle **doit** être ajouté
   dans la phase Planification, sinon supprimer un chauffeur laissera des
   tournées orphelines.
3. **Absence de timestamps.** Conforme au diagramme, mais la date de création
   d'un fournisseur n'est lisible que dans `audit_logs`. Si l'exploitation en a
   besoin sur la ligne, il faudra amender le diagramme.
4. **Immatriculation unique globalement.** Deux organisations ne peuvent pas
   référencer le même véhicule physique. Sans objet aujourd'hui ; à revoir si un
   sous-traitant travaille un jour pour deux transporteurs de la plateforme.
5. **`status` sans valeurs normatives.** Rien n'empêche deux agences de saisir
   `active` et `ACTIVE`. Une liste officielle, une fois définie, permettra de
   créer les enums.
6. **Aucun `legacy_id`.** Une reprise depuis l'ancienne plateforme devra passer
   par une table de correspondance dédiée, à décider le moment venu.
7. **Deux mécanismes d'adresse coexistent** — FK directe sur `providers`,
   `drivers`, `customer_sites`, `order_services` ; `entity_addresses` polymorphe
   ailleurs. C'est ce que posent les diagrammes ; à documenter côté conception
   pour éviter qu'un développeur ne choisisse au hasard.

## 24. Prochaine phase recommandée

**Planification et tournées** — `Tour`, `TourStop`, `TourStopService`,
`TourPeriod`, `TourPeriodAssignment`, ainsi que les enums `TourStatus` et
`TourStopStatus`, tous présents dans le diagramme interne.

C'est la suite naturelle : les tournées consomment directement les fournisseurs,
chauffeurs et véhicules livrés ici, et les `OrderService` livrés en Phase 2 —
dont la note du diagramme précise que « les stops sont générés automatiquement
après planification des services ».

Cette phase permettra aussi de fermer le risque n°2 : les refus de suppression
d'un chauffeur ou d'un véhicule référencé par une tournée deviendront
implémentables.

À trancher avant de la démarrer : les statuts `PARTIALLY_PLANNED`, `PLANNED` et
`IN_PROGRESS` d'`OrderStatus` sont volontairement non assignables manuellement
depuis la Phase 2 — c'est la planification qui devra les poser.

Ne pas démarrer sans validation explicite.
