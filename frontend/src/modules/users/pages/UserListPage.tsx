import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'

import { useMemberList } from '../hooks/useMembers'
import { memberFullName, type Member } from '../types/member'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { DataTable, type Column } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'

/**
 * Annuaire des membres de l'organisation active.
 *
 * La ressource listée est le rattachement, pas le compte : c'est lui qui porte
 * les rôles et le statut dans cette organisation. `GET /organization-users`
 * n'accepte pas de tri, la table n'en propose donc aucun.
 */
export function UserListPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')

  const { data, isPending, error, refetch } = useMemberList({
    page,
    perPage: 25,
    search: search || undefined,
  })

  const columns: Column<Member>[] = [
    {
      key: 'name',
      header: t('users.fields.name'),
      cell: (row) => (
        <Link
          to={`/users/${row.id}`}
          className="font-medium text-primary hover:underline"
          onClick={(event) => event.stopPropagation()}
        >
          {memberFullName(row)}
        </Link>
      ),
    },
    { key: 'email', header: t('users.fields.email'), cell: (row) => row.user.email },
    {
      key: 'roles',
      header: t('users.sections.roles'),
      hideOnMobile: true,
      cell: (row) =>
        row.roles.length === 0 ? (
          <span className="text-muted-foreground">—</span>
        ) : (
          <span className="flex flex-wrap gap-1">
            {row.roles.map((role) => (
              <Badge key={role.id} variant="secondary" className="font-normal">
                {role.name}
              </Badge>
            ))}
          </span>
        ),
    },
    {
      key: 'status',
      header: t('users.fields.status'),
      cell: (row) => <StatusBadge status={row.status} />,
    },
  ]

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('users.title')}
        description={t('users.subtitle')}
        actions={
          <PermissionGuard permission="users.create">
            <Button asChild>
              <Link to="/users/create">
                <Plus className="size-4" aria-hidden />
                {t('users.create')}
              </Link>
            </Button>
          </PermissionGuard>
        }
      />

      <SearchInput
        value={search}
        onChange={(value) => {
          setSearch(value)
          setPage(1)
        }}
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
        onRowClick={(row) => void navigate(`/users/${row.id}`)}
      />
    </div>
  )
}
