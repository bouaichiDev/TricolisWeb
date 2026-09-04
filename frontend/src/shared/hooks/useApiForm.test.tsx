import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useForm } from 'react-hook-form'
import { describe, expect, it } from 'vitest'

import { useApiFormError } from './useApiForm'
import { ApiError } from '@/shared/api/client'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { renderWithProviders } from '@/test/renderWithProviders'

interface Values {
  code: string
  name: string
}

/** Formulaire minimal : deux champs, une erreur injectée à la soumission. */
function Harness({ error }: { error: unknown }) {
  const form = useForm<Values>({ defaultValues: { code: '', name: '' } })
  const { formError, handleError, clearError } = useApiFormError(form)

  return (
    <form
      onSubmit={form.handleSubmit(() => {
        clearError()
        handleError(error)
      })}
    >
      <FormErrorSummary message={formError} />
      <TextField form={form} name="code" label="Code" />
      <TextField form={form} name="name" label="Nom" />
      <button type="submit">Enregistrer</button>
    </form>
  )
}

describe('useApiFormError', () => {
  /**
   * Le cœur du §31 : un 422 se pose sur les champs fautifs. Le backend renvoie
   * ses clés en camelCase, qui sont exactement les noms des champs.
   */
  it('pose un 422 sur les champs concernés', async () => {
    renderWithProviders(
      <Harness
        error={
          new ApiError(422, 'Les données fournies sont invalides.', {
            code: ['Ce code est déjà utilisé.'],
          })
        }
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(screen.getByText('Ce code est déjà utilisé.')).toBeInTheDocument()
    })
    expect(screen.getByLabelText('Code')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByLabelText('Nom')).not.toHaveAttribute('aria-invalid', 'true')
  })

  /**
   * Cas facile à manquer : un 422 dont aucune clé ne correspond à un champ
   * affiché. Sans le repli, l'utilisateur verrait un formulaire refusé sans la
   * moindre explication.
   */
  it('bascule vers un message global quand aucune clé ne correspond à un champ', async () => {
    renderWithProviders(
      <Harness
        error={
          new ApiError(422, 'Les données fournies sont invalides.', {
            organizationId: ['Organisation inconnue.'],
          })
        }
      />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(screen.getByText('Les données fournies sont invalides.')).toBeInTheDocument()
    })
  })

  it('affiche un 409 tel quel : le message est rédigé pour être lu', async () => {
    renderWithProviders(
      <Harness error={new ApiError(409, 'Ce client possède encore des commandes actives.')} />,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(
        screen.getByText('Ce client possède encore des commandes actives.'),
      ).toBeInTheDocument()
    })
  })

  it('affiche un 403 dans le bandeau, sans marquer de champ', async () => {
    renderWithProviders(<Harness error={new ApiError(403, 'Action non autorisée.')} />)

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(screen.getByText('Action non autorisée.')).toBeInTheDocument()
    })
    expect(screen.getByLabelText('Code')).not.toHaveAttribute('aria-invalid', 'true')
  })

  it('traite une erreur non-API sans laisser l’utilisateur sans message', async () => {
    renderWithProviders(<Harness error={new Error('Le serveur est injoignable.')} />)

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    await waitFor(() => {
      expect(screen.getByText('Le serveur est injoignable.')).toBeInTheDocument()
    })
  })
})
