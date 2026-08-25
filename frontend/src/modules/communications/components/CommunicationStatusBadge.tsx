import { useTranslation } from 'react-i18next'

import { Badge } from '@/shared/components/ui/badge'
import { cn } from '@/shared/utils/cn'

import type { CommunicationStatus } from '../types/communication'

/**
 * Teinte de chaque statut : neutre tant que rien n'est parti, verte une fois
 * arrivé, rouge à l'échec. Les neuf statuts officiels et rien d'autre — ni
 * `RETRYING`, ni `PROCESSING`, ni `ARCHIVED`, que le §34 interdit.
 */
const TONES: Record<CommunicationStatus, string> = {
  draft: 'bg-muted text-muted-foreground',
  scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
  queued: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
  sending: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
  sent: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
  delivered: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
  read: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
  failed: 'bg-destructive/10 text-destructive',
  cancelled: 'bg-muted text-muted-foreground line-through',
}

export function CommunicationStatusBadge({ status }: { status: string }) {
  const { t } = useTranslation()
  const tone = TONES[status as CommunicationStatus]

  return (
    <Badge variant="secondary" className={cn('whitespace-nowrap', tone)}>
      {t(`communicationStatuses.${status}`, { defaultValue: status })}
    </Badge>
  )
}
