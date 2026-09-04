import { AlertTriangle, Check } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useServiceList } from '@/modules/services/hooks/useServices'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Label } from '@/shared/components/ui/label'

import { useOrganizationSettings, useUpdateLoadingServiceCodes } from '../hooks/usePlanningSettings'

/**
 * Quels services de l'organisation sont des chargements.
 *
 * Le serveur les reconnaît **par code**, et les codes vivent dans les réglages
 * de l'organisation. Cet écran les coche depuis la liste réelle des services :
 * saisir le code à la main laisserait passer une faute de frappe que rien ne
 * signalerait avant que le regroupement au dépôt ne s'arrête.
 *
 * Un code réglé qui ne correspond plus à aucun service est montré à part —
 * c'est ce qui arrive quand un service est renommé après coup.
 */
export function LoadingServicesPanel() {
  const { t } = useTranslation()

  const settings = useOrganizationSettings()
  const services = useServiceList({ page: 1, perPage: 100 })
  const save = useUpdateLoadingServiceCodes()

  const [draft, setDraft] = useState<string[] | null>(null)

  const saved = useMemo(
    () => (settings.data?.settings?.planning?.loadingServiceCodes ?? []).map((c) => c.toUpperCase()),
    [settings.data],
  )

  const codes = draft ?? saved
  const rows = services.data?.data ?? []

  /** Codes réglés qui ne correspondent à aucun service : un renommage, ou une faute. */
  const unmatched = codes.filter(
    (code) => !rows.some((service) => service.code.toUpperCase() === code),
  )

  const toggle = (code: string) => {
    const upper = code.toUpperCase()
    setDraft(
      codes.includes(upper) ? codes.filter((c) => c !== upper) : [...codes, upper],
    )
  }

  if (settings.isPending || services.isPending) {
    return <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('planning.loadingServicesHint')}</p>

      {rows.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('planning.noService')}</p>
      ) : (
        <ul className="flex flex-col gap-1">
          {rows.map((service) => {
            const checked = codes.includes(service.code.toUpperCase())

            return (
              <li key={service.id} className="flex items-center gap-3 rounded-md border px-3 py-2">
                <Checkbox
                  id={`loading-${service.id}`}
                  checked={checked}
                  onCheckedChange={() => toggle(service.code)}
                />
                <Label htmlFor={`loading-${service.id}`} className="flex-1 cursor-pointer">
                  <span className="font-medium">{service.name}</span>{' '}
                  <span className="text-xs text-muted-foreground">{service.code}</span>
                </Label>
                {checked ? (
                  <Badge variant="outline" className="gap-1">
                    <Check className="size-3" aria-hidden />
                    {t('planning.isLoading')}
                  </Badge>
                ) : null}
              </li>
            )
          })}
        </ul>
      )}

      {unmatched.length === 0 ? null : (
        <Alert>
          <AlertTriangle className="size-4" aria-hidden />
          <AlertDescription>
            {t('planning.unmatchedCodes', { codes: unmatched.join(', ') })}
          </AlertDescription>
        </Alert>
      )}

      <div className="flex items-center gap-3">
        <Button
          type="button"
          disabled={draft === null || save.isPending}
          onClick={() => {
            if (draft === null) return
            save.mutate(draft, { onSuccess: () => setDraft(null) })
          }}
        >
          {save.isPending ? t('common.saving') : t('common.save')}
        </Button>

        {draft === null ? null : (
          <Button type="button" variant="ghost" onClick={() => setDraft(null)}>
            {t('common.cancel')}
          </Button>
        )}
      </div>
    </div>
  )
}
