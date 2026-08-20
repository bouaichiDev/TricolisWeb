import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

import { useCustomerOptions } from '../../hooks/useOrderScope'
import { useAddressOptions, useSiteOptions } from '../../hooks/useServiceScope'

/**
 * Valeur désignant le client lui-même, par opposition à l'un de ses sites.
 *
 * Radix refuse une option de valeur vide ; les sites portant un ULID, ce
 * libellé ne peut entrer en collision avec aucun d'entre eux.
 */
const CUSTOMER_SOURCE = 'customer'

interface AddressPickerProps {
  /** Client de la commande — le donneur d'ordre. Sert de valeur par défaut. */
  customerId: string
  value: string
  onChange: (addressId: string) => void
  error?: string
  required?: boolean
}

/**
 * Choix de l'adresse d'exécution d'un service.
 *
 * **L'adresse n'est pas forcément celle du client de la commande.** Une même
 * commande porte souvent un chargement chez le donneur d'ordre et une livraison
 * chez le destinataire : deux services, deux clients, deux adresses. Le client
 * est donc choisi service par service, avec celui de la commande en valeur de
 * départ.
 *
 * `OrderScopeGuard` accepte toute adresse rattachée à l'organisation active :
 * cette liberté existe déjà côté serveur, l'écran ne faisait que la brider.
 *
 * L'API expose les adresses **par entité** — celles d'un client et celles de
 * chacun de ses sites sont des listes distinctes — d'où le choix en trois temps
 * plutôt qu'une liste unique qui demanderait une requête par site.
 */
export function AddressPicker({
  customerId,
  value,
  onChange,
  error,
  required = false,
}: AddressPickerProps) {
  const { t } = useTranslation()
  const [holder, setHolder] = useState('')
  const [source, setSource] = useState(CUSTOMER_SOURCE)

  const customers = useCustomerOptions('')
  const selectedCustomer = holder === '' ? customerId : holder

  const sites = useSiteOptions(selectedCustomer)
  const isSite = source !== CUSTOMER_SOURCE
  const addresses = useAddressOptions(
    isSite ? 'customer_site' : 'customer',
    isSite ? source : selectedCustomer,
  )

  const reset = () => {
    setSource(CUSTOMER_SOURCE)
    // L'adresse retenue appartenait à l'autre entité : la garder afficherait un
    // identifiant sans libellé.
    onChange('')
  }

  const customerOptions = customers.options.map((option) => ({
    ...option,
    hint: option.value === customerId ? t('orders.services.orderCustomer') : option.hint,
  }))

  const noCustomer = selectedCustomer === ''

  return (
    <>
      <AsyncSelect
        label={t('orders.services.customer')}
        value={selectedCustomer}
        onChange={(next) => {
          setHolder(next)
          reset()
        }}
        options={customerOptions}
        isLoading={customers.isLoading}
        required={required}
        description={t('orders.services.customerHint')}
      />

      <AsyncSelect
        label={t('orders.services.addressSource')}
        value={source}
        onChange={(next) => {
          setSource(next)
          onChange('')
        }}
        options={[
          { value: CUSTOMER_SOURCE, label: t('orders.services.sourceCustomer') },
          ...sites.options,
        ]}
        isLoading={sites.isLoading}
        disabled={noCustomer}
        description={noCustomer ? t('orders.services.pickCustomerFirst') : undefined}
      />

      <AsyncSelect
        label={t('orders.services.address')}
        value={value}
        onChange={onChange}
        options={addresses.options}
        isLoading={addresses.isLoading}
        disabled={noCustomer}
        required={required}
        description={
          noCustomer
            ? t('orders.services.pickCustomerFirst')
            : addresses.options.length === 0 && !addresses.isLoading
              ? t('orders.services.noAddress')
              : undefined
        }
        error={error}
      />
    </>
  )
}
