import type { Tour } from '../types/tour'

export interface CapacityOverrun {
  /** Capacité dépassée, pour la nommer dans l'infobulle ; `null` si respectée. */
  weight: number | null
  volume: number | null
}

const toNumber = (value: number | string | null | undefined): number | null => {
  if (value === null || value === undefined || value === '') return null

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : null
}

/**
 * Ce que le chargement de la tournée dépasse chez son véhicule.
 *
 * Seuls le poids et le volume se comparent : la capacité en palettes ne se
 * rapporte pas au nombre de colis — un carton n'est pas une palette.
 *
 * Sans véhicule, ou sans capacité renseignée, rien n'est signalé : on ne
 * dépasse pas une limite inconnue.
 */
export function capacityOverrun(tour: Pick<Tour, 'totalWeight' | 'totalVolume' | 'vehicleCapacity'>): CapacityOverrun {
  const capacity = tour.vehicleCapacity
  const exceeded = (load: number | string, limit: number | string | undefined) => {
    const max = toNumber(limit)
    const current = toNumber(load)

    return max !== null && current !== null && current > max ? max : null
  }

  return {
    weight: exceeded(tour.totalWeight, capacity?.weight),
    volume: exceeded(tour.totalVolume, capacity?.volume),
  }
}
