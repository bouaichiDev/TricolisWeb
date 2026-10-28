import { useQuery } from '@tanstack/react-query'
import { Paperclip } from 'lucide-react'
import { useParams } from 'react-router-dom'

import { errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDateTime } from '@/shared/utils/format'

import { communicationsApi } from '../api/communicationsApi'

/** Une communication reçue, affichée en texte : aucun HTML n'est interprété. */
export function CommunicationDetailPage() {
  const { id = '' } = useParams()
  const communication = useQuery({ queryKey: ['communication', id], queryFn: () => communicationsApi.get(id) })

  if (communication.isPending) return <InlineLoader />
  if (communication.isError) return <ErrorState message={errorMessage(communication.error)} onRetry={() => void communication.refetch()} />

  const data = communication.data

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={data.subject ?? '(sans objet)'} description={formatDateTime(data.sentAt)} />
      <SectionCard title="Message">
        <dl className="grid gap-x-6 sm:grid-cols-3">
          <DetailField label="Canal">{data.channelLabel}</DetailField>
          <DetailField label="Statut"><StatusBadge code={data.status} label={data.statusLabel} /></DetailField>
          <DetailField label="Remise">{formatDateTime(data.deliveredAt)}</DetailField>
        </dl>
        <p className="mt-4 text-sm whitespace-pre-wrap">{data.body}</p>
        {(data.attachments ?? []).length > 0 && (
          <ul className="mt-4 space-y-1 border-t pt-3 text-sm" aria-label="Pièces jointes">
            {data.attachments?.map((attachment, index) => (
              <li key={`${attachment.fileName}-${index}`} className="text-muted-foreground flex items-center gap-1">
                <Paperclip className="size-3.5" aria-hidden /> {attachment.fileName}
              </li>
            ))}
          </ul>
        )}
      </SectionCard>
    </div>
  )
}
