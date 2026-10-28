import { useQuery } from '@tanstack/react-query'
import { UserCheck } from 'lucide-react'
import { useState } from 'react'
import { useParams } from 'react-router-dom'

import { usePermissions } from '@/app/auth/useAuth'
import { errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'
import { formatDate, formatDateTime } from '@/shared/utils/format'

import { toursApi } from '../api/toursApi'
import { AssignResourcesDialog } from '../components/AssignResourcesDialog'
import { TourDocuments } from '../components/TourDocuments'
import { TourExecutionBar } from '../components/TourExecutionBar'
import { TourStops } from '../components/TourStops'
import { TourTimeline } from '../components/TourTimeline'

/**
 * Le détail d'une tournée attribuée : ce qu'il faut pour l'organiser, puis
 * ses arrêts, sa chronologie et ses documents — chaque onglet seulement si la
 * permission le permet. L'affectation n'est offerte que si le serveur l'ouvre.
 */
export function TourDetailPage() {
  const { id = '' } = useParams()
  const tour = useQuery({ queryKey: ['tour', id], queryFn: () => toursApi.get(id) })
  const [assigning, setAssigning] = useState(false)
  const { can } = usePermissions()

  if (tour.isPending) return <InlineLoader />
  if (tour.isError) return <ErrorState message={errorMessage(tour.error)} onRetry={() => void tour.refetch()} />

  const data = tour.data
  const { totals } = data

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={`Tournée ${data.tourNumber}`}
        description={formatDate(data.tourDate)}
        actions={
          data.actions.canAssign && (
            <Button onClick={() => setAssigning(true)}>
              <UserCheck className="size-4" aria-hidden /> Affecter
            </Button>
          )
        }
      />
      <TourExecutionBar tour={data} />
      <SectionCard title="Organisation">
        <dl className="grid gap-x-6 sm:grid-cols-3">
          <DetailField label="Statut"><StatusBadge code={data.status} label={data.statusLabel} /></DetailField>
          <DetailField label="Agence">{data.agency}</DetailField>
          <DetailField label="Dépôt">{data.depot}</DetailField>
          <DetailField label="Chauffeur">{data.driver?.name}</DetailField>
          <DetailField label="Véhicule">{data.vehicle ? `${data.vehicle.code} · ${data.vehicle.registrationNumber}` : null}</DetailField>
          <DetailField label="Horaires prévus">{`${formatDateTime(data.plannedStartAt)} → ${formatDateTime(data.plannedEndAt)}`}</DetailField>
          <DetailField label="Charge">{`${Number(totals.weight).toLocaleString('fr-CH')} kg · ${Number(totals.volume).toLocaleString('fr-CH')} m³ · ${totals.packages} colis`}</DetailField>
          <DetailField label="Distance">{`${(totals.distanceMeters / 1000).toLocaleString('fr-CH')} km`}</DetailField>
          <DetailField label="Consignes">{data.instructions}</DetailField>
        </dl>
      </SectionCard>
      <Tabs defaultValue="stops">
        <TabsList>
          <TabsTrigger value="stops">Arrêts</TabsTrigger>
          {can('provider.tracking.view') && <TabsTrigger value="tracking">Chronologie</TabsTrigger>}
          {can('provider.documents.view') && <TabsTrigger value="documents">Documents</TabsTrigger>}
        </TabsList>
        <TabsContent value="stops">
          <TourStops tourId={data.id} stops={data.stops ?? []} />
        </TabsContent>
        {can('provider.tracking.view') && (
          <TabsContent value="tracking">
            <TourTimeline tourId={data.id} stops={data.stops ?? []} />
          </TabsContent>
        )}
        {can('provider.documents.view') && (
          <TabsContent value="documents">
            <TourDocuments tourId={data.id} />
          </TabsContent>
        )}
      </Tabs>
      {data.actions.canAssign && <AssignResourcesDialog tour={data} open={assigning} onOpenChange={setAssigning} />}
    </div>
  )
}
