import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { CustomerCreatePage } from '@/modules/customers/pages/CustomerCreatePage'
import { CustomerDetailPage } from '@/modules/customers/pages/CustomerDetailPage'
import { CustomerEditPage } from '@/modules/customers/pages/CustomerEditPage'
import { CustomerListPage } from '@/modules/customers/pages/CustomerListPage'
import { CustomerSiteCreatePage } from '@/modules/customerSites/pages/CustomerSiteCreatePage'
import { CustomerSiteDetailPage } from '@/modules/customerSites/pages/CustomerSiteDetailPage'
import { CustomerSiteEditPage } from '@/modules/customerSites/pages/CustomerSiteEditPage'

export const customerRoutes = [
  <Route key="list" path="/customers" element={guarded('customers.view', <CustomerListPage />)} />,
  <Route
    key="create"
    path="/customers/create"
    element={guarded('customers.create', <CustomerCreatePage />)}
  />,
  <Route
    key="detail"
    path="/customers/:id"
    element={guarded('customers.view', <CustomerDetailPage />)}
  />,
  <Route
    key="edit"
    path="/customers/:id/edit"
    element={guarded('customers.update', <CustomerEditPage />)}
  />,
  <Route
    key="site-create"
    path="/customers/:customerId/sites/create"
    element={guarded('customer_sites.create', <CustomerSiteCreatePage />)}
  />,
  <Route
    key="site-detail"
    path="/customers/:customerId/sites/:siteId"
    element={guarded('customer_sites.view', <CustomerSiteDetailPage />)}
  />,
  <Route
    key="site-edit"
    path="/customers/:customerId/sites/:siteId/edit"
    element={guarded('customer_sites.update', <CustomerSiteEditPage />)}
  />,
]
