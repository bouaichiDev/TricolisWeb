import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { z } from 'zod'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { Button } from '@/shared/components/ui/button'

import { authApi } from '../api/authApi'
import { AuthScreen } from '../components/AuthScreen'

const schema = z.object({ email: z.string().trim().email('Adresse email invalide.') })

type Values = z.infer<typeof schema>

export function ForgotPasswordPage() {
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { email: '' } })
  const [notice, setNotice] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const submit = async ({ email }: Values) => {
    setError(null)

    try {
      setNotice(await authApi.forgotPassword(email))
    } catch (caught) {
      setError(errorMessage(caught))
    }
  }

  return (
    <AuthScreen
      title="Mot de passe oublié"
      description="Indiquez votre adresse : nous vous enverrons un lien pour en choisir un nouveau."
      error={error}
      notice={notice}
      footer={<Link to={paths.login}>Retour à la connexion</Link>}
    >
      <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
        <Field form={form} name="email" label="Email" type="email" autoComplete="email" className="bg-muted/40 h-12 rounded-xl" />
        <Button type="submit" className="from-primary to-primary/80 h-12 w-full rounded-xl bg-linear-to-r font-semibold" disabled={form.formState.isSubmitting}>
          Envoyer le lien
        </Button>
      </form>
    </AuthScreen>
  )
}
