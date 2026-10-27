# Portail fournisseur — matrice d'accès (phases 1 à 3)

## Routes du portail — `api/v1/provider`

| Méthode | Route | Chaîne | Permission | Limiteur |
|---|---|---|---|---|
| POST | `auth/login` | publique | — | `provider-portal-login` + 5 essais/min IP+e-mail |
| POST | `auth/forgot-password` | publique | — | 6/min |
| POST | `auth/reset-password` | publique | — | 6/min |
| GET | `auth/invitation` | publique (jeton d'invitation) | — | 20/min |
| POST | `auth/activate` | publique (jeton d'invitation) | — | 10/min |
| POST | `auth/logout` | `auth:sanctum` → `provider.portal` | `provider.portal.access` | `provider-portal` |
| GET | `auth/me` | idem | `provider.portal.access` | `provider-portal` |
| GET | `navigation` | idem | `provider.portal.access` | `provider-portal` |
| GET | `dashboard` | idem | `provider.dashboard.view` | `provider-portal` |
| GET | `profile` | idem | `provider.profile.view` | `provider-portal` |
| PUT | `profile` | idem | `provider.portal.access` (son propre compte) | `provider-portal` |
| PUT | `profile/password` | idem | `provider.portal.access` (son propre compte) | `provider-portal` |
| GET | `tours`, `tours/{id}` | idem | `provider.tours.view` | `provider-portal` |
| GET | `tours/calendar?from&to` (≤ 62 j) | idem | `provider.calendar.view` | `provider-portal` |
| PATCH | `tours/{id}/resources` | idem | `provider.tours.view` + `provider.tours.assign-resources` | `provider-portal-write` |
| GET | `drivers` | idem | `provider.drivers.view` | `provider-portal` |
| GET | `drivers/{id}` | idem | `provider.drivers.view` | `provider-portal` |
| POST | `drivers` | idem | `provider.drivers.create` | `provider-portal-write` (30/min) |
| PATCH | `drivers/{id}` | idem | `provider.drivers.update` | `provider-portal` |
| POST | `drivers/{id}/deactivate`, `drivers/{id}/activate` | idem | `provider.drivers.update` | `provider-portal` |
| GET | `vehicle-types` | idem | `provider.vehicles.view` | `provider-portal` |
| GET | `vehicles`, `vehicles/{id}` | idem | `provider.vehicles.view` | `provider-portal` |
| POST | `vehicles` | idem | `provider.vehicles.create` | `provider-portal-write` (30/min) |
| PATCH | `vehicles/{id}` | idem | `provider.vehicles.update` | `provider-portal` |
| POST | `vehicles/{id}/deactivate`, `vehicles/{id}/activate` | idem | `provider.vehicles.update` | `provider-portal` |

Chauffeurs et véhicules sont toujours lus dans le fournisseur **et** l'organisation
du jeton (`ProviderResourceScope`) : ceux d'un autre fournisseur, du transporteur
ou inexistants répondent le même 404. `providerId`, `organizationId`, `status`,
`userId`, `addressId`, `contactId` sont refusés (422). Un code, un e-mail ou une
immatriculation déjà pris est refusé sans dire par qui. Une ressource affectée à
une tournée planifiée, confirmée ou en cours ne se désactive pas (422).

Tournées : visibles si attribuées au fournisseur dans son organisation, hors brouillon ; sinon 404. Le détail ne
contient aucun prix, coût, facture, numéro de commande, référence externe ni client (liste blanche testée).
Affectation : tournée planifiée ou confirmée, non réservée par un planificateur, ressources du fournisseur et
actives, sans chevauchement de créneau, capacité suffisante ; vérifiée sous verrou puis confiée à `UpdateTourAction`.

Aucune route de gestion d'utilisateurs ni de rôles (vérifié en E2E : 404).

## Routes du back-office — gestion des accès

| Méthode | Route | Policy |
|---|---|---|
| GET | `providers/{provider}/portal-roles` | `providers.view` |
| GET | `providers/{provider}/portal-users` | `providers.view` |
| POST | `providers/{provider}/portal-users/invite` | `providers.update` |
| PATCH | `providers/{provider}/portal-users/{providerUser}` | `providers.update` |
| POST | `providers/{provider}/portal-users/{providerUser}/disable` | `providers.update` |
| POST | `providers/{provider}/portal-users/{providerUser}/enable` | `providers.update` |
| POST | `providers/{provider}/portal-users/{providerUser}/resend-invitation` | `providers.update` |

`{providerUser}` est toujours cherché dans le fournisseur de l'URL : un membre
d'un autre fournisseur répond 404. Ces routes refusent les jetons de portail.

## Rôles par défaut

| Rôle | Base¹ | Flotte : voir | Flotte : écrire | Affecter aux tournées |
|---|---|---|---|---|
| Administrateur fournisseur (`PROVIDER_ADMIN`) | ✓ | ✓ | ✓ | ✓ |
| Exploitant (`PROVIDER_DISPATCHER`) | ✓ | ✓ | — | ✓ |
| Gestionnaire flotte (`PROVIDER_FLEET_MANAGER`) | ✓ | ✓ | ✓ | — |
| Lecture (`PROVIDER_VIEWER`) | ✓ | ✓ | — | — |

¹ `provider.portal.access`, `provider.dashboard.view`, `provider.profile.view`, `provider.tours.view`, `provider.calendar.view`.

`ProviderPortalSeeder` accorde les permissions nouvelles aux rôles existants au
rejeu : `php artisan db:seed --class=ProviderPortalSeeder --force` après chaque phase.
