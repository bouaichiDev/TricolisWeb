import { useTranslation } from 'react-i18next'

import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

import type { PackageDraft, ServicePackageDraft } from '../../schemas/orderDraft'

interface OrderServicePackagesEditorProps {
  packages: PackageDraft[]
  selected: ServicePackageDraft[]
  onChange: (packages: ServicePackageDraft[]) => void
}

/**
 * Colis pris en charge par un service.
 *
 * Un colis peut être servi par plusieurs services — chargé ici, livré là — et
 * la liaison porte sa propre quantité et ses consignes. Sans colis déclaré à
 * l'étape précédente, la liste est vide et le dit : c'est un cas normal, les
 * colis étant facultatifs.
 */
export function OrderServicePackagesEditor({
  packages,
  selected,
  onChange,
}: OrderServicePackagesEditorProps) {
  const { t } = useTranslation()
  const byKey = new Map(selected.map((link) => [link.packageKey, link]))

  const toggle = (packageKey: string, checked: boolean) => {
    onChange(
      checked
        ? [...selected, { packageKey, quantity: '', handlingInstructions: '' }]
        : selected.filter((link) => link.packageKey !== packageKey),
    )
  }

  const patch = (packageKey: string, values: Partial<ServicePackageDraft>) => {
    onChange(
      selected.map((link) => (link.packageKey === packageKey ? { ...link, ...values } : link)),
    )
  }

  if (packages.length === 0) {
    return (
      <div className="flex flex-col gap-2">
        <p className="text-sm font-medium">{t('orders.services.packages')}</p>
        <p className="text-sm text-muted-foreground">{t('orders.services.noPackages')}</p>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm font-medium">{t('orders.services.packages')}</p>

      <ul className="flex flex-col gap-2">
        {packages.map((pkg, index) => {
          const link = byKey.get(pkg.key)
          const label =
            pkg.reference.trim() !== ''
              ? pkg.reference
              : t('orders.packages.position', { position: index + 1 })

          return (
            <li key={pkg.key} className="rounded-md border px-3 py-2">
              <ControlledCheckbox
                label={label}
                checked={link !== undefined}
                onChange={(checked) => toggle(pkg.key, checked)}
              />

              {link ? (
                <div className="mt-2 grid gap-3 pl-7 sm:grid-cols-2">
                  <div className="flex flex-col gap-1">
                    <Label htmlFor={`qty-${pkg.key}`} className="text-xs">
                      {t('orders.fields.quantity')}
                    </Label>
                    <Input
                      id={`qty-${pkg.key}`}
                      type="number"
                      min="0"
                      step="0.001"
                      value={link.quantity}
                      onChange={(event) => patch(pkg.key, { quantity: event.target.value })}
                    />
                  </div>

                  <div className="flex flex-col gap-1">
                    <Label htmlFor={`hi-${pkg.key}`} className="text-xs">
                      {t('orders.services.handlingInstructions')}
                    </Label>
                    <Input
                      id={`hi-${pkg.key}`}
                      value={link.handlingInstructions}
                      onChange={(event) =>
                        patch(pkg.key, { handlingInstructions: event.target.value })
                      }
                    />
                  </div>
                </div>
              ) : null}
            </li>
          )
        })}
      </ul>
    </div>
  )
}
