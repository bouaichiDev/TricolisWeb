import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import { AgencyForm } from './AgencyForm'
import { ApiError } from '@/shared/api/client'
import { renderWithProviders } from '@/test/renderWithProviders'

describe('AgencyForm', () => {
  it('exige le code et le nom', async () => {
    const onSubmit = vi.fn()
    renderWithProviders(
      <AgencyForm onSubmit={onSubmit} onCancel={vi.fn()} submitLabel="Créer" />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(screen.getAllByText('Ce champ est obligatoire.').length).toBeGreaterThanOrEqual(2)
    })
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('transmet les valeurs quand le formulaire est valide', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined)
    renderWithProviders(
      <AgencyForm onSubmit={onSubmit} onCancel={vi.fn()} submitLabel="Créer" />,
    )

    await userEvent.type(screen.getByLabelText(/^code/i), 'AG-CASA')
    // « Nom * » et non « Nom court » : l'astérisque marque le champ requis.
    await userEvent.type(screen.getByLabelText('Nom *'), 'Agence Casablanca')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledOnce()
    })
    expect(onSubmit.mock.calls[0][0]).toMatchObject({
      code: 'AG-CASA',
      name: 'Agence Casablanca',
    })
  })

  it('reporte un 422 sur le champ concerné', async () => {
    const onSubmit = vi
      .fn()
      .mockRejectedValue(
        new ApiError(422, 'Les données fournies sont invalides.', {
          code: ['Ce code d’agence existe déjà.'],
        }),
      )
    renderWithProviders(
      <AgencyForm onSubmit={onSubmit} onCancel={vi.fn()} submitLabel="Créer" />,
    )

    await userEvent.type(screen.getByLabelText(/^code/i), 'AG-CASA')
    // « Nom * » et non « Nom court » : l'astérisque marque le champ requis.
    await userEvent.type(screen.getByLabelText('Nom *'), 'Agence Casablanca')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(screen.getByText('Ce code d’agence existe déjà.')).toBeInTheDocument()
    })
  })

  it('appelle l’annulation sans soumettre', async () => {
    const onCancel = vi.fn()
    const onSubmit = vi.fn()
    renderWithProviders(
      <AgencyForm onSubmit={onSubmit} onCancel={onCancel} submitLabel="Créer" />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))

    expect(onCancel).toHaveBeenCalledOnce()
    expect(onSubmit).not.toHaveBeenCalled()
  })
})
