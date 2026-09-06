import { Navigate, Route, Routes } from 'react-router-dom'

import { adminRoutes } from './routes/adminRoutes'
import { billingRoutes } from './routes/billingRoutes'
import { customerRoutes } from './routes/customerRoutes'
import { guarded } from './routes/guarded'
import { operationsRoutes } from './routes/operationsRoutes'
import { integrationsRoutes } from './routes/integrationsRoutes'
import { stockRoutes } from './routes/stockRoutes'
import { fleetRoutes } from './routes/fleetRoutes'
import { transportRoutes } from './routes/transportRoutes'
import { resourceRoutes } from './routes/resourceRoutes'
import { ProtectedRoute } from '@/app/guards/ProtectedRoute'
import { AppLayout } from '@/app/layouts/AppLayout'
import { homeRoute } from '@/app/router/navigation'
import { AccessRequestPage } from '@/modules/access-requests/pages/AccessRequestPage'
import { ForgotPasswordPage } from '@/modules/auth/pages/ForgotPasswordPage'
import { LoginPage } from '@/modules/auth/pages/LoginPage'
import { ResetPasswordPage } from '@/modules/auth/pages/ResetPasswordPage'
import { DashboardPage } from '@/modules/dashboard/pages/DashboardPage'
import { ForbiddenPage } from '@/modules/system/pages/ForbiddenPage'
import { NotFoundPage } from '@/modules/system/pages/NotFoundPage'
import { usePermissions } from '@/shared/hooks/usePermission'

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
      {/* Publiques, et elles doivent l'etre : on y arrive precisement parce
          qu'on ne peut pas se connecter — mot de passe perdu, ou pas encore de
          compte du tout. Sur `/reset-password`, le jeton de l'URL fait
          l'authentification ; les deux autres ne creent rien. */}
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/request-access" element={<AccessRequestPage />} />
      <Route path="/forbidden" element={<ForbiddenPage />} />

      <Route
        element={
          <ProtectedRoute>
            <AppLayout />
          </ProtectedRoute>
        }
      >
        <Route index element={<Home />} />
        <Route
          path="/dashboard"
          element={guarded('dashboard.view', <DashboardPage />, { organizationOnly: true })}
        />

        {customerRoutes}
        {resourceRoutes}
        {fleetRoutes}
        {transportRoutes}
        {operationsRoutes}
        {billingRoutes}
        {stockRoutes}
        {integrationsRoutes}
        {adminRoutes}

        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}

/**
 * Point d'entrée après connexion.
 *
 * Il dépend de la portée du compte : un compte plateforme n'a pas de tableau de
 * bord — celui-ci compte des clients et des agences, qui appartiennent aux
 * organismes — et arrive donc sur la liste des organisations inscrites.
 */
function Home() {
  const { isPlatformAdmin } = usePermissions()

  return <Navigate to={homeRoute(isPlatformAdmin)} replace />
}
