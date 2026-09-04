import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import {
  CATALOG_ITEM_FORM_DEFAULTS,
  catalogItemSchema,
  type CatalogItemFormValues,
} from '../schemas/catalogSchema'
import { CATALOG_STATUSES } from '../types/catalog'
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

interface CatalogItemDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  defaultValues?: Partial<CatalogItemFormValues>
  onSubmit: (values: CatalogItemFormValues) => Promise<unknown>
}

/**
 * Saisie d'un article de catalogue.
 *
 * `articleCode` tient lieu de référence : ni `SKU` ni `unit` n'existent dans
 * la ressource, et le §12 du prompt corrigé interdit de les inventer.
 *
 * Poids, volume et dimensions restent facultatifs — un article qui ne les
 * porte pas les recevra de la ligne de commande.
 */
export function CatalogItemDialog({
  open,
  onOpenChange,
  defaultValues,
  onSubmit,
}: CatalogItemDialogProps) {
  const { t } = useTranslation()
  const isEdit = defaultValues !== undefined

  const form = useForm<CatalogItemFormValues>({
    resolver: zodResolver(catalogItemSchema),
    defaultValues: { ...CATALOG_ITEM_FORM_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values)
      form.reset(CATALOG_ITEM_FORM_DEFAULTS)
      onOpenChange(false)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            {isEdit ? t('catalogItems.edit') : t('catalogItems.create')}
          </DialogTitle>
          <DialogDescription>{t('catalogItems.hint')}</DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
          <FormErrorSummary message={formError} />

          <div className="grid gap-5 sm:grid-cols-2">
            <TextField
              form={form}
              name="articleCode"
              label={t('catalogItems.fields.articleCode')}
              required
            />
            <TextField form={form} name="barcode" label={t('catalogItems.fields.barcode')} />
            <TextField form={form} name="name" label={t('catalogItems.fields.name')} required />
            <StatusSelect
              form={form}
              name="status"
              label={t('catalogItems.fields.status')}
              options={CATALOG_STATUSES}
            />
            <div className="sm:col-span-2">
              <TextField
                form={form}
                name="description"
                label={t('catalogItems.fields.description')}
              />
            </div>
          </div>

          <div className="grid gap-5 sm:grid-cols-5">
            <TextField form={form} name="weight" label={t('catalogItems.fields.weight')} />
            <TextField form={form} name="volume" label={t('catalogItems.fields.volume')} />
            <TextField form={form} name="length" label={t('catalogItems.fields.length')} />
            <TextField form={form} name="width" label={t('catalogItems.fields.width')} />
            <TextField form={form} name="height" label={t('catalogItems.fields.height')} />
          </div>

          {/* Le montage n'est pas une mesure de l'article : il est a part. */}
          <div className="grid gap-5 sm:grid-cols-5">
            <TextField
              form={form}
              name="assemblyTimeMinutes"
              label={t('catalogItems.fields.assemblyTimeMinutes')}
              description={t('catalogItems.assemblyTimeHint')}
            />
          </div>

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
