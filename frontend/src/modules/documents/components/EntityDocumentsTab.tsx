import { FileText } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/**
 * Documents rattaches a une entite.
 *
 * Meme manque que pour les contacts : `GET /documents` ne filtre que sur
 * `search` et `status`. Seules les commandes ont une route imbriquee
 * (`GET /orders/{order}/documents`) ; les clients n'en ont pas.
 */
export function CustomerDocumentsTab({ entityId }: { entityId: string }) {
  const { t } = useTranslation()

  return (
    <SectionCard title={t('documents.title')}>
      <EmptyState
        icon={FileText}
        title={t('documents.apiMissingTitle')}
        description={t('documents.apiMissingHint', { id: entityId })}
      />
    </SectionCard>
  )
}
