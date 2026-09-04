import { useTranslation } from 'react-i18next'

import type { RoleDashboardWidget } from '../types/dashboard'
import { Badge } from '@/shared/components/ui/badge'
import { Switch } from '@/shared/components/ui/switch'

interface RoleDashboardRowProps {
  widget: RoleDashboardWidget
  isEnabled: boolean
  disabled: boolean
  onToggle: () => void
}

/**
 * Une ligne du catalogue : ce que le widget montre, et s'il est possible.
 *
 * Quand le rôle n'a pas la permission requise, l'interrupteur est **désactivé**
 * et la permission écrite en toutes lettres. Ni masquée, ni accordée :
 *
 * - la masquer aurait laissé croire que le widget n'existe pas, alors qu'il ne
 *   manque qu'un droit — et l'onglet qui l'accorde est juste à côté ;
 * - l'accorder depuis ici aurait fait de la composition d'un tableau de bord
 *   une voie d'élévation, ouverte à qui détient `dashboard.configure`.
 *
 * Le nom de la permission est donné brut — `invoices.view` — plutôt que
 * traduit : c'est ce que l'administrateur va chercher dans la liste des
 * permissions du rôle, et un libellé français l'obligerait à deviner lequel.
 */
export function RoleDashboardRow({ widget, isEnabled, disabled, onToggle }: RoleDashboardRowProps) {
  const { t } = useTranslation()

  return (
    <li className="flex items-start justify-between gap-4 px-4 py-3">
      <div className="flex min-w-0 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm font-medium">{t(widget.labelKey)}</span>
          <Badge variant="secondary" className="font-normal">
            {t(`dashboardWidgetTypes.${widget.type}`)}
          </Badge>
        </div>

        <p className="text-sm text-muted-foreground">{t(widget.descriptionKey)}</p>

        {widget.availableForRole ? null : (
          <p className="text-sm text-warning">
            {t('dashboardSettings.permissionRequired', { permission: widget.requiredPermission })}
          </p>
        )}
      </div>

      <Switch
        checked={isEnabled}
        disabled={disabled || !widget.availableForRole}
        onCheckedChange={onToggle}
        aria-label={t(widget.labelKey)}
      />
    </li>
  )
}
