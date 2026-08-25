import { useTranslation } from 'react-i18next'

import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/shared/components/ui/sheet'
import { formatDateTime } from '@/shared/utils/format'

import { PodDocumentField } from './PodDocumentField'
import { usePod } from '../hooks/usePod'
import type { ProofOfDelivery } from '../types/proofOfDelivery'

interface PodDetailDialogProps {
  /** Preuve de la liste ; le détail est rechargé pour ses documents. */
  pod: ProofOfDelivery | null
  onClose: () => void
}

/**
 * Détail d'une preuve de livraison.
 *
 * La liste ne renvoie que `signatureDocumentId` et `photoDocumentId` ; seul le
 * détail charge les `Document` correspondants. Le tiroir refait donc un appel,
 * plutôt que de faire charger deux documents par ligne d'une liste dont on
 * n'ouvre qu'une entrée.
 *
 * En attendant la réponse, les valeurs de la liste sont affichées : un tiroir
 * vide pendant une seconde ferait douter du clic.
 */
export function PodDetailDialog({ pod, onClose }: PodDetailDialogProps) {
  const { t } = useTranslation()
  const detail = usePod(pod?.id ?? null)
  const shown = detail.data ?? pod

  return (
    <Sheet open={pod !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>{shown?.recipientName ?? ''}</SheetTitle>
          <SheetDescription>
            {shown ? formatDateTime(shown.deliveredAt) : ''}
          </SheetDescription>
        </SheetHeader>

        {shown ? (
          <div className="flex flex-col gap-4 px-4 pb-6">
            <PodDocumentField
              kind="signature"
              documentId={shown.signatureDocumentId}
              document={shown.signatureDocument}
            />

            <PodDocumentField
              kind="photo"
              documentId={shown.photoDocumentId}
              document={shown.photoDocument}
            />

            {shown.remark ? (
              <div className="border-t pt-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('pod.fields.remark')}
                </p>
                <p className="mt-1 whitespace-pre-wrap text-sm">{shown.remark}</p>
              </div>
            ) : null}

            {shown.creator ? (
              <div className="border-t pt-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {t('pod.fields.createdBy')}
                </p>
                <p className="mt-1 text-sm">
                  {`${shown.creator.firstName} ${shown.creator.lastName}`.trim()}
                </p>
              </div>
            ) : null}
          </div>
        ) : null}
      </SheetContent>
    </Sheet>
  )
}
