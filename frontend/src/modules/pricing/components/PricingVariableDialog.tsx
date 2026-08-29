import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { usePricingVariableSources, useSavePricingVariable } from '../hooks/usePricing'
import type { PricingVariable } from '../types/pricing'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { Switch } from '@/shared/components/ui/switch'
import { useApiMessage } from '@/shared/hooks/useApiMessage'

interface PricingVariableDialogProps {
  variable: PricingVariable | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Déclarer une variable, et dire d'où elle sort.
 *
 * **La source se choisit dans une liste, pas se saisit.** Une table et une
 * colonne libres n'auraient ni chemin depuis la prestation, ni garantie sur ce
 * qu'elles exposent — un jeton, un mot de passe. Le serveur déclare ce qu'il
 * sait lire ; le superadmin choisit lesquelles deviennent des variables et sous
 * quel nom.
 *
 * **Le genre suit la source, il ne se choisit pas.** Un code postal ne devient
 * pas multipliable parce qu'on le déclarerait numérique.
 */
export function PricingVariableDialog({
  variable,
  open,
  onOpenChange,
}: PricingVariableDialogProps) {
  const { t } = useTranslation()
  const sources = usePricingVariableSources(open)
  const save = useSavePricingVariable()
  const failure = useApiMessage(save.error)

  const [code, setCode] = useState(variable?.code ?? '')
  const [label, setLabel] = useState(variable?.label ?? '')
  const [sourceKey, setSourceKey] = useState(variable?.sourceKey ?? '')
  const [unit, setUnit] = useState(variable?.unit ?? '')
  const [position, setPosition] = useState(String(variable?.position ?? 100))
  const [isActive, setIsActive] = useState(variable?.isActive ?? true)

  const chosen = (sources.data ?? []).find((source) => source.key === sourceKey)
  const ready = /^[a-z][a-z0-9_]*$/.test(code) && label.trim() !== '' && sourceKey !== ''

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {variable ? t('pricing.variables.edit') : t('pricing.variables.create')}
          </DialogTitle>
          <DialogDescription>{t('pricing.variables.hint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={failure} />

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <Label htmlFor="variable-code">{t('pricing.variables.fields.code')}</Label>
            <Input
              id="variable-code"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              placeholder="poids"
              className="font-mono"
              required
            />
            <p className="text-xs text-muted-foreground">{t('pricing.variables.codeHint')}</p>
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="variable-label">{t('pricing.variables.fields.label')}</Label>
            <Input
              id="variable-label"
              value={label}
              onChange={(event) => setLabel(event.target.value)}
              required
            />
          </div>

          <div className="sm:col-span-2">
            <AsyncSelect
              label={t('pricing.variables.fields.source')}
              value={sourceKey}
              onChange={setSourceKey}
              options={(sources.data ?? []).map((source) => ({
                value: source.key,
                label: source.label,
                hint: `${source.table}.${source.column}`,
              }))}
              isLoading={sources.isPending}
              description={
                chosen
                  ? t('pricing.variables.sourceChosen', {
                      table: chosen.table,
                      column: chosen.column,
                      kind: t(`pricing.variables.kinds.${chosen.kind}`),
                    })
                  : undefined
              }
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="variable-unit">{t('pricing.variables.fields.unit')}</Label>
            <Input
              id="variable-unit"
              value={unit}
              onChange={(event) => setUnit(event.target.value)}
              placeholder="kg"
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="variable-position">{t('pricing.variables.fields.position')}</Label>
            <Input
              id="variable-position"
              type="number"
              min={0}
              value={position}
              onChange={(event) => setPosition(event.target.value)}
            />
          </div>

          <div className="flex items-center gap-3 sm:col-span-2">
            <Switch id="variable-active" checked={isActive} onCheckedChange={setIsActive} />
            <Label htmlFor="variable-active">{t('pricing.variables.fields.isActive')}</Label>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button
            disabled={!ready || save.isPending}
            onClick={() =>
              save.mutate(
                {
                  id: variable?.id,
                  payload: {
                    code: code.trim(),
                    label: label.trim(),
                    sourceKey,
                    unit: unit.trim() || null,
                    position: Number.parseInt(position, 10) || 100,
                    isActive,
                  },
                },
                { onSuccess: () => onOpenChange(false) },
              )
            }
          >
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
