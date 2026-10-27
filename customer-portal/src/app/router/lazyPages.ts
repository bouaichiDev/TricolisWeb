import { type ComponentType, lazy } from 'react'

/**
 * Pages chargées à la première visite (Phase 8, performance) : le premier
 * écran — connexion, tableau de bord — ne télécharge plus le code des
 * commandes, du stock, des intégrations…
 *
 * Les pages exportent un composant nommé : on l'expose en `default` pour
 * `React.lazy`.
 */
function lazyPage<K extends string>(load: () => Promise<Record<K, ComponentType>>, name: K) {
  return lazy(() => load().then((module) => ({ default: module[name] })))
}

export const PublicTrackingPage = lazyPage(() => import('@/modules/public-tracking/pages/PublicTrackingPage'), 'PublicTrackingPage')
export const ProfilePage = lazyPage(() => import('@/modules/profile/pages/ProfilePage'), 'ProfilePage')

export const OrdersPage = lazyPage(() => import('@/modules/orders/pages/OrdersPage'), 'OrdersPage')
export const OrderCreatePage = lazyPage(() => import('@/modules/orders/pages/OrderCreatePage'), 'OrderCreatePage')
export const OrderDetailPage = lazyPage(() => import('@/modules/orders/pages/OrderDetailPage'), 'OrderDetailPage')
export const OrderEditPage = lazyPage(() => import('@/modules/orders/pages/OrderEditPage'), 'OrderEditPage')
export const OrderServicePage = lazyPage(() => import('@/modules/orders/pages/OrderServicePage'), 'OrderServicePage')
export const OrderTrackingPage = lazyPage(() => import('@/modules/tracking/pages/OrderTrackingPage'), 'OrderTrackingPage')
export const CalendarPage = lazyPage(() => import('@/modules/calendar/pages/CalendarPage'), 'CalendarPage')

export const ClaimsPage = lazyPage(() => import('@/modules/claims/pages/ClaimsPage'), 'ClaimsPage')
export const ClaimCreatePage = lazyPage(() => import('@/modules/claims/pages/ClaimCreatePage'), 'ClaimCreatePage')
export const ClaimDetailPage = lazyPage(() => import('@/modules/claims/pages/ClaimDetailPage'), 'ClaimDetailPage')

export const StockPage = lazyPage(() => import('@/modules/stock/pages/StockPage'), 'StockPage')
export const StockItemPage = lazyPage(() => import('@/modules/stock/pages/StockItemPage'), 'StockItemPage')
export const StockMovementsPage = lazyPage(() => import('@/modules/stock/pages/StockMovementsPage'), 'StockMovementsPage')
export const StockReservationsPage = lazyPage(() => import('@/modules/stock/pages/StockReservationsPage'), 'StockReservationsPage')
export const StockAdjustmentRequestsPage = lazyPage(
  () => import('@/modules/stock/pages/StockAdjustmentRequestsPage'),
  'StockAdjustmentRequestsPage',
)

export const CatalogPage = lazyPage(() => import('@/modules/catalog/pages/CatalogPage'), 'CatalogPage')
export const CatalogItemPage = lazyPage(() => import('@/modules/catalog/pages/CatalogItemPage'), 'CatalogItemPage')
export const IntegrationsPage = lazyPage(() => import('@/modules/integrations/pages/IntegrationsPage'), 'IntegrationsPage')
export const UsersPage = lazyPage(() => import('@/modules/users/pages/UsersPage'), 'UsersPage')
export const UserDetailPage = lazyPage(() => import('@/modules/users/pages/UserDetailPage'), 'UserDetailPage')
