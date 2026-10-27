import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { formatDate, formatDateTime } from '@/shared/utils/format'

import type { OrderDetail } from '../types'

export function GeneralTab({ order }: { order: OrderDetail }) {
  return (
    <div className="flex flex-col gap-6">
      <SectionCard title="Informations générales">
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label="Référence">{order.reference}</DetailField>
          <DetailField label="Votre référence">{order.customerReference}</DetailField>
          <DetailField label="Référence externe">{order.externalReference}</DetailField>
          <DetailField label="Date de commande">{order.orderDate && formatDate(order.orderDate)}</DetailField>
          <DetailField label="Créée le">{order.createdAt && formatDateTime(order.createdAt)}</DetailField>
          <DetailField label="Statut">{order.status.label}</DetailField>
        </dl>
      </SectionCard>
      <SectionCard title="Mesures">
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label="Poids total (kg)">{order.weight === null ? null : String(Number(order.weight))}</DetailField>
          <DetailField label="Volume total (m³)">{order.volume === null ? null : String(Number(order.volume))}</DetailField>
          <DetailField label="Colis">{String(order.packageCount)}</DetailField>
        </dl>
      </SectionCard>
      {order.workerRemark && (
        <SectionCard title="Consignes pour le transporteur">
          <p className="text-sm whitespace-pre-line">{order.workerRemark}</p>
        </SectionCard>
      )}
    </div>
  )
}
