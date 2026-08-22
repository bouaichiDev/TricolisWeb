import { Check, ListChecks, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { cn } from '@/shared/utils/cn'

import { formatAmount, type LineUsage } from '../../schemas/orderAllocations'
import type { OrderLine, PackageLineLink } from '../../types/orderDetail'

interface PackageLineRowProps {
  packageId: string
  line: OrderLine
  link?: PackageLineLink
  stats?: LineUsage
  /** Faux quand la commande n'a qu'un colis : aucune répartition n'est possible. */
  canSplit: boolean
  editable: boolean
  pending: boolean
  draft: string
  onDraftChange: (value: string) => void
  onSave: () => void
  onAssignAll: (quantity: number) => void
  onDetach: () => void
}

/**
 * Une ligne dans le contenu d'un colis.
 *
 * **La quantité ne se saisit que si elle peut varier.** Quand la commande n'a
 * qu'un seul colis, une ligne y va tout entière ou n'y va pas : demander
 * « combien » n'a pas de réponse autre que « tout », et le champ ne faisait que
 * poser une question dont personne ne comprenait l'objet.
 *
 * Le champ réapparaît dès qu'il y a plusieurs colis — c'est alors une vraie
 * question, puisque dix chaises peuvent se répartir sur trois palettes.
 */
export function PackageLineRow({
  packageId,
  line,
  link,
  stats,
  canSplit,
  editable,
  pending,
  draft,
  onDraftChange,
  onSave,
  onAssignAll,
  onDetach,
}: PackageLineRowProps) {
  const { t } = useTranslation()
  const remaining = stats?.remaining ?? 0

  const detach = (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      disabled={pending}
      onClick={onDetach}
      title={t('orders.packages.detach')}
      aria-label={t('orders.packages.detach')}
    >
      <X className="size-4" aria-hidden />
    </Button>
  )

  return (
    <li className="flex flex-wrap items-end gap-3 rounded-md border px-3 py-2">
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm">{line.name}</p>
        {stats ? (
          <p className={cn('text-xs text-muted-foreground', stats.over && 'text-destructive')}>
            {t('orders.packages.ordered')} {formatAmount(stats.ordered)} ·{' '}
            {t('orders.packages.assigned')} {formatAmount(stats.assigned)} ·{' '}
            {t('orders.packages.remaining')} {formatAmount(stats.remaining)}
          </p>
        ) : null}
      </div>

      {!editable ? (
        <span className="text-sm text-muted-foreground">
          {link ? String(link.quantity) : '—'}
        </span>
      ) : canSplit ? (
        <div className="flex items-end gap-2">
          <div className="flex flex-col gap-1">
            <Label htmlFor={`${packageId}-${line.id}`} className="text-xs">
              {t('orders.packages.assignedQuantity')}
            </Label>
            <Input
              id={`${packageId}-${line.id}`}
              type="number"
              min="0"
              step="0.001"
              className="w-28"
              value={draft}
              aria-invalid={stats?.over === true}
              placeholder={t('orders.packages.assign')}
              onChange={(event) => onDraftChange(event.target.value)}
            />
          </div>

          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={pending || draft.trim() === ''}
            onClick={onSave}
            aria-label={t('common.save')}
          >
            <Check className="size-4" aria-hidden />
          </Button>

          {link === undefined && remaining > 0 ? (
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={pending}
              onClick={() => onAssignAll(remaining)}
              title={t('orders.packages.assignAll')}
              aria-label={t('orders.packages.assignAll')}
            >
              <ListChecks className="size-4" aria-hidden />
            </Button>
          ) : null}

          {link ? detach : null}
        </div>
      ) : link ? (
        // Un seul colis : la quantité est celle de la ligne, rien à saisir.
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">{String(link.quantity)}</span>
          {detach}
        </div>
      ) : remaining > 0 ? (
        <Button type="button" size="sm" disabled={pending} onClick={() => onAssignAll(remaining)}>
          <ListChecks className="size-4" aria-hidden />
          {t('orders.packages.putInPackage')}
        </Button>
      ) : null}
    </li>
  )
}
