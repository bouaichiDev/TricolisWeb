import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { MemberCreateForm } from '../components/MemberCreateForm'
import { useCreateMember } from '../hooks/useMembers'
import { phoneOrNull } from '../schemas/memberSchema'
import { PageHeader } from '@/shared/components/layout/PageHeader'

export function UserCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const create = useCreateMember()

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title={t('users.create')} description={t('users.createHint')} />

      <MemberCreateForm
        onCancel={() => void navigate('/users')}
        onSubmit={async (values, roleIds) => {
          const member = await create.mutateAsync({
            firstName: values.firstName.trim(),
            lastName: values.lastName.trim(),
            email: values.email.trim(),
            phone: phoneOrNull(values.phone),
            password: values.password,
            // Laravel attend `password_confirmation` pour la règle `confirmed`.
            password_confirmation: values.passwordConfirmation,
            preferredLanguage: values.preferredLanguage.trim(),
            isOwner: values.isOwner,
            isPrimary: values.isPrimary,
            status: values.status,
            roleIds,
          })
          void navigate(`/users/${member.id}`)
        }}
      />
    </div>
  )
}
