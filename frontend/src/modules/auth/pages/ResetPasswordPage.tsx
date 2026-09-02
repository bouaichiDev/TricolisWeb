import { zodResolver } from '@hookform/resolvers/zod'
import { Loader2, Truck } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { z } from 'zod'

import { ApiError } from '@/shared/api/client'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/shared/components/ui/card'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import { authApi } from '../api/auth.api'

const schema = z
  .object({
    password: z.string().min(8, 'auth.reset.tooShort'),
    confirmation: z.string().min(1),
  })
  .refine((values) => values.password === values.confirmation, {
    path: ['confirmation'],
    message: 'auth.reset.mismatch',
  })

type Values = z.infer<typeof schema>

/**
 * Choisir un nouveau mot de passe depuis le lien reçu par courriel.
 *
 * **Le jeton et l'adresse viennent de l'URL**, jamais d'une saisie : c'est le
 * lien qui prouve qu'on relève bien cette boîte. Les retaper n'apporterait rien
 * et laisserait croire qu'une erreur de frappe est un refus du serveur.
 *
 * Sans jeton, la page ne montre pas de formulaire : un lien tronqué par un
 * client de messagerie doit se voir tout de suite, et non après une saisie
 * perdue.
 */
export function ResetPasswordPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params] = useSearchParams()

  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''

  const [error, setError] = useState<string | null>(null)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { password: '', confirmation: '' },
  })

  const submit = form.handleSubmit(async (values) => {
    setError(null)

    try {
      await authApi.resetPassword({ token, email, password: values.password })

      // Vers la connexion, jamais connecté d'office : le mot de passe qu'on
      // vient de choisir doit servir au moins une fois pour qu'on le retienne.
      void navigate('/login', { replace: true, state: { passwordReset: true } })
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))
    }
  })

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/30 p-4">
      <Card className="w-full max-w-sm">
        <CardHeader className="items-center text-center">
          <Truck className="size-8 text-primary" aria-hidden />
          <CardTitle>{t('auth.reset.title')}</CardTitle>
          <CardDescription>
            {token === '' ? t('auth.reset.missingToken') : t('auth.reset.subtitle', { email })}
          </CardDescription>
        </CardHeader>

        <CardContent>
          {token === '' ? (
            <Button asChild variant="outline" className="w-full">
              <Link to="/login">{t('auth.reset.backToLogin')}</Link>
            </Button>
          ) : (
            <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
              {error === null ? null : (
                <Alert variant="destructive">
                  <AlertDescription>{error}</AlertDescription>
                </Alert>
              )}

              <div className="flex flex-col gap-2">
                <Label htmlFor="reset-password">{t('auth.reset.password')}</Label>
                <Input id="reset-password" type="password" {...form.register('password')} />
                <FieldError message={form.formState.errors.password?.message} />
              </div>

              <div className="flex flex-col gap-2">
                <Label htmlFor="reset-confirmation">{t('auth.reset.confirmation')}</Label>
                <Input
                  id="reset-confirmation"
                  type="password"
                  {...form.register('confirmation')}
                />
                <FieldError message={form.formState.errors.confirmation?.message} />
              </div>

              <Button type="submit" disabled={form.formState.isSubmitting}>
                {form.formState.isSubmitting ? (
                  <Loader2 className="size-4 animate-spin" aria-hidden />
                ) : null}
                {t('auth.reset.submit')}
              </Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function FieldError({ message }: { message?: string }) {
  const { t } = useTranslation()

  if (message === undefined) return null

  return <p className="text-sm text-destructive">{t(message, { defaultValue: message })}</p>
}
