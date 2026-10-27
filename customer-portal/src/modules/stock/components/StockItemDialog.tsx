import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { z } from 'zod'

import { paths } from '@/app/router/paths'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { Field } from '@/shared/components/form/Field'
import { SelectField } from '@/shared/components/form/SelectField'
import { applyServerErrors } from '@/shared/components/form/serverErrors'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'

import { stockApi } from '../api/stockApi'
import { useStockMutation } from '../hooks/useStock'
import type { StockItem } from '../types'
import { CatalogItemField } from './CatalogItemField'

const schema = z.object({
  catalogItemId: z.string(),
  articleCode: z.string().trim().min(1, 'Le code article est obligatoire.').max(64, '64 caractères au plus.'),
  barcode: z.string().trim().max(128, '128 caractères au plus.'),
  description: z.string().trim().max(255, '255 caractères au plus.'),
  status: z.string(),
})

type Values = z.infer<typeof schema>
const FIELDS = ['catalogItemId', 'articleCode', 'barcode', 'description', 'status'] as const

interface StockItemDialogProps {
  item?: StockItem
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Ajouter un article, ou corriger ses métadonnées (§13-§14). Aucun champ de
 * quantité : un article n'est pas du stock — sans mouvement du transporteur,
 * il vaut 0.
 */
export function StockItemDialog({ item, open, onOpenChange }: StockItemDialogProps) {
  // Statuts du référentiel, lus avec les options de la liste à l'ouverture.
  const options = useQuery({ queryKey: ['stock', 'options'], queryFn: () => stockApi.items({ perPage: 1 }), enabled: open, staleTime: 5 * 60 * 1000 })
  const statuses = options.data?.meta.filters.statuses ?? []
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)
  const create = useStockMutation(stockApi.createItem)
  const update = useStockMutation(stockApi.updateItem)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    values: { catalogItemId: item?.catalogItemId ?? '', articleCode: item?.articleCode ?? '', barcode: item?.barcode ?? '', description: item?.description ?? '', status: item?.status.code ?? '' },
  })

  const catalogItemId = useWatch({ control: form.control, name: 'catalogItemId' })

  const submit = form.handleSubmit(async (values) => {
    setFormError(null)
    const payload = { catalogItemId: values.catalogItemId || null, articleCode: values.articleCode, barcode: values.barcode || null, description: values.description || null, ...(values.status ? { status: values.status } : {}) }

    try {
      const saved = item ? await update.mutateAsync({ id: item.id, ...payload }) : await create.mutateAsync(payload)
      toast.success(item ? 'Article modifié.' : 'Article ajouté : son stock apparaîtra à la première entrée du transporteur.')
      onOpenChange(false)
      if (!item) navigate(paths.stockItem(saved.id))
    } catch (caught) {
      applyServerErrors(form, caught, FIELDS)
      if (!(caught instanceof ApiError && caught.isValidation)) setFormError(errorMessage(caught))
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{item ? 'Modifier l’article' : 'Ajouter un article'}</DialogTitle>
          <DialogDescription>La quantité est tenue par votre transporteur, à partir de ses mouvements de stock.</DialogDescription>
        </DialogHeader>
        <form onSubmit={(event) => void submit(event)} className="grid gap-4" noValidate>
          {formError && (
            <Alert variant="destructive">
              <AlertDescription>{formError}</AlertDescription>
            </Alert>
          )}
          <CatalogItemField
            value={catalogItemId}
            onChange={(catalogItem) => {
              form.setValue('catalogItemId', catalogItem?.id ?? '')
              if (catalogItem && !form.getValues('articleCode')) form.setValue('articleCode', catalogItem.articleCode.slice(0, 64))
              if (catalogItem && !form.getValues('description')) form.setValue('description', catalogItem.name.slice(0, 255))
            }}
          />
          <Field form={form} name="articleCode" label="Code article" maxLength={64} />
          <Field form={form} name="barcode" label="Code-barres" maxLength={128} />
          <Field form={form} name="description" label="Description" maxLength={255} />
          <SelectField form={form} name="status" label="Statut" placeholder="Statut par défaut" options={statuses.map((status) => ({ value: status.code, label: status.label }))} />
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={create.isPending || update.isPending}>
              {item ? 'Enregistrer' : 'Ajouter'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
