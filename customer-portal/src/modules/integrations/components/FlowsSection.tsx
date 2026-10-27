import { FileUp } from 'lucide-react'

import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { formatDateTime } from '@/shared/utils/format'

import type { ApiAccess, ExportIntegration, ImportIntegration, IntegrationsOverview } from '../types'
import { channelLabels, exportTypeLabel, frequencyLabel, transportLabel } from '../utils/labels'

const ActiveBadge = ({ active }: { active: boolean }) =>
  active ? <Badge variant="outline" className="border-emerald-300 text-emerald-700">Active</Badge> : <Badge variant="secondary">Inactive</Badge>

const exportColumns: Column<ExportIntegration>[] = [
  { key: 'name', header: 'Nom', cell: (flow) => <span className="font-medium">{flow.name}</span> },
  { key: 'type', header: 'Contenu', cell: (flow) => exportTypeLabel(flow.exportType) },
  { key: 'format', header: 'Format', hideOnMobile: true, cell: (flow) => flow.format?.toUpperCase() ?? '—' },
  { key: 'transport', header: 'Canal', hideOnMobile: true, cell: (flow) => transportLabel(flow.transport) },
  { key: 'frequency', header: 'Déclenchement', hideOnMobile: true, cell: (flow) => frequencyLabel(flow.frequency) },
  { key: 'status', header: 'Statut', cell: (flow) => <ActiveBadge active={flow.isActive} /> },
  { key: 'last', header: 'Dernier envoi', hideOnMobile: true, cell: (flow) => formatDateTime(flow.lastSentAt) },
]

const apiColumns: Column<ApiAccess>[] = [
  { key: 'name', header: 'Nom', cell: (access) => <span className="font-medium">{access.name}</span> },
  { key: 'permissions', header: 'Droits', cell: (access) => (access.permissions.length > 0 ? access.permissions.join(', ') : 'Identité seule') },
  { key: 'ip', header: 'Adresses', hideOnMobile: true, cell: (access) => (access.restrictedByIp ? 'Restreintes' : 'Toutes') },
  { key: 'status', header: 'Statut', cell: (access) => <ActiveBadge active={access.isActive} /> },
  { key: 'last', header: 'Dernier appel', hideOnMobile: true, cell: (access) => formatDateTime(access.lastUsedAt) },
]

interface FlowsSectionProps {
  overview: IntegrationsOverview
  onImport: (flow: ImportIntegration) => void
}

/**
 * Les flux du client : ce qui arrive chez Tricolis (imports), ce qui en part
 * (exports), et les accès de son système à l'API. Lecture seule — les
 * connexions et les clés restent administrées par le transporteur.
 */
export function FlowsSection({ overview, onImport }: FlowsSectionProps) {
  const importColumns: Column<ImportIntegration>[] = [
    { key: 'name', header: 'Nom', cell: (flow) => <span className="font-medium">{flow.name}</span> },
    { key: 'channel', header: 'Canal', cell: (flow) => channelLabels[flow.channel] },
    { key: 'format', header: 'Format', hideOnMobile: true, cell: (flow) => flow.fileFormat.toUpperCase() },
    { key: 'frequency', header: 'Mode', hideOnMobile: true, cell: (flow) => frequencyLabel(flow.frequency) },
    { key: 'status', header: 'Statut', cell: (flow) => <ActiveBadge active={flow.isActive} /> },
    { key: 'last', header: 'Dernière exécution', hideOnMobile: true, cell: (flow) => formatDateTime(flow.lastRunAt) },
  ]

  return (
    <div className="flex flex-col gap-6">
      <SectionCard title="Réception de commandes" description="Les commandes que Tricolis reçoit de vos systèmes ou de vos fichiers.">
        <DataTable
          columns={importColumns}
          rows={overview.imports}
          rowKey={(flow) => flow.id}
          emptyMessage="Aucune réception configurée."
          actions={(flow) =>
            flow.actions.canUpload && <RowActions actions={[{ key: 'import', icon: FileUp, label: 'Importer un fichier', onClick: () => onImport(flow) }]} />
          }
        />
      </SectionCard>
      <SectionCard title="Envois vers vos systèmes" description="Ce que Tricolis transmet automatiquement à vos systèmes.">
        <DataTable columns={exportColumns} rows={overview.exports} rowKey={(flow) => flow.id} emptyMessage="Aucun envoi configuré." />
      </SectionCard>
      <SectionCard title="Accès API" description="Les clés par lesquelles vos systèmes interrogent Tricolis. Leur gestion passe par votre transporteur.">
        <DataTable columns={apiColumns} rows={overview.apiAccesses} rowKey={(access) => access.id} emptyMessage="Aucun accès API." />
      </SectionCard>
    </div>
  )
}
