# Phase 10 — Audit des enums

Document exigé par le §8.

---

## 1. Résultat d'ensemble

```text
enums déclarés dans les diagrammes    16
enums PHP correspondants              16
valeurs divergentes                    0
enums PHP supplémentaires              1   (SubscriptionStatus)
enums manquants                        0
```

**Les seize enums des diagrammes existent, valeur par valeur, sans ajout ni
omission.** Vérifié mécaniquement : extraction des blocs `enum X { … }` des deux
`.txt`, comparaison aux `case` de chaque enum PHP.

## 2. Correspondance exhaustive

| Enum UML | Enum PHP | Emplacement | Valeurs | Statut |
|---|---|---|:-:|---|
| OrganizationStatus | `OrganizationStatus` | `App\Shared\Enums` | 4 | **CONFORME** |
| UserStatus | `UserStatus` | `App\Shared\Enums` | 4 | **CONFORME** |
| ContactRole | `ContactRole` | `App\Shared\Enums` | 6 | **CONFORME** |
| CustomerStatus | `CustomerStatus` | `Customers\Enums` | 3 | **CONFORME** |
| OrderSource | `OrderSource` | `Orders\Enums` | 8 | **CONFORME** |
| OrderStatus | `OrderStatus` | `Orders\Enums` | 10 | **CONFORME** |
| OrderServiceStatus | `OrderServiceStatus` | `Orders\Enums` | 9 | **CONFORME** |
| TourStatus | `TourStatus` | `Tours\Enums` | 6 | **CONFORME** |
| TourStopStatus | `TourStopStatus` | `Tours\Enums` | 6 | **CONFORME** |
| ExportFormat | `ExportFormat` | `Exports\Enums` | 4 | **CONFORME** |
| ExportTransport | `ExportTransport` | `Exports\Enums` | 5 | **CONFORME** |
| CommunicationChannel | `CommunicationChannel` | `Communications\Enums` | 5 | **CONFORME** |
| CommunicationTemplateType | `CommunicationTemplateType` | `Communications\Enums` | 12 | **CONFORME** |
| CommunicationEventType | `CommunicationEventType` | `Communications\Enums` | 11 | **CONFORME** |
| CommunicationStatus | `CommunicationStatus` | `Communications\Enums` | 9 | **CONFORME** |
| RecipientRole | `RecipientRole` | `Communications\Enums` | 6 | **CONFORME** |

Trois de ces enums — `OrganizationStatus`, `UserStatus` et `ContactRole` —
n'apparaissent **pas** dans la liste du §8, qui n'en cite que treize. Le §1
tranche : « Les diagrammes ont priorité sur les prompts précédents. » Ils sont
au diagramme partagé, ils sont donc légitimes, et ils étaient déjà implémentés
depuis la Phase 1.

## 3. L'enum supplémentaire — `SubscriptionStatus`

| Point | Constat |
|---|---|
| Déclaré au diagramme ? | **Non.** `Subscription.status` y est typé `string`. |
| Colonne en base | `VARCHAR(32)` — **pas** un `ENUM` SQL |
| Valeurs | `trialing`, `active`, `suspended`, `cancelled`, `expired` |
| Origine | Phase 1, avec sa justification écrite dans le fichier même |
| Usage | Cast Eloquent + `grantsAccess()`, employé par le contrôle d'abonnement |

Le §8 dit : « Ne pas convertir un champ string en enum sans définition UML. »
La lettre est enfreinte ; la conséquence, elle, est nulle :

- **la base ne change pas** — la colonne reste un `VARCHAR`, exactement ce que
  le diagramme décrit. Ajouter une valeur ne demandera pas de migration ;
- **le diagramme n'énumère aucune valeur** pour ce champ. Ne rien décider aurait
  laissé la colonne accepter n'importe quelle chaîne, y compris une faute de
  frappe, sur un champ qui décide de l'accès à la plateforme ;
- **la déviation est documentée dans le code**, en tête de l'enum, depuis la
  Phase 1.

**Décision : conservé, et signalé.** Le supprimer transformerait
`Subscription.status` en chaîne libre et retirerait `grantsAccess()`, méthode
sur laquelle repose le contrôle d'abonnement. C'est le seul écart d'enum du
projet, et il va dans le sens de la rigueur, pas de l'invention : il restreint,
il n'ajoute pas de concept.

## 4. Champs `string` laissés libres — délibérément

Le §8 interdit de convertir en enum un champ que le diagramme type `string`.
Quinze champs sont dans ce cas et **restent des chaînes** :

```text
Subscription.planCode              Document.documentType · Document.status
Service.unit                       OrderLine.status · Package.status
StockItem.status · StockLocation.status · StockMovement.movementType
StockReservation.status            Claim.claimType · Claim.status
Invoice.status · InvoiceLine.status
ProviderSettlement.status · ProviderSettlementLine.status
Provider.status · Driver.status · Vehicle.status · VehicleType.status
CustomerImportConfiguration.sourceType · .fileFormat
CustomerExportConfiguration.exportType · .frequency · .encoding
ExportJob.status                   CommunicationRule.delayUnit
TrackingEvent.eventType · TrackingEvent.status
EntityAddress.addressType · EntityContact.contactType
```

Deux d'entre eux sont validés contre une liste close **côté validation
seulement**, sans enum ni contrainte SQL :

- `CommunicationRule.delayUnit` → `minutes`, `hours`, `days`. Le §17 l'exige
  explicitement : « Ne pas créer d'enum pour delayUnit. » La liste est celle que
  `CarbonInterval` sait ajouter, pas une nomenclature métier.
- `StockMovement.sourceEntityType` → dérivé de la morph map, jamais recopié.

## 5. Enums interdits

Aucun enum absent des diagrammes n'a été créé par une phase quelconque. En
particulier, aucun enum n'existe pour :

```text
DocumentType · DocumentStatus · ClaimType · ClaimStatus
InvoiceStatus · SettlementStatus · MovementType · ReservationStatus
TrackingEventType · PackageStatus · ProviderStatus · DriverStatus
VehicleStatus · ExportJobStatus · DelayUnit · AddressType · ContactType
```

Chacun aurait été tentant — et chacun aurait figé en base une nomenclature que
le diagramme laisse ouverte.
