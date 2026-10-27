import { cn } from '@/shared/utils/cn'

import type { TrackedService } from '../types'

interface ServiceSelectorProps {
  services: TrackedService[]
  selectedId: string | null
  onSelect: (id: string | null) => void
}

/**
 * Choix de la prestation à détailler (§14). « Toutes » garde la vue de la
 * commande entière ; une commande à un seul service n'a pas de sélecteur.
 */
export function ServiceSelector({ services, selectedId, onSelect }: ServiceSelectorProps) {
  if (services.length < 2) return null

  const option = (id: string | null, label: string) => (
    <button
      key={id ?? 'all'}
      type="button"
      role="tab"
      aria-selected={selectedId === id}
      onClick={() => onSelect(id)}
      className={cn(
        'rounded-full border px-3 py-1 text-sm transition-colors',
        selectedId === id ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
      )}
    >
      {label}
    </button>
  )

  return (
    <div role="tablist" aria-label="Services de la commande" className="flex flex-wrap gap-2">
      {option(null, 'Tous les services')}
      {services.map((service) => option(service.id, `${service.service?.name ?? 'Service'} · ${service.serviceNumber}`))}
    </div>
  )
}
