import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import { DocumentGallery } from './DocumentGallery'
import { DocumentUploadForm } from './DocumentUploadForm'
import { useEntityDocuments } from '../hooks/useEntityDocuments'

interface EntityDocumentsTabProps {
  /** Alias de la morph map : `customer`, `customer_site`, `claim`… */
  entityType: string
  entityId: string
}

/**
 * Documents rattachés à une entité.
 *
 * `GET /documents` accepte désormais `entityType` et `entityId` : les pièces
 * d'un client sont listables comme celles d'une commande. Cet onglet annonçait
 * jusqu'ici un manque de l'API — le lien polymorphe existait, il n'était
 * simplement pas interrogeable.
 */
export function EntityDocumentsTab({ entityType, entityId }: EntityDocumentsTabProps) {
  const { t } = useTranslation()

  const documents = useEntityDocuments(entityType, entityId)

  return (
    <SectionCard title={t('documents.title')} description={t('documents.entityHint')}>
      <div className="flex flex-col gap-4">
        <PermissionGuard permission="documents.upload">
          <DocumentUploadForm entityType={entityType} entityId={entityId} />
        </PermissionGuard>

        {documents.error ? (
          <ErrorState error={documents.error} onRetry={() => void documents.refetch()} />
        ) : (
          <DocumentGallery
            documents={documents.data?.data ?? []}
            isLoading={documents.isPending}
          />
        )}
      </div>
    </SectionCard>
  )
}
