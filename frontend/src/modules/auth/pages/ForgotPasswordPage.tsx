import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowLeft, CheckCircle2, Info, Loader2, Mail } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { z } from 'zod'

import { authApi } from '../api/auth.api'
import { AuthLayout } from '@/modules/auth/components/AuthLayout'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

const schema = z.object({ email: z.string().min(1).email() })

/**
 * Demander le lien qui rend l'accès.
 *
 * **Réservé aux administrateurs.** Un exploitant ou un chauffeur qui perd son
 * mot de passe le demande au sien, qui le lui rend depuis sa fiche. L'écran le
 * dit avant la saisie, et non après : sans cet avertissement, un membre
 * ordinaire attendrait un courriel qui ne viendra jamais.
 *
 * **L'écran ne dit jamais si l'adresse est connue** — le backend non plus. Un
 * formulaire qui répondrait « adresse inconnue » serait un annuaire : essayées
 * une par une, les adresses d'une société révéleraient lesquelles ont un compte
 * ici. D'où le message d'arrivée, formulé au conditionnel.
 */
export function ForgotPasswordPage() {
  const { t } = useTranslation()
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const form = useForm<z.infer<typeof schema>>({
    resolver: zodResolver(schema),
    defaultValues: { email: '' },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setError(null)

    try {
      await authApi.forgotPassword(values.email)
      setSent(true)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : t('errors.unexpected'))
    }
  })

  return (
    <AuthLayout
      title={t('auth.forgot.title')}
      subtitle={t('auth.forgot.subtitle')}
      aside={
        <Link
          to="/login"
          className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
        >
          <ArrowLeft className="size-4" aria-hidden />
          {t('auth.reset.backToLogin')}
        </Link>
      }
    >
      {sent ? (
        <div className="flex items-start gap-3 rounded-xl border border-success/25 bg-success/10 p-4">
          <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" aria-hidden />
          <p className="text-sm">{t('auth.forgot.sent')}</p>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-5" noValidate>
          <p className="flex items-start gap-2.5 rounded-xl border border-border bg-muted/40 p-3.5 text-xs leading-relaxed text-muted-foreground">
            <Info className="mt-0.5 size-4 shrink-0" aria-hidden />
            {t('auth.forgot.adminOnly')}
          </p>

          {error !== null ? (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : null}

          <div className="flex flex-col gap-2">
            <Label
              htmlFor="email"
              className="text-xs font-semibold tracking-wider text-foreground uppercase"
            >
              {t('auth.email')}
            </Label>

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

          <Button
            type="submit"
            disabled={form.formState.isSubmitting}
            className="h-12 rounded-xl bg-linear-to-r from-primary to-primary/80 text-sm font-semibold shadow-md shadow-primary/20"
          >
            {form.formState.isSubmitting ? (
              <>
                <Loader2 className="size-4 animate-spin" aria-hidden />
                {t('auth.submitting')}
              </>
            ) : (
              t('auth.forgot.submit')
            )}
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
