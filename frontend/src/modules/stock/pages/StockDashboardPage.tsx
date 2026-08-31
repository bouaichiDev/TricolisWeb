import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'

import { CustomerFilterSelect } from '@/modules/customers/components/CustomerFilterSelect'
import { StockKpiRow } from '../components/StockKpiRow'
import { useStockBalances } from '../hooks/useStockBalances'
import type { StockBalanceFilters } from '../types/stockFilters'

/**
 * Vue d'ensemble du stock.
 *
 * **Les indicateurs portent sur la page chargée, pas sur tout le stock**, et
 * l'écran le dit. Il n'existe aucune route d'agrégat : `stock-balances` pagine
 * des lignes, sans total. Sommer l'ensemble exigerait de rapatrier toutes les
 * pages — un N+1 déguisé que le §68 interdit — ou d'inventer un endpoint que le
 * backend n'a pas.
 *
 * Le choix assumé est donc de montrer un chiffre **vrai et borné** plutôt qu'un
 * chiffre total et faux. Le filtre par client resserre le périmètre jusqu'à ce
 * que les indicateurs deviennent exacts, ce qui est le cas d'usage réel : on
 * regarde le stock d'un client, pas la somme de tous.
 */
export function StockDashboardPage() {
  const { t } = useTranslation()
  const [customerId, setCustomerId] = useState<string | undefined>(undefined)

  const filters: StockBalanceFilters = {
    page: 1,
    perPage: 100,
    customerId,
    sort: 'available_quantity',
    direction: 'desc',
  }

  const { data, isPending } = useStockBalances(filters)

  const rows = data?.data ?? []
  const total = data?.meta.total ?? rows.length

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.dashboard')} description={t('stock.dashboardSubtitle')} />

      <CustomerFilterSelect value={customerId} onChange={setCustomerId} />

      <StockKpiRow
        balances={rows}
        isLoading={isPending}
        isPartial={total > rows.length}
        total={total}
      />

      <SectionCard title={t('stock.sections.shortcuts')} description={t('stock.shortcutsHint')}>
        <ul className="flex flex-wrap gap-2">
          {[
            { to: '/stock/items', label: t('stock.items') },
            { to: '/stock/locations', label: t('stock.locations') },
            { to: '/stock/balances', label: t('stock.balancesTitle') },
            { to: '/stock/movements', label: t('stock.movementsTitle') },
            { to: '/stock/reservations', label: t('stock.reservations') },
          ].map((entry) => (
            <li key={entry.to}>
              <Link
                to={entry.to}
                className="rounded-md border px-3 py-1.5 text-sm hover:bg-muted"
              >
                {entry.label}
              </Link>
            </li>
          ))}
        </ul>
      </SectionCard>
    </div>
  )
}
