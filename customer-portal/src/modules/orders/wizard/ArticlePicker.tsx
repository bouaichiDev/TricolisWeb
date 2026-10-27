import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { useState } from 'react'

import { Button } from '@/shared/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/shared/components/ui/dialog'
import { Input } from '@/shared/components/ui/input'

import { ordersApi } from '../api/ordersApi'
import type { CatalogItem } from '../types'

interface ArticlePickerProps {
  label: string
  onPick: (item: CatalogItem) => void
}

/**
 * Choix d'un article dans le catalogue **du client**, par recherche : le
 * backend ne rend que ses articles actifs.
 */
export function ArticlePicker({ label, onPick }: ArticlePickerProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const items = useQuery({
    queryKey: ['catalog-items', search],
    queryFn: () => ordersApi.catalogItems(search),
    enabled: open,
  })

  return (
    <>
      <Button type="button" variant="outline" className="w-full justify-start" onClick={() => setOpen(true)}>
        <Search className="size-4" /> {label || 'Choisir un article'}
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Catalogue</DialogTitle>
          </DialogHeader>
          <Input autoFocus placeholder="Code, code-barres ou nom" value={search} onChange={(event) => setSearch(event.target.value)} />
          <div className="max-h-80 space-y-1 overflow-y-auto">
            {items.data?.length === 0 && <p className="text-muted-foreground py-4 text-sm">Aucun article.</p>}
            {items.data?.map((item) => (
              <button
                key={item.id}
                type="button"
                className="hover:bg-muted flex w-full flex-col items-start rounded-md px-3 py-2 text-left text-sm"
                onClick={() => {
                  onPick(item)
                  setOpen(false)
                }}
              >
                <span className="font-medium">{item.name}</span>
                <span className="text-muted-foreground text-xs">{item.articleCode}</span>
              </button>
            ))}
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}
