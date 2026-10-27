import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { usePermissions } from '@/app/auth/useAuth'
import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'

import { usersApi } from '../api/usersApi'
import { useRoles, useUserMutation } from '../hooks/useUsers'
import { RoleSelect } from './RoleSelect'

const schema = z.object({
  email: z.string().trim().email('Adresse email invalide.'),
  firstName: z.string().trim().max(255),
  lastName: z.string().trim().max(255),
  roleId: z.string(),
})

type Values = z.infer<typeof schema>

const EMPTY: Values = { email: '', firstName: '', lastName: '', roleId: '' }

export function InviteUserDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { can } = usePermissions()
  const canAssign = can('customer.users.assign_role') && can('customer.roles.view')
  const roles = useRoles()
  const invite = useUserMutation(usersApi.invite)
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: EMPTY })
  const [error, setError] = useState<string | null>(null)
  const roleId = useWatch({ control: form.control, name: 'roleId' })

  const close = (next: boolean) => {
    if (!next) {
      form.reset(EMPTY)
      setError(null)
    }
    onOpenChange(next)
  }

  const submit = async (values: Values) => {
    setError(null)

    try {
      await invite.mutateAsync({ ...values, roleId: values.roleId || null })
      toast.success(`Invitation envoyée à ${values.email}.`)
      close(false)
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, ['email', 'firstName', 'lastName', 'roleId'])
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Inviter un utilisateur</DialogTitle>
          <DialogDescription>Un lien d’activation lui sera envoyé par email.</DialogDescription>
        </DialogHeader>
        <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
          {error && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          <Field form={form} name="email" label="Email" type="email" />
          <div className="grid gap-4 sm:grid-cols-2">
            <Field form={form} name="firstName" label="Prénom (facultatif)" />
            <Field form={form} name="lastName" label="Nom (facultatif)" />
          </div>
          {canAssign && roles.data && (
            <RoleSelect roles={roles.data} value={roleId} onChange={(roleId) => form.setValue('roleId', roleId)} />
          )}
          {form.formState.errors.roleId?.message && (
            <p className="text-destructive text-sm">{form.formState.errors.roleId.message}</p>
          )}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => close(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              Envoyer l’invitation
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
