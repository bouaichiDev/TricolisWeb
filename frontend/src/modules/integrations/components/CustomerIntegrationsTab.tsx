import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ExportConfigurationListPage } from '@/modules/exports/pages/ExportConfigurationListPage'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'
import { Button } from '@/shared/components/ui/button'

import { CustomerApiAccessPanel } from './CustomerApiAccessPanel'
import { CustomerExportJobsPanel } from './CustomerExportJobsPanel'
import { CustomerImportsPanel } from './CustomerImportsPanel'

/**
 * Les intégrations d'un client, en quatre volets.
 *
 * `Historique` ne montre **que** des `ExportJob` (§68) : c'est le seul historique
 * qui existe. Il n'y a pas de trace d'import — le modèle s'arrête à la
 * configuration — ni de journal d'appels API.
 *
 * Les exports réutilisent l'écran de Facturation avec le client déjà fixé :
 * ce sont les mêmes configurations, et le §47 demande précisément de réutiliser
 * les composants plutôt que d'en écrire de seconds.
 */
export function CustomerIntegrationsTab({ customerId }: { customerId: string }) {
  const { t } = useTranslation()

  return (
    <Tabs defaultValue="imports">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <TabsList>
          <TabsTrigger value="imports">{t('integrations.sections.imports.title')}</TabsTrigger>
          <TabsTrigger value="api">{t('integrations.sections.apiAccess.title')}</TabsTrigger>
          <TabsTrigger value="exports">{t('integrations.sections.exports.title')}</TabsTrigger>
          <TabsTrigger value="history">{t('integrations.sections.exportJobs.title')}</TabsTrigger>
        </TabsList>
      </div>

      <TabsContent value="imports" className="mt-6 flex flex-col gap-4">
        <div className="flex justify-end">
          <PermissionGuard permission="customer_import_configurations.create">
            <Button variant="outline" size="sm" asChild>
              <Link to={`/integrations/imports/create?customerId=${customerId}`}>
                <Plus className="size-4" aria-hidden />
                {t('integrations.imports.create')}
              </Link>
            </Button>
          </PermissionGuard>
        </div>

        <CustomerImportsPanel customerId={customerId} />
      </TabsContent>

      <TabsContent value="api" className="mt-6 flex flex-col gap-4">
        <div className="flex justify-end">
          <PermissionGuard permission="customer_api_configurations.create">
            <Button variant="outline" size="sm" asChild>
              <Link to={`/integrations/api-access/create?customerId=${customerId}`}>
                <Plus className="size-4" aria-hidden />
                {t('integrations.api.create')}
              </Link>
            </Button>
          </PermissionGuard>
        </div>

        <CustomerApiAccessPanel customerId={customerId} />
      </TabsContent>

      {/* Le même écran que la Facturation, sur les mêmes données. */}
      <TabsContent value="exports" className="mt-6">
        <ExportConfigurationListPage customerId={customerId} embedded />
      </TabsContent>

      <TabsContent value="history" className="mt-6">
        <CustomerExportJobsPanel customerId={customerId} />
      </TabsContent>
    </Tabs>
  )
}
