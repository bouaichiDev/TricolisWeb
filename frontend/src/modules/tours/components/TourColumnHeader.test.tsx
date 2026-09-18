import { screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

import { renderWithProviders } from '@/test/renderWithProviders'

import type { Tour } from '../types/tour'
import { capacityOverrun } from '../utils/vehicleCapacity'
import { TourColumnHeader } from './TourColumnHeader'

const tour = (values: Partial<Tour>): Tour =>
  ({
    id: '01JQZ0000000000000TOUR01',
    tourNumber: '53',
    plannedStartAt: null,
    plannedEndAt: null,
    totalWeight: '540.000',
    totalVolume: '2.8800',
    totalPackages: 3,
    totalCustomers: 1,
    distanceMeters: 7900,
    status: 'draft',
    vehicleRegistration: 'RC-12345',
    vehicleCapacity: { weight: '500.000', volume: '50.0000', pallets: 150 },
    ...values,
  }) as Tour

describe('capacityOverrun', () => {
  it('signale le poids qui dépasse la charge utile, pas le volume qui tient', () => {
    expect(capacityOverrun(tour({}))).toEqual({ weight: 500, volume: null })
  })

  it('ne signale rien sans véhicule affecté', () => {
    expect(capacityOverrun(tour({ vehicleCapacity: null }))).toEqual({
      weight: null,
      volume: null,
    })
  })

  it('accepte un chargement égal à la capacité', () => {
    expect(capacityOverrun(tour({ totalWeight: '500' })).weight).toBeNull()
  })
})

describe('en-tête de colonne', () => {
  it('encadre de rouge le poids qui dépasse la capacité du véhicule', () => {
    renderWithProviders(<TourColumnHeader tour={tour({})} />)

    const weight = screen.getByTitle(/^Poids — dépasse la capacité du véhicule \(500\)/)
    const volume = screen.getByTitle('Volume')

    expect(weight).toHaveAttribute('data-over', 'true')
    expect(weight).toHaveClass('border-destructive')
    expect(volume).not.toHaveAttribute('data-over')
  })
})
