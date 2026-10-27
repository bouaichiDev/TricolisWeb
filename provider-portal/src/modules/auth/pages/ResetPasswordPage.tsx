import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { z } from 'zod'

import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Button } from '@/shared/components/ui/button'

import { authApi } from '../api/authApi'
import { AuthScreen } from '../components/AuthScreen'
import { mismatch, newPasswordFields, passwordsMatch } from '../passwordSchema'

const schema = z.object(newPasswordFields).refine(passwordsMatch, mismatch)

type Values = z.infer<typeof schema>

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { password: '', password_confirmation: '' } })
  const [error, setError] = useState<string | null>(null)

  if (token === '' || email === '') {
    return (
      <AuthScreen title="Lien incomplet" error="Ce lien de réinitialisation est incomplet. Demandez-en un nouveau.">
        <Link to={paths.forgotPassword}>Demander un nouveau lien</Link>
      </AuthScreen>
    )
  }

  const submit = async (values: Values) => {
    setError(null)

    try {
      const notice = await authApi.resetPassword({ ...values, token, email })
      navigate(paths.login, { replace: true, state: { notice } })
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, ['password', 'password_confirmation'])
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <AuthScreen title="Nouveau mot de passe" description={email} error={error}>
      <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
        <Field form={form} name="password" label="Nouveau mot de passe" type="password" autoComplete="new-password" className="bg-muted/40 h-12 rounded-xl" />
        <Field form={form} name="password_confirmation" label="Confirmation" type="password" autoComplete="new-password" className="bg-muted/40 h-12 rounded-xl" />
        <Button type="submit" className="from-primary to-primary/80 h-12 w-full rounded-xl bg-linear-to-r font-semibold" disabled={form.formState.isSubmitting}>
          Enregistrer
        </Button>
      </form>
    </AuthScreen>
  )
}
