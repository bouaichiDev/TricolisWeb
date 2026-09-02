import { useTranslation } from 'react-i18next'

import { Label } from '@/shared/components/ui/label'
import { Switch } from '@/shared/components/ui/switch'

import { useOrganizationSettings, useUpdateAutoCreateLoading } from '../hooks/usePlanningSettings'

/**
 * Créer le chargement manquant au moment de planifier.
 *
 * Une livraison sans chargement décrit un camion qui part chargé sans que
 * personne ne l'ait chargé : le temps de quai n'apparaît nulle part, et la
 * tournée s'annonce plus courte qu'elle ne sera. Les saisir un par un est le
 * genre d'oubli qu'on ne découvre qu'au dépôt, le matin.
 *
 * **Réglable par organisation, et coupé par défaut.** Toutes ne travaillent pas
 * ainsi : un transporteur d'enlèvements n'a pas de quai à ouvrir, et lui
 * imposer un chargement fictif fausserait ses tournées dans l'autre sens.
 *
 * L'interrupteur enregistre au basculement, sans bouton : une option qu'on
 * croit active parce qu'on l'a cochée sans enregistrer est pire que pas
 * d'option du tout.
 */
export function AutoLoadingServicePanel() {
  const { t } = useTranslation()

  const settings = useOrganizationSettings()
  const save = useUpdateAutoCreateLoading()

  const enabled = settings.data?.settings?.planning?.autoCreateLoadingService === true

  return (
    <div className="flex items-start justify-between gap-4 rounded-md border p-3">
      <div className="min-w-0">
        <Label htmlFor="auto-loading" className="cursor-pointer font-medium">
          {t('planning.autoLoading')}
        </Label>
        <p className="mt-1 text-xs text-muted-foreground">{t('planning.autoLoadingHint')}</p>
        {/* Ce que l'option coute quand elle ne peut pas tenir sa promesse : le
            dire ici evite de decouvrir le refus une commande a la main. */}
        <p className="mt-1 text-xs text-muted-foreground">{t('planning.autoLoadingDepotHint')}</p>
      </div>

      <Switch
        id="auto-loading"
        checked={enabled}
        disabled={settings.isPending || save.isPending}
        onCheckedChange={(next) => save.mutate(next)}
        aria-label={t('planning.autoLoading')}
      />
    </div>
  )
}
