import { Check, X } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AccessRequestDecisionDialog } from '../components/AccessRequestDecisionDialog'
import {
  useAccessRequestList,
  useApproveAccessRequest,
  useRejectAccessRequest,
} from '../hooks/useAccessRequests'
import {
  ACCESS_REQUEST_STATUSES,
  type AccessRequest,
  type AccessRequestStatus,
} from '../types/accessRequest'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

/**
 * Les demandes d'accès, côté plateforme.
 *
 * **L'écran s'ouvre sur les demandes en attente** : ce sont les seules qui
 * appellent un geste le jour même. Les tranchées restent atteignables — c'est
 * là qu'on relit un motif de refus, ou qu'on retrouve à quelle organisation une
 * acceptation a donné naissance.
 *
 * Accepter n'est pas un geste anodin : cela crée une organisation, un compte
 * administrateur, et envoie un courriel. D'où la confirmation, et le motif
 * qu'elle propose de laisser.
 */
export function AccessRequestListPage() {
  const { t } = useTranslation()

  const [status, setStatus] = useState<AccessRequestStatus>('pending')
  const [page, setPage] = useState(1)
  const [decision, setDecision] = useState<{
    request: AccessRequest
    kind: 'approve' | 'reject'
  } | null>(null)

  const { data, isPending, error, refetch } = useAccessRequestList({ page, perPage: 25, status })
  const approve = useApproveAccessRequest()
  const reject = useRejectAccessRequest()

  const isDeciding = approve.isPending || reject.isPending

  const confirm = (note: string | undefined) => {
    if (decision === null) return

    const mutation = decision.kind === 'approve' ? approve : reject

    mutation.mutate({ id: decision.request.id, note }, { onSuccess: () => setDecision(null) })
  }

  const columns: Column<AccessRequest>[] = [
    { key: 'company', header: t('accessRequests.fields.companyName'), cell: (row) => row.companyName },
    { key: 'contact', header: t('accessRequests.fields.contactName'), cell: (row) => row.contactName },
    {
      key: 'email',
      header: t('accessRequests.fields.email'),
      hideOnMobile: true,
      cell: (row) => row.email,
    },
    {
      key: 'phone',
      header: t('accessRequests.fields.phone'),
      hideOnMobile: true,
      cell: (row) => row.phone,
    },
    { key: 'status', header: t('accessRequests.fields.status'), cell: (row) => <StatusBadge status={row.status} /> },
    {
      key: 'actions',
      header: '',
      cell: (row) =>
        row.status === 'pending' ? (
          <div className="flex justify-end gap-2">
            <Button size="sm" onClick={() => setDecision({ request: row, kind: 'approve' })}>
              <Check className="size-4" aria-hidden />
              {t('accessRequests.approve.action')}
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() => setDecision({ request: row, kind: 'reject' })}
            >
              <X className="size-4" aria-hidden />
              {t('accessRequests.reject.action')}
            </Button>
          </div>
        ) : (
          <span className="text-muted-foreground">{row.decisionNote ?? '—'}</span>
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('accessRequests.title')} description={t('accessRequests.subtitle')} />

      <div className="flex flex-wrap gap-2">
        {ACCESS_REQUEST_STATUSES.map((value) => (
          <Button
            key={value}
            size="sm"
            variant={value === status ? 'default' : 'outline'}
            onClick={() => {
              setStatus(value)
              setPage(1)
            }}
          >
            {t(`status.${value}`)}
          </Button>
        ))}
      </div>

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        emptyMessage={t('accessRequests.empty')}
      />

      <AccessRequestDecisionDialog
        request={decision?.request ?? null}
        decision={decision?.kind ?? 'approve'}
        onClose={() => setDecision(null)}
        onConfirm={confirm}
        isPending={isDeciding}
      />
    </div>
  )
}
