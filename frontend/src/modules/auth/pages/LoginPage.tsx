import { zodResolver } from '@hookform/resolvers/zod'
import { Loader2, Truck } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'

import { ApiError } from '@/shared/api/client'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/components/ui/card'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { useAuth } from '@/shared/hooks/useAuth'

const loginSchema = z.object({
  email: z.string().min(1).email(),
  password: z.string().min(1),
})

type LoginValues = z.infer<typeof loginSchema>

interface LocationState {
  from?: { pathname: string }
}

/**
 * Page de connexion.
 *
 * Un utilisateur déjà authentifié n'a rien à y faire : il est renvoyé vers la
 * destination qu'il visait, ou le tableau de bord. Sans cette redirection, un
 * retour arrière du navigateur après connexion réafficherait le formulaire.
 */
export function LoginPage() {
  const { t } = useTranslation()
  const { login, isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [serverError, setServerError] = useState<string | null>(null)

  const target = (location.state as LocationState | null)?.from?.pathname ?? '/dashboard'

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  if (isAuthenticated) return <Navigate to={target} replace />

  const onSubmit = form.handleSubmit(async (values) => {
    setServerError(null)

    try {
      await login(values.email, values.password)
      void navigate(target, { replace: true })
    } catch (error) {
      if (error instanceof ApiError && (error.isValidation || error.status === 401)) {
        setServerError(t('auth.invalidCredentials'))
        return
      }

      setServerError(error instanceof Error ? error.message : t('errors.unexpected'))
    }
  })

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted/40 p-4">
      <Card className="w-full max-w-sm">
        <CardHeader className="items-center text-center">
          <Truck className="size-8 text-primary" aria-hidden />
          <CardTitle>{t('auth.loginTitle')}</CardTitle>
          <CardDescription>{t('auth.loginSubtitle')}</CardDescription>
        </CardHeader>

        <CardContent>
          <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
            {serverError !== null ? (
              <Alert variant="destructive">
                <AlertDescription>{serverError}</AlertDescription>
              </Alert>
            ) : null}

            <div className="flex flex-col gap-2">
              <Label htmlFor="email">{t('auth.email')}</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                autoFocus
                aria-invalid={form.formState.errors.email !== undefined}
                {...form.register('email')}
              />
              {form.formState.errors.email ? (
                <p className="text-sm text-destructive">{t('validation.email')}</p>
              ) : null}
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="password">{t('auth.password')}</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                aria-invalid={form.formState.errors.password !== undefined}
                {...form.register('password')}
              />
              {form.formState.errors.password ? (
                <p className="text-sm text-destructive">{t('validation.required')}</p>
              ) : null}
            </div>

            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? (
                <>
                  <Loader2 className="size-4 animate-spin" aria-hidden />
                  {t('auth.submitting')}
                </>
              ) : (
                t('auth.submit')
              )}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
