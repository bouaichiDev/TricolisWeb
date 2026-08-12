import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { MemberIdentityFields } from './MemberIdentityFields'
import { RoleAssignment } from './RoleAssignment'
import {
  MEMBER_UPDATE_DEFAULTS,
  memberUpdateSchema,
  type MemberUpdateValues,
} from '../schemas/memberSchema'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'
import { usePermissions } from '@/shared/hooks/usePermission'

interface MemberEditFormProps {
  defaultValues: MemberUpdateValues
  defaultRoleIds: string[]
  email: string
  onSubmit: (values: MemberUpdateValues, roleIds: string[]) => Promise<unknown>
  onCancel: () => void
}

/**
 * Modification d'un membre.
 *
 * L'email n'est pas modifiable ici : `UpdateOrganizationUserRequest` ne
 * l'accepte pas, il relève de `PATCH /users/{id}`. Il est affiché en lecture
 * plutôt que masqué, pour qu'on sache de quel compte il s'agit.
 */
export function MemberEditForm({
  defaultValues,
  defaultRoleIds,
  email,
  onSubmit,
  onCancel,
}: MemberEditFormProps) {
  const { t } = useTranslation()
  const { has } = usePermissions()
  const canAssignRoles = has('users.assign_roles')
  const [roleIds, setRoleIds] = useState<string[]>(defaultRoleIds)

  const form = useForm<MemberUpdateValues>({
    resolver: zodResolver(memberUpdateSchema),
    defaultValues: { ...MEMBER_UPDATE_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values, roleIds)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('users.sections.identity')} description={email}>
        <MemberIdentityFields form={form} />
      </SectionCard>

      {canAssignRoles ? (
        <SectionCard title={t('users.sections.roles')} description={t('users.rolesHint')}>
          <RoleAssignment selected={roleIds} onChange={setRoleIds} />
        </SectionCard>
      ) : null}

      <FormActions
        onCancel={onCancel}
        submitLabel={t('common.save')}
        isSubmitting={form.formState.isSubmitting}
      />
    </form>
  )
}
