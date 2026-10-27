import { Suspense } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'

import { ProtectedRoute, PublicOnlyRoute } from '@/app/guards/ProtectedRoute'
import { PortalLayout } from '@/app/layouts/PortalLayout'
import { ActivateAccountPage } from '@/modules/auth/pages/ActivateAccountPage'
import { ForgotPasswordPage } from '@/modules/auth/pages/ForgotPasswordPage'
import { LoginPage } from '@/modules/auth/pages/LoginPage'
import { ResetPasswordPage } from '@/modules/auth/pages/ResetPasswordPage'
import { DashboardPage } from '@/modules/dashboard/pages/DashboardPage'
import { AccessDeniedPage, NotFoundPage } from '@/modules/errors/ErrorPages'
import { InlineLoader } from '@/shared/components/feedback/States'

import {
  CalendarPage, CatalogItemPage, CatalogPage, ClaimCreatePage, ClaimDetailPage, ClaimsPage, IntegrationsPage, OrderCreatePage,
  OrderDetailPage, OrderEditPage, OrderServicePage, OrdersPage, OrderTrackingPage, ProfilePage, PublicTrackingPage,
  StockAdjustmentRequestsPage, StockItemPage, StockMovementsPage, StockPage, StockReservationsPage, UserDetailPage, UsersPage,
} from './lazyPages'
import { paths } from './paths'

/**
 * Routes du portail : Phase 1 (§13), commandes (Phase 2), calendrier, suivi
 * et suivi public (Phase 3), réclamations, documents et preuves de livraison
 * (Phase 4), stock (Phase 5), intégrations (Phase 6), catalogue (Phase 8).
 *
 * Les liens d'activation et de réinitialisation restent accessibles avec une
 * session ouverte : on peut cliquer une invitation reçue en étant déjà
 * connecté à un autre compte.
 *
 * Hors connexion et tableau de bord, chaque page se charge à sa première
 * visite (`lazyPages`) ; `PortalLayout` garde la barre latérale pendant ce
 * chargement.
 */
export function AppRouter() {
  return (
    <Routes>
      <Route path={paths.login} element={<PublicOnlyRoute><LoginPage /></PublicOnlyRoute>} />
      <Route path={paths.forgotPassword} element={<PublicOnlyRoute><ForgotPasswordPage /></PublicOnlyRoute>} />
      <Route path={paths.resetPassword} element={<ResetPasswordPage />} />
      <Route path={paths.activateAccount} element={<ActivateAccountPage />} />
      {/* Suivi public (Phase 3) : sans session, sans barre latérale, ouvert
          que l'on soit connecté ou non. */}
      <Route path="/t/:token" element={<Suspense fallback={<InlineLoader />}><PublicTrackingPage /></Suspense>} />

      <Route element={<ProtectedRoute><PortalLayout /></ProtectedRoute>}>
        <Route index element={<Navigate to={paths.dashboard} replace />} />
        <Route path={paths.dashboard} element={<DashboardPage />} />
        <Route path={paths.profile} element={<ProfilePage />} />
        <Route path={paths.orders} element={<ProtectedRoute permission="customer.orders.view"><OrdersPage /></ProtectedRoute>} />
        <Route path={paths.orderCreate} element={<ProtectedRoute permission="customer.orders.create"><OrderCreatePage /></ProtectedRoute>} />
        <Route path="/orders/:id" element={<ProtectedRoute permission="customer.orders.view"><OrderDetailPage /></ProtectedRoute>} />
        <Route path="/orders/:id/edit" element={<ProtectedRoute permission="customer.orders.update"><OrderEditPage /></ProtectedRoute>} />
        <Route
          path="/orders/:id/tracking"
          element={<ProtectedRoute permission={['customer.tracking.view', 'customer.orders.track']}><OrderTrackingPage /></ProtectedRoute>}
        />
        <Route
          path="/orders/:id/services/:serviceId"
          element={<ProtectedRoute permission="customer.orders.view"><OrderServicePage /></ProtectedRoute>}
        />
        <Route path={paths.claims} element={<ProtectedRoute permission="customer.claims.view"><ClaimsPage /></ProtectedRoute>} />
        <Route path={paths.claimCreate} element={<ProtectedRoute permission="customer.claims.create"><ClaimCreatePage /></ProtectedRoute>} />
        <Route path="/claims/:id" element={<ProtectedRoute permission="customer.claims.view"><ClaimDetailPage /></ProtectedRoute>} />
        <Route path={paths.stock} element={<ProtectedRoute permission="customer.stock.view"><StockPage /></ProtectedRoute>} />
        <Route path={paths.stockMovements} element={<ProtectedRoute permission="customer.stock.movements.view"><StockMovementsPage /></ProtectedRoute>} />
        <Route path={paths.stockReservations} element={<ProtectedRoute permission="customer.stock.reservations.view"><StockReservationsPage /></ProtectedRoute>} />
        <Route path={paths.stockAdjustments} element={<ProtectedRoute permission="customer.stock.adjustment_requests.view"><StockAdjustmentRequestsPage /></ProtectedRoute>} />
        <Route path="/stock/:id" element={<ProtectedRoute permission="customer.stock.view"><StockItemPage /></ProtectedRoute>} />
        <Route path={paths.catalog} element={<ProtectedRoute permission={['customer.catalog.view', 'customer.articles.view']}><CatalogPage /></ProtectedRoute>} />
        <Route path="/catalog/:id" element={<ProtectedRoute permission={['customer.catalog.view', 'customer.articles.view']}><CatalogItemPage /></ProtectedRoute>} />
        <Route path={paths.integrations} element={<ProtectedRoute permission="customer.integrations.view"><IntegrationsPage /></ProtectedRoute>} />
        <Route path={paths.calendar} element={<ProtectedRoute permission="customer.calendar.view"><CalendarPage /></ProtectedRoute>} />
        <Route
          path={paths.users}
          element={<ProtectedRoute permission="customer.users.view"><UsersPage /></ProtectedRoute>}
        />
        <Route
          path="/users/:id"
          element={<ProtectedRoute permission="customer.users.view"><UserDetailPage /></ProtectedRoute>}
        />
        <Route path={paths.accessDenied} element={<AccessDeniedPage />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}
