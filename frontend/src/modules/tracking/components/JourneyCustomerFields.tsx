import { useTranslation } from 'react-i18next'

import { useServiceList } from '@/modules/services/hooks/useServices'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Label } from '@/shared/components/ui/label'
import { Switch } from '@/shared/components/ui/switch'

/** Valeur désignant « toutes les prestations », Radix refusant une option vide. */
export const ALL_SERVICES = 'all'

interface JourneyCustomerFieldsProps {
  serviceId: string
  visibleToCustomer: boolean
  showsProofOfDelivery: boolean
  onChange: (patch: {
    serviceId?: string
    visibleToCustomer?: boolean
    showsProofOfDelivery?: boolean
  }) => void
  /** Les prestations ne se chargent qu'à l'ouverture de la fenêtre. */
  enabled: boolean
}

/**
 * De quelle prestation l'étape parle, et ce que le destinataire en voit.
 *
 * **Une commande porte souvent trois prestations** — charger, livrer, monter —
 * et le parcours les mélangeait toutes : le destinataire lisait « planifié »
 * trois fois sans savoir de quoi. L'étape vise donc une prestation ; laissée sur
 * « toutes », elle garde son comportement d'avant.
 *
 * **Suivre et montrer sont deux choses.** Le chargement au dépôt intéresse le
 * planificateur, jamais le destinataire. Sans ce second réglage, il faudrait
 * choisir entre suivre une étape en interne et la publier dehors.
 *
 * La preuve de livraison s'attache à l'étape qui la produit : offerte dès
 * « planifié » elle n'existe pas encore, offerte à « livré » elle répond à la
 * seule question que le client se pose alors.
 */
export function JourneyCustomerFields({
  serviceId,
  visibleToCustomer,
  showsProofOfDelivery,
  onChange,
  enabled,
}: JourneyCustomerFieldsProps) {
  const { t } = useTranslation()

  const services = useServiceList({ page: 1, perPage: 100 }, enabled)

  return (
    <div className="flex flex-col gap-4 rounded-md border p-3">
      <AsyncSelect
        label={t('journey.fields.service')}
        value={serviceId}
        onChange={(value) => onChange({ serviceId: value })}
        options={[
          { value: ALL_SERVICES, label: t('journey.allServices') },
          ...(services.data?.data ?? []).map((service) => ({
            value: service.id,
            label: service.name,
            hint: service.code,
          })),
        ]}
        isLoading={services.isPending}
        description={t('journey.serviceHint')}
      />

      <Toggle
        id="journey-visible"
        label={t('journey.fields.visibleToCustomer')}
        hint={t('journey.visibleHint')}
        checked={visibleToCustomer}
        onChange={(next) => onChange({ visibleToCustomer: next })}
      />

      <Toggle
        id="journey-pod"
        label={t('journey.fields.showsProof')}
        hint={t('journey.showsProofHint')}
        // La preuve n'a de sens que sur une étape que le client voit : la
        // proposer sur une étape interne promettrait un document que personne
        // n'irait chercher.
        disabled={!visibleToCustomer}
        checked={showsProofOfDelivery && visibleToCustomer}
        onChange={(next) => onChange({ showsProofOfDelivery: next })}
      />
    </div>
  )
}

function Toggle({
  id,
  label,
  hint,
  checked,
  onChange,
  disabled = false,
}: {
  id: string
  label: string
  hint: string
  checked: boolean
  onChange: (next: boolean) => void
  disabled?: boolean
}) {
  return (
    <div className="flex items-start justify-between gap-4">
      <div className="min-w-0">
        <Label htmlFor={id} className={disabled ? 'text-muted-foreground' : 'cursor-pointer'}>
          {label}
        </Label>
        <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
      </div>

      <Switch id={id} checked={checked} disabled={disabled} onCheckedChange={onChange} />
    </div>
  )
}
