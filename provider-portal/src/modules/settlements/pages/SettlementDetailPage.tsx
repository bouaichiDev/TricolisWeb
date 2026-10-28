import { useQuery } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { toast } from 'sonner'
import { useParams } from 'react-router-dom'

import { usePermissions } from '@/app/auth/useAuth'
import { saveBlob } from '@/shared/api/documentsApi'
import { errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { money, settlementsApi, type SettlementDocument } from '../api/settlementsApi'

async function download(settlementId: string, document: SettlementDocument) {
  try {
    saveBlob(await settlementsApi.file(settlementId, document.id), document.fileName)
  } catch (caught) {
    toast.error(errorMessage(caught))
  }
}

/** Un décompte clôturé : montants, lignes par numéro de service, documents. Lecture seule. */
export function SettlementDetailPage() {
  const { id = '' } = useParams()
  const { can } = usePermissions()
  const settlement = useQuery({ queryKey: ['settlement', id], queryFn: () => settlementsApi.get(id) })
  const documents = useQuery({ queryKey: ['settlement', id, 'documents'], queryFn: () => settlementsApi.documents(id), enabled: can('provider.settlements.documents.view') })

  if (settlement.isPending) return <InlineLoader />
  if (settlement.isError) return <ErrorState message={errorMessage(settlement.error)} onRetry={() => void settlement.refetch()} />

  const data = settlement.data

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={`Décompte ${data.settlementNumber}`} description={`${formatDate(data.periodFrom)} → ${formatDate(data.periodTo)}`} />
      <SectionCard title="Montants">
        <dl className="grid gap-x-6 sm:grid-cols-4">
          <DetailField label="Statut"><StatusBadge code={data.status} label={data.statusLabel} /></DetailField>
          <DetailField label="Sous-total">{money(data.subtotal)}</DetailField>
          <DetailField label="Taxes">{money(data.taxTotal)}</DetailField>
          <DetailField label="Total">{money(data.total)}</DetailField>
        </dl>
      </SectionCard>
      <SectionCard title="Lignes">
        <ul className="divide-y text-sm" aria-label="Lignes du décompte">
          {(data.lines ?? []).map((line) => (
            <li key={line.id} className="flex flex-wrap justify-between gap-2 py-2">
              <span>
                {line.serviceNumber && <span className="font-medium">{line.serviceNumber} · </span>}
                {line.description}
              </span>
              <span className="text-muted-foreground">{`${Number(line.quantity).toLocaleString('fr-CH')} × ${money(line.unitCost)} = `}<span className="text-foreground font-medium">{money(line.totalCost)}</span></span>
            </li>
          ))}
        </ul>
      </SectionCard>
      {can('provider.settlements.documents.view') && (
        <SectionCard title="Documents">
          {(documents.data ?? []).length === 0 ? (
            <p className="text-muted-foreground text-sm">Aucun document joint.</p>
          ) : (
            <ul className="divide-y text-sm">
              {documents.data?.map((document) => (
                <li key={document.id} className="flex items-center justify-between gap-2 py-2">
                  <span className="font-medium">{document.fileName}</span>
                  <Button size="sm" variant="outline" onClick={() => void download(data.id, document)}>
                    <Download className="size-4" aria-hidden /> Télécharger
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </SectionCard>
      )}
    </div>
  )
}
