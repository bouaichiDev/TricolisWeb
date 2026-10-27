import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { toast } from 'sonner'

import { ApiError, errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Label } from '@/shared/components/ui/label'
import { Textarea } from '@/shared/components/ui/textarea'

import { claimsApi } from '../api/claimsApi'
import { claimSchema, type ClaimFormValues } from '../form/claimSchema'
import { useClaimMutation } from '../hooks/useClaims'
import type { ClaimDetail } from '../types'

const EDITABLE = ['title', 'claimType', 'description'] as const

interface ClaimEditDialogProps {
  claim: ClaimDetail
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Corriger une réclamation encore ouverte (§11) : titre, type, description.
 * Commande, service et tout ce que le transporteur instruit restent hors
 * de portée — le backend refuse d'ailleurs de les recevoir.
 */
export function ClaimEditDialog({ claim, open, onOpenChange }: ClaimEditDialogProps) {
  const [formError, setFormError] = useState<string | null>(null)
  const update = useClaimMutation(claimsApi.update)
  const form = useForm<ClaimFormValues>({
    resolver: zodResolver(claimSchema),
    values: { orderId: '', orderServiceId: '', title: claim.title, claimType: claim.claimType, description: claim.description ?? '' },
  })

  const submit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      await update.mutateAsync({ id: claim.id, title: values.title, claimType: values.claimType, description: values.description || null })
      toast.success('Réclamation modifiée.')
      onOpenChange(false)
    } catch (caught) {
      applyServerErrors(form, caught, EDITABLE)
      if (!(caught instanceof ApiError && caught.isValidation)) setFormError(errorMessage(caught))
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Modifier la réclamation</DialogTitle>
        </DialogHeader>
        <form onSubmit={(event) => void submit(event)} className="grid gap-4" noValidate>
          {formError && (
            <Alert variant="destructive">
              <AlertDescription>{formError}</AlertDescription>
            </Alert>
          )}
          <Field form={form} name="title" label="Titre" maxLength={255} />
          <Field form={form} name="claimType" label="Type de réclamation" maxLength={64} />
          <div className="grid gap-1.5">
            <Label htmlFor="claim-edit-description">Description</Label>
            <Textarea id="claim-edit-description" rows={5} maxLength={5000} {...form.register('description')} />
          </div>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={update.isPending}>
              Enregistrer
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
