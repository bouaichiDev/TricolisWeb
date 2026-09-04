import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { useRoleList } from '../hooks/useRoles'
import { isEditableRole, type Role } from '../types/role'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/** `GET /roles` n'accepte ni tri ni recherche : la table n'en propose donc pas. */
export function RoleListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)

  const { data, isPending, error, refetch } = useRoleList({ page, perPage: 25 })

  const columns: Column<Role>[] = [
    {
      key: 'code',
      header: t('roles.fields.code'),
      cell: (row) => (
        <Link
          to={`/roles/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {row.code}
        </Link>
      ),
    },
    {
      key: 'name',
      header: t('roles.fields.name'),
      cell: (row) => (
        <span className="flex flex-wrap items-center gap-2">
          {row.name}
          {row.isSystem ? (
            <Badge variant="secondary" className="font-normal">
              {t('roles.system')}
            </Badge>
          ) : null}
          {/* Le §23 demande de marquer clairement ce qui n'est pas modifiable :
              un rôle système visible mais silencieusement verrouillé donnerait
              l'impression d'une panne au premier clic. */}
          {isEditableRole(row) ? null : (
            <Badge variant="outline" className="font-normal">
              {t('roles.readOnly')}
            </Badge>
          )}
          {row.scope === 'platform' ? (
            <Badge className="font-normal">{t('roles.platform')}</Badge>
          ) : null}
        </span>
      ),
    },
    {
      key: 'permissions',
      header: t('roles.sections.permissions'),
      hideOnMobile: true,
      cell: (row) => row.permissions?.length ?? 0,
    },
    {
      key: 'status',
      header: t('roles.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('roles.title')}
        description={t('roles.subtitle')}
        actions={
          <PermissionGuard permission="roles.create">
            <Button asChild>
              <Link to="/roles/create">
                <Plus className="size-4" aria-hidden />
                {t('roles.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        rowKey={(row) => row.id}
        meta={data?.meta}
        isLoading={isPending}
        error={error}
        onPageChange={setPage}
        onRetry={() => void refetch()}
        onRowClick={(row) => void navigate(`/roles/${row.id}`)}
      />
    </div>
  )
}
