import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'

import { MemberEditForm } from '../components/MemberEditForm'
import { useMember, useUpdateMember } from '../hooks/useMembers'
import { phoneOrNull } from '../schemas/memberSchema'
import { memberFullName } from '../types/member'
import { ErrorState } from '@/shared/components/feedback/ErrorState'
import { DetailSkeleton } from '@/shared/components/feedback/LoadingSkeleton'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function UserEditPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { id = '' } = useParams<{ id: string }>()

  const { data: member, isPending, error, refetch } = useMember(id)
  const update = useUpdateMember(id)

  if (isPending) return <DetailSkeleton />
  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  if (!member) return null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={memberFullName(member)} description={t('users.edit')} />

      <MemberEditForm
        email={member.user.email}
        defaultValues={{
          firstName: member.user.firstName,
          lastName: member.user.lastName,
          phone: member.user.phone ?? '',
          preferredLanguage: member.user.preferredLanguage ?? 'fr',
          isOwner: member.isOwner,
          isPrimary: member.isPrimary,
          status: member.status,
        }}
        defaultRoleIds={member.roles.map((role) => role.id)}
        onCancel={() => void navigate(`/users/${id}`)}
        onSubmit={async (values, roleIds) => {
          await update.mutateAsync({
            firstName: values.firstName.trim(),
            lastName: values.lastName.trim(),
            phone: phoneOrNull(values.phone),
            preferredLanguage: values.preferredLanguage.trim(),
            isOwner: values.isOwner,
            isPrimary: values.isPrimary,
            status: values.status,
            roleIds,
          })
          void navigate(`/users/${id}`)
        }}
      />
    </div>
  )
}
