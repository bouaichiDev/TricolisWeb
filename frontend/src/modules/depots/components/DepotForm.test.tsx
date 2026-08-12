import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import { DepotForm } from './DepotForm'
import { ApiError } from '@/shared/api/client'
import { renderWithProviders } from '@/test/renderWithProviders'

describe('DepotForm', () => {
  it('exige le code et le nom', async () => {
    const onSubmit = vi.fn()
    renderWithProviders(<DepotForm onSubmit={onSubmit} onCancel={vi.fn()} submitLabel="Créer" />)

    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(screen.getAllByText('Ce champ est obligatoire.').length).toBeGreaterThanOrEqual(2)
    })
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('refuse un code contenant un espace', async () => {
    const onSubmit = vi.fn()
    renderWithProviders(<DepotForm onSubmit={onSubmit} onCancel={vi.fn()} submitLabel="Créer" />)

    await userEvent.type(screen.getByLabelText(/^code/i), 'DEP 01')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Dépôt principal')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(
        screen.getByText('Lettres, chiffres, tirets et tirets bas uniquement.'),
      ).toBeInTheDocument()
    })
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('transmet les valeurs quand le formulaire est valide', async () => {
    const onSubmit = vi.fn().mockResolvedValue(undefined)
    renderWithProviders(<DepotForm onSubmit={onSubmit} onCancel={vi.fn()} submitLabel="Créer" />)

    await userEvent.type(screen.getByLabelText(/^code/i), 'DEP01')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Dépôt principal')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledOnce()
    })
    expect(onSubmit.mock.calls[0][0]).toMatchObject({ code: 'DEP01', name: 'Dépôt principal' })
  })

  it('reporte un 422 sur le champ concerné', async () => {
    const onSubmit = vi
      .fn()
      .mockRejectedValue(
        new ApiError(422, 'Les données fournies sont invalides.', {
          code: ['Ce code de dépôt existe déjà dans cette agence.'],
        }),
      )
    renderWithProviders(<DepotForm onSubmit={onSubmit} onCancel={vi.fn()} submitLabel="Créer" />)

    await userEvent.type(screen.getByLabelText(/^code/i), 'DEP01')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Dépôt principal')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(
        screen.getByText('Ce code de dépôt existe déjà dans cette agence.'),
      ).toBeInTheDocument()
    })
  })

  it('verrouille le code en modification', () => {
    renderWithProviders(
      <DepotForm
        lockCode
        defaultValues={{ code: 'DEP01', name: 'Dépôt principal' }}
        onSubmit={vi.fn()}
        onCancel={vi.fn()}
        submitLabel="Enregistrer"
      />,
    )

    expect(screen.getByLabelText(/^code/i)).toBeDisabled()
  })
})
