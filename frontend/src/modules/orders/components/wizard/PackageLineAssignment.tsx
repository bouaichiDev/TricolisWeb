import { X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { cn } from '@/shared/utils/cn'

import { formatQuantity, type LineAllocation } from '../../schemas/allocations'
import type { LineDraft, PackageDraft } from '../../schemas/orderDraft'

interface PackageLineAssignmentProps {
  pkg: PackageDraft
  lines: LineDraft[]
  allocations: Map<string, LineAllocation>
  onAssign: (lineKey: string, quantity: string) => void
  onDetach: (lineKey: string) => void
}

/**
 * Affectation des lignes de commande à un colis.
 *
 * Chaque ligne affiche ce qui est commandé, ce qui est déjà réparti entre tous
 * les colis et ce qu'il reste : sans ces trois nombres, répartir une ligne entre
 * plusieurs colis se fait à l'aveugle et le dépassement n'apparaît qu'au retour
 * du serveur.
 */
export function PackageLineAssignment({
  pkg,
  lines,
  allocations,
  onAssign,
  onDetach,
}: PackageLineAssignmentProps) {
  const { t } = useTranslation()
  const assignedHere = new Map(pkg.lines.map((link) => [link.lineKey, link.quantity]))

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm font-medium">{t('orders.packages.contents')}</p>

      {lines.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('orders.packages.noLines')}</p>
      ) : null}

      <ul className="flex flex-col gap-2">
        {lines.map((line, index) => {
          const allocation = allocations.get(line.key)
          const current = assignedHere.get(line.key)
          const attached = current !== undefined

          return (
            <li
              key={line.key}
              className="flex flex-wrap items-end gap-3 rounded-md border px-3 py-2"
            >
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm">
                  {line.name.trim() === ''
                    ? t('orders.lines.position', { position: index + 1 })
                    : line.name}
                </p>
                {allocation ? (
                  <p
                    className={cn(
                      'text-xs text-muted-foreground',
                      allocation.over && 'text-destructive',
                    )}
                  >
                    {t('orders.packages.ordered')} {formatQuantity(allocation.ordered)} ·{' '}
                    {t('orders.packages.assigned')} {formatQuantity(allocation.assigned)} ·{' '}
                    {t('orders.packages.remaining')} {formatQuantity(allocation.remaining)}
                  </p>
                ) : null}
              </div>

              <div className="flex items-end gap-2">
                <div className="flex flex-col gap-1">
                  <Label htmlFor={`${pkg.key}-${line.key}`} className="text-xs">
                    {t('orders.packages.assignedQuantity')}
                  </Label>
                  <Input
                    id={`${pkg.key}-${line.key}`}
                    type="number"
                    min="0"
                    step="0.001"
                    className="w-28"
                    value={current ?? ''}
                    aria-invalid={allocation?.over === true}
                    placeholder={t('orders.packages.assign')}
                    onChange={(event) => {
                      const value = event.target.value

                      if (value === '') onDetach(line.key)
                      else onAssign(line.key, value)
                    }}
                  />
                </div>

                {attached ? (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => onDetach(line.key)}
                    aria-label={t('orders.packages.detach')}
                  >
                    <X className="size-4" aria-hidden />
                  </Button>
                ) : null}
              </div>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
