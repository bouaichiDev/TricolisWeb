import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'

import { useCustomerList } from '@/modules/customers/hooks/useCustomers'
import { StatusFilterSelect } from '@/modules/statuses/components/StatusFilterSelect'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'

/** Sentinelle « tous les clients » : Radix refuse une option de valeur vide. */
export const ALL_CUSTOMERS = 'all'

interface TourFilterBarProps {
  date: string
  onDateChange: (date: string) => void
  customerId: string
  onCustomerChange: (customerId: string) => void
  search: string
  onSearchChange: (search: string) => void
  status?: string
  onStatusChange: (status?: string) => void
}

/**
 * Les filtres de la liste des tournées.
 *
 * **La date est obligatoire**, décision du 27 août 2026 : une tournée se lit
 * par jour. Sans date, la liste mélange un mois de préparation et on ne compare
 * plus rien. Le champ ne peut donc pas être vidé — il revient au jour courant.
 *
 * Le client ne filtre **que les commandes à planifier** : une tournée n'a pas de
 * client, elle en dessert plusieurs, et le serveur n'expose aucun filtre client
 * sur `/tours`. Le prétendre ici donnerait un filtre sans effet.
 *
 * **Les quatre colonnes ont la même forme** — un libellé, puis le contrôle.
 * Deux d'entre elles n'en avaient pas : alignée par le bas, la barre faisait
 * alors remonter le sélecteur de client de la hauteur de son texte d'aide, et
 * les quatre contrôles ne partageaient plus aucune ligne. Un « Tous » seul, sans
 * libellé, ne disait pas non plus de quoi il parlait.
 */
export function TourFilterBar({
  date,
  onDateChange,
  customerId,
  onCustomerChange,
  search,
  onSearchChange,
  status,
  onStatusChange,
}: TourFilterBarProps) {
  const { t } = useTranslation()

  const customers = useCustomerList({ page: 1, perPage: 100 })

  return (
    // Aligné par le haut : les libellés partagent une ligne, les contrôles la
    // suivante, et le texte d'aide du client pend sous sa seule colonne sans
    // décaler les autres.
    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start">
      <Field label={t('tours.fields.tourDate')} htmlFor="tour-date">
        <Input
          id="tour-date"
          type="date"
          value={date}
          required
          // Vider le champ ramene au jour courant : une liste sans date
          // melangerait un mois de tournees et ne se comparerait plus.
          onChange={(event) =>
            onDateChange(event.target.value === '' ? todayIso() : event.target.value)
          }
          className="w-full sm:w-44"
        />
      </Field>

      {/* `AsyncSelect` porte déjà son libellé et son aide : l'envelopper d'un
          second `Field` en ferait deux. */}
      <div className="w-full sm:w-56">
        <AsyncSelect
          label={t('tours.filters.customer')}
          value={customerId}
          onChange={onCustomerChange}
          options={[
            { value: ALL_CUSTOMERS, label: t('tours.filters.allCustomers') },
            ...(customers.data?.data ?? []).map((customer) => ({
              value: customer.id,
              label: customer.name,
              hint: customer.code,
            })),
          ]}
          isLoading={customers.isPending}
          description={t('tours.filters.customerHint')}
        />
      </div>

      <Field label={t('common.search')} htmlFor="tour-search" className="w-full sm:w-64">
        <SearchInput id="tour-search" value={search} onChange={onSearchChange} />
      </Field>

      <Field label={t('statuses.filter')} htmlFor="tour-status" className="w-full sm:w-48">
        {/* La largeur est celle de la colonne : la laisser au composant lui
            ferait porter la sienne, plus étroite que son libellé. */}
        <StatusFilterSelect
          id="tour-status"
          source="tour"
          value={status}
          onChange={onStatusChange}
          className="w-full"
        />
      </Field>
    </div>
  )
}

/**
 * Un filtre : son libellé, puis son contrôle.
 *
 * L'écart de deux unités est celui qu'`AsyncSelect` applique entre son propre
 * libellé et son sélecteur ; sans lui, la colonne du client ne s'alignerait pas
 * sur les autres.
 */
function Field({
  label,
  htmlFor,
  className,
  children,
}: {
  label: string
  htmlFor: string
  className?: string
  children: ReactNode
}) {
  return (
    <div className={`flex flex-col gap-2 ${className ?? ''}`}>
      <Label htmlFor={htmlFor}>{label}</Label>
      {children}
    </div>
  )
}

/** Le jour courant au format que l'API et l'input attendent. */
export function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}
