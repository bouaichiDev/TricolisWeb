import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { InvoiceCreatePage } from '@/modules/billing/pages/InvoiceCreatePage'
import { InvoiceDetailPage } from '@/modules/billing/pages/InvoiceDetailPage'
import { InvoiceListPage } from '@/modules/billing/pages/InvoiceListPage'
import { ExportConfigurationListPage } from '@/modules/exports/pages/ExportConfigurationListPage'
import { ExportJobListPage } from '@/modules/exports/pages/ExportJobListPage'
import { SettlementCreatePage } from '@/modules/providerSettlements/pages/SettlementCreatePage'
import { SettlementDetailPage } from '@/modules/providerSettlements/pages/SettlementDetailPage'
import { SettlementListPage } from '@/modules/providerSettlements/pages/SettlementListPage'

/**
 * Facturation : les factures clients et les décomptes fournisseurs.
 *
 * La clôture n'a pas d'écran à elle — elle se déclenche depuis la facture,
 * parce que c'est là qu'on voit ce qu'on fige. Le §24 refuse par ailleurs une
 * action d'envoi séparée : l'envoi suit la clôture.
 *
 * Les décomptes vivent sous le même préfixe sans partager ce mécanisme : le
 * §108 refuse de les transmettre par les configurations d'export client.
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
  <Route
    key="settlements"
    path="/billing/settlements"
    element={guarded('provider_settlements.view', <SettlementListPage />, { organizationOnly: true })}
  />,
  <Route
    key="settlement-create"
    path="/billing/settlements/create"
    element={guarded('provider_settlements.create', <SettlementCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="settlement-detail"
    path="/billing/settlements/:id"
    element={guarded('provider_settlements.view', <SettlementDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="export-configurations"
    path="/billing/export-configurations"
    element={guarded('customer_export_configurations.view', <ExportConfigurationListPage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="export-jobs"
    path="/billing/exports"
    element={guarded('export_jobs.view', <ExportJobListPage />, { organizationOnly: true })}
  />,
]
