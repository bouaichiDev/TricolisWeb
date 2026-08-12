import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { MemberIdentityFields } from './MemberIdentityFields'
import { RoleAssignment } from './RoleAssignment'
import {
  MEMBER_CREATE_DEFAULTS,
  memberCreateSchema,
  type MemberCreateValues,
} from '../schemas/memberSchema'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'
import { usePermissions } from '@/shared/hooks/usePermission'

interface MemberCreateFormProps {
  onSubmit: (values: MemberCreateValues, roleIds: string[]) => Promise<unknown>
  onCancel: () => void
}

/**
 * Création d'un membre.
 *
 * L'API crée le compte et son rattachement dans la même transaction : un compte
 * sans rattachement serait inatteignable. Le mot de passe initial est donc
 * obligatoire ici, alors qu'il ne se modifie plus ensuite depuis cet écran.
 */
export function MemberCreateForm({ onSubmit, onCancel }: MemberCreateFormProps) {
  const { t } = useTranslation()
  const { has } = usePermissions()
  const canAssignRoles = has('users.assign_roles')
  const [roleIds, setRoleIds] = useState<string[]>([])

  const form = useForm<MemberCreateValues>({
    resolver: zodResolver(memberCreateSchema),
    defaultValues: MEMBER_CREATE_DEFAULTS,
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

      <SectionCard title={t('users.sections.identity')}>
        <MemberIdentityFields form={form} />
      </SectionCard>

      <SectionCard title={t('users.sections.account')} description={t('users.accountHint')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="email"
            label={t('users.fields.email')}
            type="email"
            required
          />
          <div className="hidden sm:block" />
          <TextField
            form={form}
            name="password"
            label={t('users.fields.password')}
            type="password"
            required
          />
          <TextField
            form={form}
            name="passwordConfirmation"
            label={t('users.fields.passwordConfirmation')}
            type="password"
            required
          />
        </div>
      </SectionCard>

      {canAssignRoles ? (
        <SectionCard title={t('users.sections.roles')} description={t('users.rolesHint')}>
          <RoleAssignment selected={roleIds} onChange={setRoleIds} />
        </SectionCard>
      ) : null}

      <FormActions
        onCancel={onCancel}
        submitLabel={t('common.create')}
        isSubmitting={form.formState.isSubmitting}
      />
    </form>
  )
}
