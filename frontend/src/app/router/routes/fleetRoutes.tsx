import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { DriverCreatePage } from '@/modules/drivers/pages/DriverCreatePage'
import { DriverDetailPage } from '@/modules/drivers/pages/DriverDetailPage'
import { DriverEditPage } from '@/modules/drivers/pages/DriverEditPage'
import { DriverListPage } from '@/modules/drivers/pages/DriverListPage'
import { ProviderCreatePage } from '@/modules/providers/pages/ProviderCreatePage'
import { ProviderDetailPage } from '@/modules/providers/pages/ProviderDetailPage'
import { ProviderEditPage } from '@/modules/providers/pages/ProviderEditPage'
import { ProviderListPage } from '@/modules/providers/pages/ProviderListPage'
import { VehicleCreatePage } from '@/modules/vehicles/pages/VehicleCreatePage'
import { VehicleDetailPage } from '@/modules/vehicles/pages/VehicleDetailPage'
import { VehicleEditPage } from '@/modules/vehicles/pages/VehicleEditPage'
import { VehicleListPage } from '@/modules/vehicles/pages/VehicleListPage'

/**
 * Fournisseurs, chauffeurs et véhicules.
 *
 * Routes à plat, comme le reste de l'application. Le prompt de phase propose un
 * préfixe `/resources`, mais l'autorise à s'aligner sur l'existant : ajouter ce
 * préfixe ferait cohabiter deux conventions d'URL pour une même application.
 *
 * Il n'y a **pas** de routes pour les types de véhicule : le référentiel a été
 * fusionné dans `types` / `type_items`, et l'écran `/types` l'administre.
 *
 * `organizationOnly` sur chaque route : ces ressources appartiennent aux
 * organismes, pas à la plateforme.
 */
export const fleetRoutes = [
  <Route
    key="providers"
    path="/providers"
    element={guarded('providers.view', <ProviderListPage />, { organizationOnly: true })}
  />,
  <Route
    key="provider-create"
    path="/providers/create"
    element={guarded('providers.create', <ProviderCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="provider-detail"
    path="/providers/:id"
    element={guarded('providers.view', <ProviderDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="provider-edit"
    path="/providers/:id/edit"
    element={guarded('providers.update', <ProviderEditPage />, { organizationOnly: true })}
  />,

  <Route
    key="drivers"
    path="/drivers"
    element={guarded('drivers.view', <DriverListPage />, { organizationOnly: true })}
  />,
  <Route
    key="driver-create"
    path="/drivers/create"
    element={guarded('drivers.create', <DriverCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="driver-detail"
    path="/drivers/:id"
    element={guarded('drivers.view', <DriverDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="driver-edit"
    path="/drivers/:id/edit"
    element={guarded('drivers.update', <DriverEditPage />, { organizationOnly: true })}
  />,

  <Route
    key="vehicles"
    path="/vehicles"
    element={guarded('vehicles.view', <VehicleListPage />, { organizationOnly: true })}
  />,
  <Route
    key="vehicle-create"
    path="/vehicles/create"
    element={guarded('vehicles.create', <VehicleCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="vehicle-detail"
    path="/vehicles/:id"
    element={guarded('vehicles.view', <VehicleDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="vehicle-edit"
    path="/vehicles/:id/edit"
    element={guarded('vehicles.update', <VehicleEditPage />, { organizationOnly: true })}
  />,
]
