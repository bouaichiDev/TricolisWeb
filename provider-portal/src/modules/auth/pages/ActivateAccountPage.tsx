import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router-dom'

import { paths } from '@/app/router/paths'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { FullPageLoader } from '@/shared/components/feedback/States'

import { authApi } from '../api/authApi'
import { ActivationForm } from '../components/ActivationForm'
import { AuthScreen } from '../components/AuthScreen'

/**
 * Arrivée depuis le lien d'invitation.
 *
 * L'invitation est lue d'abord : elle dit pour quel fournisseur, et s'il faut
 * choisir un mot de passe — un compte qui existait déjà garde le sien.
 */
export function ActivateAccountPage() {
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''

  const invitation = useQuery({
    queryKey: ['invitation', token],
    queryFn: () => authApi.invitation(token),
    enabled: token !== '',
    retry: false,
  })

  if (token === '') {
    return <InvalidLink message="Ce lien d’activation est incomplet." />
  }

  if (invitation.isPending) return <FullPageLoader />

  if (invitation.isError) {
    const error = invitation.error
    const message = error instanceof ApiError && error.isValidation ? (error.fieldError('token') ?? error.message) : errorMessage(error)

    return <InvalidLink message={message} />
  }

  return (
    <AuthScreen
      title={`Rejoindre ${invitation.data.providerName}`}
      description={`Invitation pour ${invitation.data.email}${invitation.data.carrierName ? ` — portail ${invitation.data.carrierName}` : ''}.`}
    >
      <ActivationForm token={token} invitation={invitation.data} />
    </AuthScreen>
  )
}

function InvalidLink({ message }: { message: string }) {
  return (
    <AuthScreen title="Invitation indisponible" error={message} footer={<Link to={paths.login}>Aller à la connexion</Link>}>
      <p className="text-muted-foreground text-sm">Demandez à votre administrateur de vous renvoyer une invitation.</p>
    </AuthScreen>
  )
}
