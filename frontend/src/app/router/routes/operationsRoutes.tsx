import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { GroupingTypeListPage } from '@/modules/packages/pages/GroupingTypeListPage'
import { PackageTypeListPage } from '@/modules/packages/pages/PackageTypeListPage'
import { ServiceCreatePage } from '@/modules/services/pages/ServiceCreatePage'
import { ServiceDetailPage } from '@/modules/services/pages/ServiceDetailPage'
import { ServiceEditPage } from '@/modules/services/pages/ServiceEditPage'
import { ServiceListPage } from '@/modules/services/pages/ServiceListPage'

/**
 * Exploitation — référentiels de la Phase 2.
 *
 * Les types de colis et de regroupement sont gouvernés par `packages.*` :
 * `PermissionSeeder` ne leur donne aucune permission propre, et en inventer
 * une produirait un code que rien ne vérifie côté serveur.
 *
 * `organizationOnly` sur chaque route : ces référentiels appartiennent aux
 * organismes, pas à la plateforme.
 */
export const operationsRoutes = [
  <Route
    key="services"
    path="/services"
    element={guarded('services.view', <ServiceListPage />, { organizationOnly: true })}
  />,
  <Route
    key="service-create"
    path="/services/create"
    element={guarded('services.create', <ServiceCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="service-detail"
    path="/services/:id"
    element={guarded('services.view', <ServiceDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="service-edit"
    path="/services/:id/edit"
    element={guarded('services.update', <ServiceEditPage />, { organizationOnly: true })}
  />,

  <Route
    key="package-types"
    path="/package-types"
    element={guarded('packages.view', <PackageTypeListPage />, { organizationOnly: true })}
  />,
  <Route
    key="grouping-types"
    path="/package-grouping-types"
    element={guarded('packages.view', <GroupingTypeListPage />, { organizationOnly: true })}
  />,
]
