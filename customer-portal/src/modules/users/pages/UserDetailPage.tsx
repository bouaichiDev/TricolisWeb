import { useParams } from 'react-router-dom'

import { useAuth } from '@/app/auth/useAuth'
import { ApiError, errorMessage } from '@/shared/api/errors'
import { StatusBadge } from '@/shared/components/data/StatusBadge'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { DetailField } from '@/shared/components/layout/DetailField'
import { EntityHeader } from '@/shared/components/layout/EntityHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Badge } from '@/shared/components/ui/badge'
import { formatDateTime } from '@/shared/utils/format'

import { UserActions } from '../components/UserActions'
import { useUser } from '../hooks/useUsers'
import { displayName } from '../utils'

export function UserDetailPage() {
  const { id = '' } = useParams()
  const { context } = useAuth()
  const member = useUser(id)

  if (member.isPending) return <InlineLoader />

  if (member.isError) {
    const notFound = member.error instanceof ApiError && member.error.isNotFound

    return (
      <ErrorState
        title={notFound ? 'Utilisateur introuvable' : undefined}
        message={notFound ? 'Cet utilisateur n’existe pas dans votre entreprise.' : errorMessage(member.error)}
      />
    )
  }

  const user = member.data

  return (
    <div className="flex flex-col gap-6">
      <EntityHeader
        title={displayName(user)}
        subtitle={user.user.email}
        status={<StatusBadge code={user.status} label={user.statusLabel} />}
        actions={<UserActions member={user} isSelf={context?.membership.id === user.id} />}
      />

      <SectionCard title="Compte">
        <dl className="grid gap-x-8 sm:grid-cols-3">
          <DetailField label="Téléphone">{user.user.phone}</DetailField>
          <DetailField label="Invité le">{user.invitedAt && formatDateTime(user.invitedAt)}</DetailField>
          <DetailField label="Activé le">{user.activatedAt && formatDateTime(user.activatedAt)}</DetailField>
          <DetailField label="Dernière connexion">{user.user.lastLoginAt && formatDateTime(user.user.lastLoginAt)}</DetailField>
          <DetailField label="Propriétaire">{user.isOwner ? 'Oui' : 'Non'}</DetailField>
          {user.disabledAt && <DetailField label="Désactivé le">{formatDateTime(user.disabledAt)}</DetailField>}
        </dl>
      </SectionCard>

      <SectionCard title="Rôles" description="Les permissions de l’utilisateur sont l’union de ses rôles.">
        {user.roles.length === 0 ? (
          <p className="text-muted-foreground text-sm">Aucun rôle : cet utilisateur ne voit rien.</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {user.roles.map((role) => (
              <Badge key={role.id} variant="outline" className="font-normal">
                {role.name}
              </Badge>
            ))}
          </div>
        )}
      </SectionCard>
    </div>
  )
}
