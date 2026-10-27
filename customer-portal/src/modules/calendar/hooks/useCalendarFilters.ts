import { useSearchParams } from 'react-router-dom'

import type { CalendarFilters } from '../types'

const KEYS = ['status', 'service', 'search'] as const

/**
 * Les filtres du calendrier, conservés dans l'URL : revenir d'une commande
 * retrouve le même calendrier filtré.
 */
export function useCalendarFilters() {
  const [params, setParams] = useSearchParams()

  const filters: CalendarFilters = {}
  KEYS.forEach((key) => {
    const value = params.get(key)
    if (value) filters[key] = value
  })

  const update = (patch: Partial<CalendarFilters>) => {
    const next = new URLSearchParams(params)

    Object.entries(patch).forEach(([key, value]) => {
      if (!value) next.delete(key)
      else next.set(key, value)
    })

    setParams(next, { replace: true })
  }

  const reset = () => {
    const next = new URLSearchParams(params)
    KEYS.forEach((key) => next.delete(key))
    setParams(next, { replace: true })
  }

  return { filters, update, reset }
}
