import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { TemplateDialog } from '@/modules/templates/components/TemplateDialog'
import { useTemplateList } from '@/modules/templates/hooks/useTemplates'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { Skeleton } from '@/shared/components/ui/skeleton'

import type { Customer } from '../types/customer'

/**
 * Le modèle de facture de ce client — ou l'absence de modèle.
 *
 * Nommé `CustomerBillingTemplate` et non `CustomerInvoiceTemplate` : ce dernier
 * est un nom d'**entité** que le §11 interdit, et une recherche globale
 * cherchant un second référentiel de modèles tomberait sur ce composant sans
 * savoir qu'il n'en est pas un. Le nom d'un écran ne vaut pas qu'on ait à
 * raisonner pour l'écarter.
 *
 * Deux cas, et le second n'est pas une erreur :
 *
 * - un **modèle spécifique** existe : ses factures l'emploieront ;
 * - **aucun** : le modèle du transporteur servira, et c'est le fonctionnement
 *   normal. Le §0.19 demande de le dire plutôt que de laisser une case vide,
 *   qui se lirait comme une configuration manquante.
 *
 * Créer un modèle spécifique n'ouvre pas un CRUD parallèle : c'est le même
 * dialogue que l'écran des modèles, avec le client et le type déjà posés.
 */
export function CustomerBillingTemplate({ customer }: { customer: Customer }) {
  const { t } = useTranslation()
  const [creating, setCreating] = useState(false)

  const query = useTemplateList({
    page: 1,
    perPage: 5,
    templateType: 'invoice',
    customerId: customer.id,
  })

  const templates = query.data?.data ?? []

  return (
    <SectionCard
      title={t('customers.invoiceTemplate.title')}
      description={t('customers.invoiceTemplate.hint')}
      actions={
        templates.length === 0 ? (
          <PermissionGuard permission="templates.create">
            <Button size="sm" variant="outline" onClick={() => setCreating(true)}>
              <Plus className="size-4" aria-hidden />
              {t('customers.invoiceTemplate.create')}
            </Button>
          </PermissionGuard>
        ) : null
      }
    >
      {query.isPending ? <Skeleton className="h-10 w-full" /> : null}

      {!query.isPending && templates.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          {t('customers.invoiceTemplate.usesGlobal')}
        </p>
      ) : null}

      {templates.length > 0 ? (
        <ul className="flex flex-col gap-2">
          {templates.map((template) => (
            <li key={template.id} className="flex flex-wrap items-center gap-2 text-sm">
              <Link
                to="/templates?templateType=invoice"
                className="font-medium underline-offset-2 hover:underline"
              >
                {template.name}
              </Link>
              <span className="font-mono text-xs text-muted-foreground">{template.code}</span>
              <Badge variant={template.isActive ? 'secondary' : 'outline'}>
                {template.isActive ? t('common.active') : t('common.inactive')}
              </Badge>
            </li>
          ))}
        </ul>
      ) : null}

      {creating ? (
        <TemplateDialog
          template={null}
          initial={{ templateType: 'invoice', customerId: customer.id }}
          open
          onOpenChange={(open) => !open && setCreating(false)}
        />
      ) : null}
    </SectionCard>
  )
}
