import { CornerDownRight, Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { useTypeItemOptions } from '@/modules/types/hooks/useTypes'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Button } from '@/shared/components/ui/button'

import type { LineAllocation } from '../../schemas/allocations'
import type { LineDraft, PackageDraft } from '../../schemas/orderDraft'
import { fieldError, type OrderIssue } from '../../schemas/orderErrors'
import { PackageLineAssignment } from './PackageLineAssignment'

interface PackageCardProps {
  pkg: PackageDraft
  depth: number
  position: number
  lines: LineDraft[]
  allocations: Map<string, LineAllocation>
  issues: OrderIssue[]
  onChange: (values: Partial<PackageDraft>) => void
  onRemove: () => void
  onAddChild: () => void
  onAssign: (lineKey: string, quantity: string) => void
  onDetach: (lineKey: string) => void
}

/**
 * Un colis du brouillon, avec sa hiérarchie et son contenu.
 *
 * L'imbrication est rendue par un décalage : un colis peut en contenir
 * d'autres — palette contenant des cartons — et cette relation est envoyée au
 * serveur par la clé du parent.
 */
export function PackageCard({
  pkg,
  depth,
  position,
  lines,
  allocations,
  issues,
  onChange,
  onRemove,
  onAddChild,
  onAssign,
  onDetach,
}: PackageCardProps) {
  const { t } = useTranslation()
  const types = useTypeItemOptions('package')
  const groupings = useTypeItemOptions('grouping')

  return (
    <li style={{ marginLeft: `${depth * 1.5}rem` }} className="rounded-lg border p-4">
      <div className="mb-4 flex items-start justify-between gap-2">
        <span className="flex items-center gap-2 text-sm font-medium">
          {depth > 0 ? <CornerDownRight className="size-4 text-muted-foreground" aria-hidden /> : null}
          {t('orders.packages.position', { position })}
        </span>

        <div className="flex gap-2">
          <Button type="button" variant="outline" size="sm" onClick={onAddChild}>
            <Plus className="size-4" aria-hidden />
            {t('orders.packages.addChild')}
          </Button>

          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onRemove}
            aria-label={t('orders.packages.remove')}
          >
            <Trash2 className="size-4" aria-hidden />
          </Button>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <AsyncSelect
          label={t('orders.packages.packageType')}
          value={pkg.packageTypeId ?? ''}
          onChange={(packageTypeId) => onChange({ packageTypeId: packageTypeId || null })}
          options={types.options}
          isLoading={types.isLoading}
          error={fieldError(issues, 'packageTypeId')}
        />

        <AsyncSelect
          label={t('orders.packages.groupingType')}
          value={pkg.groupingTypeId ?? ''}
          onChange={(groupingTypeId) => onChange({ groupingTypeId: groupingTypeId || null })}
          options={groupings.options}
          isLoading={groupings.isLoading}
          error={fieldError(issues, 'groupingTypeId')}
        />

        <ControlledField
          label={t('orders.fields.reference')}
          value={pkg.reference}
          onChange={(reference) => onChange({ reference })}
          error={fieldError(issues, 'reference')}
        />

        <ControlledField
          label={t('orders.fields.barcode')}
          value={pkg.barcode}
          onChange={(barcode) => onChange({ barcode })}
          error={fieldError(issues, 'barcode')}
        />

        <ControlledField
          label={t('orders.fields.quantity')}
          type="number"
          min="0"
          step="0.001"
          value={pkg.quantity}
          onChange={(quantity) => onChange({ quantity })}
          error={fieldError(issues, 'quantity')}
        />

        <ControlledField
          label={t('orders.fields.weight')}
          type="number"
          min="0"
          step="0.001"
          value={pkg.weight}
          onChange={(weight) => onChange({ weight })}
          error={fieldError(issues, 'weight')}
        />

        <ControlledField
          label={t('orders.fields.volume')}
          type="number"
          min="0"
          step="0.001"
          value={pkg.volume}
          onChange={(volume) => onChange({ volume })}
          error={fieldError(issues, 'volume')}
        />

        <ControlledField
          label={t('orders.fields.description')}
          value={pkg.description}
          onChange={(description) => onChange({ description })}
          error={fieldError(issues, 'description')}
        />
      </div>

      <p className="mt-2 text-xs text-muted-foreground">
        {t('orders.packages.dimensionsCreateHint')}
      </p>

      <div className="mt-4 border-t pt-4">
        <PackageLineAssignment
          pkg={pkg}
          lines={lines}
          allocations={allocations}
          onAssign={onAssign}
          onDetach={onDetach}
        />
      </div>
    </li>
  )
}
