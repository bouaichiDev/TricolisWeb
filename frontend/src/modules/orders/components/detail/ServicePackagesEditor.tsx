import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { ApiError } from '@/shared/api/errors'
import { usePermission } from '@/shared/hooks/usePermission'

import {
  useAttachServicePackage,
  useDetachServicePackage,
  useServicePackages,
  useUpdateServicePackage,
} from '../../hooks/useServicePackages'
import type { OrderPackage } from '../../types/orderDetail'
import { ServicePackageRow } from './ServicePackageRow'

interface ServicePackagesEditorProps {
  orderId: string
  serviceId: string
  packages: OrderPackage[]
  editable: boolean
}

/**
 * Colis pris en charge par un service.
 *
 * C'est la relation `OrderServicePackage` du diagramme. Elle n'existait qu'à la
 * création complète d'une commande : ni l'API ni l'écran ne permettaient de la
 * modifier ensuite, alors qu'un colis change de prestation aussi souvent qu'il
 * change de main.
 *
 * Chaque liaison porte sa propre quantité et ses consignes — un service peut
 * n'en charger qu'une partie.
 */
export function ServicePackagesEditor({
  orderId,
  serviceId,
  packages,
  editable,
}: ServicePackagesEditorProps) {
  const { t } = useTranslation()
  // Sans la permission, la liste se lit toujours : la masquer entierement
  // ferait croire qu'aucun colis n'est pris en charge.
  const mayUpdate = usePermission('order_services.update')
  const canEdit = editable && mayUpdate
  const [drafts, setDrafts] = useState<Record<string, { quantity: string; instructions: string }>>({})
  const [error, setError] = useState<string | null>(null)

  const links = useServicePackages(orderId, serviceId)
  const attach = useAttachServicePackage(orderId, serviceId)
  const update = useUpdateServicePackage(orderId, serviceId)
  const detach = useDetachServicePackage(orderId, serviceId)

  const byPackage = new Map((links.data ?? []).map((link) => [link.packageId, link]))
  const pending = attach.isPending || update.isPending || detach.isPending

  const onError = (cause: unknown) =>
    setError(cause instanceof ApiError ? cause.message : t('errors.unexpected'))

  const draftFor = (packageId: string) => {
    const link = byPackage.get(packageId)

    return (
      drafts[packageId] ?? {
        quantity: link?.quantity === null || link === undefined ? '' : String(link.quantity),
        instructions: link?.handlingInstructions ?? '',
      }
    )
  }

  const patchDraft = (packageId: string, values: Partial<{ quantity: string; instructions: string }>) =>
    setDrafts((previous) => ({ ...previous, [packageId]: { ...draftFor(packageId), ...values } }))

  const toggle = (pkg: OrderPackage, checked: boolean) => {
    setError(null)
    const link = byPackage.get(pkg.id)

    if (!checked) {
      if (link) detach.mutate(link.id, { onError })
      return
    }

    attach.mutate({ packageId: pkg.id }, { onError })
  }

  const save = (packageId: string) => {
    const link = byPackage.get(packageId)

    if (link === undefined) return

    const draft = draftFor(packageId)
    const quantity = draft.quantity.trim() === '' ? undefined : Number(draft.quantity)

    setError(null)
    update.mutate(
      {
        id: link.id,
        quantity,
        handlingInstructions: draft.instructions.trim() === '' ? null : draft.instructions.trim(),
      },
      { onError },
    )
  }

  if (packages.length === 0) {
    return (
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium">{t('orders.services.packages')}</p>
        <p className="text-sm text-muted-foreground">{t('orders.services.noPackages')}</p>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm font-medium">{t('orders.services.packages')}</p>

      {error !== null ? <p className="text-sm text-destructive">{error}</p> : null}

      <ul className="flex flex-col gap-2">
        {packages.map((pkg) => (
          <ServicePackageRow
            key={pkg.id}
            pkg={pkg}
            link={byPackage.get(pkg.id)}
            draft={draftFor(pkg.id)}
            canEdit={canEdit}
            pending={pending}
            onToggle={(checked) => toggle(pkg, checked)}
            onDraftChange={(values) => patchDraft(pkg.id, values)}
            onSave={() => save(pkg.id)}
          />
        ))}
      </ul>
    </div>
  )
}
