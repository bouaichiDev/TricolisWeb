import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowRight, Loader2, Mail } from 'lucide-react'
import { type ReactNode, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { z } from 'zod'

import { PasswordInput } from '@/modules/auth/components/PasswordInput'
import { ApiError } from '@/shared/api/client'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { useAuth } from '@/shared/hooks/useAuth'

const loginSchema = z.object({
  email: z.string().min(1).email(),
  password: z.string().min(1),
})

type LoginValues = z.infer<typeof loginSchema>

/** Le libellé d'un champ : petites capitales, comme sur la maquette. */
function FieldLabel({ htmlFor, children }: { htmlFor: string; children: ReactNode }) {
  return (
    <Label htmlFor={htmlFor} className="text-xs font-semibold tracking-wider text-foreground uppercase">
      {children}
    </Label>
  )
}

/**
 * Le formulaire de connexion proprement dit.
 *
 * Il porte la saisie et l'appel au serveur ; la page qui l'accueille décide où
 * mène une connexion réussie. Les deux se séparent parce que la mise en page —
 * l'écran scindé, le panneau de marque — n'a rien à voir avec l'authentification.
 */
export function LoginForm({ onAuthenticated }: { onAuthenticated: () => void }) {
  const { t } = useTranslation()
  const { login } = useAuth()
  const [serverError, setServerError] = useState<string | null>(null)

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setServerError(null)

    try {
      await login(values.email, values.password)
      onAuthenticated()
    } catch (error) {
      if (error instanceof ApiError && (error.isValidation || error.status === 401)) {
        setServerError(t('auth.invalidCredentials'))
        return
      }

      setServerError(error instanceof Error ? error.message : t('errors.unexpected'))
    }
  })

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-5" noValidate>
      {serverError !== null ? (
        <Alert variant="destructive">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      <div className="flex flex-col gap-2">
        <FieldLabel htmlFor="email">{t('auth.email')}</FieldLabel>

        <div className="relative">
          <Mail
            className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            id="email"
            type="email"
            autoComplete="email"
            autoFocus
            placeholder={t('auth.emailPlaceholder')}
            className="h-12 rounded-xl bg-muted/40 pl-11"
            aria-invalid={form.formState.errors.email !== undefined}
            {...form.register('email')}
          />
        </div>

        {form.formState.errors.email ? (
          <p className="text-sm text-destructive">{t('validation.email')}</p>
        ) : null}
      </div>

      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <FieldLabel htmlFor="password">{t('auth.password')}</FieldLabel>

          <Link
            to="/forgot-password"
            className="text-xs font-semibold text-primary hover:underline"
          >
            {t('auth.forgotPassword')}
          </Link>
        </div>

        <PasswordInput
          id="password"
          autoComplete="current-password"
          placeholder={t('auth.passwordPlaceholder')}
          aria-invalid={form.formState.errors.password !== undefined}
          {...form.register('password')}
        />

        {form.formState.errors.password ? (
          <p className="text-sm text-destructive">{t('validation.required')}</p>
        ) : null}
      </div>

      <Button
        type="submit"
        disabled={form.formState.isSubmitting}
        className="mt-1 h-12 rounded-xl bg-linear-to-r from-primary to-primary/80 text-sm font-semibold shadow-md shadow-primary/20 transition-transform active:scale-[0.99]"
      >
        {form.formState.isSubmitting ? (
          <>
            <Loader2 className="size-4 animate-spin" aria-hidden />
            {t('auth.submitting')}
          </>
        ) : (
          <>
            {t('auth.submit')}
            <ArrowRight className="size-4" aria-hidden />
          </>
        )}
      </Button>
    </form>
  )
}
