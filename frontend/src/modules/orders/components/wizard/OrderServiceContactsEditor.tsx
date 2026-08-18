import { Plus, Trash2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { CONTACT_ROLES } from '@/modules/addresses/types/address'
import { AsyncSelect } from '@/shared/components/form/AsyncSelect'
import { ControlledCheckbox } from '@/shared/components/form/ControlledCheckbox'
import { ControlledField } from '@/shared/components/form/ControlledField'
import { Button } from '@/shared/components/ui/button'

import { useAddressContactOptions } from '../../hooks/useServiceScope'
import { emptyContact, type ServiceContactDraft } from '../../schemas/orderDraft'
import { fieldError, issuesOf, type OrderErrorReport } from '../../schemas/orderErrors'

interface OrderServiceContactsEditorProps {
  serviceKey: string
  addressId: string
  contacts: ServiceContactDraft[]
  report: OrderErrorReport
  onChange: (contacts: ServiceContactDraft[]) => void
}

/**
 * Contacts d'un service.
 *
 * Deux cas cohabitent, tous deux acceptés par le serveur : un contact déjà
 * enregistré sur l'adresse, désigné par son identifiant, ou un contact ponctuel
 * saisi ici. Dans les deux cas les coordonnées sont recopiées dans la commande
 * à la création — une modification ultérieure du contact partagé ne réécrit pas
 * l'historique de la commande.
 */
export function OrderServiceContactsEditor({
  serviceKey,
  addressId,
  contacts,
  report,
  onChange,
}: OrderServiceContactsEditorProps) {
  const { t } = useTranslation()
  const known = useAddressContactOptions(addressId)

  const patch = (index: number, values: Partial<ServiceContactDraft>) => {
    onChange(contacts.map((contact, i) => (i === index ? { ...contact, ...values } : contact)))
  }

  const pickKnown = (index: number, contactId: string) => {
    const link = known.links.find((item) => item.contact.id === contactId)

    patch(index, {
      contactId: contactId === '' ? null : contactId,
      firstName: link?.contact.firstName ?? '',
      lastName: link?.contact.lastName ?? '',
      phone: link?.contact.phone ?? '',
      mobile: link?.contact.mobile ?? '',
      email: link?.contact.email ?? '',
      contactRole: link?.contactRole ?? 'delivery',
    })
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium">{t('orders.services.contacts')}</p>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => onChange([...contacts, emptyContact()])}
        >
          <Plus className="size-4" aria-hidden />
          {t('orders.services.addContact')}
        </Button>
      </div>

      {contacts.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('orders.services.contactHint')}</p>
      ) : null}

      <ul className="flex flex-col gap-3">
        {contacts.map((contact, index) => {
          const issues = issuesOf(report, serviceKey, { kind: 'contacts', index })

          return (
            <li key={contact.key} className="rounded-md border p-3">
              <div className="mb-3 flex items-center justify-between">
                <span className="text-xs text-muted-foreground">
                  {contact.contactId === null
                    ? t('orders.services.newContact')
                    : t('orders.services.existingContact')}
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => onChange(contacts.filter((_, i) => i !== index))}
                  aria-label={t('orders.services.removeContact')}
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </div>

              <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <AsyncSelect
                  label={t('orders.services.existingContact')}
                  value={contact.contactId ?? ''}
                  onChange={(value) => pickKnown(index, value)}
                  options={known.options}
                  isLoading={known.isLoading}
                  disabled={addressId === ''}
                  description={
                    addressId === ''
                      ? t('orders.services.pickAddressFirst')
                      : known.options.length === 0
                        ? t('orders.services.noContact')
                        : undefined
                  }
                  error={fieldError(issues, 'contactId')}
                />

                <AsyncSelect
                  label={t('orders.services.contactRole')}
                  value={contact.contactRole}
                  onChange={(contactRole) => patch(index, { contactRole })}
                  options={CONTACT_ROLES.map((role) => ({
                    value: role,
                    label: t(`contactRoles.${role}`),
                  }))}
                  error={fieldError(issues, 'contactRole')}
                />

                <ControlledField
                  label={t('contacts.fields.firstName')}
                  value={contact.firstName}
                  onChange={(firstName) => patch(index, { firstName })}
                  required={contact.contactId === null}
                  error={fieldError(issues, 'firstName')}
                />

                <ControlledField
                  label={t('contacts.fields.lastName')}
                  value={contact.lastName}
                  onChange={(lastName) => patch(index, { lastName })}
                  error={fieldError(issues, 'lastName')}
                />

                <ControlledField
                  label={t('contacts.fields.phone')}
                  type="tel"
                  value={contact.phone}
                  onChange={(phone) => patch(index, { phone })}
                  error={fieldError(issues, 'phone')}
                />

                <ControlledField
                  label={t('contacts.fields.mobile')}
                  type="tel"
                  value={contact.mobile}
                  onChange={(mobile) => patch(index, { mobile })}
                  error={fieldError(issues, 'mobile')}
                />

                <ControlledField
                  label={t('contacts.fields.email')}
                  type="email"
                  value={contact.email}
                  onChange={(email) => patch(index, { email })}
                  error={fieldError(issues, 'email')}
                />
              </div>

              <div className="mt-3">
                <ControlledCheckbox
                  label={t('orders.services.isPrimary')}
                  checked={contact.isPrimary}
                  onChange={(isPrimary) => patch(index, { isPrimary })}
                />
              </div>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
