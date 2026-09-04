import { Plus } from 'lucide-react'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import type { OrderDraftController } from '../../hooks/useOrderDraft'
import { lineAllocations } from '../../schemas/allocations'
import { issuesOf, type OrderErrorReport } from '../../schemas/orderErrors'
import { packageTree } from '../../schemas/packageOrder'
import { PackageCard } from './PackageCard'

interface OrderPackagesStepProps {
  controller: OrderDraftController
  report: OrderErrorReport
}

/**
 * Conditionnement de la commande.
 *
 * L'étape est facultative : `StoreOrderRequest` déclare `packages` en
 * `sometimes`, une commande sans colis est valide. L'écran le dit plutôt que de
 * laisser croire à un oubli.
 */
export function OrderPackagesStep({ controller, report }: OrderPackagesStepProps) {
  const { t } = useTranslation()
  const { draft, addPackage, patchPackage, removePackage, assignLine, detachLine } = controller

  const nodes = useMemo(() => packageTree(draft.packages), [draft.packages])
  const allocations = useMemo(() => lineAllocations(draft), [draft])

  return (
    <SectionCard
      title={t('orders.packages.title')}
      description={t('orders.packages.description')}
      actions={
        <Button type="button" variant="outline" size="sm" onClick={() => addPackage(null)}>
          <Plus className="size-4" aria-hidden />
          {t('orders.packages.add')}
        </Button>
      }
    >
      <div className="flex flex-col gap-4">
        {nodes.length === 0 ? (
          <EmptyState
            title={t('orders.packages.empty')}
            description={t('orders.wizard.packagesOptional')}
            action={
              <Button type="button" variant="outline" size="sm" onClick={() => addPackage(null)}>
                <Plus className="size-4" aria-hidden />
                {t('orders.packages.add')}
              </Button>
            }
          />
        ) : (
          <ul className="flex flex-col gap-4">
            {nodes.map((node, index) => (
              <PackageCard
                key={node.draft.key}
                pkg={node.draft}
                depth={node.depth}
                position={index + 1}
                lines={draft.lines}
                allocations={allocations}
                issues={issuesOf(report, node.draft.key)}
                onChange={(values) => patchPackage(node.draft.key, values)}
                onRemove={() => removePackage(node.draft.key)}
                onAddChild={() => addPackage(node.draft.key)}
                onAssign={(lineKey, quantity) => assignLine(node.draft.key, lineKey, quantity)}
                onDetach={(lineKey) => detachLine(node.draft.key, lineKey)}
              />
            ))}
          </ul>
        )}
      </div>
    </SectionCard>
  )
}
