import { HttpResponse, http } from 'msw'

import { API } from '@/test/server'

/** Entrée telle que la renvoie `GET /menu`. */
export function menuItem(
  code: string,
  overrides: Partial<Record<string, unknown>> = {},
): Record<string, unknown> {
  return {
    code,
    labelKey: `nav.${code}`,
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
