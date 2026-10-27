import { UserPlus } from 'lucide-react'
import { useState } from 'react'

import { Can } from '@/app/guards/Can'
import { errorMessage } from '@/shared/api/errors'
import type { MemberStatus } from '@/shared/api/types'
import { DataTable } from '@/shared/components/data/DataTable'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select'

import { InviteUserDialog } from '../components/InviteUserDialog'
import { UserRowActions } from '../components/UserRowActions'
import { usersColumns } from '../components/usersColumns'
import { useUsers } from '../hooks/useUsers'
import { STATUS_OPTIONS } from '../utils'

const ALL = '__all__'

export function UsersPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<MemberStatus | ''>('')
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState<number | undefined>(undefined)
  const [inviting, setInviting] = useState(false)
  const users = useUsers({ search, status, page, perPage })

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Utilisateurs"
        description="Les personnes de votre entreprise qui accèdent au portail."
        actions={
          <Can permission="customer.users.invite">
            <Button onClick={() => setInviting(true)}>
              <UserPlus className="size-4" aria-hidden /> Inviter
            </Button>
          </Can>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
        <SearchInput value={search} onChange={(value) => { setSearch(value); setPage(1) }} placeholder="Nom ou e-mail" />
        <Select value={status || ALL} onValueChange={(value) => { setStatus(value === ALL ? '' : (value as MemberStatus)); setPage(1) }}>
          <SelectTrigger className="w-full sm:w-48" aria-label="Filtrer par statut">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Tous les statuts</SelectItem>
            {STATUS_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <DataTable
        columns={usersColumns}
        rows={users.data?.data ?? []}
        rowKey={(member) => member.id}
        meta={users.data?.meta}
        isLoading={users.isPending}
        errorMessage={users.isError ? errorMessage(users.error) : null}
        onRetry={() => void users.refetch()}
        onPageChange={setPage}
        onPerPageChange={(size) => { setPerPage(size); setPage(1) }}
        emptyMessage="Aucun utilisateur ne correspond."
        actions={(member) => <UserRowActions member={member} />}
      />

      <InviteUserDialog open={inviting} onOpenChange={setInviting} />
    </div>
  )
}
