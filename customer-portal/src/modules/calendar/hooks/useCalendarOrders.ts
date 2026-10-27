import { keepPreviousData, useQuery } from '@tanstack/react-query'

import { calendarApi } from '../api/calendarApi'
import type { CalendarFilters, CalendarRange } from '../types'

export const calendarKeys = {
  orders: (range: CalendarRange | null, filters: CalendarFilters) => ['calendar', 'orders', range, filters] as const,
}

/**
 * Événements de la période visible. Rien n'est demandé tant que FullCalendar
 * n'a pas annoncé sa période (`datesSet`) : jamais « toutes les commandes ».
 */
export function useCalendarOrders(range: CalendarRange | null, filters: CalendarFilters) {
  return useQuery({
    queryKey: calendarKeys.orders(range, filters),
    queryFn: () => calendarApi.orders(range as CalendarRange, filters),
    enabled: range !== null,
    placeholderData: keepPreviousData,
  })
}
