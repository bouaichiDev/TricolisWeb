import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
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

import { useCreateCatalogItem, useUpdateCatalogItem } from '../hooks/useCatalog'
import type { Catalog, CatalogItem } from '../types'

const decimal = z.string().trim().regex(/^(\d+([.,]\d{1,4})?)?$/, 'Un nombre positif, quatre décimales au plus.')

const schema = z.object({
  catalogId: z.string(),
  articleCode: z.string().trim().min(1, 'Le code article est obligatoire.').max(128, '128 caractères au plus.'),
  name: z.string().trim().min(1, 'La désignation est obligatoire.').max(255, '255 caractères au plus.'),
  barcode: z.string().trim().max(128, '128 caractères au plus.'),
  description: z.string().trim().max(2000, '2 000 caractères au plus.'),
  weight: decimal,
  volume: decimal,
})

type Values = z.infer<typeof schema>
const FIELDS = ['catalogId', 'articleCode', 'name', 'barcode', 'description', 'weight', 'volume'] as const

/** Un décimal stocké (`12.500`) tel qu'on l'écrit : `12,5`. */
const shown = (value: string | null) =>
  value === null ? '' : (value.includes('.') ? value.replace(/0+$/, '').replace(/\.$/, '') : value).replace('.', ',')
const sent = (value: string) => (value === '' ? null : value.replace(',', '.'))

interface CatalogItemDialogProps {
  catalogs: Catalog[]
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Présent : modification de cet article. Absent : ajout. */
  item?: CatalogItem
}

/**
 * Ajouter (`customer.articles.create`) ou modifier (`customer.articles.update`)
 * un article. Un article ne change jamais de catalogue, et son statut passe par
 * « Désactiver » / « Réactiver » : ni l'un ni l'autre ne sont envoyés ici.
 */
export function CatalogItemDialog({ catalogs, open, onOpenChange, item }: CatalogItemDialogProps) {
  const active = catalogs.filter((catalog) => catalog.isActive)
  const navigate = useNavigate()
  const create = useCreateCatalogItem()
  const update = useUpdateCatalogItem()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    values: item
      ? { catalogId: item.catalog?.id ?? '', articleCode: item.articleCode, name: item.name, barcode: item.barcode ?? '', description: item.description ?? '', weight: shown(item.weight), volume: shown(item.volume) }
      : { catalogId: active.length === 1 ? active[0].id : '', articleCode: '', name: '', barcode: '', description: '', weight: '', volume: '' },
  })

  const submit = form.handleSubmit(async (values) => {
    setFormError(null)
    if (!item && values.catalogId === '') {
      form.setError('catalogId', { message: 'Choisissez un catalogue.' })
      return
    }
    const fields = {
      articleCode: values.articleCode,
      name: values.name,
      barcode: values.barcode || null,
      description: values.description || null,
      weight: sent(values.weight),
      volume: sent(values.volume),
    }

    try {
      if (item) {
        await update.mutateAsync({ id: item.id, ...fields })
        toast.success(`Article ${values.articleCode} modifié.`)
        onOpenChange(false)
        return
      }
      const saved = await create.mutateAsync({ catalogId: values.catalogId, ...fields })
      toast.success(`Article ${saved.articleCode} ajouté au catalogue.`)
      onOpenChange(false)
      navigate(paths.catalogItem(saved.id))
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
          <DialogDescription>
            {item ? 'Les commandes et le stock existants gardent leurs informations.' : 'Il sera utilisable dans vos commandes et rattachable à votre stock.'}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={(event) => void submit(event)} className="grid gap-4" noValidate>
          {formError && (
            <Alert variant="destructive">
              <AlertDescription>{formError}</AlertDescription>
            </Alert>
          )}
          {!item && (
            <SelectField form={form} name="catalogId" label="Catalogue" placeholder="Choisir…" options={active.map((catalog) => ({ value: catalog.id, label: catalog.name }))} />
          )}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field form={form} name="articleCode" label="Code article" />
            <Field form={form} name="barcode" label="Code-barres" />
          </div>
          <Field form={form} name="name" label="Désignation" />
          <Field form={form} name="description" label="Description" />
          <div className="grid gap-4 sm:grid-cols-2">
            <Field form={form} name="weight" label="Poids (kg)" inputMode="decimal" />
            <Field form={form} name="volume" label="Volume (m³)" inputMode="decimal" />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Annuler</Button>
            <Button type="submit" disabled={create.isPending || update.isPending}>{item ? 'Enregistrer' : 'Ajouter'}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
