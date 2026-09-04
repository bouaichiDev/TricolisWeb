import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

import { CustomerForm } from './CustomerForm'
import { ApiError } from '@/shared/api/client'
import { renderWithProviders } from '@/test/renderWithProviders'

function setup(onSubmit = vi.fn().mockResolvedValue(undefined)) {
  const onCancel = vi.fn()
  renderWithProviders(
    <CustomerForm onSubmit={onSubmit} onCancel={onCancel} submitLabel="Créer" />,
  )

  return { onSubmit, onCancel }
}

describe('CustomerForm', () => {
  it('refuse la soumission sans code ni nom', async () => {
    const { onSubmit } = setup()

    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(screen.getAllByText('Ce champ est obligatoire.').length).toBeGreaterThanOrEqual(2)
    })
    expect(onSubmit).not.toHaveBeenCalled()
  })

  /**
   * Le code sert d'identifiant aux intégrations : le backend le contraint à
   * `[A-Za-z0-9._-]`. Le valider ici évite un aller-retour pour un refus certain.
   */
  it('refuse un code contenant des caractères interdits', async () => {
    const { onSubmit } = setup()

    await userEvent.type(screen.getByLabelText(/code/i), 'CLI 001')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Client test')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(
        screen.getByText('Lettres, chiffres, tirets et tirets bas uniquement.'),
      ).toBeInTheDocument()
    })
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('refuse une adresse e-mail mal formée', async () => {
    const { onSubmit } = setup()

    await userEvent.type(screen.getByLabelText(/code/i), 'CLI001')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Client test')
    await userEvent.type(screen.getByLabelText(/e-mail/i), 'pas-une-adresse')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(screen.getByText('Adresse e-mail invalide.')).toBeInTheDocument()
    })
    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('transmet les valeurs saisies quand le formulaire est valide', async () => {
    const { onSubmit } = setup()

    await userEvent.type(screen.getByLabelText(/code/i), 'CLI001')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Client test')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledOnce()
    })
    expect(onSubmit.mock.calls[0][0]).toMatchObject({ code: 'CLI001', name: 'Client test' })
  })

  it('reporte un 422 du serveur sur le champ concerné', async () => {
    const onSubmit = vi
      .fn()
      .mockRejectedValue(
        new ApiError(422, 'Les données fournies sont invalides.', {
          code: ['Ce code est déjà utilisé dans cette organisation.'],
        }),
      )
    setup(onSubmit)

    await userEvent.type(screen.getByLabelText(/code/i), 'CLI001')
    await userEvent.type(screen.getByLabelText(/^nom/i), 'Client test')
    await userEvent.click(screen.getByRole('button', { name: 'Créer' }))

    await waitFor(() => {
      expect(
        screen.getByText('Ce code est déjà utilisé dans cette organisation.'),
      ).toBeInTheDocument()
    })
  })

  /** Le code identifie le client pour les intégrations : figé après création. */
  it('verrouille le code en modification', () => {
    renderWithProviders(
      <CustomerForm
        lockCode
        defaultValues={{ code: 'CLI001', name: 'Client test' }}
        onSubmit={vi.fn()}
        onCancel={vi.fn()}
        submitLabel="Enregistrer"
      />,
    )

    expect(screen.getByLabelText(/code/i)).toBeDisabled()
  })
})
