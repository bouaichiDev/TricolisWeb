import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { organizationsApi } from '@/modules/organizations/api/organizations.api'
import {
  planningSettingKeys,
  useOrganizationSettings,
} from '@/modules/planning/hooks/usePlanningSettings'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { useAuth } from '@/shared/hooks/useAuth'

type StockSettings = { stock?: { adjustmentMovementType?: string } } & Record<string, unknown>

/**
 * Type du mouvement créé par l'acceptation d'une demande de correction
 * (`organizations.settings.stock.adjustmentMovementType`).
 *
 * Vide, le serveur retombe sur `adjustment` — la valeur par défaut documentée
 * dans `StockAdjustmentSettings`. Les autres réglages sont recopiés : un
 * `PATCH` remplace `settings` en entier.
 */
export function StockAdjustmentSettingsPanel() {
  const { t } = useTranslation()
  const { organizationId } = useAuth()
  const queryClient = useQueryClient()
  const current = useOrganizationSettings()
  const settings = (current.data?.settings ?? {}) as StockSettings
  const saved = settings.stock?.adjustmentMovementType ?? ''
  const [draft, setDraft] = useState<string | null>(null)

  const save = useMutation({
    mutationFn: (type: string) =>
      organizationsApi.update(organizationId as string, {
        settings: { ...settings, stock: { ...settings.stock, adjustmentMovementType: type || undefined } },
      }),
    onSuccess: () => {
      setDraft(null)
      void queryClient.invalidateQueries({ queryKey: planningSettingKeys.all })
      void queryClient.invalidateQueries({ queryKey: ['organizations'] })
      toast.success(t('toast.updated'))
    },
  })

  if (current.isPending) {
    return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  }

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm text-muted-foreground">{t('stock.adjustments.settings.hint')}</p>
      <div className="flex flex-col gap-2 sm:max-w-sm">
        <Label htmlFor="adjustment-movement-type">{t('stock.adjustments.settings.movementType')}</Label>
        <Input
          id="adjustment-movement-type"
          value={draft ?? saved}
          maxLength={64}
          placeholder={t('stock.adjustments.settings.defaultValue')}
          onChange={(event) => setDraft(event.target.value)}
        />
      </div>
      <div>
        <Button
          type="button"
          disabled={draft === null || save.isPending}
          onClick={() => save.mutate((draft ?? '').trim())}
        >
          {save.isPending ? t('common.saving') : t('common.save')}
        </Button>
      </div>
    </div>
  )
}
