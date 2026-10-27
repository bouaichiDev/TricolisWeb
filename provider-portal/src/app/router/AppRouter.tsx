import { Navigate, Route, Routes } from 'react-router-dom'

import { ProtectedRoute, PublicOnlyRoute } from '@/app/guards/ProtectedRoute'
import { PortalLayout } from '@/app/layouts/PortalLayout'
import { ActivateAccountPage } from '@/modules/auth/pages/ActivateAccountPage'
import { ForgotPasswordPage } from '@/modules/auth/pages/ForgotPasswordPage'
import { LoginPage } from '@/modules/auth/pages/LoginPage'
import { ResetPasswordPage } from '@/modules/auth/pages/ResetPasswordPage'
import { DashboardPage } from '@/modules/dashboard/pages/DashboardPage'
import { AccessDeniedPage, NotFoundPage } from '@/modules/errors/ErrorPages'

import { DriversPage, ProfilePage, VehiclesPage } from './lazyPages'
import { paths } from './paths'

/**
 * Routes du portail fournisseur — Phase 1 : connexion, activation, mot de
 * passe, tableau de bord, profil ; Phase 2 : chauffeurs, véhicules. Aucune
 * route de gestion d'utilisateurs ni de rôles : elle reste au back-office du
 * transporteur.
 *
 * Les liens d'activation et de réinitialisation restent accessibles avec une
 * session ouverte : on peut cliquer une invitation reçue en étant déjà
 * connecté à un autre compte.
 */
export function AppRouter() {
  return (
    <Routes>
      <Route path={paths.login} element={<PublicOnlyRoute><LoginPage /></PublicOnlyRoute>} />
      <Route path={paths.forgotPassword} element={<PublicOnlyRoute><ForgotPasswordPage /></PublicOnlyRoute>} />
      <Route path={paths.resetPassword} element={<ResetPasswordPage />} />
      <Route path={paths.activateAccount} element={<ActivateAccountPage />} />

      <Route element={<ProtectedRoute><PortalLayout /></ProtectedRoute>}>
        <Route index element={<Navigate to={paths.dashboard} replace />} />
        <Route path={paths.dashboard} element={<DashboardPage />} />
        <Route path={paths.profile} element={<ProtectedRoute permission="provider.profile.view"><ProfilePage /></ProtectedRoute>} />
        <Route path={paths.drivers} element={<ProtectedRoute permission="provider.drivers.view"><DriversPage /></ProtectedRoute>} />
        <Route path={paths.vehicles} element={<ProtectedRoute permission="provider.vehicles.view"><VehiclesPage /></ProtectedRoute>} />
        <Route path={paths.accessDenied} element={<AccessDeniedPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
