import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { InvoiceCreatePage } from '@/modules/billing/pages/InvoiceCreatePage'
import { InvoiceDetailPage } from '@/modules/billing/pages/InvoiceDetailPage'
import { InvoiceListPage } from '@/modules/billing/pages/InvoiceListPage'

/**
 * Facturation : les factures clients.
 *
 * La clôture n'a pas d'écran à elle — elle se déclenche depuis la facture,
 * parce que c'est là qu'on voit ce qu'on fige. Le §24 refuse par ailleurs une
 * action d'envoi séparée : l'envoi suit la clôture.
 */
export const billingRoutes = [
  <Route
    key="invoices"
    path="/billing/invoices"
    element={guarded('invoices.view', <InvoiceListPage />, { organizationOnly: true })}
  />,
  <Route
    key="invoice-create"
    path="/billing/invoices/create"
    element={guarded('invoices.create', <InvoiceCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="invoice-detail"
    path="/billing/invoices/:id"
    element={guarded('invoices.view', <InvoiceDetailPage />, { organizationOnly: true })}
  />,
]
