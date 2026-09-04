import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { CustomerApiConfigurationCreatePage } from './CustomerApiConfigurationCreatePage'
import { apiKeyIssued, CUSTOMER_ID, serveCustomers, servePermissions } from '../testSupport'

const API_KEY = 'trk_live_0123456789abcdef'

function serveCreate() {
  const bodies: unknown[] = []

  serveCustomers()
  servePermissions()
  server.use(
    http.post(`${API}/customer-api-configurations`, async ({ request }) => {
      bodies.push(await request.json())

      return HttpResponse.json({ data: apiKeyIssued(API_KEY), meta: [] }, { status: 201 })
    }),
  )

  return bodies
}

const render = () =>
  renderWithProviders(<CustomerApiConfigurationCreatePage />, {
    membership: withPermissions(['customer_api_configurations.create']),
  })

async function fillAndSubmit() {
  await userEvent.click(await screen.findByLabelText(/^Client/))
  await userEvent.click(await screen.findByRole('option', { name: /Client Nord/ }))
  await userEvent.type(screen.getByLabelText(/^Nom/), 'Portail client')
  await userEvent.click(screen.getByRole('button', { name: 'Créer' }))
}

describe('création d’un accès API client', () => {
  beforeEach(() => {
    // `navigator.clipboard` n'existe pas dans jsdom : le bouton Copier en a
    // besoin, et c'est le seul endroit où la clé doit transiter.
    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockResolvedValue(undefined) },
    })
  })

  /** Le formulaire ne demande jamais la clé : le serveur la produit. */
  it('ne demande ni clé ni empreinte', async () => {
    serveCreate()
    render()

    await screen.findByLabelText(/^Nom/)

    expect(screen.queryByLabelText(/apiKeyHash/i)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/Clé API/i)).not.toBeInTheDocument()
  })

  it('n’envoie ni clé ni empreinte au serveur', async () => {
    const bodies = serveCreate()
    render()

    await fillAndSubmit()
    await waitFor(() => expect(bodies).toHaveLength(1))

    const body = bodies[0] as Record<string, unknown>
    expect(body).toMatchObject({ customerId: CUSTOMER_ID, name: 'Portail client' })
    expect(body).not.toHaveProperty('apiKeyHash')
    expect(body).not.toHaveProperty('apiKey')
  })

  /**
   * La clé n'existe qu'une fois : le serveur n'en garde qu'un hash. Le dialogue
   * est le seul endroit où elle apparaît.
   */
  it('affiche la clé une seule fois, avec son avertissement', async () => {
    serveCreate()
    render()

    await fillAndSubmit()

    const dialog = within(await screen.findByRole('dialog'))
    expect(dialog.getByText(API_KEY)).toBeInTheDocument()
    expect(dialog.getByText(/ne sera plus affichée/)).toBeInTheDocument()
  })

  it('copie la clé dans le presse-papiers', async () => {
    serveCreate()
    render()

    await fillAndSubmit()

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: /Copier/ }))

    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(API_KEY)
  })

  /**
   * La clé est longue : elle doit se lire **en entier**. Un défilement
   * horizontal en cacherait la moitié, et personne ne vérifie ce qu'il a copié
   * dans une valeur tronquée.
   */
  it('affiche la clé entière, sans la tronquer', async () => {
    serveCreate()
    render()

    await fillAndSubmit()

    const key = await screen.findByText(API_KEY)
    expect(key.textContent).toBe(API_KEY)
    expect(key.className).toContain('break-all')
  })

  /**
   * Le presse-papiers exige un contexte sécurisé : une application servie en
   * HTTP simple n'y a pas droit. Un échec silencieux laisserait croire que la
   * clé est copiée alors qu'elle ne l'est pas — pour une valeur qu'on ne reverra
   * jamais, c'est la pire issue possible.
   */
  it('prévient quand la copie automatique échoue', async () => {
    Object.assign(navigator, {
      clipboard: { writeText: vi.fn().mockRejectedValue(new Error('refusé')) },
    })

    serveCreate()
    render()

    await fillAndSubmit()

    const dialog = within(await screen.findByRole('dialog'))
    await userEvent.click(dialog.getByRole('button', { name: /Copier la clé/ }))

    expect(await screen.findByText(/copiez-la à la main/)).toBeInTheDocument()
  })

  /**
   * Le §22 : après fermeture, la clé ne subsiste nulle part. Elle vivait dans
   * l'état du composant, et rien d'autre ne l'a écrite.
   */
  it('ne laisse la clé ni à l’écran, ni dans le stockage du navigateur', async () => {
    serveCreate()
    render()

    await fillAndSubmit()

    const dialog = await screen.findByRole('dialog')
    await userEvent.click(within(dialog).getByRole('button', { name: /J’ai copié la clé/ }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(screen.queryByText(API_KEY)).not.toBeInTheDocument()

    expect(JSON.stringify(window.localStorage)).not.toContain(API_KEY)
    expect(JSON.stringify(window.sessionStorage)).not.toContain(API_KEY)
    expect(window.location.href).not.toContain(API_KEY)
  })
})
