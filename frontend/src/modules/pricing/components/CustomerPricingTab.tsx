import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { usePriceLists } from '../hooks/usePricing'
import type { PriceList } from '../types/pricing'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

interface CustomerPricingTabProps {
  customerId: string
}

/**
 * Ce que ce client paie, et d'où ça vient.
 *
 * **Le repli est dit explicitement** (§169S). Un client sans barème propre
 * n'est pas un client sans tarif : il est facturé au barème général, et
 * l'écran doit l'annoncer plutôt que de laisser une liste vide faire croire
 * qu'aucun tarif n'existe.
 *
 * Le repli reste partiel : un client qui a négocié la livraison garde le
 * barème général pour le chargement. C'est pourquoi la mention accompagne
 * aussi un client qui a bien ses barèmes.
 *
 * Les requêtes ne partent qu'à l'ouverture de l'onglet : Radix démonte le
 * contenu inactif, donc ce composant ne se monte qu'une fois l'onglet choisi.
 */
export function CustomerPricingTab({ customerId }: CustomerPricingTabProps) {
  const { t } = useTranslation()

  const owned = usePriceLists({ page: 1, perPage: 50, scope: 'customer', customerId })
  const global = usePriceLists({ page: 1, perPage: 50, scope: 'global' })

  if (owned.error) return <ErrorState error={owned.error} onRetry={() => void owned.refetch()} />

  const lists = owned.data?.data ?? []
  const globals = global.data?.data ?? []

  const columns: Column<PriceList>[] = [
    {
      key: 'code',
      header: t('pricing.lists.fields.code'),
      cell: (row) => (
        <Link
          to={`/billing/pricing/${row.id}`}
          className="flex flex-col text-primary hover:underline"
        >
          <span className="font-medium">{row.code}</span>
          <span className="text-xs text-muted-foreground">{row.name}</span>
        </Link>
      ),
    },
    {
      key: 'content',
      header: t('pricing.lists.fields.content'),
      cell: (row) =>
        t('pricing.lists.counts', { rules: row.ruleCount ?? 0, matrices: row.matrixCount ?? 0 }),
    },
    {
      key: 'validity',
      header: t('pricing.lists.fields.validity'),
      cell: (row) =>
        row.validFrom || row.validTo
          ? `${formatDate(row.validFrom)} — ${formatDate(row.validTo)}`
          : t('pricing.lists.always'),
    },
    {
      key: 'isActive',
      header: t('pricing.lists.fields.isActive'),
      cell: (row) => (
        <Badge variant={row.isActive ? 'default' : 'secondary'}>
          {row.isActive ? t('common.yes') : t('common.no')}
        </Badge>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <SectionCard
        title={t('pricing.customerTab.title')}
        description={
          lists.length === 0
            ? t('pricing.customerTab.noneHint')
            : t('pricing.customerTab.partialHint')
        }
        actions={
          <Button asChild size="sm" variant="outline">
            <Link to="/billing/pricing/customers">{t('pricing.customerTab.manage')}</Link>
          </Button>
        }
      >
        <DataTable
          columns={columns}
          rows={lists}
          rowKey={(row) => row.id}
          isLoading={owned.isPending}
          emptyMessage={t('pricing.customerTab.none')}
        />
      </SectionCard>

      <SectionCard
        title={t('pricing.customerTab.fallbackTitle')}
        description={t('pricing.customerTab.fallbackHint')}
      >
        <DataTable
          columns={columns}
          rows={globals}
          rowKey={(row) => row.id}
          isLoading={global.isPending}
          emptyMessage={t('pricing.customerTab.noGlobal')}
        />
      </SectionCard>
    </div>
  )
}
