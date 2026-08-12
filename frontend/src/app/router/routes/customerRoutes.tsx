import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { CustomerCreatePage } from '@/modules/customers/pages/CustomerCreatePage'
import { CustomerDetailPage } from '@/modules/customers/pages/CustomerDetailPage'
import { CustomerEditPage } from '@/modules/customers/pages/CustomerEditPage'
import { CustomerListPage } from '@/modules/customers/pages/CustomerListPage'
import { CustomerSiteCreatePage } from '@/modules/customerSites/pages/CustomerSiteCreatePage'
import { CustomerSiteDetailPage } from '@/modules/customerSites/pages/CustomerSiteDetailPage'
import { CustomerSiteEditPage } from '@/modules/customerSites/pages/CustomerSiteEditPage'

/**
 * Clients et sites client.
 *
 * `organizationOnly` sur chaque route : ces données appartiennent aux
 * organismes, pas à la plateforme. Un compte plateforme y est renvoyé vers son
 * propre périmètre plutôt que d'atteindre les clients de l'organisation dont il
 * se trouve techniquement membre.
 */
export const customerRoutes = [
  <Route key="list" path="/customers" element={guarded('customers.view', <CustomerListPage />, { organizationOnly: true })} />,
  <Route
    key="create"
    path="/customers/create"
    element={guarded('customers.create', <CustomerCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="detail"
    path="/customers/:id"
    element={guarded('customers.view', <CustomerDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="edit"
    path="/customers/:id/edit"
    element={guarded('customers.update', <CustomerEditPage />, { organizationOnly: true })}
  />,
  <Route
    key="site-create"
    path="/customers/:customerId/sites/create"
    element={guarded('customer_sites.create', <CustomerSiteCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="site-detail"
    path="/customers/:customerId/sites/:siteId"
    element={guarded('customer_sites.view', <CustomerSiteDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="site-edit"
    path="/customers/:customerId/sites/:siteId/edit"
    element={guarded('customer_sites.update', <CustomerSiteEditPage />, { organizationOnly: true })}
  />,
]
