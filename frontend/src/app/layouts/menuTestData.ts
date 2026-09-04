import { HttpResponse, http } from 'msw'

import { API } from '@/test/server'

/**
 * Entrée telle que la renvoie `GET /menu`.
 *
 * `canReparent` est **déduit de la route**, comme le fait `MenuEntry` côté
 * backend, et non posé en valeur par défaut : une entrée sans route est un
 * groupe, et un groupe ne se déplace pas d'un niveau. Le laisser à `true` pour
 * tout le monde ferait passer des tests sur un menu que le serveur n'enverrait
 * jamais — un jeu d'essai qui ment sur l'invariant ne prouve rien.
 *
 * Il reste surchargeable, pour le test qui voudrait précisément le cas absurde.
 */
export function menuItem(
  code: string,
  overrides: Partial<Record<string, unknown>> = {},
): Record<string, unknown> {
  const item = {
    code,
    labelKey: `nav.${code}`,
    label: null,
    icon: 'Boxes',
    route: `/${code}`,
    permission: null,
    parent: null,
    section: 'customers',
    position: 0,
    isVisible: true,
    canHide: true,
    ...overrides,
  }

  return { canReparent: item.route !== null, isCustom: false, ...item }
}

export function menuHandler(items: unknown[]) {
  return http.get(`${API}/menu`, () => HttpResponse.json({ data: items, meta: [] }))
}

export function catalogueHandler(items: unknown[]) {
  return http.get(`${API}/menu/catalogue`, () => HttpResponse.json({ data: items, meta: [] }))
}

/** Menu d'organisme réaliste : deux entrées simples et un groupe. */
export const ORGANIZATION_MENU = [
  menuItem('dashboard', { labelKey: 'nav.dashboard', icon: 'LayoutDashboard', position: 0 }),
  menuItem('customers', { labelKey: 'nav.customers', icon: 'Building2', position: 10 }),
  menuItem('resources', { labelKey: 'nav.resources', route: null, position: 20 }),
  menuItem('agencies', { labelKey: 'nav.agencies', parent: 'resources', position: 21 }),
  menuItem('depots', { labelKey: 'nav.depots', parent: 'resources', position: 22 }),
]
