import { useState } from 'react'

import { usePermissions } from '@/app/auth/useAuth'
import { errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/components/ui/tabs'

import { ExportJobsSection } from '../components/ExportJobsSection'
import { FileImportDialog } from '../components/FileImportDialog'
import { FlowsSection } from '../components/FlowsSection'
import { ImportRunsSection } from '../components/ImportRunsSection'
import { useIntegrationsOverview } from '../hooks/useIntegrations'
import type { ImportIntegration } from '../types'

/**
 * Les intégrations du client (Phase 6) : ses flux, l'historique de ses
 * imports, leurs envois, et le dépôt de fichiers de commandes. Les connexions, secrets et clés API restent chez
 * le transporteur.
 */
export function IntegrationsPage() {
  const { can } = usePermissions()
  const overview = useIntegrationsOverview()
  const [importing, setImporting] = useState<ImportIntegration | null>(null)
  const showJobs = can('customer.integrations.logs.view')
  const failed = overview.data?.failedExportJobs ?? 0

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Intégrations" description="Les échanges automatiques entre vos systèmes et Tricolis." />
      {overview.isPending ? (
        <InlineLoader />
      ) : overview.isError ? (
        <ErrorState message={errorMessage(overview.error)} onRetry={() => void overview.refetch()} />
      ) : (
        <Tabs defaultValue="flows">
          <TabsList>
            <TabsTrigger value="flows">Flux</TabsTrigger>
            {showJobs && <TabsTrigger value="imports">Imports</TabsTrigger>}
            {showJobs && <TabsTrigger value="jobs">Envois{failed > 0 ? ` (${failed} en échec)` : ''}</TabsTrigger>}
          </TabsList>
          <TabsContent value="flows" className="mt-4">
            <FlowsSection overview={overview.data} onImport={setImporting} />
          </TabsContent>
          {showJobs && (
            <TabsContent value="imports" className="mt-4">
              <ImportRunsSection />
            </TabsContent>
          )}
          {showJobs && (
            <TabsContent value="jobs" className="mt-4">
              <ExportJobsSection />
            </TabsContent>
          )}
        </Tabs>
      )}
      <FileImportDialog flow={importing} onClose={() => setImporting(null)} />
    </div>
  )
}
