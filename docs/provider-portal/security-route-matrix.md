# Matrice de sécurité des routes — portail fournisseur

> Généré par `php artisan provider-portal:route-matrix --output=docs/provider-portal/security-route-matrix.md`.
> Ne pas modifier à la main : régénérer après tout changement de route.
> `php artisan provider-portal:route-matrix --check` (et `ProviderRouteGuardsTest`) échouent si une route perd une protection.

| Méthode | URI | Authentification | Contexte | Permission | Isolation | Débit |
|---|---|---|---|---|---|---|
| POST | `api/v1/provider/auth/activate` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 10,1 |
| POST | `api/v1/provider/auth/forgot-password` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 6,1 |
| GET | `api/v1/provider/auth/invitation` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 20,1 |
| POST | `api/v1/provider/auth/login` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | provider-portal-login |
| POST | `api/v1/provider/auth/logout` | Sanctum (jeton portail fournisseur) | provider.portal | — | jeton de la requête | provider-portal |
| GET | `api/v1/provider/auth/me` | Sanctum (jeton portail fournisseur) | provider.portal | — | session (compte connecté) | provider-portal |
| POST | `api/v1/provider/auth/reset-password` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 6,1 |
| GET | `api/v1/provider/communications` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.communications.view` | ProviderCommunications (adresse du membre + organisation) | provider-portal |
| GET | `api/v1/provider/communications/{communication}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.communications.view` | ProviderCommunications (adresse du membre + organisation) | provider-portal |
| GET | `api/v1/provider/dashboard` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.dashboard.view` | ProviderDashboardComposer (fournisseur + organisation) | provider-portal |
| GET | `api/v1/provider/documents/{document}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.documents.view` | ProviderDocumentAccess (tournée visible, service acquis) | provider-portal |
| GET | `api/v1/provider/drivers` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.drivers.view` | ProviderResourceScope (fournisseur + organisation) | provider-portal |
| POST | `api/v1/provider/drivers` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.drivers.create` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
| GET | `api/v1/provider/drivers/{driver}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.drivers.view` | ProviderResourceScope (fournisseur + organisation) | provider-portal |
| PATCH | `api/v1/provider/drivers/{driver}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.drivers.update` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
| POST | `api/v1/provider/drivers/{driver}/activate` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.drivers.update` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
| POST | `api/v1/provider/drivers/{driver}/deactivate` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.drivers.update` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
| GET | `api/v1/provider/navigation` | Sanctum (jeton portail fournisseur) | provider.portal | — | session (compte et fournisseur du jeton) | provider-portal |
| GET | `api/v1/provider/profile` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.profile.view` | session (compte et fournisseur du jeton) | provider-portal |
| PUT | `api/v1/provider/profile` | Sanctum (jeton portail fournisseur) | provider.portal | — | session (compte et fournisseur du jeton) | provider-portal |
| PUT | `api/v1/provider/profile/password` | Sanctum (jeton portail fournisseur) | provider.portal | — | session (compte et fournisseur du jeton) | provider-portal |
| GET | `api/v1/provider/settlements` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.settlements.view` | ProviderSettlementScope (fournisseur + organisation, clôturés) | provider-portal |
| GET | `api/v1/provider/settlements/{settlement}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.settlements.view` | ProviderSettlementScope (fournisseur + organisation, clôturés) | provider-portal |
| GET | `api/v1/provider/settlements/{settlement}/documents` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.settlements.view`<br>`provider.settlements.documents.view` | ProviderSettlementScope (fournisseur + organisation, clôturés) | provider-portal |
| GET | `api/v1/provider/settlements/{settlement}/documents/{document}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.settlements.view`<br>`provider.settlements.documents.view` | ProviderSettlementScope (fournisseur + organisation, clôturés) | provider-portal |
| GET | `api/v1/provider/tours` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal |
| GET | `api/v1/provider/tours/calendar` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.calendar.view` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal |
| GET | `api/v1/provider/tours/{tour}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal |
| POST | `api/v1/provider/tours/{tour}/complete` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.tours.execute` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal<br>provider-portal-execute |
| GET | `api/v1/provider/tours/{tour}/documents` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.documents.view` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal |
| PATCH | `api/v1/provider/tours/{tour}/resources` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.tours.assign-resources` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal<br>provider-portal-write |
| POST | `api/v1/provider/tours/{tour}/start` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.tours.execute` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal<br>provider-portal-execute |
| POST | `api/v1/provider/tours/{tour}/stops/{stop}/arrive` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.tour-stops.update-status` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal<br>provider-portal-execute |
| POST | `api/v1/provider/tours/{tour}/stops/{stop}/complete` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.tour-stops.update-status` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal<br>provider-portal-execute |
| GET | `api/v1/provider/tours/{tour}/stops/{stop}/proofs` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.pod.view` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal |
| POST | `api/v1/provider/tours/{tour}/stops/{stop}/proofs` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.pod.create` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal<br>provider-portal-execute |
| POST | `api/v1/provider/tours/{tour}/stops/{stop}/skip` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.tour-stops.update-status` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal<br>provider-portal-execute |
| GET | `api/v1/provider/tours/{tour}/tracking` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.tours.view`<br>`provider.tracking.view` | ProviderTourScope (fournisseur + organisation, hors brouillon) | provider-portal |
| GET | `api/v1/provider/vehicle-types` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.vehicles.view` | référentiel de l’organisation | provider-portal |
| GET | `api/v1/provider/vehicles` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.vehicles.view` | ProviderResourceScope (fournisseur + organisation) | provider-portal |
| POST | `api/v1/provider/vehicles` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.vehicles.create` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
| GET | `api/v1/provider/vehicles/{vehicle}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.vehicles.view` | ProviderResourceScope (fournisseur + organisation) | provider-portal |
| PATCH | `api/v1/provider/vehicles/{vehicle}` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.vehicles.update` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
| POST | `api/v1/provider/vehicles/{vehicle}/activate` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.vehicles.update` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
| POST | `api/v1/provider/vehicles/{vehicle}/deactivate` | Sanctum (jeton portail fournisseur) | provider.portal | `provider.vehicles.update` | ProviderResourceScope (fournisseur + organisation) | provider-portal<br>provider-portal-write |
