import { ShieldCheck } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { DocumentGallery } from '@/modules/documents/components/DocumentGallery'
import type { Document } from '@/modules/documents/types/document'
import { formatDate } from '@/shared/utils/format'

/** Type de document déposé par le chauffeur comme preuve de livraison. */
export const POD_DOCUMENT_TYPE = 'pod'

interface OrderPodSectionProps {
  documents: Document[]
  isLoading: boolean
}

/**
 * Preuves de livraison, dans l'onglet Documents.
 *
 * Ce ne sont **pas** des entités à part : ce sont les fichiers que le chauffeur
 * dépose dans le dossier de la commande, reconnus à leur `documentType`. Un
 * écran séparé laissait croire à une saisie de bureau, alors que la preuve
 * vient du terrain.
 *
 * Elles ne se suppriment pas — et ce n'est pas l'écran qui le décide :
 * `DocumentPolicy::delete()` refuse tout document de type `pod`, quelle que
 * soit la permission. Une preuve détruite laisserait une commande livrée sans
 * trace de ce qui a été remis ; une preuve erronée se conteste par une
 * réclamation.
 *
 * Aucun bouton de suppression n'est donc affiché : proposer une action que le
 * serveur refuse serait une promesse en l'air. Le téléchargement et l'aperçu,
 * eux, sont là : une preuve qu'on ne peut pas regarder ne prouve rien.
 */
export function OrderPodSection({ documents, isLoading }: OrderPodSectionProps) {
  const { t } = useTranslation()

  const proofs = documents.filter(
    (item) => item.documentType.toLowerCase() === POD_DOCUMENT_TYPE,
  )

  return (
    <section className="flex flex-col gap-3">
      {/* Le titre est porte par la SectionCard : seule l'explication reste. */}
      <p className="flex items-start gap-2 text-sm text-muted-foreground">
        <ShieldCheck className="mt-0.5 size-4 shrink-0" aria-hidden />
        {t('pod.sectionHint')}
      </p>

      {/* Une preuve se regarde : c'est une photo du colis remis, ou un PDF
          signe. La date de remise reste sous chaque piece. */}
      <DocumentGallery
        documents={proofs}
        defaultView="cards"
        isLoading={isLoading}
        emptyMessage={t('pod.empty')}
        details={(item) =>
          t('pod.deliveredOn', { date: formatDate(item.receivedAt ?? item.createdAt) })
        }
      />
    </section>
  )
}
