import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowRight, Loader2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { useSubmitAccessRequest } from '../hooks/useAccessRequests'
import { ApiError } from '@/shared/api/client'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

/**
 * Les quatre champs obligatoires sont ceux dont l'administrateur a besoin pour
 * décider : la société, le nom de qui demande, et **deux** moyens de le
 * joindre. Le téléphone n'est pas de confort — une adresse de courriel se crée
 * en trente secondes, et ne prouve donc rien.
 */
const schema = z.object({
  companyName: z.string().min(1).max(255),
  contactName: z.string().min(1).max(255),
  email: z.string().min(1).email().max(255),
  phone: z.string().min(6).max(40),
  message: z.string().max(2000).optional(),
})

type Values = z.infer<typeof schema>

const FIELD = 'h-11 rounded-xl bg-muted/40'
const LABEL = 'text-xs font-semibold tracking-wider text-foreground uppercase'

export function AccessRequestForm({ onSubmitted }: { onSubmitted: () => void }) {
  const { t } = useTranslation()
  const submit = useSubmitAccessRequest()
  const [serverError, setServerError] = useState<string | null>(null)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { companyName: '', contactName: '', email: '', phone: '', message: '' },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setServerError(null)

    try {
      await submit.mutateAsync({ ...values, message: values.message || undefined })
      onSubmitted()
    } catch (error) {
      // Le message du backend est affichable tel quel — « une demande est déjà
      // en cours pour cette adresse » se comprend mieux que « champ invalide ».
      setServerError(
        error instanceof ApiError ? error.message : t('errors.unexpected'),
      )
    }
  })

  const field = (name: keyof Values, type = 'text') => (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={name} className={LABEL}>
        {t(`accessRequests.fields.${name}`)}
      </Label>
      <Input
        id={name}
        type={type}
        className={FIELD}
        aria-invalid={form.formState.errors[name] !== undefined}
        {...form.register(name)}
      />
      {form.formState.errors[name] ? (
        <p className="text-sm text-destructive">
          {t(name === 'email' ? 'validation.email' : 'validation.required')}
        </p>
      ) : null}
    </div>
  )

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-3.5" noValidate>
      {serverError !== null ? (
        <Alert variant="destructive">
          <AlertDescription>{serverError}</AlertDescription>
        </Alert>
      ) : null}

      {/* Deux colonnes dès `sm` : le formulaire tient alors sous la ligne de
          flottaison, et l'écran de connexion ne fait pas défiler ce qu'il
          demande de remplir. */}
      <div className="grid gap-3.5 sm:grid-cols-2">
        {field('companyName')}
        {field('contactName')}
      </div>

      <div className="grid gap-3.5 sm:grid-cols-2">
        {field('email', 'email')}
        {field('phone', 'tel')}
      </div>

      <div className="flex flex-col gap-1.5">
        <Label htmlFor="message" className={LABEL}>
          {t('accessRequests.fields.message')}
        </Label>
        <Textarea
          id="message"
          rows={2}
          className="rounded-xl bg-muted/40"
          placeholder={t('accessRequests.messagePlaceholder')}
          {...form.register('message')}
        />
      </div>

      <Button
        type="submit"
        disabled={form.formState.isSubmitting}
        className="mt-1 h-12 rounded-xl bg-linear-to-r from-primary to-primary/80 text-sm font-semibold shadow-md shadow-primary/20"
      >
        {form.formState.isSubmitting ? (
          <>
            <Loader2 className="size-4 animate-spin" aria-hidden />
            {t('auth.submitting')}
          </>
        ) : (
          <>
            {t('accessRequests.submit')}
            <ArrowRight className="size-4" aria-hidden />
          </>
        )}
      </Button>
    </form>
  )
}
