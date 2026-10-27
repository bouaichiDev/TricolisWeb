import { BookOpen, PenLine, Plus, Trash2 } from 'lucide-react'
import { useFieldArray, useWatch, type UseFormReturn } from 'react-hook-form'

import { Field } from '@/shared/components/form/Field'
import { fieldError } from '@/shared/components/form/fieldError'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import { emptyLine, newUid } from '../form/orderMapping'
import type { LineValues, OrderFormValues } from '../form/orderSchema'
import { ArticlePicker } from './ArticlePicker'
import { CatalogBrowserDialog, type PickedArticle } from './CatalogBrowserDialog'

const isBlank = (line: LineValues | undefined) => line !== undefined && line.catalogItemId === '' && line.name.trim() === ''

/**
 * Étape « Lignes ». Un client doté d'un catalogue y choisit ses articles —
 * plusieurs d'un coup, ou un par ligne — et peut toujours saisir une ligne
 * libre. Sans catalogue, seule la saisie libre est proposée.
 */
export function LinesStep({ form, catalogEnabled }: { form: UseFormReturn<OrderFormValues>; catalogEnabled: boolean }) {
  const lines = useFieldArray({ control: form.control, name: 'lines', keyName: 'fieldKey' })
  const values = useWatch({ control: form.control, name: 'lines' })
  const rootError = fieldError(form, 'lines')

  const addFromCatalog = (picked: PickedArticle[]) => {
    // La ligne vide posée d'office ne doit pas rester en trop devant les articles.
    if (lines.fields.length === 1 && isBlank(values?.[0])) lines.remove(0)

    lines.append(
      picked.map(({ item, quantity }) => ({
        uid: newUid(),
        catalogItemId: item.id,
        articleLabel: `${item.articleCode} — ${item.name}`,
        name: '',
        quantity: quantity || '1',
        weight: item.weight ? String(Number(item.weight)) : '',
      })),
    )
  }

  const setArticle = (index: number, id: string, label: string, weight: string) => {
    form.setValue(`lines.${index}.catalogItemId`, id)
    form.setValue(`lines.${index}.articleLabel`, label)
    form.setValue(`lines.${index}.name`, '')
    form.setValue(`lines.${index}.weight`, weight)
    form.clearErrors(`lines.${index}.name`)
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap gap-2">
        {catalogEnabled && <CatalogBrowserDialog onAdd={addFromCatalog} />}
        <Button type="button" variant="outline" onClick={() => lines.append(emptyLine())}>
          <Plus className="size-4" aria-hidden /> Ajouter une ligne libre
        </Button>
      </div>
      {rootError && <p className="text-destructive text-sm">{rootError}</p>}

      <ul className="divide-y rounded-lg border">
        {lines.fields.map((line, index) => {
          const current = values?.[index]
          const fromCatalog = current !== undefined && current.catalogItemId !== ''
          const error = fieldError(form, `lines.${index}.name`)

          return (
            <li key={line.fieldKey} className="grid gap-3 p-4 sm:grid-cols-[1fr_120px_140px_auto] sm:items-end">
              <div className="grid gap-1.5">
                {fromCatalog ? (
                  <>
                    <span className="text-sm font-medium">Article du catalogue</span>
                    <div className="flex items-center gap-2">
                      <Badge variant="secondary" className="font-normal"><BookOpen className="size-3" aria-hidden /> Catalogue</Badge>
                      <span className="truncate text-sm">{current.articleLabel}</span>
                      <Button type="button" variant="link" size="sm" className="px-1" onClick={() => setArticle(index, '', '', '')}>
                        <PenLine className="size-3" aria-hidden /> Saisie libre
                      </Button>
                    </div>
                  </>
                ) : (
                  <>
                    <Field form={form} name={`lines.${index}.name`} label="Désignation" placeholder="Ex. Canapé 3 places" />
                    {catalogEnabled && (
                      <ArticlePicker label="…ou choisir un article du catalogue"
                        onPick={(item) => setArticle(index, item.id, `${item.articleCode} — ${item.name}`, item.weight ? String(Number(item.weight)) : '')} />
                    )}
                  </>
                )}
                {fromCatalog && error && <p className="text-destructive text-sm">{error}</p>}
              </div>
              <Field form={form} name={`lines.${index}.quantity`} label="Quantité" inputMode="decimal" />
              <Field form={form} name={`lines.${index}.weight`} label="Poids unitaire (kg)" inputMode="decimal" />
              <Button type="button" variant="ghost" size="icon" aria-label={`Supprimer la ligne ${index + 1}`}
                disabled={lines.fields.length === 1} onClick={() => lines.remove(index)}>
                <Trash2 className="size-4" aria-hidden />
              </Button>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
