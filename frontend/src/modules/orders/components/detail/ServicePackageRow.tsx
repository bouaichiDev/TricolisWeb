import { Check, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import type { ServicePackageLink } from '../../api/servicePackages.api'
import type { OrderPackage } from '../../types/orderDetail'
import { packageDisplayName } from './packageParents'

export interface LinkDraft {
  quantity: string
  instructions: string
}

interface ServicePackageRowProps {
  pkg: OrderPackage
  /** Liaison existante, ou `undefined` si le colis n'est pas pris en charge. */
  link?: ServicePackageLink
  draft: LinkDraft
  canEdit: boolean
  pending: boolean
  onToggle: (checked: boolean) => void
  onDraftChange: (values: Partial<LinkDraft>) => void
  onSave: () => void
}

/**
 * Un colis de la commande, pris en charge ou non par le service.
 *
 * Cocher crée la liaison, décocher la retire. Une fois liée, elle porte sa
 * quantité et ses consignes : un service peut ne charger qu'une partie d'un
 * colis, et le suivant s'occuper du reste.
 */
export function ServicePackageRow({
  pkg,
  link,
  draft,
  canEdit,
  pending,
  onToggle,
  onDraftChange,
  onSave,
}: ServicePackageRowProps) {
  const { t } = useTranslation()

  return (
    <li className="rounded-md border px-3 py-2">
      {canEdit ? (
        <ControlledCheckbox
          label={packageDisplayName(pkg)}
          checked={link !== undefined}
          disabled={pending}
          onChange={onToggle}
        />
      ) : (
        <div className="flex items-baseline justify-between gap-2 text-sm">
          <span>{packageDisplayName(pkg)}</span>
          {link === undefined ? <span className="text-muted-foreground">—</span> : null}
        </div>
      )}

      {link !== undefined && canEdit ? (
        <div className="mt-2 grid gap-3 pl-7 sm:grid-cols-[8rem_1fr_auto]">
          <div className="flex flex-col gap-1">
            <Label htmlFor={`sp-qty-${pkg.id}`} className="text-xs">
              {t('orders.fields.quantity')}
            </Label>
            <Input
              id={`sp-qty-${pkg.id}`}
              type="number"
              min="0"
              step="0.001"
              value={draft.quantity}
              onChange={(event) => onDraftChange({ quantity: event.target.value })}
            />
          </div>

          <div className="flex flex-col gap-1">
            <Label htmlFor={`sp-hi-${pkg.id}`} className="text-xs">
              {t('orders.services.handlingInstructions')}
            </Label>
            <Input
              id={`sp-hi-${pkg.id}`}
              value={draft.instructions}
              onChange={(event) => onDraftChange({ instructions: event.target.value })}
            />
          </div>

          <div className="flex items-end gap-1">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={pending}
              onClick={onSave}
              aria-label={t('common.save')}
            >
              <Check className="size-4" aria-hidden />
            </Button>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              disabled={pending}
              onClick={() => onToggle(false)}
              aria-label={t('orders.services.detachPackage')}
            >
              <X className="size-4" aria-hidden />
            </Button>
          </div>
        </div>
      ) : null}

      {link !== undefined && !canEdit ? (
        <p className="mt-1 text-sm text-muted-foreground">
          {t('orders.fields.quantity')} {link.quantity ?? '—'}
          {link.handlingInstructions ? ` · ${link.handlingInstructions}` : ''}
        </p>
      ) : null}
    </li>
  )
}
