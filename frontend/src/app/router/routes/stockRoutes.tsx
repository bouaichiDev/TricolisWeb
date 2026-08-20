import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { StockLocationListPage } from '@/modules/stock/pages/StockLocationListPage'

/**
 * Stock client chez le transporteur.
 *
 * Une seule route pour l'instant : les emplacements. Les soldes et les
 * mouvements d'un article se consultent depuis son article de catalogue, où la
 * question se pose — « combien en reste-t-il, et qui l'a bougé » — plutôt que
 * dans une liste globale qui obligerait à retrouver l'article d'abord.
 *
 * `organizationOnly` : un emplacement appartient au dépôt d'un organisme, pas
 * à la plateforme.
 */
export const stockRoutes = [
  <Route
    key="stock-locations"
    path="/stock-locations"
    element={guarded('stock_locations.view', <StockLocationListPage />, {
      organizationOnly: true,
    })}
  />,
]
