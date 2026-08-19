import { BookOpen, Trash2, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import type { CatalogItem } from '@/modules/catalogs/types/catalog'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

import type { LineDraft } from '../../schemas/orderDraft'
import { fieldError, type OrderIssue } from '../../schemas/orderErrors'
import { CatalogItemPicker } from './CatalogItemPicker'
import { LineDimensionFields } from './LineDimensionFields'

interface OrderLineCardProps {
  line: LineDraft
  position: number
  customerId: string
  catalogEnabled: boolean
  issues: OrderIssue[]
  onChange: (values: Partial<LineDraft>) => void
  onRemove: () => void
  canRemove: boolean
}

/**
 * Une ligne de commande, saisie librement ou reprise d'un catalogue.
 *
 * Reprendre un article recopie ses champs dans la ligne plutôt que de les
 * masquer : le backend accepte des valeurs explicites qui priment sur celles du
 * catalogue, et une commande doit rester lisible même si l'article évolue plus
 * tard.
 */
export function OrderLineCard({
  line,
  position,
  customerId,
  catalogEnabled,
  issues,
  onChange,
  onRemove,
  canRemove,
}: OrderLineCardProps) {
  const { t } = useTranslation()
  const [picking, setPicking] = useState(false)

  const applyItem = (item: CatalogItem) => {
    onChange({
      catalogItemId: item.id,
      articleCode: item.articleCode,
      barcode: item.barcode ?? '',
      name: item.name,
      description: item.description ?? '',
      weight: item.weight === null ? '' : String(item.weight),
      volume: item.volume === null ? '' : String(item.volume),
      length: item.length === null ? '' : String(item.length),
      width: item.width === null ? '' : String(item.width),
      height: item.height === null ? '' : String(item.height),
    })
  }

  return (
    <li className="rounded-lg border p-4">
      <div className="mb-4 flex items-start justify-between gap-2">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">
            {t('orders.lines.position', { position })}
          </span>
          {line.catalogItemId !== null ? (
            <Badge variant="secondary">{t('orders.lines.catalogItem')}</Badge>
          ) : null}
        </div>

        <div className="flex gap-2">
          {catalogEnabled && customerId !== '' ? (
            <Button type="button" variant="outline" size="sm" onClick={() => setPicking(true)}>
              <BookOpen className="size-4" aria-hidden />
              {t('orders.lines.pickFromCatalog')}
            </Button>
          ) : null}

          {line.catalogItemId !== null ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => onChange({ catalogItemId: null })}
            >
              <X className="size-4" aria-hidden />
              {t('orders.lines.clearCatalogItem')}
            </Button>
          ) : null}

          {canRemove ? (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={onRemove}
              aria-label={t('orders.lines.remove')}
            >
              <Trash2 className="size-4" aria-hidden />
            </Button>
          ) : null}
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <ControlledField
          label={t('orders.fields.name')}
          value={line.name}
          onChange={(name) => onChange({ name })}
          required={line.catalogItemId === null}
          error={fieldError(issues, 'name')}
        />

        <ControlledField
          label={t('orders.fields.articleCode')}
          value={line.articleCode}
          onChange={(articleCode) => onChange({ articleCode })}
          error={fieldError(issues, 'articleCode')}
        />

        <ControlledField
          label={t('orders.fields.barcode')}
          value={line.barcode}
          onChange={(barcode) => onChange({ barcode })}
          error={fieldError(issues, 'barcode')}
        />

        <ControlledField
          label={t('orders.fields.quantity')}
          type="number"
          min="0"
          step="0.001"
          value={line.quantity}
          onChange={(quantity) => onChange({ quantity })}
          required
          error={fieldError(issues, 'quantity')}
        />

        <ControlledField
          label={t('orders.fields.weight')}
          type="number"
          min="0"
          step="0.001"
          value={line.weight}
          onChange={(weight) => onChange({ weight })}
          error={fieldError(issues, 'weight')}
        />

        <ControlledField
          label={t('orders.fields.volume')}
          type="number"
          min="0"
          step="0.001"
          value={line.volume}
          onChange={(volume) => onChange({ volume })}
          error={fieldError(issues, 'volume')}
        />

        <ControlledField
          label={t('orders.fields.externalReference')}
          value={line.externalReference}
          onChange={(externalReference) => onChange({ externalReference })}
          error={fieldError(issues, 'externalReference')}
        />
      </div>

      <LineDimensionFields line={line} issues={issues} onChange={onChange} />

      <div className="mt-4">
        <ControlledField
          label={t('orders.fields.description')}
          value={line.description}
          onChange={(description) => onChange({ description })}
          multiline
          error={fieldError(issues, 'description')}
        />
      </div>

      {picking ? (
        <CatalogItemPicker
          customerId={customerId}
          open={picking}
          onOpenChange={setPicking}
          onSelect={applyItem}
        />
      ) : null}
    </li>
  )
}
