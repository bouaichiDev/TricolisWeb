import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useCatalogItemList } from '@/modules/catalogs/hooks/useCatalogItems'
import { useCatalogList } from '@/modules/catalogs/hooks/useCatalogs'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

interface CatalogItemPickerProps {
  customerId: string
  value: string
  onChange: (catalogItemId: string) => void
}

/**
 * Choix d'un article de catalogue, en deux temps.
 *
 * L'API n'expose pas les articles d'un client : ils vivent sous un catalogue —
 * `/customers/{c}/catalogs/{catalog}/items`. Il faut donc désigner le catalogue
 * avant l'article, et c'est la forme de la route qui l'impose, pas un choix
 * d'interface.
 *
 * Le lien reste **facultatif** : `stock_items.catalog_item_id` est nullable, et
 * de la marchandise peut arriver en dépôt sans figurer au catalogue. « Aucun »
 * est donc une réponse valable, pas un champ laissé vide par oubli.
 *
 * Le catalogue choisi n'est jamais envoyé : seul `catalogItemId` part au
 * serveur, qui vérifie lui-même que l'article appartient bien au client.
 */
export function CatalogItemPicker({ customerId, value, onChange }: CatalogItemPickerProps) {
  const { t } = useTranslation()
  const [catalogId, setCatalogId] = useState('')

  const catalogs = useCatalogList(
    customerId,
    { page: 1, perPage: 100, status: 'active' },
    customerId !== '',
  )

  // `useCatalogItemList` ne lance rien tant que le catalogue est vide : la
  // garde est portée par le hook, pas par l'appelant.
  const items = useCatalogItemList(customerId, catalogId, { page: 1, perPage: 100 })

  const catalogOptions = (catalogs.data?.data ?? []).map((catalog) => ({
    value: catalog.id,
    label: catalog.name,
    hint: catalog.code,
  }))

  const itemOptions = [
    { value: 'none', label: t('stock.noCatalogItem') },
    ...(items.data?.data ?? []).map((item) => ({
      value: item.id,
      label: item.articleCode,
      hint: item.name,
    })),
  ]

  return (
    <>
      <AsyncSelect
        label={t('stock.fields.catalog')}
        value={catalogId}
        onChange={(next) => {
          setCatalogId(next)
          // Le catalogue change : l'article retenu n'en fait plus partie.
          onChange('')
        }}
        options={catalogOptions}
        isLoading={customerId !== '' && catalogs.isPending}
        disabled={customerId === ''}
        description={customerId === '' ? t('stock.pickCustomerFirst') : t('stock.catalogHint')}
      />

      <AsyncSelect
        label={t('stock.fields.catalogItem')}
        value={value === '' ? 'none' : value}
        onChange={(next) => onChange(next === 'none' ? '' : next)}
        options={itemOptions}
        isLoading={catalogId !== '' && items.isPending}
        disabled={catalogId === ''}
        description={catalogId === '' ? t('stock.pickCatalogFirst') : t('stock.catalogItemHint')}
      />
    </>
  )
}
