import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { useAgency, useDeleteAgency } from '../hooks/useAgencies'
import { AgencyDepotsPanel } from '@/modules/depots/components/AgencyDepotsPanel'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

export function AgencyDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: agency, isPending, error, refetch } = useAgency(id)
  const remove = useDeleteAgency()

  if (isPending) return <DetailSkeleton rows={4} />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!agency) return null

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={agency.name}
        subtitle={agency.code}
        status={agency.status}
        editTo={`/agencies/${agency.id}/edit`}
        editPermission="agencies.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="agencies.delete"
      />

      <Tabs defaultValue="information">
        <TabsList>
          <TabsTrigger value="information">{t('customers.tabs.information')}</TabsTrigger>
          <TabsTrigger value="depots">{t('nav.depots')}</TabsTrigger>
        </TabsList>

        <TabsContent value="information" className="mt-6">
          <SectionCard title={t('agencies.sections.general')}>
            <dl className="grid gap-x-8 sm:grid-cols-2">
              <DetailField label={t('agencies.fields.code')}>{agency.code}</DetailField>
              <DetailField label={t('agencies.fields.name')}>{agency.name}</DetailField>
              <DetailField label={t('agencies.fields.shortName')}>{agency.shortName}</DetailField>
              <DetailField label={t('agencies.fields.email')}>{agency.email}</DetailField>
              <DetailField label={t('agencies.fields.phone')}>{agency.phone}</DetailField>
              <DetailField label={t('agencies.fields.loadingPoint')}>
                {agency.loadingPoint}
              </DetailField>
            </dl>
          </SectionCard>
        </TabsContent>

        <TabsContent value="depots" className="mt-6">
          <AgencyDepotsPanel agencyId={agency.id} />
        </TabsContent>
      </Tabs>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('confirm.deleteEntity', { name: agency.name })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() =>
          remove.mutate(agency.id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/agencies')
            },
          })
        }
      />
    </div>
  )
}
