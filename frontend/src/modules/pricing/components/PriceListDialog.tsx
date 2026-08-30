import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useCreatePriceList, useUpdatePriceList } from '../hooks/usePricing'
import type { PriceList } from '../types/pricing'
import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { Button } from '@/shared/components/ui/button'
import { Checkbox } from '@/shared/components/ui/checkbox'
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

interface PriceListDialogProps {
  scope: 'global' | 'customer'
  /** Absent : on crée. Présent : on corrige ce barème. */
  priceList?: PriceList | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Créer ou corriger un barème.
 *
 * **La portée ne se change jamais**, ni à la création ni après : elle est fixée
 * par l'écran d'où l'on vient, et basculer une liste client en globale
 * l'appliquerait d'un coup à toute la clientèle. Le serveur l'ignore de toute
 * façon sur une modification.
 *
 * Un barème client désigne ses clients : sans eux, il ne servirait personne.
 * Les retirer tous revient à le débrancher — c'est possible en modification,
 * parce qu'un barème peut cesser de s'appliquer sans qu'on veuille le
 * supprimer, mais l'écran le refuse à la création où ce serait une erreur.
 */
export function PriceListDialog({
  scope,
  priceList = null,
  open,
  onOpenChange,
}: PriceListDialogProps) {
  const { t } = useTranslation()
  const create = useCreatePriceList()
  const update = useUpdatePriceList()
  const failure = useApiMessage(create.error ?? update.error)
  const customers = useCustomerList({ page: 1, perPage: 100 })

  const editing = priceList !== null

  const [code, setCode] = useState(priceList?.code ?? '')
  const [name, setName] = useState(priceList?.name ?? '')
  const [validFrom, setValidFrom] = useState(priceList?.validFrom ?? '')
  const [validTo, setValidTo] = useState(priceList?.validTo ?? '')
  const [isActive, setIsActive] = useState(priceList?.isActive ?? true)
  const [chosen, setChosen] = useState<string[]>(
    (priceList?.customers ?? []).map((customer) => customer.id),
  )

  const ready =
    code.trim() !== '' &&
    name.trim() !== '' &&
    (scope === 'global' || editing || chosen.length > 0)

  const submit = () => {
    const payload = {
      code: code.trim(),
      name: name.trim(),
      validFrom: validFrom || null,
      validTo: validTo || null,
      isActive,
      customerIds: scope === 'customer' ? chosen : undefined,
    }

    if (editing) {
      update.mutate(
        { id: priceList.id, payload },
        { onSuccess: () => onOpenChange(false) },
      )

      return
    }

    create.mutate({ ...payload, scope }, { onSuccess: () => onOpenChange(false) })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {editing ? t('pricing.lists.edit') : t(`pricing.lists.create.${scope}`)}
          </DialogTitle>
          <DialogDescription>{t(`pricing.lists.hint.${scope}`)}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={failure} />

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-2">
            <Label htmlFor="list-code">{t('pricing.lists.fields.code')}</Label>
            <Input
              id="list-code"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              required
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="list-name">{t('pricing.lists.fields.name')}</Label>
            <Input
              id="list-name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="list-from">{t('pricing.lists.fields.validFrom')}</Label>
            <Input
              id="list-from"
              type="date"
              value={validFrom}
              onChange={(event) => setValidFrom(event.target.value)}
            />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="list-to">{t('pricing.lists.fields.validTo')}</Label>
            <Input
              id="list-to"
              type="date"
              value={validTo}
              onChange={(event) => setValidTo(event.target.value)}
            />
          </div>

          <div className="flex items-center gap-3 sm:col-span-2">
            <Switch id="list-active" checked={isActive} onCheckedChange={setIsActive} />
            <Label htmlFor="list-active">{t('pricing.lists.fields.isActive')}</Label>
            <span className="text-xs text-muted-foreground">
              {t('pricing.lists.activeHint')}
            </span>
          </div>
        </div>

        {scope === 'customer' ? (
          <div className="flex flex-col gap-2">
            <Label>{t('pricing.lists.fields.customers')}</Label>
            <ul className="flex max-h-48 flex-col gap-1 overflow-y-auto rounded-md border p-2">
              {(customers.data?.data ?? []).map((customer) => (
                <li key={customer.id} className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={chosen.includes(customer.id)}
                    onCheckedChange={() =>
                      setChosen(
                        chosen.includes(customer.id)
                          ? chosen.filter((id) => id !== customer.id)
                          : [...chosen, customer.id],
                      )
                    }
                    aria-label={customer.name}
                  />
                  {customer.name}
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button disabled={!ready || create.isPending || update.isPending} onClick={submit}>
            {editing ? t('common.save') : t('common.create')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
