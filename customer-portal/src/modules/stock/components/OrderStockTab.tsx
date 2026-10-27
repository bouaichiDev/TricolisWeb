import { useState } from 'react'

import { StockReservationsTable } from './StockReservationsTable'

/**
 * Le stock réservé pour une commande (§18) : les réservations de ses lignes,
 * en lecture seule. Rien ne se réserve ni ne se libère d'ici.
 */
export function OrderStockTab({ orderId }: { orderId: string }) {
  const [page, setPage] = useState(1)

  return <StockReservationsTable filters={{ orderId, page, perPage: 10 }} onPageChange={setPage} />
}
