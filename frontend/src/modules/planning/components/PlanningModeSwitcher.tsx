import { Map, Plus, Rows3 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { Button } from '@/shared/components/ui/button'

export type PlanningMode = 'panels' | 'map'

/**
 * Les deux lectures de la planification, et la création d'une tournée.
 *
 * Ce ne sont pas deux écrans : le §73 impose qu'ils travaillent sur les mêmes
 * brouillons. Le bouton de création les accompagne parce que sans brouillon,
 * aucune des deux vues ne peut rien recevoir.
 */
export function PlanningModeSwitcher({
  mode,
  onChange,
}: {
  mode: PlanningMode
  onChange: (mode: PlanningMode) => void
}) {
  const { t } = useTranslation()

  return (
    <span className="flex items-center gap-1">
      <Button
        type="button"
        variant={mode === 'panels' ? 'secondary' : 'ghost'}
        size="icon"
        title={t('planning.modePanels')}
        aria-label={t('planning.modePanels')}
        aria-pressed={mode === 'panels'}
        onClick={() => onChange('panels')}
      >
        <Rows3 className="size-4" aria-hidden />
      </Button>
      <Button
        type="button"
        variant={mode === 'map' ? 'secondary' : 'ghost'}
        size="icon"
        title={t('planning.modeMap')}
        aria-label={t('planning.modeMap')}
        aria-pressed={mode === 'map'}
        onClick={() => onChange('map')}
      >
        <Map className="size-4" aria-hidden />
      </Button>

      <PermissionGuard permission="tours.create">
        <Button asChild className="ml-2">
          <Link to="/tours/create">
            <Plus className="size-4" aria-hidden />
            {t('tours.create')}
          </Link>
        </Button>
      </PermissionGuard>
    </span>
  )
}
