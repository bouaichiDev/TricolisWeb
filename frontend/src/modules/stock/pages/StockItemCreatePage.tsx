import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router-dom'

import { PageHeader } from '@/shared/components/layout/PageHeader'

import { StockItemForm } from '../components/StockItemForm'
import { useCreateStockItem } from '../hooks/useStockItems'
import { toStockItemPayload, type StockItemFormValues } from '../schemas/stockItemSchema'

/**
 * Création d'un article de stock.
 *
 * `customerId` peut arriver par l'URL : l'onglet Stock d'une fiche client y
 * amène avec le client déjà choisi, ce qui évite de le rechercher dans une
 * liste où il figure déjà.
 *
 */
export function StockItemCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const presetCustomerId = params.get('customerId') ?? ''

  // Sans client dans l'URL, il se choisit dans le formulaire : la route plate
  // prend alors le relais.
  const create = useCreateStockItem(presetCustomerId === '' ? undefined : presetCustomerId)

  const submit = async (values: StockItemFormValues) => {
    const item = await create.mutateAsync(toStockItemPayload(values))
    await navigate(`/stock/items/${item.id}`)
  }

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('stock.newItem')} description={t('stock.newItemHint')} />

      <StockItemForm
        defaultValues={presetCustomerId === '' ? undefined : { customerId: presetCustomerId }}
        onSubmit={submit}
        onCancel={() => void navigate('/stock/items')}
        submitLabel={t('common.create')}
      />
    </div>
  )
}
