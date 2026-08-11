import { Users } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { SectionCard } from '@/shared/components/layout/SectionCard'

/**
 * Contacts rattaches a une entite.
 *
 * **L'API ne permet pas cette lecture.** `GET /contacts` ne filtre que sur
 * `search` et `isActive` ; les routes de liaison partent du contact
 * (`GET /contacts/{contact}/links`), pas de l'entite. Lister ici tous les
 * contacts de l'organisation afficherait ceux des autres clients — exactement
 * ce que le §1 interdit de contourner.
 *
 * Le manque est consigne au rapport de phase. Il se comble par un filtre
 * `entityType` / `entityId` sur `GET /contacts`, sans nouvelle table.
 */
export function CustomerContactsTab({ entityId }: { entityId: string }) {
  const { t } = useTranslation()

  return (
    <SectionCard title={t('contacts.title')} description={t('contacts.notAUser')}>
      <EmptyState
        icon={Users}
        title={t('contacts.apiMissingTitle')}
        description={t('contacts.apiMissingHint', { id: entityId })}
      />
    </SectionCard>
  )
}
