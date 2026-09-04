import { Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useSaveMatrix } from '../hooks/usePricing'
import { MATCH_MODES, type PriceMatrix, type PriceRule } from '../types/pricing'
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
import { useApiMessage } from '@/shared/hooks/useApiMessage'

interface PriceMatrixDialogProps {
  priceListId: string
  matrix: PriceMatrix | null
  /** Les règles du barème : une zone ne peut désigner qu'elles. */
  rules: PriceRule[]
  open: boolean
  onOpenChange: (open: boolean) => void
}

interface ZoneDraft {
  label: string
  priceRuleId: string
  matchMode: string
  rangeFrom: string
  rangeTo: string
}

/**
 * Une matrice et ses zones.
 *
 * **La matrice ne calcule pas** : chaque zone désigne une règle, et c'est la
 * formule de cette règle qui produit le prix. L'écran montre donc la formule
 * de la règle choisie, pour qu'on voie ce qu'on vient d'attacher à la zone.
 *
 * Le mode de correspondance est explicite : `numeric` compare des bornes,
 * `prefix` et `exact` gardent les zéros de tête et les lettres — un code
 * postal n'est pas partout un entier.
 */
export function PriceMatrixDialog({
  priceListId,
  matrix,
  rules,
  open,
  onOpenChange,
}: PriceMatrixDialogProps) {
  const { t } = useTranslation()
  const save = useSaveMatrix(priceListId)
  const failure = useApiMessage(save.error)

  const [code, setCode] = useState(matrix?.code ?? '')
  const [name, setName] = useState(matrix?.name ?? '')
  const [zones, setZones] = useState<ZoneDraft[]>(
    (matrix?.rows ?? []).map((row) => ({
      label: row.label,
      priceRuleId: row.priceRuleId,
      matchMode: row.matchMode,
      rangeFrom: row.rangeFrom,
      rangeTo: row.rangeTo ?? '',
    })),
  )

  const ready =
    code.trim() !== '' &&
    name.trim() !== '' &&
    zones.length > 0 &&
    zones.every((zone) => zone.label.trim() !== '' && zone.rangeFrom.trim() !== '' && zone.priceRuleId !== '')

  const patch = (index: number, change: Partial<ZoneDraft>) => {
    const next = [...zones]
    next[index] = { ...next[index], ...change }
    setZones(next)
  }

  const submit = () => {
    save.mutate(
      {
        id: matrix?.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          rows: zones.map((zone, index) => ({
            label: zone.label.trim(),
            priceRuleId: zone.priceRuleId,
            matchMode: zone.matchMode,
            rangeFrom: zone.rangeFrom.trim(),
            rangeTo: zone.rangeTo.trim() || null,
            // L'ordre de saisie fait la priorite : la premiere zone ecrite est
            // la premiere consultee, ce qui se lit sans explication.
            priority: (index + 1) * 10,
          })),
        },
      },
      { onSuccess: () => onOpenChange(false) },
    )
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>
            {matrix ? t('pricing.matrices.edit') : t('pricing.matrices.create')}
          </DialogTitle>
          <DialogDescription>{t('pricing.matrices.hint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={failure} />

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <Label htmlFor="matrix-code">{t('pricing.matrices.fields.code')}</Label>
            <Input
              id="matrix-code"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              required
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="matrix-name">{t('pricing.matrices.fields.name')}</Label>
            <Input
              id="matrix-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
            />
          </div>
        </div>

        <div className="flex flex-col gap-3">
          <div className="flex items-center justify-between">
            <Label>{t('pricing.matrices.zones')}</Label>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() =>
                setZones([
                  ...zones,
                  {
                    label: '',
                    priceRuleId: rules[0]?.id ?? '',
                    matchMode: 'numeric',
                    rangeFrom: '',
                    rangeTo: '',
                  },
                ])
              }
            >
              {t('pricing.matrices.addZone')}
            </Button>
          </div>

          {zones.map((zone, index) => (
            <div key={index} className="flex flex-col gap-2 rounded-md border p-2">
              <div className="flex flex-wrap items-end gap-2">
                <Input
                  aria-label={t('pricing.matrices.fields.label')}
                  placeholder={t('pricing.matrices.fields.label')}
                  value={zone.label}
                  onChange={(event) => patch(index, { label: event.target.value })}
                  className="h-8 w-32"
                />

                <select
                  aria-label={t('pricing.matrices.fields.matchMode')}
                  value={zone.matchMode}
                  onChange={(event) => patch(index, { matchMode: event.target.value })}
                  className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                >
                  {MATCH_MODES.map((mode) => (
                    <option key={mode} value={mode}>
                      {t(`pricing.matchModes.${mode}`)}
                    </option>
                  ))}
                </select>

                <Input
                  aria-label={t('pricing.matrices.fields.rangeFrom')}
                  placeholder="1144"
                  value={zone.rangeFrom}
                  onChange={(event) => patch(index, { rangeFrom: event.target.value })}
                  className="h-8 w-24"
                />

                {zone.matchMode === 'numeric' ? (
                  <Input
                    aria-label={t('pricing.matrices.fields.rangeTo')}
                    placeholder="4000"
                    value={zone.rangeTo}
                    onChange={(event) => patch(index, { rangeTo: event.target.value })}
                    className="h-8 w-24"
                  />
                ) : null}

                <select
                  aria-label={t('pricing.matrices.fields.rule')}
                  value={zone.priceRuleId}
                  onChange={(event) => patch(index, { priceRuleId: event.target.value })}
                  className="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                >
                  {rules.map((rule) => (
                    <option key={rule.id} value={rule.id}>
                      {rule.code}
                    </option>
                  ))}
                </select>

                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label={t('common.delete')}
                  onClick={() => setZones(zones.filter((_, at) => at !== index))}
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </div>

              <code className="text-xs text-muted-foreground">
                {rules.find((rule) => rule.id === zone.priceRuleId)?.formula ?? ''}
              </code>
            </div>
          ))}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button disabled={!ready || save.isPending} onClick={submit}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
