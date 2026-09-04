import { useTranslation } from 'react-i18next'

import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'

import type { LineUsage } from '../../schemas/orderAllocations'
import type { OrderLine, OrderPackage, OrderService } from '../../types/orderDetail'
import { OrderPackageFields } from './OrderPackageFields'
import { PackageLinesEditor } from './PackageLinesEditor'
import { PackageServicesEditor } from './PackageServicesEditor'
import { packageDisplayName } from './packageParents'

interface PackageContentSheetProps {
  orderId: string
  pkg: OrderPackage | null
  parentLabel?: string
  lines: OrderLine[]
  usage: Map<string, LineUsage>
  editable: boolean
  /** Nombre de colis de la commande : un seul rend l'affectation sans ambiguïté. */
  packageCount: number
  /** Services de la commande, pour dire lesquels prennent ce colis en charge. */
  services: OrderService[]
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
  packageCount,
  services,
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

            {/* Par quels services il passe, avant ce qu'il contient : c'est
                l'acheminement qu'on vient verifier en premier. */}
            <div className="border-t pt-4">
              <PackageServicesEditor
                orderId={orderId}
                packageId={pkg.id}
                services={services}
                editable={editable}
              />
            </div>

            <div className="border-t pt-4">
              <PackageLinesEditor
                orderId={orderId}
                pkg={pkg}
                lines={lines}
                usage={usage}
                editable={editable}
                isSolePackage={packageCount === 1}
              />
            </div>
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
