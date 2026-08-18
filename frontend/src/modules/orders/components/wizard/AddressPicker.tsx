import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { AsyncSelect } from '@/shared/components/form/AsyncSelect'

import { useAddressOptions, useSiteOptions } from '../../hooks/useServiceScope'

/**
 * Valeur désignant le client lui-même comme source.
 *
 * Radix refuse une option de valeur vide ; les sites portant un ULID, ce
 * libellé ne peut entrer en collision avec aucun d'entre eux.
 */
const CUSTOMER_SOURCE = 'customer'

interface AddressPickerProps {
  customerId: string
  value: string
  onChange: (addressId: string) => void
  error?: string
  required?: boolean
}

/**
 * Choix de l'adresse d'exécution d'un service.
 *
 * L'API expose les adresses **par entité** : celles du client et celles de
 * chacun de ses sites sont des listes distinctes. La source est donc choisie
 * d'abord — le client, ou l'un de ses sites — puis l'adresse. Tout charger d'un
 * coup demanderait une requête par site, pour un résultat que personne ne lit
 * en entier.
 *
 * `OrderScopeGuard` accepte toute adresse rattachée à l'organisation active :
 * ces deux sources ne restreignent rien côté serveur, elles rendent le choix
 * praticable.
 */
export function AddressPicker({
  customerId,
  value,
  onChange,
  error,
  required = false,
}: AddressPickerProps) {
  const { t } = useTranslation()
  const [source, setSource] = useState(CUSTOMER_SOURCE)

  const sites = useSiteOptions(customerId)
  const isSite = source !== CUSTOMER_SOURCE
  const addresses = useAddressOptions(
    isSite ? 'customer_site' : 'customer',
    isSite ? source : customerId,
  )

  const sourceOptions = [
    { value: CUSTOMER_SOURCE, label: t('orders.services.sourceCustomer') },
    ...sites.options,
  ]

  return (
    <>
      <AsyncSelect
        label={t('orders.services.addressSource')}
        value={source}
        onChange={(next) => {
          setSource(next)
          // L'adresse retenue appartenait à l'autre source : la garder
          // afficherait un identifiant sans libellé.
          onChange('')
        }}
        options={sourceOptions}
        isLoading={sites.isLoading}
        disabled={customerId === ''}
        description={customerId === '' ? t('orders.services.pickCustomerFirst') : undefined}
      />

      <AsyncSelect
        label={t('orders.services.address')}
        value={value}
        onChange={onChange}
        options={addresses.options}
        isLoading={addresses.isLoading}
        disabled={customerId === ''}
        required={required}
        description={
          customerId === ''
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
