import { useTranslation } from 'react-i18next'

import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'

import type { LineUsage } from '../../schemas/orderAllocations'
import type { OrderLine, OrderPackage } from '../../types/orderDetail'
import { OrderPackageFields } from './OrderPackageFields'
import { PackageLinesEditor } from './PackageLinesEditor'
import { packageDisplayName } from './packageParents'

interface PackageContentSheetProps {
  orderId: string
  pkg: OrderPackage | null
  parentLabel?: string
  lines: OrderLine[]
  usage: Map<string, LineUsage>
  editable: boolean
  onClose: () => void
}

/**
 * Fiche complète d'un colis, en tiroir.
 *
 * Le tableau porte les six valeurs qu'on lit d'un coup d'œil ; le reste vit
 * ici — les champs du diagramme, et surtout le **contenu** du colis, cette
 * relation `PackageOrderLine` qui demande trois nombres par ligne et qu'aucune
 * colonne ne pourrait porter.
 */
export function PackageContentSheet({
  orderId,
  pkg,
  parentLabel,
  lines,
  usage,
  editable,
  onClose,
}: PackageContentSheetProps) {
  const { t } = useTranslation()

  return (
    <Sheet open={pkg !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>{pkg ? packageDisplayName(pkg) : ''}</SheetTitle>
          <SheetDescription>{t('orders.packages.title')}</SheetDescription>
        </SheetHeader>

        {pkg ? (
          <div className="flex flex-col gap-4 px-4 pb-6">
            <OrderPackageFields pkg={pkg} parentLabel={parentLabel} />

            <div className="border-t pt-4">
              <PackageLinesEditor
                orderId={orderId}
                pkg={pkg}
                lines={lines}
                usage={usage}
                editable={editable}
              />
            </div>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
