import { Pencil, Power, PowerOff } from 'lucide-react'
import { useState } from 'react'
import { useParams } from 'react-router-dom'

import { errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { formatDate } from '@/shared/utils/format'

import { CatalogItemDialog } from '../components/CatalogItemDialog'
import { useCatalogItem } from '../hooks/useCatalog'
import { useCatalogItemToggle } from '../hooks/useCatalogItemToggle'

const measure = (value: string | null, unit: string) => (value === null ? '—' : `${value.replace('.', ',')} ${unit}`)

/** La fiche d'un article du catalogue, et où il est utilisé. */
export function CatalogItemPage() {
  const { id = '' } = useParams<{ id: string }>()
  const item = useCatalogItem(id)
  const { toggle, isPending } = useCatalogItemToggle()
  const [editing, setEditing] = useState(false)

  if (item.isPending) return <InlineLoader />
  if (item.isError) return <ErrorState title="Article introuvable" message={errorMessage(item.error)} onRetry={() => void item.refetch()} />

  const article = item.data
  const canUpdate = article.actions?.canUpdate === true


  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={article.articleCode}
        description={article.name}
        actions={
          <>
            {article.isActive ? <Badge variant="outline">Actif</Badge> : <Badge variant="secondary">Inactif</Badge>}
            {canUpdate && (
              <>
                <Button variant="outline" onClick={() => setEditing(true)}>
                  <Pencil className="size-4" aria-hidden /> Modifier
                </Button>
                <Button variant="outline" disabled={isPending} onClick={() => void toggle(article)}>
                  {article.isActive ? <PowerOff className="size-4" aria-hidden /> : <Power className="size-4" aria-hidden />}
                  {article.isActive ? 'Désactiver' : 'Réactiver'}
                </Button>
              </>
            )}
          </>
        }
      />

      <SectionCard title="Article">
        <dl className="grid gap-x-8 sm:grid-cols-2">
          <DetailField label="Catalogue">{article.catalog?.name ?? '—'}</DetailField>
          <DetailField label="Code-barres">{article.barcode ?? '—'}</DetailField>
          <DetailField label="Description">{article.description ?? '—'}</DetailField>
          <DetailField label="Ajouté le">{formatDate(article.createdAt)}</DetailField>
        </dl>
      </SectionCard>

      <SectionCard title="Dimensions">
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label="Poids">{measure(article.weight, 'kg')}</DetailField>
          <DetailField label="Volume">{measure(article.volume, 'm³')}</DetailField>
          <DetailField label="Montage">{article.assemblyTimeMinutes === null ? '—' : `${article.assemblyTimeMinutes} min`}</DetailField>
          <DetailField label="Longueur">{measure(article.length, 'm')}</DetailField>
          <DetailField label="Largeur">{measure(article.width, 'm')}</DetailField>
          <DetailField label="Hauteur">{measure(article.height, 'm')}</DetailField>
        </dl>
      </SectionCard>

      {article.usage && (
        <SectionCard title="Utilisation" description="Ce qui s’appuie sur cet article chez vous.">
          <dl className="grid gap-x-8 sm:grid-cols-2">
            <DetailField label="Lignes de commande">{article.usage.orderLines}</DetailField>
            <DetailField label="Articles en stock">{article.usage.stockItems}</DetailField>
          </dl>
        </SectionCard>
      )}
      {canUpdate && <CatalogItemDialog catalogs={[]} item={article} open={editing} onOpenChange={setEditing} />}
    </div>
  )
}
