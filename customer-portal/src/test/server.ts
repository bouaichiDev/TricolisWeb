import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'

/**
 * Configuration publique et tableau de bord ont un gestionnaire par défaut :
 * l'une est lue par chaque écran de connexion, l'autre par l'accueil. Chaque test déclare les autres appels.
 */
export const server = setupServer(
  http.get('http://localhost:8000/api/v1/configuration', () =>
    HttpResponse.json({ data: { hasDefaultLogo: false }, meta: {} }),
  ),
  // Le tableau de bord : vide par défaut, chaque test y pose ses widgets.
  http.get('http://localhost:8000/api/v1/customer/dashboard', () =>
    HttpResponse.json({ data: { customer: { id: '01IKEA', name: 'IKEA' }, period: null, widgets: [] }, meta: {} }),
  ),
)

export const API = 'http://localhost:8000/api/v1/customer'
