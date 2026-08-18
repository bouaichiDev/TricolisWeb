import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useCatalogItemList } from '@/modules/catalogs/hooks/useCatalogItems'
import { useCatalogList } from '@/modules/catalogs/hooks/useCatalogs'
import type { CatalogItem } from '@/modules/catalogs/types/catalog'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { EmptyState } from '@/shared/components/feedback/EmptyState'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'

interface CatalogItemPickerProps {
  customerId: string
  open: boolean
  onOpenChange: (open: boolean) => void
  onSelect: (item: CatalogItem) => void
}

/**
 * Choix d'un article dans les catalogues du client.
 *
 * Les catalogues sont portés par le client — `GET /customers/{customer}/catalogs`
 * — il n'existe pas de liste globale. Le catalogue est donc choisi d'abord, puis
 * ses articles, toujours paginés : un catalogue peut en compter des milliers.
 *
 * Seuls les catalogues et articles actifs sont proposés : `OrderScopeGuard`
 * refuse les autres, et les afficher ferait échouer la commande après coup.
 */
export function CatalogItemPicker({
  customerId,
  open,
  onOpenChange,
  onSelect,
}: CatalogItemPickerProps) {
  const { t } = useTranslation()
  const [catalogId, setCatalogId] = useState('')
  const [search, setSearch] = useState('')

  const catalogs = useCatalogList(customerId, { page: 1, perPage: 100, status: 'active' })
  const items = useCatalogItemList(customerId, catalogId, {
    page: 1,
    perPage: 25,
    search: search || undefined,
    status: 'active',
  })

  const catalogOptions = (catalogs.data?.data ?? []).map((catalog) => ({
    value: catalog.id,
    label: catalog.name,
    hint: catalog.code,
  }))

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{t('orders.lines.pickFromCatalog')}</DialogTitle>
          <DialogDescription>{t('catalogs.subtitle')}</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4">
          <AsyncSelect
            label={t('catalogs.title')}
            value={catalogId}
            onChange={setCatalogId}
            options={catalogOptions}
            isLoading={catalogs.isPending}
          />

          {catalogOptions.length === 0 && !catalogs.isPending ? (
            <EmptyState title={t('orders.lines.noCatalog')} />
          ) : null}

          {catalogId !== '' ? (
            <>
              <SearchInput
                value={search}
                onChange={setSearch}
                placeholder={t('orders.lines.searchPlaceholder')}
              />

              <ul className="max-h-80 divide-y overflow-y-auto rounded-md border">
                {(items.data?.data ?? []).map((item) => (
                  <li key={item.id}>
                    <Button
                      type="button"
                      variant="ghost"
                      className="h-auto w-full justify-start rounded-none px-3 py-2 text-left"
                      onClick={() => {
                        onSelect(item)
                        onOpenChange(false)
                      }}
                    >
                      <span className="flex flex-col items-start">
                        <span className="font-medium">{item.name}</span>
                        <span className="text-xs text-muted-foreground">
                          {[item.articleCode, item.barcode].filter(Boolean).join(' · ')}
                        </span>
                      </span>
                    </Button>
                  </li>
                ))}
              </ul>

              {(items.data?.data ?? []).length === 0 && !items.isPending ? (
                <EmptyState title={t('orders.lines.noItem')} />
              ) : null}
            </>
          ) : null}
        </div>
      </DialogContent>
    </Dialog>
  )
}
