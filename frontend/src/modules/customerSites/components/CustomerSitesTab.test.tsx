import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import { CustomerSitesTab } from './CustomerSitesTab'
import { paginated, withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

const CUSTOMER_ID = '01JQZ000000000000000CUST'

const site = {
  id: '01JQZ000000000000000SITE',
  customerId: CUSTOMER_ID,
  addressId: '01JQZ000000000000000ADDR',
  code: 'SITE01',
  name: 'Entrepôt Nord',
  siteType: 'warehouse',
  isDefault: true,
  status: 'active',
}

function listHandler(rows: unknown[] = [site]) {
  return http.get(`${API}/customers/${CUSTOMER_ID}/sites`, () =>
    HttpResponse.json(paginated(rows)),
  )
}

describe('CustomerSitesTab', () => {
  it('affiche les sites renvoyés par la route imbriquée', async () => {
    server.use(listHandler())
    renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
      membership: withPermissions(['customer_sites.view']),
    })

    expect(await screen.findByText('Entrepôt Nord')).toBeInTheDocument()
    expect(screen.getByText('SITE01')).toBeInTheDocument()
  })

  it('signale le site par défaut', async () => {
    server.use(listHandler())
    renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
      membership: withPermissions(['customer_sites.view']),
    })

    expect(await screen.findByText('Par défaut')).toBeInTheDocument()
  })

  it('affiche un état vide quand le client n’a aucun site', async () => {
    server.use(listHandler([]))
    renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
      membership: withPermissions(['customer_sites.view']),
    })

    expect(await screen.findByText('Aucun site enregistré pour ce client.')).toBeInTheDocument()
  })

  /**
   * Le §21 demande une seconde barrière côté frontend : un site dont le
   * `customerId` ne correspond pas à la route n'est pas affiché. Le backend
   * l'assure déjà par la route imbriquée ; ceci vérifie que le filtre existe
   * bien et n'a pas été retiré par inadvertance.
   */
  it('écarte un site rattaché à un autre client', async () => {
    server.use(listHandler([{ ...site, customerId: 'AUTRE-CLIENT' }]))
    renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
      membership: withPermissions(['customer_sites.view']),
    })

    expect(await screen.findByText('Aucun site enregistré pour ce client.')).toBeInTheDocument()
    expect(screen.queryByText('Entrepôt Nord')).not.toBeInTheDocument()
  })

  it('remonte l’échec de chargement au lieu d’une table vide', async () => {
    server.use(
      http.get(`${API}/customers/${CUSTOMER_ID}/sites`, () =>
        HttpResponse.json({ message: 'Service indisponible.' }, { status: 500 }),
      ),
    )
    renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
      membership: withPermissions(['customer_sites.view']),
    })

    expect(await screen.findByText('Le chargement a échoué')).toBeInTheDocument()
  })

  describe('actions non autorisées', () => {
    it('masque la création sans customer_sites.create', async () => {
      server.use(listHandler())
      renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
        membership: withPermissions(['customer_sites.view']),
      })

      await screen.findByText('Entrepôt Nord')
      expect(screen.queryByRole('link', { name: /nouveau site/i })).not.toBeInTheDocument()
    })

    it('masque la suppression sans customer_sites.delete', async () => {
      server.use(listHandler())
      renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
        membership: withPermissions(['customer_sites.view']),
      })

      await screen.findByText('Entrepôt Nord')
      expect(screen.queryByRole('button', { name: 'Supprimer' })).not.toBeInTheDocument()
    })

    it('propose la suppression et demande confirmation avec le droit', async () => {
      server.use(listHandler())
      renderWithProviders(<CustomerSitesTab customerId={CUSTOMER_ID} />, {
        membership: withPermissions(['customer_sites.view', 'customer_sites.delete']),
      })

      await screen.findByText('Entrepôt Nord')
      await userEvent.click(screen.getByRole('button', { name: 'Supprimer' }))

      // Le nom apparaît deux fois — dans la table et dans la confirmation :
      // la recherche est donc portée sur le dialogue seul.
      const dialog = await screen.findByRole('dialog')
      expect(dialog).toHaveTextContent('Entrepôt Nord')
      expect(dialog).toHaveTextContent('Confirmer la suppression')
    })
  })
})
