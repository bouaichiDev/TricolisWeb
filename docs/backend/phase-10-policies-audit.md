# Phase 10 — Audit des Policies

Document exigé par le §11.

---

## 1. Résultat d'ensemble

```text
Policies déclarées            49
Ressources exposées par API   49 (couvertes)
Policies manquantes            0
Policies corrigées             5   (403 → 404 hors organisation)
```

Toutes héritent de `BaseOrganizationPolicy`, qui porte les trois primitives :
`hasOrganizationAccess()`, `hasPermission()` et — ajouté par cette phase —
`notFound()`.

## 2. Ce que vérifie chaque Policy

| Contrôle | Mécanisme | Couverture |
|---|---|---|
| Utilisateur authentifié | `auth:sanctum` sur le groupe de routes | 304 / 308 routes (4 publiques : login, register, forgot, reset) |
| Organisation active | `EnsureOrganizationContext` + `requireOrganizationId()` | 296 / 308 (12 exclues, voir l'audit des routes) |
| Permission | `hasPermission()` sur `OrganizationUser → roles → permissions` | Toutes les Policies |
| Parent correct | traits `ResolvesTourScope`, `ResolvesCustomerScope`, `ResolvesCommunicationScope` + `abort_unless(…, 404)` | Toutes les routes imbriquées |
| Ressource dans le périmètre | `scopeInOrganization()` sur les modèles, ou jointure vers le porteur d'`organization_id` | Tous les modèles métier |

**Le rôle seul n'autorise jamais rien.** `hasPermission()` traverse
`OrganizationUser → roles → permissions` et compare un **code de permission**.
La seule exception est `is_owner`, qui court-circuite le contrôle : le
propriétaire d'une organisation ne peut pas se verrouiller hors de sa propre
organisation.

## 3. La correction de cette phase

Cinq Policies des Phases 1 et 2 renvoyaient **403** pour une ressource d'une
autre organisation, là où vingt-et-une autres renvoyaient **404**.

| Policy | Avant | Après |
|---|---|---|
| `CustomerPolicy` | 403 | **404** |
| `DocumentPolicy` | 403 | **404** |
| `AddressPolicy` | 403 | **404** |
| `ContactPolicy` | 403 | **404** |
| `AgencyPolicy` | 403 | **404** |

### Pourquoi c'est une faille, pas une inélégance

Les deux refus ne doivent pas se distinguer. Un `403` sur un identifiant tiré au
hasard répond « cette ressource existe, mais pas pour vous » ; un `404` répond
« il n'y a rien ici ». Un attaquant qui énumère des ULID apprend, du premier, la
liste des identifiants valides du système entier — donc le volume d'activité
d'organisations concurrentes, et la validité d'un identifiant obtenu par
ailleurs.

L'incohérence aggravait le problème : vingt-et-une ressources se taisaient, cinq
parlaient. Il suffisait d'interroger `customers` pour valider un identifiant que
`orders` refusait de confirmer.

### Comment

`BaseOrganizationPolicy::notFound()` retourne
`Illuminate\Auth\Access\Response::denyAsNotFound()`. Le refus est donc décidé
**là où l'information de périmètre existe** — dans la Policy — et non par un
`abort(404)` recopié dans cinq contrôleurs.

Les deux cas restent distincts dans le code, et c'est le point :

```php
if (! $this->seesOrganization($user, $resource->organization_id)) {
    return $this->notFound();          // hors périmètre → 404
}

return $this->hasPermission($user, $resource->organization_id, $permission);
                                        // dans le périmètre, sans droit → 403
```

Un membre de l'organisation qui n'a pas la permission continue de recevoir
**403** : il doit savoir quoi demander à son administrateur.

`AddressPolicy` et `ContactPolicy` ont en outre gagné le contrôle de périmètre
sur `update` et `delete`, qu'elles n'appliquaient qu'à `view`.

### Ce que cela a changé dans les tests

Neuf assertions des Phases 1 et 2 attendaient `403` sur des cas explicitement
nommés « from another organization ». Elles attendent désormais `404`.

Ce n'est pas une assertion assouplie pour masquer une erreur — c'est le contrat
qui devient plus strict, dans le sens exigé par le §32. Aucun test n'a été
supprimé ni ignoré, et le nouveau `OrganizationIsolationTest` vérifie
désormais les **dix-sept** ressources de premier niveau d'un seul tenant.

## 4. Inventaire des Policies

| Domaine | Policies |
|---|---|
| Identité | `OrganizationPolicy`, `OrganizationUserPolicy`, `UserPolicy`, `RolePolicy`, `SubscriptionPolicy`, `AuditLogPolicy` |
| Réseau | `AgencyPolicy`, `DepotPolicy` |
| Tiers | `CustomerPolicy`, `CustomerSitePolicy`, `CustomerCatalogPolicy`, `ProviderPolicy`, `DriverPolicy` |
| Référentiels | `AddressPolicy`, `EntityAddressPolicy`, `ContactPolicy`, `EntityContactPolicy`, `ServicePolicy`, `PackageReferentialPolicy`, `VehicleTypePolicy`, `VehiclePolicy`, `DocumentPolicy` |
| Commandes | `OrderPolicy` |
| Planification | `TourPolicy`, `TourStopPolicy`, `TourStopServicePolicy`, `TourPeriodPolicy`, `TourPeriodAssignmentPolicy` |
| Exécution | `TrackingEventPolicy`, `ProofOfDeliveryPolicy`, `ClaimPolicy` |
| Facturation | `InvoicePolicy`, `InvoiceLinePolicy`, `ProviderSettlementPolicy`, `ProviderSettlementLinePolicy` |
| Stock | `StockItemPolicy`, `StockLocationPolicy`, `StockBalancePolicy`, `StockMovementPolicy`, `StockReservationPolicy` |
| Intégrations | `CustomerImportConfigurationPolicy`, `CustomerApiConfigurationPolicy`, `CustomerExportConfigurationPolicy`, `ExportJobPolicy` |
| Communication | `CommunicationTemplatePolicy`, `CommunicationRulePolicy`, `OrderCommunicationPolicy`, `CommunicationAttachmentPolicy` |

## 5. Actions distinctes du CRUD

Sept Policies exposent une capacité qui n'est ni `create`, ni `update`, ni
`delete`, parce que le geste n'en est pas un :

| Policy | Capacité | Raison |
|---|---|---|
| `CustomerPolicy` | `block` | **Ajouté en Phase 10.** Bloquer un client interrompt ses commandes. |
| `OrderPolicy` | `changeStatus`, `cancel`, `duplicate` | Changer l'état d'une commande engage l'exploitation. |
| `OrderCommunicationPolicy` | `queue`, `cancel`, `retry` | Déclenchent ou interrompent un envoi vers un tiers. |
| `CustomerApiConfigurationPolicy` | `rotateKey` | Coupe l'accès des intégrations en cours. |
| `ExportJobPolicy` | `retry` | Relance un envoi client. |
| `StockReservationPolicy` | `release` | Libère du stock engagé. |
| `TourStopPolicy`, `TourPeriodPolicy`, `TourStopServicePolicy` | `reorder` | Réécrit un ordre d'exécution. |

Un test vérifie, pour les communications, qu'`update` ne suffit à aucune des
trois transitions.
