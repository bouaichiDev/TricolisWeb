import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { PlanningPage } from '@/modules/planning/pages/PlanningPage'
import { TourDetailPage } from '@/modules/tours/pages/TourDetailPage'
import { TourListPage } from '@/modules/tours/pages/TourListPage'

/**
 * Transport : la planification et les tournées.
 *
 * `tours.view` gouverne les trois écrans — la planification est une façon de
 * composer une tournée, pas un domaine à part avec ses propres droits.
 */
export const transportRoutes = [
  <Route
    key="planning"
    path="/planning"
    element={guarded('tours.view', <PlanningPage />, { organizationOnly: true })}
  />,
  <Route
    key="tours"
    path="/tours"
    element={guarded('tours.view', <TourListPage />, { organizationOnly: true })}
  />,
  <Route
    key="tour-detail"
    path="/tours/:id"
    element={guarded('tours.view', <TourDetailPage />, { organizationOnly: true })}
  />,
]
