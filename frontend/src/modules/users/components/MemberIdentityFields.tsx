import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

import { USER_STATUSES } from '../types/member'
import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { StatusSelect } from '@/shared/components/form/StatusSelect'
import { TextField } from '@/shared/components/form/TextField'

/**
 * Champs partagés par la création et la modification d'un membre.
 *
 * `isOwner` et `isPrimary` portent des conséquences réelles : le propriétaire
 * contourne les vérifications de permission, et l'organisation principale est
 * celle proposée à la connexion. Les libellés le disent.
 */
export function MemberIdentityFields<T extends FieldValues>({
  form,
}: {
  form: UseFormReturn<T>
}) {
  const { t } = useTranslation()
  const field = (name: string) => name as Path<T>

  return (
    <div className="grid gap-5 sm:grid-cols-2">
      <TextField form={form} name={field('firstName')} label={t('users.fields.firstName')} required />
      <TextField form={form} name={field('lastName')} label={t('users.fields.lastName')} required />
      <TextField form={form} name={field('phone')} label={t('users.fields.phone')} />
      <TextField
        form={form}
        name={field('preferredLanguage')}
        label={t('users.fields.preferredLanguage')}
        placeholder="fr"
        required
      />
      <StatusSelect
        form={form}
        name={field('status')}
        label={t('users.fields.status')}
        options={USER_STATUSES}
      />
      <div className="flex flex-col gap-2 sm:col-span-2">
        <CheckboxField
          form={form}
          name={field('isOwner')}
          label={t('users.fields.isOwner')}
          description={t('users.ownerHint')}
        />
        <CheckboxField
          form={form}
          name={field('isPrimary')}
          label={t('users.fields.isPrimary')}
          description={t('users.primaryHint')}
        />
      </div>
    </div>
  )
}
