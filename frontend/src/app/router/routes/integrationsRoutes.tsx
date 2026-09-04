import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { ExportConfigurationDirectoryPage } from '@/modules/exports/pages/ExportConfigurationDirectoryPage'
import { ExportJobDetailPage } from '@/modules/exports/pages/ExportJobDetailPage'
import { ExportJobListPage } from '@/modules/exports/pages/ExportJobListPage'
import { CustomerApiConfigurationCreatePage } from '@/modules/integrations/pages/CustomerApiConfigurationCreatePage'
import { CustomerApiConfigurationDetailPage } from '@/modules/integrations/pages/CustomerApiConfigurationDetailPage'
import { CustomerApiConfigurationEditPage } from '@/modules/integrations/pages/CustomerApiConfigurationEditPage'
import { CustomerApiConfigurationListPage } from '@/modules/integrations/pages/CustomerApiConfigurationListPage'
import { CustomerImportConfigurationCreatePage } from '@/modules/integrations/pages/CustomerImportConfigurationCreatePage'
import { CustomerImportConfigurationDetailPage } from '@/modules/integrations/pages/CustomerImportConfigurationDetailPage'
import { CustomerImportConfigurationEditPage } from '@/modules/integrations/pages/CustomerImportConfigurationEditPage'
import { CustomerImportConfigurationListPage } from '@/modules/integrations/pages/CustomerImportConfigurationListPage'
import { IntegrationsHubPage } from '@/modules/integrations/pages/IntegrationsHubPage'

/**
 * Intégrations clients.
 *
 * `organizationOnly` partout : ces configurations appartiennent aux clients
 * d'un organisme, et un compte plateforme n'en a aucun.
 *
 * **Aucune route d'exécution d'import**, aucune route de modification ou de
 * suppression d'envoi, aucune route de téléchargement : le serveur ne les
 * expose pas. Une route qui mènerait à un 404 ou un 405 n'est pas un manque à
 * combler, c'est une promesse à ne pas faire.
 *
 * Les deux écrans d'export sont **les mêmes** que ceux de Facturation, montés
 * sous un second chemin : le §77 interdit une seconde implémentation, et
 * `/billing/export-configurations` comme `/billing/exports` continuent de
 * fonctionner (§48).
 */
export const integrationsRoutes = [
  <Route
    key="integrations"
    path="/integrations"
    element={guarded('customer_export_configurations.view', <IntegrationsHubPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="integrations-imports"
    path="/integrations/imports"
    element={guarded(
      'customer_import_configurations.view',
      <CustomerImportConfigurationListPage />,
      { organizationOnly: true },
    )}
  />,
  <Route
    key="integrations-imports-create"
    path="/integrations/imports/create"
    element={guarded(
      'customer_import_configurations.create',
      <CustomerImportConfigurationCreatePage />,
      { organizationOnly: true },
    )}
  />,
  <Route
    key="integrations-imports-detail"
    path="/integrations/imports/:id"
    element={guarded(
      'customer_import_configurations.view',
      <CustomerImportConfigurationDetailPage />,
      { organizationOnly: true },
    )}
  />,
  <Route
    key="integrations-imports-edit"
    path="/integrations/imports/:id/edit"
    element={guarded(
      'customer_import_configurations.update',
      <CustomerImportConfigurationEditPage />,
      { organizationOnly: true },
    )}
  />,

  <Route
    key="integrations-api-access"
    path="/integrations/api-access"
    element={guarded('customer_api_configurations.view', <CustomerApiConfigurationListPage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="integrations-api-access-create"
    path="/integrations/api-access/create"
    element={guarded(
      'customer_api_configurations.create',
      <CustomerApiConfigurationCreatePage />,
      { organizationOnly: true },
    )}
  />,
  <Route
    key="integrations-api-access-detail"
    path="/integrations/api-access/:id"
    element={guarded('customer_api_configurations.view', <CustomerApiConfigurationDetailPage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="integrations-api-access-edit"
    path="/integrations/api-access/:id/edit"
    element={guarded(
      'customer_api_configurations.update',
      <CustomerApiConfigurationEditPage />,
      { organizationOnly: true },
    )}
  />,

  <Route
    key="integrations-exports"
    path="/integrations/exports"
    element={guarded(
      'customer_export_configurations.view',
      <ExportConfigurationDirectoryPage />,
      { organizationOnly: true },
    )}
  />,

  <Route
    key="integrations-export-jobs"
    path="/integrations/export-jobs"
    element={guarded('export_jobs.view', <ExportJobListPage />, { organizationOnly: true })}
  />,
  <Route
    key="integrations-export-jobs-detail"
    path="/integrations/export-jobs/:id"
    element={guarded('export_jobs.view', <ExportJobDetailPage />, { organizationOnly: true })}
  />,
]
