import { type ComponentType, lazy } from 'react'

/**
 * Pages chargées à la première visite : le premier écran — connexion,
 * tableau de bord — ne télécharge pas le code des autres.
 *
 * Les pages exportent un composant nommé : on l'expose en `default` pour
 * `React.lazy`.
 */
function lazyPage<K extends string>(load: () => Promise<Record<K, ComponentType>>, name: K) {
  return lazy(() => load().then((module) => ({ default: module[name] })))
}

export const ProfilePage = lazyPage(() => import('@/modules/profile/pages/ProfilePage'), 'ProfilePage')
export const DriversPage = lazyPage(() => import('@/modules/drivers/pages/DriversPage'), 'DriversPage')
export const VehiclesPage = lazyPage(() => import('@/modules/vehicles/pages/VehiclesPage'), 'VehiclesPage')
