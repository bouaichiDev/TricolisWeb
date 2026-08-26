import { Boxes, Package, Weight } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { SectionCard } from '@/shared/components/layout/SectionCard'

import type { Vehicle } from '../types/vehicle'

/**
 * Les trois capacités d'un véhicule, ensemble.
 *
 * Regroupées dans une carte plutôt qu'en trois lignes de tableau : ce sont
 * trois chiffres qui ne se lisent qu'ensemble — une charge utile sans son
 * volume ne dit pas ce que le camion emporte.
 */
export function VehicleCapacitySummary({ vehicle }: { vehicle: Vehicle }) {
  const { t } = useTranslation()

  const items = [
    { icon: Weight, label: t('vehicles.fields.payloadCapacity'), value: vehicle.payloadCapacity },
    { icon: Boxes, label: t('vehicles.fields.volumeCapacity'), value: vehicle.volumeCapacity },
    { icon: Package, label: t('vehicles.fields.palletCapacity'), value: vehicle.palletCapacity },
  ]

  return (
    <SectionCard title={t('vehicles.capacities')} description={t('vehicles.capacitiesHint')}>
      <div className="grid gap-4 sm:grid-cols-3">
        {items.map(({ icon: Icon, label, value }) => (
          <div key={label} className="flex items-center gap-3 rounded-lg border p-3">
            <Icon className="size-5 shrink-0 text-muted-foreground" aria-hidden />
            <div className="min-w-0">
              <p className="text-xs text-muted-foreground">{label}</p>
              <p className="truncate text-lg font-semibold">{value}</p>
            </div>
          </div>
        ))}
      </div>
    </SectionCard>
  )
}
