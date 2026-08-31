import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'

import {
  EXPORT_JOB_ID,
  exportJob,
  serveExportJobStatuses,
} from '@/modules/integrations/testSupport'
import { withPermissions } from '@/test/fixtures'
import { renderWithProviders } from '@/test/renderWithProviders'
import { API, server } from '@/test/server'

import { ExportJobDetailPage } from './ExportJobDetailPage'

function serve(overrides: Record<string, unknown> = {}) {
  serveExportJobStatuses()
  server.use(
    http.get(`${API}/export-jobs/${EXPORT_JOB_ID}`, () =>
      HttpResponse.json({ data: exportJob(overrides), meta: [] }),
    ),
  )
}

const render = (permissions: string[] = ['export_jobs.view', 'export_jobs.retry']) =>
  renderWithProviders(<ExportJobDetailPage />, {
    membership: withPermissions(permissions),
    route: `/integrations/export-jobs/${EXPORT_JOB_ID}`,
    routePath: '/integrations/export-jobs/:id',
  })

describe('fiche d’un envoi', () => {
  it('montre le statut, les tentatives et l’erreur', async () => {
    serve()
    render()

    expect(await screen.findByText('Échoué')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
    expect(screen.getByText('Connexion SFTP refusée.')).toBeInTheDocument()
  })

  /**
   * Le §55 : `storagePath` est un chemin interne. La ressource ne le renvoie
   * pas — elle rend `hasFile` — et l'écran n'en affiche rien.
   */
  it('ne révèle jamais le chemin de stockage', async () => {
    serve()
    render()

    await screen.findByText('Échoué')

    expect(screen.queryByText(/storagePath/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/exports\//)).not.toBeInTheDocument()
    expect(screen.getByText('Fichier généré')).toBeInTheDocument()
  })

  /**
   * Aucun bouton de téléchargement : ni la route `download`, ni la permission
   * qui l'accompagnerait n'existent. Le §58 interdit par ailleurs de fabriquer
   * une URL depuis le chemin de stockage.
   */
  it('ne propose pas de téléchargement', async () => {
    serve()
    render()

    await screen.findByText('Échoué')

    expect(screen.queryByRole('button', { name: /Télécharger/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Télécharger/ })).not.toBeInTheDocument()
  })

  /** Un envoi est un fait daté : ni modification, ni suppression (§50). */
  it('ne propose ni modification ni suppression', async () => {
    serve()
    render()

    await screen.findByText('Échoué')

    for (const label of [/Modifier/, /Supprimer/]) {
      expect(screen.queryByRole('button', { name: label })).not.toBeInTheDocument()
      expect(screen.queryByRole('link', { name: label })).not.toBeInTheDocument()
    }
  })

  it('relance un envoi manqué', async () => {
    serve()

    let body: unknown = null
    server.use(
      http.post(`${API}/export-jobs/${EXPORT_JOB_ID}/retry`, async ({ request }) => {
        body = await request.json()

        return HttpResponse.json({ data: exportJob(), meta: [] })
      }),
    )

    render()
    await screen.findByText('Échoué')

    await userEvent.click(screen.getByRole('button', { name: /Relancer/ }))
    await waitFor(() => expect(body).toEqual({ status: 'pending' }))
  })

  /**
   * Un envoi déjà transmis ne se rejoue pas : le client aurait deux fois la
   * même facture. Le serveur refuse en 409 ; le bouton disparaît en amont.
   */
  it('retire la relance d’un envoi déjà transmis', async () => {
    serve({ status: 'sent', sentAt: '2026-08-30T10:00:00+00:00', errorMessage: null })
    render()

    expect(await screen.findByText('Envoyé')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Relancer/ })).not.toBeInTheDocument()
  })

  it('masque la relance sans la permission', async () => {
    serve()
    render(['export_jobs.view'])

    await screen.findByText('Échoué')
    expect(screen.queryByRole('button', { name: /Relancer/ })).not.toBeInTheDocument()
  })
})
