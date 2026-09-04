import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { TypeListPage } from '@/modules/types/pages/TypeListPage'
import { ClaimListPage } from '@/modules/claims/pages/ClaimListPage'
import { ServiceCreatePage } from '@/modules/services/pages/ServiceCreatePage'
import { ServiceDetailPage } from '@/modules/services/pages/ServiceDetailPage'
import { ServiceEditPage } from '@/modules/services/pages/ServiceEditPage'
import { OrderCreatePage } from '@/modules/orders/pages/OrderCreatePage'
import { OrderDetailPage } from '@/modules/orders/pages/OrderDetailPage'
import { OrderEditPage } from '@/modules/orders/pages/OrderEditPage'
import { OrderListPage } from '@/modules/orders/pages/OrderListPage'
import { ServiceListPage } from '@/modules/services/pages/ServiceListPage'

/**
 * Exploitation — référentiels de la Phase 2.
 *
 * Les référentiels de type sont gouvernés par `types.*`, un seul module pour
 * toutes les sources : celle qu'un organisme ajoute est couverte d'office,
 * alors qu'une permission par référentiel aurait demandé du code à chaque fois.
 *
 * `organizationOnly` sur chaque route : ces référentiels appartiennent aux
 * organismes, pas à la plateforme.
 */
export const operationsRoutes = [
  <Route
    key="orders"
    path="/orders"
    element={guarded('orders.view', <OrderListPage />, { organizationOnly: true })}
  />,
  <Route
    key="order-create"
    path="/orders/create"
    element={guarded('orders.create', <OrderCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="order-detail"
    path="/orders/:id"
    element={guarded('orders.view', <OrderDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="order-edit"
    path="/orders/:id/edit"
    element={guarded('orders.update', <OrderEditPage />, { organizationOnly: true })}
  />,

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
    key="claims"
    path="/claims"
    element={guarded('claims.view', <ClaimListPage />, { organizationOnly: true })}
  />,

  <Route
    key="types"
    path="/types"
    element={guarded('types.view', <TypeListPage />, { organizationOnly: true })}
  />,
]
