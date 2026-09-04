import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { useStatusList } from '@/modules/statuses/hooks/useStatuses'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { useAuth } from '@/shared/hooks/useAuth'

interface StatusFromReferentialProps {
  /** Alias de morph map : `claim`, `order_communication`… */
  source: string
  label: string
  value: string
  onChange: (code: string) => void
  enabled?: boolean
  required?: boolean
}

/**
 * Sélecteur de statut alimenté par le référentiel.
 *
 * Le vocabulaire des statuts vit en base, dans la table `statuses` : c'est un
 * administrateur plateforme qui le dessine. En dresser une liste ici la ferait
 * diverger au premier statut ajouté.
 *
 * `claim` n'a **aucun statut décrit** à ce jour — `StatusSeeder` sème depuis les
 * énumérations PHP, et il n'en existe pas pour les réclamations. Le sélecteur
 * est alors vide, et le dit : le renvoi vers l'écran Statuts n'est proposé qu'à
 * qui peut s'y rendre, la plateforme.
 */
export function StatusFromReferential({
  source,
  label,
  value,
  onChange,
  enabled = true,
  required = false,
}: StatusFromReferentialProps) {
  const { t } = useTranslation()
  const { isPlatformAdmin } = useAuth()

  const referential = useStatusList(
    { page: 1, perPage: 100, source, active: true, sort: 'position', direction: 'asc' },
    enabled,
  )

  const statuses = referential.data?.data ?? []
  const empty = !referential.isPending && statuses.length === 0

  return (
    <div className="flex flex-col gap-1">
      <AsyncSelect
        label={label}
        value={value}
        onChange={onChange}
        options={statuses.map((item) => ({ value: item.code, label: item.label }))}
        isLoading={referential.isPending}
        required={required}
      />

      {empty ? (
        <p className="text-xs text-muted-foreground">
          {isPlatformAdmin ? (
            <>
              {t('claims.noStatus')}{' '}
              <Link to="/statuses" className="underline">
                {t('nav.statuses')}
              </Link>
            </>
          ) : (
            t('claims.noStatusMember')
          )}
        </p>
      ) : null}
    </div>
  )
}
