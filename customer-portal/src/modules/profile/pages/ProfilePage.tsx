import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { useAuth } from '@/app/auth/useAuth'
import { api } from '@/shared/api/client'
import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { SectionCard } from '@/shared/components/layout/SectionCard'

const schema = z.object({
  firstName: z.string().trim().min(1, 'Prénom requis.').max(255),
  lastName: z.string().trim().min(1, 'Nom requis.').max(255),
  phone: z.string().trim().max(255),
})

type Values = z.infer<typeof schema>

/** Le membre corrige sa propre identité. L'email, identifiant de connexion, est fixe. */
export function ProfilePage() {
  const { context, refresh } = useAuth()
  const [error, setError] = useState<string | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    values: {
      firstName: context?.user.firstName ?? '',
      lastName: context?.user.lastName ?? '',
      phone: context?.user.phone ?? '',
    },
  })

  if (context === null) return null

  const submit = async (values: Values) => {
    setError(null)

    try {
      await api.put('/profile', { ...values, phone: values.phone || null })
      await refresh()
      toast.success('Profil mis à jour.')
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, ['firstName', 'lastName', 'phone'])
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Mon profil" description={context.user.email} />
      <div className="max-w-2xl">
        <SectionCard title="Identité" description="L’adresse e-mail sert à vous connecter : elle ne se modifie pas ici.">
          <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
            {error && (
              <Alert variant="destructive" role="alert">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
              <Field form={form} name="firstName" label="Prénom" autoComplete="given-name" />
              <Field form={form} name="lastName" label="Nom" autoComplete="family-name" />
            </div>
            <Field form={form} name="phone" label="Téléphone" type="tel" autoComplete="tel" />
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Enregistrer
            </Button>
          </form>
        </SectionCard>
      </div>
    </div>
  )
}
