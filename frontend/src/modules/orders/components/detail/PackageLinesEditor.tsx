import { useEffect, useRef, useState } from 'react'
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
 * **Le cas sans ambiguïté se lie tout seul.** `PackageOrderLine` porte une
 * *quantité* : une ligne de dix chaises peut se répartir sur trois palettes, et
 * une répartition ne s'invente pas. Mais quand la commande n'a **qu'une** ligne,
 * **qu'un** colis et rien d'affecté, il n'existe qu'une réponse possible —
 * la demander ne fait que faire recopier un nombre déjà affiché. Le lien est
 * alors écrit à l'ouverture du contenu.
 *
 * Dès qu'il y a plusieurs colis ou plusieurs lignes, l'écran redemande : c'est
 * exactement là que « tout mettre dans le premier » serait faux.
 *
 * Au-delà, le bouton « Tout affecter » couvre le cas courant. Préremplir le
 * champ aurait paru plus direct et tendait un piège : sur un champ affichant
 * « 6 », cliquer puis taper « 3 » donne « 63 », et `select()` n'est pas fiable
 * sur un `input type="number"`.
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

  // Une seule reponse possible : une ligne, un colis, rien d'affecte. La ref
  // garde l'ecriture unique -- l'invalidation qui suit relance ce rendu, et
  // sans elle la mutation repartirait en boucle.
  const only = lines.length === 1 ? lines[0] : undefined
  const onlyUsage = only ? usage.get(only.id) : undefined
  const autoLinkable =
    editable &&
    isSolePackage &&
    only !== undefined &&
    onlyUsage !== undefined &&
    onlyUsage.assigned === 0 &&
    onlyUsage.remaining > 0 &&
    !attached.has(only.id)

  const autoLinked = useRef(false)

  useEffect(() => {
    if (!autoLinkable || autoLinked.current || !canUpdate) return

    autoLinked.current = true
    assign.mutate({ packageId: pkg.id, orderLineId: only.id, quantity: onlyUsage.remaining })
  }, [autoLinkable, assign, canUpdate, pkg.id, only, onlyUsage])

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
