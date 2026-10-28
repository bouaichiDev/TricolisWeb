import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'
import { z } from 'zod'

import { errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'

import { driversApi } from '../api/driversApi'
import type { Driver, DriverPayload } from '../types'

const schema = z.object({
  code: z.string().trim().min(1, 'Code requis.').max(64),
  firstName: z.string().trim().min(1, 'Prénom requis.').max(255),
  lastName: z.string().trim().min(1, 'Nom requis.').max(255),
  email: z.string().trim().max(255),
  phone: z.string().trim().max(32),
})

type Values = z.infer<typeof schema>
const FIELDS = ['code', 'firstName', 'lastName', 'email', 'phone'] as const

/** En modification, seuls les champs réellement changés partent au serveur. */
function changedFields(payload: Omit<DriverPayload, 'email'>, driver: Driver): Partial<DriverPayload> {
  const original: Omit<DriverPayload, 'email'> = { code: driver.code, firstName: driver.firstName ?? '', lastName: driver.lastName ?? '', phone: driver.phone }

  return Object.fromEntries(Object.entries(payload).filter(([key, value]) => value !== original[key as keyof typeof original]))
}

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Absent : ajout. Présent : modification (l'e-mail du compte ne change pas). */
  driver?: Driver
}

/**
 * Ajout ou modification d'un chauffeur. Le fournisseur n'est jamais envoyé :
 * le serveur l'impose. Un e-mail ou un code déjà pris revient en refus neutre,
 * affiché sous le champ.
 */
export function DriverDialog({ open, onOpenChange, driver }: Props) {
  const queryClient = useQueryClient()
  const [error, setError] = useState<string | null>(null)
  const editing = driver !== undefined
  const form = useForm<Values>({
    resolver: zodResolver(editing ? schema : schema.extend({ email: z.string().trim().min(1, 'E-mail requis.').email('E-mail invalide.') })),
    values: {
      code: driver?.code ?? '', firstName: driver?.firstName ?? '', lastName: driver?.lastName ?? '',
      email: driver?.email ?? '', phone: driver?.phone ?? '',
    },
  })

  const accountLocked = editing && driver.actions.canEditAccount === false

  const submit = async ({ email, ...values }: Values) => {
    setError(null)
    const payload = { ...values, phone: values.phone || null }

    try {
      if (editing) await driversApi.update(driver.id, changedFields(payload, driver))
      else await driversApi.create({ ...payload, email })
      await queryClient.invalidateQueries({ queryKey: ['drivers'] })
      toast.success(editing ? 'Chauffeur modifié.' : 'Chauffeur ajouté.')
      if (!editing) form.reset()
      onOpenChange(false)
    } catch (caught) {
      const orphans = applyServerErrors(form, caught, FIELDS)
      setError(orphans[0] ?? (orphans.length === 0 ? errorMessage(caught) : null))
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{editing ? 'Modifier le chauffeur' : 'Ajouter un chauffeur'}</DialogTitle>
          <DialogDescription>
            {editing ? driver.email : 'Un compte est préparé pour le chauffeur ; il ne donne pas accès à ce portail.'}
          </DialogDescription>
        </DialogHeader>
        <form className="space-y-4" noValidate onSubmit={form.handleSubmit(submit)}>
          {error && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
          <Field form={form} name="code" label="Code" />
          <div className="grid gap-4 sm:grid-cols-2">
            <Field form={form} name="firstName" label="Prénom" disabled={accountLocked} />
            <Field form={form} name="lastName" label="Nom" disabled={accountLocked} />
          </div>
          {!editing && <Field form={form} name="email" label="E-mail" type="email" />}
          <Field form={form} name="phone" label="Téléphone" type="tel" disabled={accountLocked} />
          {accountLocked && <p className="text-muted-foreground text-sm">Ce compte sert aussi ailleurs : son identité et son téléphone ne se modifient pas ici.</p>}
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
