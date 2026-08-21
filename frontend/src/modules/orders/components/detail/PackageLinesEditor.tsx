import { Check, ListChecks, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ApiError } from '@/shared/api/errors'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { cn } from '@/shared/utils/cn'

import {
  useAssignPackageLine,
  useDetachPackageLine,
  useUpdatePackageLine,
} from '../../hooks/useOrderContent'
import { formatAmount, type LineUsage } from '../../schemas/orderAllocations'
import type { OrderLine, OrderPackage } from '../../types/orderDetail'

interface PackageLinesEditorProps {
  orderId: string
  pkg: OrderPackage
  lines: OrderLine[]
  usage: Map<string, LineUsage>
  editable: boolean
}

/**
 * Contenu d'un colis : quelles lignes il transporte, et en quelle quantité.
 *
 * C'est la relation `PackageOrderLine` du diagramme — celle qui manquait à la
 * fiche. L'assistant de création la proposait, mais une fois la commande
 * enregistrée, plus rien ne permettait de la corriger : lignes et colis
 * vivaient côte à côte sans qu'on puisse les relier.
 *
 * Les trois nombres — commandé, affecté, reste — sont ceux que
 * `PackageLineAllocator` fait respecter côté serveur, sous verrou. L'écran les
 * montre pendant la saisie ; c'est le serveur qui tranche.
 *
 * **Pourquoi rien ne se lie tout seul.** `PackageOrderLine` porte une
 * *quantité* : une ligne de dix chaises peut se répartir sur trois palettes, et
 * `packages[].lines` est facultatif à la création — un colis peut légitimement
 * ne rien contenir. Lier d'office écrirait une répartition que personne n'a
 * décidée, et qu'il faudrait défaire.
 *
 * Le cas courant — tout le reste dans ce colis — a donc un bouton dédié,
 * « Tout affecter », le même que dans l'assistant. Préremplir le champ aurait
 * paru plus direct et tendait un piège : sur un champ affichant « 6 », cliquer
 * puis taper « 3 » donne « 63 », et `select()` n'est pas fiable sur un
 * `input type="number"`.
 */
export function PackageLinesEditor({
  orderId,
  pkg,
  lines,
  usage,
  editable,
}: PackageLinesEditorProps) {
  const { t } = useTranslation()
  const [drafts, setDrafts] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const assign = useAssignPackageLine(orderId)
  const update = useUpdatePackageLine(orderId)
  const detach = useDetachPackageLine(orderId)

  const attached = new Map((pkg.lines ?? []).map((link) => [link.orderLineId, link]))

  const onError = (cause: unknown) =>
    setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))

  const save = (orderLineId: string) => {
    const raw = (drafts[orderLineId] ?? '').trim()
    const quantity = Number(raw)

    if (raw === '' || !Number.isFinite(quantity) || quantity <= 0) return

    setError(null)
    const done = () => setDrafts((previous) => ({ ...previous, [orderLineId]: '' }))
    const payload = { packageId: pkg.id, orderLineId, quantity }

    if (attached.has(orderLineId)) update.mutate(payload, { onSuccess: done, onError })
    else assign.mutate(payload, { onSuccess: done, onError })
  }

  const pending = assign.isPending || update.isPending || detach.isPending

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm font-medium">{t('orders.packages.contents')}</p>

      {error !== null ? <p className="text-sm text-destructive">{error}</p> : null}

      {lines.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('orders.packages.noLines')}</p>
      ) : null}

      <ul className="flex flex-col gap-2">
        {lines.map((line) => {
          const link = attached.get(line.id)
          const stats = usage.get(line.id)
          const remaining = stats?.remaining ?? 0
          const draft = drafts[line.id] ?? (link ? String(link.quantity) : '')

          return (
            <li key={line.id} className="flex flex-wrap items-end gap-3 rounded-md border px-3 py-2">
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm">{line.name}</p>
                {stats ? (
                  <p
                    className={cn('text-xs text-muted-foreground', stats.over && 'text-destructive')}
                  >
                    {t('orders.packages.ordered')} {formatAmount(stats.ordered)} ·{' '}
                    {t('orders.packages.assigned')} {formatAmount(stats.assigned)} ·{' '}
                    {t('orders.packages.remaining')} {formatAmount(stats.remaining)}
                  </p>
                ) : null}
              </div>

              {editable ? (
                <PermissionGuard permission="packages.update">
                  <div className="flex items-end gap-2">
                    <div className="flex flex-col gap-1">
                      <Label htmlFor={`${pkg.id}-${line.id}`} className="text-xs">
                        {t('orders.packages.assignedQuantity')}
                      </Label>
                      <Input
                        id={`${pkg.id}-${line.id}`}
                        type="number"
                        min="0"
                        step="0.001"
                        className="w-28"
                        value={draft}
                        aria-invalid={stats?.over === true}
                        placeholder={t('orders.packages.assign')}
                        onChange={(event) =>
                          setDrafts((previous) => ({ ...previous, [line.id]: event.target.value }))
                        }
                      />
                    </div>

                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={pending || draft.trim() === ''}
                      onClick={() => save(line.id)}
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
                        onClick={() => {
                          setError(null)
                          assign.mutate(
                            { packageId: pkg.id, orderLineId: line.id, quantity: remaining },
                            { onError },
                          )
                        }}
                        title={t('orders.packages.assignAll')}
                        aria-label={t('orders.packages.assignAll')}
                      >
                        <ListChecks className="size-4" aria-hidden />
                      </Button>
                    ) : null}

                    {link ? (
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={pending}
                        onClick={() => {
                          setError(null)
                          detach.mutate({ packageId: pkg.id, lineId: line.id }, { onError })
                        }}
                        aria-label={t('orders.packages.detach')}
                      >
                        <X className="size-4" aria-hidden />
                      </Button>
                    ) : null}
                  </div>
                </PermissionGuard>
              ) : (
                <span className="text-sm text-muted-foreground">
                  {link ? String(link.quantity) : '—'}
                </span>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}
