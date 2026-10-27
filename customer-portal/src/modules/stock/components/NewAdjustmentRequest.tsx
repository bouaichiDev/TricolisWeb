import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { MessageSquareWarning } from 'lucide-react'
import { useState } from 'react'

import { usePermissions } from '@/app/auth/useAuth'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'

import { stockApi } from '../api/stockApi'
import type { StockItem } from '../types'
import { formatQuantity } from '../utils/quantity'
import { AdjustmentRequestDialog } from './AdjustmentRequestDialog'

/**
 * « Nouvelle demande » depuis la page des demandes : choisir l'article, puis
 * le même dialogue que depuis la fiche. La recherche interroge le serveur
 * (`stock/items?search=`) : seuls les articles du client remontent, et
 * l'API refuse de toute façon l'article d'un autre client (404).
 *
 * Sans aucun article de stock, rien à corriger : on le dit au lieu d'offrir
 * un bouton qui ne mène nulle part.
 */
export function NewAdjustmentRequest() {
  const { can } = usePermissions()
  const [picking, setPicking] = useState(false)
  const [search, setSearch] = useState('')
  const [item, setItem] = useState<StockItem | null>(null)
  const allowed = can('customer.stock.adjustment_requests.create')
  const any = useQuery({ queryKey: ['stock', 'items', 'any'], queryFn: () => stockApi.items({ perPage: 1 }), enabled: allowed })
  const found = useQuery({
    queryKey: ['stock', 'items', 'pick', search],
    queryFn: () => stockApi.items({ search: search || undefined, perPage: 10 }),
    enabled: allowed && picking,
    placeholderData: keepPreviousData,
  })

  if (!allowed || any.isPending || any.isError) return null

  if (any.data.meta.total === 0) {
    return <p className="text-muted-foreground text-sm">Aucun article en stock : une demande de correction porte sur un article reçu par votre transporteur.</p>
  }

  const choose = (chosen: StockItem) => {
    setPicking(false)
    setSearch('')
    setItem(chosen)
  }

  return (
    <>
      <Button onClick={() => setPicking(true)}>
        <MessageSquareWarning className="size-4" aria-hidden /> Nouvelle demande
      </Button>
      <Dialog open={picking} onOpenChange={setPicking}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Choisir l’article</DialogTitle>
            <DialogDescription>L’article dont la quantité affichée ne correspond pas à ce que vous constatez.</DialogDescription>
          </DialogHeader>
          {/* Saisie différée (350 ms) : une requête par mot, pas par lettre. */}
          <SearchInput value={search} onChange={setSearch} label="Rechercher un article" placeholder="Code, code-barres ou description" />
          <ul className="grid max-h-72 gap-1 overflow-y-auto" aria-label="Articles trouvés">
            {(found.data?.data ?? []).map((candidate) => (
              <li key={candidate.id}>
                <Button variant="ghost" className="h-auto w-full justify-between py-2 text-left" onClick={() => choose(candidate)}>
                  <span>
                    <span className="font-medium">{candidate.articleCode}</span>
                    {candidate.description ? <span className="text-muted-foreground"> — {candidate.description}</span> : null}
                  </span>
                  <span className="text-muted-foreground tabular-nums">{formatQuantity(candidate.stock.quantity)}</span>
                </Button>
              </li>
            ))}
            {found.data && found.data.data.length === 0 ? <li className="text-muted-foreground px-2 text-sm">Aucun article ne correspond.</li> : null}
          </ul>
        </DialogContent>
      </Dialog>
      {item && <AdjustmentRequestDialog item={item} open onOpenChange={(open) => !open && setItem(null)} />}
    </>
  )
}
