import { ArrowRight, FileInput, History, KeyRound, Send } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { usePermissions } from '@/shared/hooks/usePermission'
import { PageHeader } from '@/shared/components/layout/PageHeader'

interface Section {
  key: string
  to: string
  icon: LucideIcon
  permission: string
}

/**
 * Les quatre volets des intégrations client, et rien d'autre.
 *
 * Ni webhooks, ni exports planifiés, ni journaux d'appels API, ni historique
 * d'import : ces tables n'existent pas, et le §67 interdit d'en annoncer les
 * écrans. Ce qui figure ici correspond exactement aux quatre entités du modèle.
 *
 * Une section dont la permission manque disparaît, plutôt que de mener à un
 * refus : la garde de route s'en chargerait, mais après un clic inutile.
 */
const SECTIONS: Section[] = [
  {
    key: 'imports',
    to: '/integrations/imports',
    icon: FileInput,
    permission: 'customer_import_configurations.view',
  },
  {
    key: 'apiAccess',
    to: '/integrations/api-access',
    icon: KeyRound,
    permission: 'customer_api_configurations.view',
  },
  {
    key: 'exports',
    to: '/integrations/exports',
    icon: Send,
    permission: 'customer_export_configurations.view',
  },
  {
    key: 'exportJobs',
    to: '/integrations/export-jobs',
    icon: History,
    permission: 'export_jobs.view',
  },
]

export function IntegrationsHubPage() {
  const { t } = useTranslation()
  const { has } = usePermissions()

  const visible = SECTIONS.filter((section) => has(section.permission))

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('integrations.title')} description={t('integrations.subtitle')} />

      <div className="grid gap-4 sm:grid-cols-2">
        {visible.map((section) => (
          <Link
            key={section.key}
            to={section.to}
            className="group flex items-start gap-4 rounded-lg border bg-card p-5 transition-colors hover:bg-muted/50"
          >
            <section.icon className="mt-0.5 size-5 shrink-0 text-muted-foreground" aria-hidden />

            <div className="min-w-0 flex-1">
              <p className="flex items-center gap-1.5 font-medium">
                {t(`integrations.sections.${section.key}.title`)}
                <ArrowRight
                  className="size-4 opacity-0 transition-opacity group-hover:opacity-100"
                  aria-hidden
                />
              </p>
              <p className="mt-1 text-sm text-muted-foreground">
                {t(`integrations.sections.${section.key}.description`)}
              </p>
            </div>
          </Link>
        ))}
      </div>
    </div>
  )
}
