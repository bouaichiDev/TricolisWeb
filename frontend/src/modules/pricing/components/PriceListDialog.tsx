import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { useCreatePriceList } from '../hooks/usePricing'
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
import { useApiMessage } from '@/shared/hooks/useApiMessage'

interface PriceListDialogProps {
  scope: 'global' | 'customer'
  open: boolean
  onOpenChange: (open: boolean) => void
}

/**
 * Créer un barème.
 *
 * La portée est fixée par l'écran d'où l'on vient, et ne se change plus
 * ensuite : basculer une liste client en globale l'appliquerait d'un coup à
 * toute la clientèle.
 *
 * Un barème client désigne ses clients dès la création — sans eux, il ne
 * servirait personne, et le serveur le refuse.
 */
export function PriceListDialog({ scope, open, onOpenChange }: PriceListDialogProps) {
  const { t } = useTranslation()
  const create = useCreatePriceList()
  const failure = useApiMessage(create.error)
  const customers = useCustomerList({ page: 1, perPage: 100 })

  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [validFrom, setValidFrom] = useState('')
  const [validTo, setValidTo] = useState('')
  const [chosen, setChosen] = useState<string[]>([])

  const ready = code.trim() !== '' && name.trim() !== '' && (scope === 'global' || chosen.length > 0)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t(`pricing.lists.create.${scope}`)}</DialogTitle>
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
          <Button
            disabled={!ready || create.isPending}
            onClick={() =>
              create.mutate(
                {
                  code: code.trim(),
                  name: name.trim(),
                  scope,
                  validFrom: validFrom || null,
                  validTo: validTo || null,
                  customerIds: scope === 'customer' ? chosen : undefined,
                },
                { onSuccess: () => onOpenChange(false) },
              )
            }
          >
            {t('common.create')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
