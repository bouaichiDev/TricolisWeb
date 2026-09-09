import { CalendarRange, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { PERIOD_PRESETS, isPreset, periodFrom, presetPeriod } from '../utils/period'
import type { DashboardPeriod } from '../types/dashboard'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

interface DashboardPeriodBarProps {
  period: DashboardPeriod | null
  onChange: (period: DashboardPeriod | null) => void
}

/**
 * Le filtre de dates du tableau de bord.
 *
 * **Il ne s'applique pas à tout**, et l'écran le dit deux fois : ici par une
 * phrase, plus bas par deux sections. C'est la seule façon honnête de le
 * présenter — la moitié des cartes portent un état, et un état n'a pas
 * d'intervalle : « commandes à planifier » ne veut rien dire filtré sur août.
 * Un filtre qui aurait l'air de tout couvrir aurait fait lire des compteurs
 * inchangés comme des compteurs filtrés, ce que rien ne signale.
 *
 * Les bornes ne partent qu'**ensemble**. Une période à moitié saisie n'est pas
 * une période : le serveur la refuse, et compléter la borne manquante ici
 * aurait filtré sur un intervalle que personne n'a choisi. Tant que la seconde
 * date manque, le tableau de bord reste celui d'avant.
 */
export function DashboardPeriodBar({ period, onChange }: DashboardPeriodBarProps) {
  const { t } = useTranslation()

  const patch = (bounds: Partial<DashboardPeriod>) =>
    onChange(periodFrom(bounds.from ?? period?.from ?? null, bounds.to ?? period?.to ?? null))

  return (
    <div className="flex flex-col gap-3 rounded-lg border bg-card p-4">
      <div className="flex flex-wrap items-end gap-3">
        <div className="flex items-center gap-2 pb-2 text-sm font-medium text-muted-foreground">
          <CalendarRange className="size-4" aria-hidden />
          {t('dashboard.period.label')}
        </div>

        <div className="flex flex-wrap gap-2 pb-1">
          {PERIOD_PRESETS.map((preset) => (
            <Button
              key={preset}
              type="button"
              size="sm"
              variant={isPreset(period, preset) ? 'default' : 'outline'}
              onClick={() => onChange(presetPeriod(preset))}
            >
              {t(`dashboard.period.presets.${preset}`)}
            </Button>
          ))}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="dashboard-period-from">{t('dashboard.period.from')}</Label>
          <Input
            id="dashboard-period-from"
            type="date"
            className="w-40"
            value={period?.from ?? ''}
            onChange={(event) => patch({ from: event.target.value })}
          />
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="dashboard-period-to">{t('dashboard.period.to')}</Label>
          <Input
            id="dashboard-period-to"
            type="date"
            className="w-40"
            value={period?.to ?? ''}
            onChange={(event) => patch({ to: event.target.value })}
          />
        </div>

        {period === null ? null : (
          <Button type="button" variant="ghost" size="sm" onClick={() => onChange(null)}>
            <X className="size-4" aria-hidden />
            {t('common.reset')}
          </Button>
        )}
      </div>

      <p className="text-xs text-muted-foreground">{t('dashboard.period.hint')}</p>
    </div>
  )
}
