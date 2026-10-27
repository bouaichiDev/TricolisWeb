# Portail fournisseur — matrice d'accès (Phase 1)

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

## Rôles par défaut (Phase 1)

| Rôle | `portal.access` | `dashboard.view` | `profile.view` |
|---|---|---|---|
| Administrateur fournisseur (`PROVIDER_ADMIN`) | ✓ | ✓ | ✓ |
| Exploitant (`PROVIDER_DISPATCHER`) | ✓ | ✓ | ✓ |
| Gestionnaire flotte (`PROVIDER_FLEET_MANAGER`) | ✓ | ✓ | ✓ |
| Lecture (`PROVIDER_VIEWER`) | ✓ | ✓ | ✓ |

Les phases suivantes ajoutent leurs permissions et différencient les rôles ;
`ProviderPortalSeeder` les accorde aux rôles existants au rejeu.
