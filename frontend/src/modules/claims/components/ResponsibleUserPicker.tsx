import { useTranslation } from 'react-i18next'

import { useMemberList } from '@/modules/users/hooks/useMembers'
import { memberFullName } from '@/modules/users/types/member'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

import { NO_USER } from '../schemas/claimForm'

interface ResponsibleUserPickerProps {
  value: string
  onChange: (userId: string) => void
}

/**
 * Personne chargée d'instruire la réclamation.
 *
 * `responsibleUserId` est accepté **dès la création** par `StoreClaimRequest` :
 * affecter tout de suite évite qu'une réclamation reste sans personne pour la
 * traiter. Il reste facultatif — le serveur l'accepte nul.
 *
 * Les membres de l'organisation active, et eux seuls : la liste vient de
 * `organization-users`, portée par l'en-tête `X-Organization-Id`.
 */
export function ResponsibleUserPicker({ value, onChange }: ResponsibleUserPickerProps) {
  const { t } = useTranslation()
  const members = useMemberList({ page: 1, perPage: 100 })

  return (
    <AsyncSelect
      label={t('claims.fields.responsible')}
      value={value}
      onChange={onChange}
      options={[
        { value: NO_USER, label: t('claims.noResponsible') },
        ...(members.data?.data ?? []).map((member) => ({
          value: member.userId,
          label: memberFullName(member),
          hint: member.user.email,
        })),
      ]}
      isLoading={members.isPending}
      description={t('claims.responsibleHint')}
    />
  )
}
