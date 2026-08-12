import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { PermissionPicker } from './PermissionPicker'
import { ROLE_FORM_DEFAULTS, roleSchema, type RoleFormValues } from '../schemas/roleSchema'
import { ROLE_STATUSES } from '../types/role'
import { usePermissions } from '@/shared/hooks/usePermission'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'
import { DetailField } from '@/shared/components/layout/DetailField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

interface RoleFormProps {
  defaultValues?: Partial<RoleFormValues>
  defaultPermissionIds?: string[]
  onSubmit: (values: RoleFormValues, permissionIds: string[]) => Promise<unknown>
  onCancel: () => void
  submitLabel: string
  lockCode?: boolean
  /** Nom de l'organisation d'accueil, affiché en lecture. */
  organizationName?: string
}

/**
 * Formulaire de rôle.
 *
 * La **portée** et le **drapeau système** ne sont pas saisissables. Un rôle créé
 * ici s'applique à l'organisation active et n'est pas système : l'API l'impose,
 * et proposer un champ laisserait croire à un choix qui n'existe pas. Le
 * formulaire proposait auparavant une portée libre — un administrateur pouvait y
 * écrire « platform » et le backend l'enregistrait.
 *
 * Les deux valeurs restent affichées, en lecture : les taire priverait
 * l'utilisateur d'une information utile.
 *
 * L'attribution des permissions a sa propre permission côté API,
 * `roles.assign_permissions`, distincte de `roles.update` : sans elle le bloc
 * est affiché en lecture, plutôt que de laisser envoyer un `permissionIds` que
 * l'API rejetterait.
 */
export function RoleForm({
  defaultValues,
  defaultPermissionIds = [],
  onSubmit,
  onCancel,
  submitLabel,
  lockCode = false,
  organizationName,
}: RoleFormProps) {
  const { t } = useTranslation()
  const { has } = usePermissions()
  const canAssign = has('roles.assign_permissions')

  const [permissionIds, setPermissionIds] = useState<string[]>(defaultPermissionIds)

  const form = useForm<RoleFormValues>({
    resolver: zodResolver(roleSchema),
    defaultValues: { ...ROLE_FORM_DEFAULTS, ...defaultValues },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()
    try {
      await onSubmit(values, permissionIds)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard title={t('roles.sections.general')}>
        <div className="grid gap-5 sm:grid-cols-2">
          <TextField
            form={form}
            name="code"
            label={t('roles.fields.code')}
            required
            disabled={lockCode}
            description={lockCode ? t('roles.codeLocked') : undefined}
          />
          <TextField form={form} name="name" label={t('roles.fields.name')} required />
          <StatusSelect
            form={form}
            name="status"
            label={t('roles.fields.status')}
            options={ROLE_STATUSES}
          />
        </div>

        <dl className="mt-6 grid gap-x-8 border-t pt-4 sm:grid-cols-2">
          <DetailField label={t('roles.organizationField')}>{organizationName}</DetailField>
          <DetailField label={t('roles.scopeReadOnly')}>
            {t('roles.scopeOrganization')}
          </DetailField>
        </dl>
        <p className="mt-2 text-xs text-muted-foreground">{t('roles.scopeFixed')}</p>
      </SectionCard>

      <SectionCard
        title={t('roles.sections.permissions')}
        description={canAssign ? t('roles.permissionsDelegable') : t('roles.permissionsReadOnly')}
      >
        <PermissionPicker
          selected={permissionIds}
          onChange={setPermissionIds}
          disabled={!canAssign}
        />
      </SectionCard>

      <FormActions
        onCancel={onCancel}
        submitLabel={submitLabel}
        isSubmitting={form.formState.isSubmitting}
      />
    </form>
  )
}
