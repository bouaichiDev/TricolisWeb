import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { errorMessage } from '@/shared/api/errors'
import type { PortalUser } from '@/shared/api/types'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'

import { usersApi } from '../api/usersApi'
import { useUserMutation } from '../hooks/useUsers'

const schema = z.object({
  firstName: z.string().trim().min(1, 'Prénom requis.').max(255),
  lastName: z.string().trim().min(1, 'Nom requis.').max(255),
  phone: z.string().trim().max(255),
})

type Values = z.infer<typeof schema>

interface Props {
  member: PortalUser
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function EditUserDialog({ member, open, onOpenChange }: Props) {
  const update = useUserMutation((values: Values) =>
    usersApi.update(member.id, { ...values, phone: values.phone || null }),
  )
  const [error, setError] = useState<string | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    values: { firstName: member.user.firstName, lastName: member.user.lastName, phone: member.user.phone ?? '' },
  })

  const submit = async (values: Values) => {
    setError(null)

    try {
      await update.mutateAsync(values)
      toast.success('Utilisateur mis à jour.')
      onOpenChange(false)
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, ['firstName', 'lastName', 'phone'])
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Modifier l’utilisateur</DialogTitle>
        </DialogHeader>
        <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
          {error && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field form={form} name="firstName" label="Prénom" />
            <Field form={form} name="lastName" label="Nom" />
          </div>
          <Field form={form} name="phone" label="Téléphone" type="tel" />
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Enregistrer
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
