import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import { TYPE_STATUSES } from '../types/type'

/** Longueurs reprises de `StoreTypeRequest` et `StoreTypeItemRequest`. */
export const typeFormSchema = z.object({
  code: z.string().min(1, 'validation.required').max(64, 'validation.max'),
  name: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  status: z.string().min(1, 'validation.required'),
})

export type TypeFormValues = z.infer<typeof typeFormSchema>

const DEFAULTS: TypeFormValues = { code: '', name: '', status: 'active' }

interface TypeFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description: string
  defaultValues?: Partial<TypeFormValues>
  /**
   * Vrai pour une source structurelle : le schéma la désigne par son code, que
   * modifier laisserait `vehicles.vehicle_type_id` sans source. Le serveur le
   * refuse déjà ; le champ le montre plutôt que de laisser saisir en vain.
   */
  codeLocked?: boolean
  onSubmit: (values: TypeFormValues) => Promise<unknown>
}

/**
 * Saisie d'une source ou d'une valeur de référentiel.
 *
 * Les deux ont les mêmes trois champs. Un dialogue plutôt qu'une page : on ne
 * quitte pas la liste pour nommer trois lignes.
 */
export function TypeFormDialog({
  open,
  onOpenChange,
  title,
  description,
  defaultValues,
  codeLocked = false,
  onSubmit,
}: TypeFormDialogProps) {
  const { t } = useTranslation()

  const form = useForm<TypeFormValues>({
    resolver: zodResolver(typeFormSchema),
    defaultValues: { ...DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values)
      form.reset(DEFAULTS)
      onOpenChange(false)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
          <FormErrorSummary message={formError} />

          <TextField
            form={form}
            name="code"
            label={t('types.fields.code')}
            required
            disabled={codeLocked}
            description={codeLocked ? t('types.codeLocked') : undefined}
          />
          <TextField form={form} name="name" label={t('types.fields.name')} required />
          <StatusSelect
            form={form}
            name="status"
            label={t('types.fields.status')}
            options={TYPE_STATUSES}
          />

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? t('common.saving') : t('common.save')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
