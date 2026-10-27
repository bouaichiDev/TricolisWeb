import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowRight, Loader2, Mail } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { z } from 'zod'

import { paths } from '@/app/router/paths'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import type { ProviderChoice } from '../api/authApi'
import { PasswordInput } from './PasswordInput'

const schema = z.object({
  email: z.string().trim().min(1, 'Adresse e-mail requise.').email('Adresse e-mail invalide.'),
  password: z.string().min(1, 'Mot de passe requis.'),
})

export type LoginValues = z.infer<typeof schema>

function FieldLabel({ htmlFor, children }: { htmlFor: string; children: ReactNode }) {
  return (
    <Label htmlFor={htmlFor} className="text-foreground text-xs font-semibold tracking-wider uppercase">
      {children}
    </Label>
  )
}

interface LoginFormProps {
  onSubmit: (values: LoginValues) => Promise<void>
  /** Appelé quand le compte est rattaché à plusieurs fournisseurs. */
  onChooseProvider: (values: LoginValues, choices: ProviderChoice[]) => void
}

/**
 * Le formulaire de connexion, à la facture du back-office : petites capitales,
 * champs à icône, œil sur le mot de passe, bouton en dégradé.
 */
export function LoginForm({ onSubmit, onChooseProvider }: LoginFormProps) {
  const [serverError, setServerError] = useState<string | null>(null)
  const form = useForm<LoginValues>({ resolver: zodResolver(schema), defaultValues: { email: '', password: '' } })
  const { errors, isSubmitting } = form.formState

  const submit = form.handleSubmit(async (values) => {
    setServerError(null)

    try {
      await onSubmit(values)
    } catch (caught) {
      if (caught instanceof ApiError && caught.code === 'provider_selection_required') {
        onChooseProvider(values, (caught.body.providers as ProviderChoice[]) ?? [])

        return
      }

      const detail = caught instanceof ApiError ? (caught.fieldError('email') ?? caught.fieldError('providerId')) : undefined
      setServerError(detail ?? errorMessage(caught))
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
      {serverError !== null && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      )}

      <div className="flex flex-col gap-2">
        <FieldLabel htmlFor="email">Email</FieldLabel>
        <div className="relative">
          <Mail
            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2"
            aria-hidden
          />
          <Input
            id="email"
            type="email"
            autoComplete="username"
            autoFocus
            placeholder="nom@entreprise.ch"
            className="bg-muted/40 h-12 rounded-xl pl-11"
            aria-invalid={errors.email !== undefined}
            {...form.register('email')}
          />
        </div>
        {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
      </div>

      <div className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <FieldLabel htmlFor="password">Mot de passe</FieldLabel>
          <Link to={paths.forgotPassword} className="text-primary text-xs font-semibold hover:underline">
            Mot de passe oublié ?
          </Link>
        </div>
        <PasswordInput
          id="password"
          autoComplete="current-password"
          placeholder="Entrez votre mot de passe"
          aria-invalid={errors.password !== undefined}
          {...form.register('password')}
        />
        {errors.password && <p className="text-destructive text-sm">{errors.password.message}</p>}
      </div>

      <Button
        type="submit"
        disabled={isSubmitting}
        className="from-primary to-primary/80 shadow-primary/20 mt-1 h-12 rounded-xl bg-linear-to-r text-sm font-semibold shadow-md transition-transform active:scale-[0.99]"
      >
        {isSubmitting ? (
          <>
            <Loader2 className="size-4 animate-spin" aria-hidden /> Connexion…
          </>
        ) : (
          <>
            Se connecter <ArrowRight className="size-4" aria-hidden />
          </>
        )}
      </Button>
    </form>
  )
}
