import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { AgencyCreatePage } from '@/modules/agencies/pages/AgencyCreatePage'
import { AgencyDetailPage } from '@/modules/agencies/pages/AgencyDetailPage'
import { AgencyEditPage } from '@/modules/agencies/pages/AgencyEditPage'
import { AgencyListPage } from '@/modules/agencies/pages/AgencyListPage'
import { DepotCreatePage } from '@/modules/depots/pages/DepotCreatePage'
import { DepotDetailPage } from '@/modules/depots/pages/DepotDetailPage'
import { DepotEditPage } from '@/modules/depots/pages/DepotEditPage'
import { DepotListPage } from '@/modules/depots/pages/DepotListPage'

/**
 * Agences et depots.
 *
 * Les depots vivent sous `/agencies/:agencyId/depots` parce que l'API les y
 * place : garder la meme forme d'URL evite d'avoir a deviner l'agence a partir
 * du depot. `/depots` reste un point d'entree de navigation, qui demande
 * d'abord de choisir une agence.
 */
export const resourceRoutes = [
  <Route key="agencies" path="/agencies" element={guarded('agencies.view', <AgencyListPage />, { organizationOnly: true })} />,
  <Route
    key="agency-create"
    path="/agencies/create"
    element={guarded('agencies.create', <AgencyCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="agency-detail"
    path="/agencies/:id"
    element={guarded('agencies.view', <AgencyDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="agency-edit"
    path="/agencies/:id/edit"
    element={guarded('agencies.update', <AgencyEditPage />, { organizationOnly: true })}
  />,
  <Route key="depots" path="/depots" element={guarded('depots.view', <DepotListPage />, { organizationOnly: true })} />,
  <Route
    key="depot-create"
    path="/agencies/:agencyId/depots/create"
    element={guarded('depots.create', <DepotCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="depot-detail"
    path="/agencies/:agencyId/depots/:depotId"
    element={guarded('depots.view', <DepotDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="depot-edit"
    path="/agencies/:agencyId/depots/:depotId/edit"
    element={guarded('depots.update', <DepotEditPage />, { organizationOnly: true })}
  />,
]
