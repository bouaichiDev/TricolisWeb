import { useQuery } from '@tanstack/react-query'
import { useId, useState } from 'react'

import type { CatalogItem } from '@/modules/orders/types'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { Label } from '@/shared/components/ui/label'

import { stockApi } from '../api/stockApi'

interface CatalogItemFieldProps {
  value: string
  onChange: (item: CatalogItem | null) => void
}

/**
 * L'article du catalogue **du client** auquel rattacher l'article en stock
 * (§13). La liste vient de l'API du portail, bornée à ses catalogues ; le
 * backend revérifie l'appartenance à l'enregistrement.
 */
export function CatalogItemField({ value, onChange }: CatalogItemFieldProps) {
  const id = useId()
  const [search, setSearch] = useState('')
  const items = useQuery({ queryKey: ['stock', 'catalog-items', search], queryFn: () => stockApi.catalogItems(search) })

  return (
    <div className="grid gap-1.5">
      <Label htmlFor={id}>Article du catalogue (facultatif)</Label>
      <SearchInput value={search} onChange={setSearch} placeholder="Chercher dans votre catalogue…" />
      <select
        id={id}
        className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
        value={value}
        onChange={(event) => onChange(items.data?.find((item) => item.id === event.target.value) ?? null)}
      >
        <option value="">Aucun</option>
        {items.data?.map((item) => (
          <option key={item.id} value={item.id}>
            {item.articleCode} — {item.name}
          </option>
        ))}
      </select>
    </div>
  )
}
