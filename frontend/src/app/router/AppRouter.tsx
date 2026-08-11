import { Navigate, Route, Routes } from 'react-router-dom'

import { ProtectedRoute } from '@/app/guards/ProtectedRoute'
import { AppLayout } from '@/app/layouts/AppLayout'
import { LoginPage } from '@/modules/auth/pages/LoginPage'
import { CustomerListPage } from '@/modules/customers/pages/CustomerListPage'
import { DashboardPage } from '@/modules/dashboard/pages/DashboardPage'
import { ForbiddenPage } from '@/modules/system/pages/ForbiddenPage'
import { NotFoundPage } from '@/modules/system/pages/NotFoundPage'

/**
 * Table des routes.
 *
 * Toutes les routes métier vivent sous une unique `ProtectedRoute` englobante,
 * qui vérifie l'authentification ; la permission, elle, est déclarée route par
 * route. Ce découpage évite de répéter le contrôle de session dix fois tout en
 * gardant chaque permission visible à côté de sa page.
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

        <Route
          path="/dashboard"
          element={
            <ProtectedRoute permission="dashboard.view">
              <DashboardPage />
            </ProtectedRoute>
          }
        />

        <Route
          path="/customers"
          element={
            <ProtectedRoute permission="customers.view">
              <CustomerListPage />
            </ProtectedRoute>
          }
        />

        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
