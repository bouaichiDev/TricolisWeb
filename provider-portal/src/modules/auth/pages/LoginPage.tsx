import { Building2, ChevronRight } from 'lucide-react'
import { useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'

import { useAuth } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'

import type { ProviderChoice } from '../api/authApi'
import { AuthLayout } from '../components/AuthLayout'
import { LoginForm, type LoginValues } from '../components/LoginForm'

interface LocationState {
  from?: { pathname: string }
  notice?: string
}

/**
 * Connexion au portail fournisseur.
 *
 * Un compte rattaché à plusieurs fournisseurs passe par une seconde étape : le
 * choix du fournisseur, parmi ceux que le backend lui a listés après
 * vérification du mot de passe.
 */
export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const state = useLocation().state as LocationState | null
  const target = state?.from?.pathname ?? paths.dashboard
  const [pending, setPending] = useState<{ values: LoginValues; choices: ProviderChoice[] } | null>(null)
  const [choiceError, setChoiceError] = useState<string | null>(null)

  const submit = async (values: LoginValues, providerId?: string) => {
    await login({ ...values, providerId })
    navigate(target, { replace: true })
  }

  if (pending !== null) {
    const choose = (providerId: string) =>
      submit(pending.values, providerId).catch((caught) => setChoiceError(errorMessage(caught)))

    return (
      <AuthLayout title="Choisissez un fournisseur" subtitle="Votre compte donne accès à plusieurs fournisseurs.">
        {choiceError && (
          <Alert variant="destructive" role="alert" className="mb-4">
            <AlertDescription>{choiceError}</AlertDescription>
          </Alert>
        )}
        <div className="flex flex-col gap-3">
          {pending.choices.map((choice) => (
            <Button
              key={choice.id}
              variant="outline"
              className="h-12 justify-between rounded-xl"
              onClick={() => void choose(choice.id)}
            >
              <span className="flex items-center gap-2">
                <Building2 className="text-primary size-4" aria-hidden /> {choice.name}
              </span>
              <ChevronRight className="size-4" aria-hidden />
            </Button>
          ))}
        </div>
        <Button variant="link" className="mt-4 px-0" onClick={() => setPending(null)}>
          Utiliser un autre compte
        </Button>
      </AuthLayout>
    )
  }

  return (
    <AuthLayout title="Connexion au portail fournisseur" subtitle="Accédez à l’espace de votre entreprise : tournées, chauffeurs et véhicules.">
      {state?.notice && (
        <Alert role="status" className="mb-5">
          <AlertDescription>{state.notice}</AlertDescription>
        </Alert>
      )}
      <LoginForm onSubmit={(values) => submit(values)} onChooseProvider={(values, choices) => setPending({ values, choices })} />
    </AuthLayout>
  )
}
