import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'

import { renderWithProviders } from '@/test/renderWithProviders'

import { TourBoardColumn } from './TourBoardColumn'
import type { Tour, TourLeg } from '../types/tour'

const TOUR_ID = '01JQZ0000000000000TOUR01'
const STOPS = ['01JQZ00000000000000STOP1', '01JQZ00000000000000STOP2', '01JQZ00000000000000STOP3']

const stop = (id: string, sequence: number) => ({
  id,
  tourId: TOUR_ID,
  addressId: `ADR-${sequence}`,
  sequence,
  status: 'pending',
  addressLabel: `Arrêt ${sequence}`,
  serviceCount: 1,
})

const tour = (legs: TourLeg[]): Tour =>
  ({
    id: TOUR_ID,
    organizationId: '01JQZ0000000000000000ORG1',
    tourNumber: 'TR-001',
    tourDate: '2026-09-01',
    agencyId: '01JQZ0000000000000AGEN01',
    depotId: null,
    providerId: null,
    vehicleId: null,
    driverId: null,
    tourType: null,
    instructions: null,
    plannedStartAt: null,
    plannedEndAt: null,
    totalWeight: 0,
    totalVolume: 0,
    totalPackages: 0,
    totalCustomers: 0,
    drivingTimeMinutes: 0,
    workingTimeMinutes: 0,
    distanceMeters: 0,
    status: 'draft',
    stopCount: 3,
    stops: STOPS.map((id, index) => stop(id, index + 1)),
    legs,
  }) as Tour

const render = (legs: TourLeg[]) => renderWithProviders(<TourBoardColumn tour={tour(legs)} />)

describe('le trajet entre deux arrêts', () => {
  it('annonce le temps de route et la distance', () => {
    render([{ tourStopId: STOPS[1], distanceMeters: 8400, travelMinutes: 12 }])

    const band = within(screen.getByTestId(`tour-leg-${STOPS[1]}`))

    expect(band.getByText('12 min de route')).toBeInTheDocument()
    // Une seule fois : sur le premier trajet, le cumul vaut le trajet lui-meme.
    expect(band.getAllByText('8,4 km')).toHaveLength(1)
  })

  /**
   * Le temps d'un segment ne dit pas si le dernier arrêt sera atteint avant la
   * fermeture ; c'est le total depuis le départ qui le dit.
   */
  it('additionne le trajet à ceux qui le précèdent', () => {
    render([
      { tourStopId: STOPS[1], distanceMeters: 8400, travelMinutes: 40 },
      { tourStopId: STOPS[2], distanceMeters: 3600, travelMinutes: 35 },
    ])

    expect(screen.getByText('1 h 15 · 12,0 km')).toBeInTheDocument()
  })

  /** Le premier arrêt n'a pas de trajet entrant : rien ne doit le précéder. */
  it('ne précède pas le premier arrêt', () => {
    render([{ tourStopId: STOPS[1], distanceMeters: 8400, travelMinutes: 12 }])

    expect(screen.queryByTestId(`tour-leg-${STOPS[0]}`)).not.toBeInTheDocument()
    expect(screen.getByTestId(`tour-leg-${STOPS[1]}`)).toBeInTheDocument()
  })

  /**
   * Un itinéraire calculé avant que la durée par segment ne soit conservée n'a
   * que sa distance. « 0 min » se lirait comme deux arrêts à la même adresse.
   */
  it('avoue la durée manquante au lieu d’afficher zéro', () => {
    render([{ tourStopId: STOPS[1], distanceMeters: 8400, travelMinutes: 0 }])

    // Restreint a la bande : l'en-tete de colonne ecrit lui aussi « — » pour
    // les chiffres qu'il n'a pas.
    const band = within(screen.getByTestId(`tour-leg-${STOPS[1]}`))

    expect(screen.queryByText('0 min de route')).not.toBeInTheDocument()
    expect(band.getByText('—')).toBeInTheDocument()
    expect(band.getByText('8,4 km')).toBeInTheDocument()
  })

  it('reste discret quand aucun itinéraire n’a été calculé', () => {
    render([])

    expect(screen.getByText('Arrêt 1')).toBeInTheDocument()
    expect(screen.queryByTestId(`tour-leg-${STOPS[1]}`)).not.toBeInTheDocument()
  })
})

/**
 * La fenêtre carte n'affiche pas seulement l'itinéraire : elle ouvre l'écran de
 * planification et son vivier de commandes. C'est là qu'on va pour remplir une
 * tournée vide — la masquer tant qu'elle l'est obligeait à poser une première
 * commande à l'aveugle.
 */
describe('l’accès à la carte', () => {
  const empty = (): Tour => ({ ...tour([]), stops: [], stopCount: 0 })

  it('reste offert sur une tournée sans arrêt', () => {
    renderWithProviders(<TourBoardColumn tour={empty()} onShowMap={() => undefined} />)

    expect(screen.getByRole('button', { name: 'Voir la tournée sur la carte' })).toBeInTheDocument()
    expect(screen.getByText('Aucun arrêt')).toBeInTheDocument()
  })

  it('ouvre la tournée cliquée', async () => {
    const opened: string[] = []

    renderWithProviders(
      <TourBoardColumn tour={empty()} onShowMap={(clicked) => opened.push(clicked.id)} />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Voir la tournée sur la carte' }))

    expect(opened).toEqual([TOUR_ID])
  })

  /** En lecture seule, rien n'ouvre la carte : le bouton ne doit pas mentir. */
  it('disparaît quand la vue n’offre pas la carte', () => {
    renderWithProviders(<TourBoardColumn tour={empty()} />)

    expect(screen.queryByRole('button', { name: 'Voir la tournée sur la carte' })).not.toBeInTheDocument()
  })
})
