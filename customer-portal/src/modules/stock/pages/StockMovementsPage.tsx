import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Input } from '@/shared/components/ui/input'

import { StockMovementsTable } from '../components/StockMovementsTable'
import { StockNav } from '../components/StockNav'
import { useStockFilters } from '../hooks/useStockFilters'

/** Les entrées, sorties et transferts de ses articles (§16) — lecture seule. */
export function StockMovementsPage() {
  const { filters, update } = useStockFilters()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Mouvements de stock" description="Les entrées, sorties et transferts enregistrés par votre transporteur sur vos articles." />
      <StockNav />
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <Input aria-label="Type de mouvement" placeholder="Type de mouvement" className="sm:w-48" value={filters.movementType ?? ''} onChange={(event) => update({ movementType: event.target.value })} />
        <Input type="date" aria-label="Du" className="sm:w-40" value={filters.createdFrom ?? ''} onChange={(event) => update({ createdFrom: event.target.value })} />
        <Input type="date" aria-label="Au" className="sm:w-40" value={filters.createdTo ?? ''} onChange={(event) => update({ createdTo: event.target.value })} />
      </div>
      <StockMovementsTable filters={filters} onPageChange={(page) => update({ page })} />
    </div>
  )
}
