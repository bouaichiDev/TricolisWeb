import { useQuery } from '@tanstack/react-query'
import { BookOpen } from 'lucide-react'
import { useState } from 'react'

import { SearchInput } from '@/shared/components/data/SearchInput'
import { InlineLoader } from '@/shared/components/feedback/States'
import { Button } from '@/shared/components/ui/button'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Input } from '@/shared/components/ui/input'

import { ordersApi } from '../api/ordersApi'
import type { CatalogItem } from '../types'

export interface PickedArticle {
  item: CatalogItem
  quantity: string
}

/**
 * Le catalogue du client, parcouru d'un coup : on coche les articles, on
 * règle leur quantité, et chacun devient une ligne de la commande. Seuls les
 * articles actifs de **ses** catalogues sont proposés — le serveur le garantit.
 */
export function CatalogBrowserDialog({ onAdd }: { onAdd: (picked: PickedArticle[]) => void }) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [picked, setPicked] = useState<Record<string, PickedArticle>>({})
  const items = useQuery({ queryKey: ['catalog-items', search], queryFn: () => ordersApi.catalogItems(search), enabled: open })
  const count = Object.keys(picked).length

  const toggle = (item: CatalogItem, checked: boolean) =>
    setPicked((current) => {
      const next = { ...current }
      if (checked) next[item.id] = { item, quantity: current[item.id]?.quantity ?? '1' }
      else delete next[item.id]
      return next
    })

  const close = (next: boolean) => {
    if (!next) setPicked({})
    setOpen(next)
  }

  return (
    <>
      <Button type="button" onClick={() => setOpen(true)}>
        <BookOpen className="size-4" aria-hidden /> Parcourir le catalogue
      </Button>
      <Dialog open={open} onOpenChange={close}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>Votre catalogue</DialogTitle>
            <DialogDescription>Cochez les articles à commander et indiquez leur quantité.</DialogDescription>
          </DialogHeader>
          <SearchInput value={search} onChange={setSearch} placeholder="Code, code-barres ou nom" />
          <div className="max-h-96 overflow-y-auto rounded-lg border">
            {items.isPending && <InlineLoader />}
            {items.data?.length === 0 && <p className="text-muted-foreground p-4 text-sm">Aucun article ne correspond.</p>}
            <ul className="divide-y">
              {items.data?.map((item) => {
                const selected = picked[item.id]

                return (
                  <li key={item.id} className="flex items-center gap-3 px-4 py-2">
                    <Checkbox id={`catalog-${item.id}`} checked={selected !== undefined} onCheckedChange={(checked) => toggle(item, checked === true)} />
                    <label htmlFor={`catalog-${item.id}`} className="min-w-0 flex-1 cursor-pointer">
                      <span className="block truncate text-sm font-medium">{item.name}</span>
                      <span className="text-muted-foreground text-xs">
                        {item.articleCode}
                        {item.weight ? ` · ${Number(item.weight)} kg` : ''}
                      </span>
                    </label>
                    <Input
                      type="number"
                      min="1"
                      step="1"
                      aria-label={`Quantité de ${item.name}`}
                      className="w-24"
                      disabled={selected === undefined}
                      value={selected?.quantity ?? ''}
                      onChange={(event) => setPicked((current) => ({ ...current, [item.id]: { item, quantity: event.target.value } }))}
                    />
                  </li>
                )
              })}
            </ul>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => close(false)}>
              Annuler
            </Button>
            <Button
              type="button"
              disabled={count === 0}
              onClick={() => {
                onAdd(Object.values(picked))
                close(false)
              }}
            >
              Ajouter {count > 0 ? `${count} article${count > 1 ? 's' : ''}` : ''}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
