import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'

export const API = 'http://localhost:8000/api/v1/provider'

/**
 * Configuration publique, navigation et tableau de bord ont un gestionnaire
 * par défaut : chaque écran de connexion lit la première, chaque écran
 * connecté les deux autres. Chaque test déclare ses autres appels.
 */
export const server = setupServer(
  http.get('http://localhost:8000/api/v1/configuration', () =>
    HttpResponse.json({ data: { hasDefaultLogo: false }, meta: {} }),
  ),
  http.get(`${API}/navigation`, () =>
    HttpResponse.json({
      data: [
        { key: 'dashboard', label: 'Tableau de bord', path: '/dashboard' },
        { key: 'profile', label: 'Profil', path: '/profile' },
      ],
      meta: {},
    }),
  ),
  http.get(`${API}/dashboard`, () =>
    HttpResponse.json({
      data: { toursToday: 0, toursInProgress: 0, toursCompletedToday: 0, activeDrivers: 0, activeVehicles: 0 },
      meta: {},
    }),
  ),
)
