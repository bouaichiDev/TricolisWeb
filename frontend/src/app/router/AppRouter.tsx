import { Navigate, Route, Routes } from 'react-router-dom'

import { adminRoutes } from './routes/adminRoutes'
import { customerRoutes } from './routes/customerRoutes'
import { guarded } from './routes/guarded'
import { resourceRoutes } from './routes/resourceRoutes'
import { ProtectedRoute } from '@/app/guards/ProtectedRoute'
import { AppLayout } from '@/app/layouts/AppLayout'
import { LoginPage } from '@/modules/auth/pages/LoginPage'
import { DashboardPage } from '@/modules/dashboard/pages/DashboardPage'
import { ForbiddenPage } from '@/modules/system/pages/ForbiddenPage'
import { NotFoundPage } from '@/modules/system/pages/NotFoundPage'

/**
 * Table des routes.
 *
 * Toutes les routes métier vivent sous une unique `ProtectedRoute` englobante,
 * qui vérifie l'authentification ; la permission, elle, est déclarée route par
 * route. Les routes sont regroupées par domaine dans `routes/` : une table
 * unique dépassait vite la taille lisible, et chaque domaine se relit
 * désormais isolément.
 */
export function AppRouter() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forbidden" element={<ForbiddenPage />} />

      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="/dashboard" element={guarded('dashboard.view', <DashboardPage />)} />

        {customerRoutes}
        {resourceRoutes}
        {adminRoutes}

        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
