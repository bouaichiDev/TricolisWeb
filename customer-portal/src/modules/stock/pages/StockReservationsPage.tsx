import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import { StockNav } from '../components/StockNav'
import { StockReservationsTable } from '../components/StockReservationsTable'
import { useStockFilters } from '../hooks/useStockFilters'

const ALL = '__all__'

/** Le stock réservé pour ses commandes (§17) — lecture seule, avec la commande liée. */
export function StockReservationsPage() {
  const { filters, update } = useStockFilters()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Réservations de stock" description="Le stock que votre transporteur a réservé pour vos commandes." />
      <StockNav />
      <Select value={filters.state || ALL} onValueChange={(value) => update({ state: value === ALL ? '' : value })}>
        <SelectTrigger className="w-full sm:w-52" aria-label="État">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={ALL}>Toutes</SelectItem>
          <SelectItem value="active">En cours</SelectItem>
          <SelectItem value="released">Libérées</SelectItem>
        </SelectContent>
      </Select>
      <StockReservationsTable filters={filters} onPageChange={(page) => update({ page })} />
    </div>
  )
}
