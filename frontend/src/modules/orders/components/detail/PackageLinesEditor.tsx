import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { usePermission } from '@/shared/hooks/usePermission'
import { ApiError } from '@/shared/api/errors'

import {
  useAssignPackageLine,
  useDetachPackageLine,
  useUpdatePackageLine,
} from '../../hooks/useOrderContent'
import type { LineUsage } from '../../schemas/orderAllocations'
import { PackageLineRow } from './PackageLineRow'
import type { OrderLine, OrderPackage } from '../../types/orderDetail'

interface PackageLinesEditorProps {
  orderId: string
  pkg: OrderPackage
  lines: OrderLine[]
  usage: Map<string, LineUsage>
  editable: boolean
  /** Vrai quand la commande ne porte que ce colis : un colis ne le sait pas seul. */
  isSolePackage: boolean
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
 * **Cet écran ne crée pas le contenu, il le corrige.** Le lien naît avec la
 * commande — `CreateOrderPackages` lit `packages[].lines[]` — ou, plus tard, du
 * terrain, quand le dépôt constate ce qui est réellement dans le carton. Le web
 * sert à *voir* ce contenu et à l'amender : ajouter un article que le dépôt
 * avait oublié, corriger une quantité, détacher une ligne.
 *
 * Il ne l'invente donc pas. Une version précédente écrivait le lien à
 * l'ouverture du panneau, quand il n'y avait qu'une réponse possible : c'était
 * transformer un geste de lecture en écriture, et affirmer depuis un bureau ce
 * qui se constate dans un entrepôt.
 *
 * L'automatisme est resté, mais à sa place : l'assistant met la ligne dans le
 * colis dès sa création, là où l'utilisateur déclare justement le contenu.
 *
 * Le bouton « Tout affecter » couvre le cas courant. Préremplir le champ aurait
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
  isSolePackage,
}: PackageLinesEditorProps) {
  const { t } = useTranslation()
  const canUpdate = usePermission('packages.update')
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
      <div>
        <p className="text-sm font-medium">{t('orders.packages.contents')}</p>
        <p className="text-xs text-muted-foreground">
          {isSolePackage
            ? t('orders.packages.contentsHint')
            : t('orders.packages.contentsSplitHint')}
        </p>
      </div>

      {error !== null ? <p className="text-sm text-destructive">{error}</p> : null}

      {lines.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('orders.packages.noLines')}</p>
      ) : null}

      <ul className="flex flex-col gap-2">
        {lines.map((line) => (
          <PackageLineRow
            key={line.id}
            packageId={pkg.id}
            line={line}
            link={attached.get(line.id)}
            stats={usage.get(line.id)}
            canSplit={!isSolePackage}
            editable={editable && canUpdate}
            pending={pending}
            draft={drafts[line.id] ?? (attached.get(line.id) ? String(attached.get(line.id)?.quantity) : '')}
            onDraftChange={(value) =>
              setDrafts((previous) => ({ ...previous, [line.id]: value }))
            }
            onSave={() => save(line.id)}
            onAssignAll={(quantity) => {
              setError(null)
              assign.mutate({ packageId: pkg.id, orderLineId: line.id, quantity }, { onError })
            }}
            onDetach={() => {
              setError(null)
              detach.mutate({ packageId: pkg.id, lineId: line.id }, { onError })
            }}
          />
        ))}
      </ul>
    </div>
  )
}
