# Matrice de sécurité des routes — portail client

> Généré par `php artisan customer-portal:route-matrix --output=docs/customer-portal/security-route-matrix.md`.
> Ne pas modifier à la main : régénérer après tout changement de route.
> `php artisan customer-portal:route-matrix --check` (et `PortalRouteSecurityTest`) échouent si une route sensible perd son autorisation.

| Méthode | URI | Authentification | Contexte | Permission | Isolation | Débit |
|---|---|---|---|---|---|---|
| GET | `api/v1/client/me` | Clé API | — | — | client de la clé API (CustomerApiContext) | customer-api |
| GET | `api/v1/client/orders` | Clé API | — | `orders.view` | client de la clé API (CustomerApiContext) | customer-api |
| GET | `api/v1/client/orders/{order}` | Clé API | — | `orders.view` | client de la clé API (CustomerApiContext) | customer-api |
| POST | `api/v1/customer/auth/activate` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 10,1 |
| POST | `api/v1/customer/auth/forgot-password` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 6,1 |
| GET | `api/v1/customer/auth/invitation` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 20,1 |
| POST | `api/v1/customer/auth/login` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | customer-portal-login |
| POST | `api/v1/customer/auth/logout` | Sanctum (jeton portail) | customer.portal | — | jeton de la requête | customer-portal |
| GET | `api/v1/customer/auth/me` | Sanctum (jeton portail) | customer.portal | — | session (compte connecté) | customer-portal |
| POST | `api/v1/customer/auth/reset-password` | Aucune (public) | — | — | avant authentification : identifiants, jeton d’invitation ou de réinitialisation | 6,1 |
| GET | `api/v1/customer/calendar/orders` | Sanctum (jeton portail) | customer.portal | `customer.calendar.view` | CustomerCalendarQuery (client + organisation) | customer-portal |
| GET | `api/v1/customer/catalog-items` | Sanctum (jeton portail) | customer.portal | `customer.orders.view` | catalogue du client | customer-portal |
| GET | `api/v1/customer/catalog/catalogs` | Sanctum (jeton portail) | customer.portal | `customer.catalog.view` | CustomerCatalogScope (catalogues du client + organisation) | customer-portal |
| GET | `api/v1/customer/catalog/items` | Sanctum (jeton portail) | customer.portal | `customer.catalog.view`<br>`customer.articles.view` | CustomerCatalogScope (catalogues du client + organisation) | customer-portal |
| POST | `api/v1/customer/catalog/items` | Sanctum (jeton portail) | customer.portal | `customer.catalog.view`<br>`customer.articles.create` | CustomerCatalogScope (catalogues du client + organisation) | customer-portal |
| GET | `api/v1/customer/catalog/items/{item}` | Sanctum (jeton portail) | customer.portal | `customer.catalog.view`<br>`customer.articles.view` | CustomerCatalogScope (catalogues du client + organisation) | customer-portal |
| PATCH | `api/v1/customer/catalog/items/{item}` | Sanctum (jeton portail) | customer.portal | `customer.catalog.view`<br>`customer.articles.update` | CustomerCatalogScope (catalogues du client + organisation) | customer-portal |
| POST | `api/v1/customer/catalog/items/{item}/activate` | Sanctum (jeton portail) | customer.portal | `customer.catalog.view`<br>`customer.articles.update` | CustomerCatalogScope (catalogues du client + organisation) | customer-portal |
| POST | `api/v1/customer/catalog/items/{item}/deactivate` | Sanctum (jeton portail) | customer.portal | `customer.catalog.view`<br>`customer.articles.update` | CustomerCatalogScope (catalogues du client + organisation) | customer-portal |
| GET | `api/v1/customer/claims` | Sanctum (jeton portail) | customer.portal | `customer.claims.view` | CustomerClaimListQuery / réclamation du client | customer-portal |
| POST | `api/v1/customer/claims` | Sanctum (jeton portail) | customer.portal | `customer.claims.create` | CustomerClaimListQuery / réclamation du client | customer-portal |
| GET | `api/v1/customer/claims/{claim}` | Sanctum (jeton portail) | customer.portal | `customer.claims.view` | CustomerClaimListQuery / réclamation du client | customer-portal |
| PATCH | `api/v1/customer/claims/{claim}` | Sanctum (jeton portail) | customer.portal | `customer.claims.update` | CustomerClaimListQuery / réclamation du client | customer-portal |
| GET | `api/v1/customer/claims/{claim}/documents` | Sanctum (jeton portail) | customer.portal | `customer.claims.view` | CustomerClaimListQuery / réclamation du client | customer-portal |
| POST | `api/v1/customer/claims/{claim}/documents` | Sanctum (jeton portail) | customer.portal | `customer.claims.create` | CustomerClaimListQuery / réclamation du client | customer-portal |
| GET | `api/v1/customer/context` | Sanctum (jeton portail) | customer.portal | — | session (compte connecté) | customer-portal |
| GET | `api/v1/customer/dashboard` | Sanctum (jeton portail) | customer.portal | — | CustomerDashboardComposer | customer-portal |
| GET | `api/v1/customer/documents/{document}` | Sanctum (jeton portail) | customer.portal | — | CustomerDocumentAccess | customer-portal |
| GET | `api/v1/customer/documents/{document}/download` | Sanctum (jeton portail) | customer.portal | — | CustomerDocumentAccess | customer-portal |
| GET | `api/v1/customer/integrations` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view` | CustomerIntegrationScope | customer-portal |
| GET | `api/v1/customer/integrations/api-configurations` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view` | CustomerIntegrationScope | customer-portal |
| GET | `api/v1/customer/integrations/export-configurations` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view` | CustomerIntegrationScope | customer-portal |
| GET | `api/v1/customer/integrations/export-jobs` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.logs.view` | CustomerIntegrationScope | customer-portal |
| GET | `api/v1/customer/integrations/export-jobs/{exportJob}` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.logs.view` | CustomerIntegrationScope | customer-portal |
| POST | `api/v1/customer/integrations/export-jobs/{exportJob}/retry` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.export_jobs.retry` | CustomerIntegrationScope | customer-portal<br>customer-portal-retry |
| GET | `api/v1/customer/integrations/import-configurations` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view` | CustomerIntegrationScope | customer-portal |
| GET | `api/v1/customer/integrations/import-runs` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.logs.view` | CustomerIntegrationScope | customer-portal |
| GET | `api/v1/customer/integrations/import-runs/{importRun}` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.logs.view` | CustomerIntegrationScope | customer-portal |
| POST | `api/v1/customer/integrations/imports/execute` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.import` | CustomerIntegrationScope | customer-portal<br>customer-portal-import |
| POST | `api/v1/customer/integrations/imports/preview` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.import` | CustomerIntegrationScope | customer-portal<br>customer-portal-import |
| GET | `api/v1/customer/integrations/imports/{token}` | Sanctum (jeton portail) | customer.portal | `customer.integrations.view`<br>`customer.integrations.import` | CustomerIntegrationScope | customer-portal |
| GET | `api/v1/customer/order-form-options` | Sanctum (jeton portail) | customer.portal | `customer.orders.view` | options du client | customer-portal |
| GET | `api/v1/customer/orders` | Sanctum (jeton portail) | customer.portal | `customer.orders.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| POST | `api/v1/customer/orders` | Sanctum (jeton portail) | customer.portal | `customer.orders.create` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}` | Sanctum (jeton portail) | customer.portal | `customer.orders.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| PUT | `api/v1/customer/orders/{order}` | Sanctum (jeton portail) | customer.portal | `customer.orders.update` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| POST | `api/v1/customer/orders/{order}/cancel` | Sanctum (jeton portail) | customer.portal | `customer.orders.cancel` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/documents` | Sanctum (jeton portail) | customer.portal | `customer.orders.documents.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| POST | `api/v1/customer/orders/{order}/documents` | Sanctum (jeton portail) | customer.portal | `customer.orders.documents.upload` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/documents/{document}/download` | Sanctum (jeton portail) | customer.portal | `customer.orders.documents.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| POST | `api/v1/customer/orders/{order}/duplicate` | Sanctum (jeton portail) | customer.portal | `customer.orders.duplicate` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/history` | Sanctum (jeton portail) | customer.portal | `customer.orders.history.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/lines` | Sanctum (jeton portail) | customer.portal | `customer.orders.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/packages` | Sanctum (jeton portail) | customer.portal | `customer.orders.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/proofs-of-delivery` | Sanctum (jeton portail) | customer.portal | `customer.orders.proofs_of_delivery.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| DELETE | `api/v1/customer/orders/{order}/public-tracking-link` | Sanctum (jeton portail) | customer.portal | `customer.tracking.public_link.manage` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/public-tracking-link` | Sanctum (jeton portail) | customer.portal | `customer.tracking.public_link.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| POST | `api/v1/customer/orders/{order}/public-tracking-link` | Sanctum (jeton portail) | customer.portal | `customer.tracking.public_link.manage` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/services` | Sanctum (jeton portail) | customer.portal | `customer.orders.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/services/{service}/documents` | Sanctum (jeton portail) | customer.portal | `customer.orders.documents.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/services/{service}/proofs-of-delivery` | Sanctum (jeton portail) | customer.portal | `customer.orders.proofs_of_delivery.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/tracking` | Sanctum (jeton portail) | customer.portal | `customer.tracking.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/orders/{order}/tracking/services/{service}` | Sanctum (jeton portail) | customer.portal | `customer.tracking.view` | ResolvesCustomerOrder (client + organisation) | customer-portal |
| GET | `api/v1/customer/permissions` | Sanctum (jeton portail) | customer.portal | — | session (compte connecté) | customer-portal |
| GET | `api/v1/customer/profile` | Sanctum (jeton portail) | customer.portal | — | session (compte connecté) | customer-portal |
| PUT | `api/v1/customer/profile` | Sanctum (jeton portail) | customer.portal | — | session (compte connecté) | customer-portal |
| GET | `api/v1/customer/proofs-of-delivery/{proofOfDelivery}` | Sanctum (jeton portail) | customer.portal | `customer.orders.proofs_of_delivery.view` | CustomerProofsOfDelivery | customer-portal |
| GET | `api/v1/customer/roles` | Sanctum (jeton portail) | customer.portal | `customer.roles.view` | rôles portail de l’organisation | customer-portal |
| GET | `api/v1/customer/stock/adjustment-requests` | Sanctum (jeton portail) | customer.portal | `customer.stock.adjustment_requests.view` | CustomerStockScope | customer-portal |
| POST | `api/v1/customer/stock/adjustment-requests` | Sanctum (jeton portail) | customer.portal | `customer.stock.adjustment_requests.create` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/adjustment-requests/{stockAdjustmentRequest}` | Sanctum (jeton portail) | customer.portal | `customer.stock.adjustment_requests.view` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/catalog-items` | Sanctum (jeton portail) | customer.portal | `customer.stock.items.create` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/items` | Sanctum (jeton portail) | customer.portal | `customer.stock.view` | CustomerStockScope | customer-portal |
| POST | `api/v1/customer/stock/items` | Sanctum (jeton portail) | customer.portal | `customer.stock.items.create` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/items/{stockItem}` | Sanctum (jeton portail) | customer.portal | `customer.stock.view` | CustomerStockScope | customer-portal |
| PATCH | `api/v1/customer/stock/items/{stockItem}` | Sanctum (jeton portail) | customer.portal | `customer.stock.items.update` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/items/{stockItem}/balances` | Sanctum (jeton portail) | customer.portal | `customer.stock.view` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/movements` | Sanctum (jeton portail) | customer.portal | `customer.stock.movements.view` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/movements/{stockMovement}` | Sanctum (jeton portail) | customer.portal | `customer.stock.movements.view` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/reservations` | Sanctum (jeton portail) | customer.portal | `customer.stock.reservations.view` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/reservations/{stockReservation}` | Sanctum (jeton portail) | customer.portal | `customer.stock.reservations.view` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/stock/summary` | Sanctum (jeton portail) | customer.portal | `customer.stock.view` | CustomerStockScope | customer-portal |
| GET | `api/v1/customer/users` | Sanctum (jeton portail) | customer.portal | `customer.users.view` | CustomerUserAccess (même client) | customer-portal |
| POST | `api/v1/customer/users/invite` | Sanctum (jeton portail) | customer.portal | `customer.users.invite` | CustomerUserAccess (même client) | customer-portal |
| GET | `api/v1/customer/users/{id}` | Sanctum (jeton portail) | customer.portal | `customer.users.view` | CustomerUserAccess (même client) | customer-portal |
| PUT | `api/v1/customer/users/{id}` | Sanctum (jeton portail) | customer.portal | `customer.users.update` | CustomerUserAccess (même client) | customer-portal |
| POST | `api/v1/customer/users/{id}/disable` | Sanctum (jeton portail) | customer.portal | `customer.users.disable` | CustomerUserAccess (même client) | customer-portal |
| POST | `api/v1/customer/users/{id}/enable` | Sanctum (jeton portail) | customer.portal | `customer.users.disable` | CustomerUserAccess (même client) | customer-portal |
| POST | `api/v1/customer/users/{id}/resend-invitation` | Sanctum (jeton portail) | customer.portal | `customer.users.invite` | CustomerUserAccess (même client) | customer-portal |
| PUT | `api/v1/customer/users/{id}/roles` | Sanctum (jeton portail) | customer.portal | `customer.users.assign_role` | CustomerUserAccess (même client) | customer-portal |
| GET | `api/v1/public/tracking/{token}` | Aucune (public) | — | — | jeton public aléatoire (PublicTrackingLinks) | public-tracking |
| GET | `api/v1/public/tracking/{token}/logo` | Aucune (public) | — | — | jeton public aléatoire (PublicTrackingLinks) | public-tracking |
