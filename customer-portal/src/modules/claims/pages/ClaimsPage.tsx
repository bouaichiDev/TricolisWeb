import { Eye, Plus } from 'lucide-react'
import { Link } from 'react-router-dom'

import { Can } from '@/app/guards/Can'
import { paths } from '@/app/router/paths'
import { errorMessage } from '@/shared/api/errors'
import { DataTable } from '@/shared/components/data/DataTable'
import { RowActions } from '@/shared/components/data/RowActions'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { Button } from '@/shared/components/ui/button'

import { ClaimFiltersBar } from '../components/ClaimFiltersBar'
import { claimsColumns } from '../components/claimsColumns'
import { useClaimFilters } from '../hooks/useClaimFilters'
import { useClaims } from '../hooks/useClaims'

/** Les réclamations de l'entreprise (§8) : recherche, filtres, tri, pagination. */
export function ClaimsPage() {
  const { filters, update, toggleSort, reset } = useClaimFilters()
  const claims = useClaims(filters)

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title="Réclamations"
        description="Les réclamations de votre entreprise et leur suivi par votre transporteur."
        actions={
          <Can permission="customer.claims.create">
            <Button asChild>
              <Link to={paths.claimCreate}>
                <Plus className="size-4" aria-hidden /> Nouvelle réclamation
              </Link>
            </Button>
          </Can>
        }
      />

      <ClaimFiltersBar filters={filters} onChange={update} onReset={reset} />

      <DataTable
        columns={claimsColumns}
        rows={claims.data?.data ?? []}
        rowKey={(claim) => claim.id}
        meta={claims.data?.meta}
        isLoading={claims.isPending}
        errorMessage={claims.isError ? errorMessage(claims.error) : null}
        onRetry={() => void claims.refetch()}
        sort={filters.sort ?? 'createdAt'}
        direction={filters.direction ?? 'desc'}
        onSortChange={toggleSort}
        onPageChange={(page) => update({ page })}
        onPerPageChange={(perPage) => update({ perPage })}
        emptyMessage="Aucune réclamation pour le moment."
        actions={(claim) => <RowActions actions={[{ key: 'view', icon: Eye, label: 'Consulter', to: paths.claim(claim.id) }]} />}
      />
    </div>
  )
}
