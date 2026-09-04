import { Navigate, Route } from 'react-router-dom'

import { guarded } from './guarded'
import { StockBalanceListPage } from '@/modules/stock/pages/StockBalanceListPage'
import { StockDashboardPage } from '@/modules/stock/pages/StockDashboardPage'
import { StockItemCreatePage } from '@/modules/stock/pages/StockItemCreatePage'
import { StockItemDetailPage } from '@/modules/stock/pages/StockItemDetailPage'
import { StockItemEditPage } from '@/modules/stock/pages/StockItemEditPage'
import { StockItemListPage } from '@/modules/stock/pages/StockItemListPage'
import { StockLocationCreatePage } from '@/modules/stock/pages/StockLocationCreatePage'
import { StockLocationDetailPage } from '@/modules/stock/pages/StockLocationDetailPage'
import { StockLocationEditPage } from '@/modules/stock/pages/StockLocationEditPage'
import { StockLocationListPage } from '@/modules/stock/pages/StockLocationListPage'
import { StockMovementCreatePage } from '@/modules/stock/pages/StockMovementCreatePage'
import { StockMovementDetailPage } from '@/modules/stock/pages/StockMovementDetailPage'
import { StockMovementListPage } from '@/modules/stock/pages/StockMovementListPage'
import { StockReservationCreatePage } from '@/modules/stock/pages/StockReservationCreatePage'
import { StockReservationDetailPage } from '@/modules/stock/pages/StockReservationDetailPage'
import { StockReservationListPage } from '@/modules/stock/pages/StockReservationListPage'

/**
 * Stock client chez le transporteur.
 *
 * `organizationOnly` partout : le stock appartient aux dépôts d'un organisme,
 * pas à la plateforme, qui n'a ni dépôt ni client.
 *
 * **Aucune route de modification ni de suppression pour les mouvements**, et
 * aucune suppression pour les réservations : le serveur ne les expose pas. Une
 * route qui mènerait à un 405 n'est pas un oubli à combler, c'est une promesse
 * qu'on ne doit pas faire.
 *
 * `/stock-locations` reste redirigée : c'était l'unique route de stock avant
 * cette phase, et les signets ne doivent pas casser.
 */
export const stockRoutes = [
  <Route
    key="stock"
    path="/stock"
    element={guarded('stock_balances.view', <StockDashboardPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="stock-items"
    path="/stock/items"
    element={guarded('stock_items.view', <StockItemListPage />, { organizationOnly: true })}
  />,
  <Route
    key="stock-items-create"
    path="/stock/items/create"
    element={guarded('stock_items.create', <StockItemCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="stock-items-detail"
    path="/stock/items/:id"
    element={guarded('stock_items.view', <StockItemDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="stock-items-edit"
    path="/stock/items/:id/edit"
    element={guarded('stock_items.update', <StockItemEditPage />, { organizationOnly: true })}
  />,

  <Route
    key="stock-locations"
    path="/stock/locations"
    element={guarded('stock_locations.view', <StockLocationListPage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="stock-locations-create"
    path="/stock/locations/create"
    element={guarded('stock_locations.create', <StockLocationCreatePage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="stock-locations-detail"
    path="/stock/locations/:id"
    element={guarded('stock_locations.view', <StockLocationDetailPage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="stock-locations-edit"
    path="/stock/locations/:id/edit"
    element={guarded('stock_locations.update', <StockLocationEditPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="stock-balances"
    path="/stock/balances"
    element={guarded('stock_balances.view', <StockBalanceListPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="stock-movements"
    path="/stock/movements"
    element={guarded('stock_movements.view', <StockMovementListPage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="stock-movements-create"
    path="/stock/movements/create"
    element={guarded('stock_movements.create', <StockMovementCreatePage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="stock-movements-detail"
    path="/stock/movements/:id"
    element={guarded('stock_movements.view', <StockMovementDetailPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="stock-reservations"
    path="/stock/reservations"
    element={guarded('stock_reservations.view', <StockReservationListPage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="stock-reservations-create"
    path="/stock/reservations/create"
    element={guarded('stock_reservations.create', <StockReservationCreatePage />, {
      organizationOnly: true,
    })}
  />,
  <Route
    key="stock-reservations-detail"
    path="/stock/reservations/:id"
    element={guarded('stock_reservations.view', <StockReservationDetailPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="stock-locations-legacy"
    path="/stock-locations"
    element={<Navigate to="/stock/locations" replace />}
  />,
]
