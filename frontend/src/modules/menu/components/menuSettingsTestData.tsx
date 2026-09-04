import { HttpResponse, http } from 'msw'

import { MenuSettingsPanel } from './MenuSettingsPanel'
import { menuItem } from '@/app/layouts/menuTestData'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API } from '@/test/server'

/** Rôle sur lequel les tests règlent le menu. */
export const ROLE_ID = 'role-1'

/**
 * Menu d'essai partagé par les deux fichiers de test du réglage.
 *
 * Il porte **un groupe avec deux enfants et deux entrées de premier niveau** :
 * c'est le plus petit menu où les quatre gestes se distinguent — déplacer parmi
 * ses frères, sortir d'un groupe, entrer dans un groupe, et se heurter au bord
 * de la fratrie. Un menu plat les rendrait tous indiscernables.
 */
export const CATALOGUE = [
  menuItem('customers', { labelKey: 'nav.customers', position: 10 }),
  menuItem('resources', { labelKey: 'nav.resources', route: null, position: 20 }),
  menuItem('agencies', { labelKey: 'nav.agencies', position: 21, parent: 'resources' }),
  menuItem('depots', { labelKey: 'nav.depots', position: 22, parent: 'resources' }),
  menuItem('administration', {
    labelKey: 'nav.administration',
    route: null,
    position: 80,
    canHide: false,
  }),
]

/** Une entrée telle qu'elle part vers `PATCH /menu`. */
export interface SentItem {
  code: string
  isVisible: boolean
  position: number
  label: string | null
  parent: string | null
  icon?: string
}

/**
 * Intercepte l'enregistrement et rend les entrées telles qu'elles partent.
 *
 * Les tests regardent la charge utile plutôt que l'écran : l'ordre et le
 * rattachement ne se lisent pas dans le DOM — une entrée promue et une entrée
 * restée en place s'y ressemblent — alors qu'ils se lisent sans ambiguïté dans
 * ce qui est envoyé.
 */
export function captureSave(sent: SentItem[][]) {
  return http.patch(`${API}/roles/${ROLE_ID}/menu`, async ({ request }) => {
    const body = (await request.json()) as { items: SentItem[] }
    sent.push(body.items)

    return HttpResponse.json({ data: CATALOGUE, meta: [] })
  })
}

/** Le catalogue configurable, tel que `GET /roles/{role}/menu` le renvoie. */
export function menuHandler(items: unknown[] = CATALOGUE) {
  return http.get(`${API}/roles/${ROLE_ID}/menu`, () =>
    HttpResponse.json({ data: items, meta: [] }),
  )
}

export function renderPanel(editable = true) {
  renderWithProviders(<MenuSettingsPanel roleId={ROLE_ID} editable={editable} />, {
    membership: withPermissions(['roles.update']),
  })
}
