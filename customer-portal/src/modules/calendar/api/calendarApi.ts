import { api } from '@/shared/api/client'

import type { CalendarFilters, CalendarMeta, CalendarOrderEvent, CalendarRange } from '../types'

/**
 * Commandes du client sur la période visible. Aucun identifiant de client :
 * le backend l'impose depuis le jeton.
 */
export const calendarApi = {
  orders: (range: CalendarRange, filters: CalendarFilters) =>
    api.get<{ data: CalendarOrderEvent[]; meta: CalendarMeta }>('/calendar/orders', {
      query: {
        from: range.from,
        to: range.to,
        status: filters.status ? [filters.status] : undefined,
        service: filters.service ? [filters.service] : undefined,
        search: filters.search,
      },
    }),
}
