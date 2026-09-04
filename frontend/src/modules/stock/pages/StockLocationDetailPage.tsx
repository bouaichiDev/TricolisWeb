import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useParams } from 'react-router-dom'

import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import { LocationBalancesTable } from '../components/LocationBalancesTable'
import { useDeleteStockLocation, useStockLocation } from '../hooks/useStockLocations'
import { STOCK_LOCATION_SOURCE } from '../utils/stockSources'

/**
 * Fiche d'un emplacement.
 *
 * `show` charge le parent et les enfants : la place de l'emplacement dans la
 * hiérarchie se lit ici sans passer par l'arbre entier.
 *
 * La suppression est refusée en 409 par quatre dépendances distinctes — enfants,
 * soldes, réservations, et le colis dont c'est l'emplacement courant. Le
 * message du serveur dit laquelle ; il est affiché tel quel.
 */
export function StockLocationDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()
  const [confirmDelete, setConfirmDelete] = useState(false)

  const { data: location, isPending, error, refetch } = useStockLocation(id)
  const remove = useDeleteStockLocation()

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!location) return null

  const text = (value: string | null) =>
    value === null || value === '' ? <span className="text-muted-foreground">—</span> : value

  const children = location.children ?? []

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={location.locationCode}
        subtitle={location.zoneCode ?? undefined}
        editTo={`/stock/locations/${id}/edit`}
        editPermission="stock_locations.update"
        onDelete={() => setConfirmDelete(true)}
        deletePermission="stock_locations.delete"
      />

      <SectionCard title={t('stock.sections.place')}>
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label={t('stock.fields.locationCode')}>{location.locationCode}</DetailField>
          <DetailField label={t('stock.fields.barcode')}>{text(location.barcode)}</DetailField>
          <DetailField label={t('stock.fields.zoneCode')}>{text(location.zoneCode)}</DetailField>
          <DetailField label={t('stock.fields.aisle')}>{text(location.aisle)}</DetailField>
          <DetailField label={t('stock.fields.rack')}>{text(location.rack)}</DetailField>
          <DetailField label={t('stock.fields.level')}>{text(location.level)}</DetailField>
          <DetailField label={t('stock.fields.status')}>
            <StatusBadge status={location.status} source={STOCK_LOCATION_SOURCE} />
          </DetailField>
          <DetailField label={t('stock.fields.parent')}>
            {location.parent ? (
              <Link
                to={`/stock/locations/${location.parent.id}`}
                className="hover:underline"
              >
                {location.parent.locationCode}
              </Link>
            ) : (
              t('stock.noParent')
            )}
          </DetailField>
        </dl>
      </SectionCard>

      <SectionCard title={t('stock.children')} description={t('stock.childrenHint')}>
        {children.length === 0 ? (
          <p className="text-sm text-muted-foreground">{t('stock.noChild')}</p>
        ) : (
          <ul className="flex flex-wrap gap-2">
            {children.map((child) => (
              <li key={child.id}>
                <Link
                  to={`/stock/locations/${child.id}`}
                  className="rounded-md border px-2.5 py-1 text-sm hover:bg-muted"
                >
                  {child.locationCode}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </SectionCard>

      <SectionCard title={t('stock.balances')} description={t('stock.locationBalancesHint')}>
        <LocationBalancesTable stockLocationId={id} />
      </SectionCard>

      <ConfirmDialog
        open={confirmDelete}
        onOpenChange={setConfirmDelete}
        title={t('confirm.deleteTitle')}
        description={t('stock.deleteLocationConfirm', { code: location.locationCode })}
        confirmLabel={t('common.delete')}
        isPending={remove.isPending}
        onConfirm={() => {
          remove.mutate(id, {
            onSuccess: () => {
              setConfirmDelete(false)
              void navigate('/stock/locations')
            },
          })
        }}
      />
    </div>
  )
}
