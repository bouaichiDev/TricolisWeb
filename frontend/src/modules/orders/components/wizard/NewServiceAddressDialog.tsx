import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { AddressFields } from '@/modules/addresses/components/AddressFields'
import { useCreateEntityAddress } from '@/modules/addresses/hooks/useEntityAddressMutations'
import { addressSchema, ADDRESS_FORM_DEFAULTS } from '@/modules/addresses/schemas/addressSchema'
import { ADDRESS_TYPES, CONTACT_ROLES } from '@/modules/addresses/types/address'
import { contactsApi } from '@/modules/contacts/api/contacts.api'
import { addressesApi } from '@/modules/addresses/api/addresses.api'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { SelectField } from '@/shared/components/form/SelectField'
import { TextField } from '@/shared/components/form/TextField'
import { Button } from '@/shared/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/shared/components/ui/dialog'
import { useApiFormError } from '@/shared/hooks/useApiForm'
import { useAuth } from '@/shared/hooks/useAuth'

import { newKey } from '../../schemas/orderDraftFactories'
import type { ServiceContactDraft } from '../../schemas/orderDraft'

/**
 * Adresse et contact saisis d'un seul tenant.
 *
 * Le contact est facultatif : un prénom suffit à le créer, et son absence
 * n'empêche pas l'adresse d'exister.
 */
const schema = addressSchema.extend({
  addressType: z.string().min(1, 'validation.required'),
  contactFirstName: z.string().max(255, 'validation.max'),
  contactLastName: z.string().max(255, 'validation.max'),
  contactPhone: z.string().max(255, 'validation.max'),
  contactEmail: z.string().max(255, 'validation.max'),
  contactRole: z.string(),
})

type FormValues = z.infer<typeof schema>

const DEFAULTS: FormValues = {
  ...ADDRESS_FORM_DEFAULTS,
  addressType: 'delivery',
  contactFirstName: '',
  contactLastName: '',
  contactPhone: '',
  contactEmail: '',
  contactRole: 'delivery',
}

interface NewServiceAddressDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  /**
   * Reçoit l'adresse créée et, le cas échéant, le contact saisi — prêt à être
   * versé dans le service, pour ne pas être demandé une seconde fois.
   */
  onCreated: (addressId: string, contact: ServiceContactDraft | null) => void
}

/**
 * Création d'une adresse de destination, sans quitter la commande.
 *
 * `StoreOrderRequest` exige un `addressId` existant : une adresse ne peut pas
 * voyager dans la charge utile de la commande. Elle est donc créée d'abord, par
 * sa propre route, puis désignée par le service.
 *
 * **Elle n'est pas rattachée au donneur d'ordre.** Le lien avec la commande est
 * déjà au diagramme : `Address 1 → 0..* OrderService`, porté par
 * `order_services.address_id`. Rattacher en plus l'adresse d'un client final au
 * carnet du donneur y ajouterait une ligne par livraison — des milliers
 * d'adresses qui ne sont pas les siennes. `Address` étant une entité
 * `«shared»`, son carnet est celui de l'**organisation**.
 *
 * **Le contact est saisi dans la foulée** : sur un point de livraison, l'adresse
 * sans le nom de qui reçoit ne sert à rien, et les demander en deux temps fait
 * perdre le second. Il est rattaché à l'adresse *et* renvoyé au service, qui
 * l'inscrit aussitôt dans ses contacts.
 */
export function NewServiceAddressDialog({
  open,
  onOpenChange,
  onCreated,
}: NewServiceAddressDialogProps) {
  const { t } = useTranslation()
  const { organizationId } = useAuth()
  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: DEFAULTS })
  const { formError, handleError, clearError } = useApiFormError(form)
  const createAddress = useCreateEntityAddress({
    entityType: 'organization',
    entityId: organizationId ?? '',
  })

  const submit = form.handleSubmit(async (values) => {
    clearError()

    try {
      const address = await createAddress.mutateAsync({
        name: values.name,
        addressLine1: values.addressLine1,
        addressLine2: values.addressLine2,
        addressNumber: values.addressNumber,
        route: values.route,
        postalCode: values.postalCode,
        city: values.city,
        country: values.country,
        instructions: values.instructions,
        timeWindowFrom: values.timeWindowFrom,
        timeWindowTo: values.timeWindowTo,
        addressType: values.addressType,
        isDefault: false,
      })

      onCreated(address.id, await attachContact(address.id, values, organizationId))
      form.reset(DEFAULTS)
      onOpenChange(false)
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{t('orders.services.newAddress')}</DialogTitle>
          <DialogDescription>{t('orders.services.newAddressHint')}</DialogDescription>
        </DialogHeader>

        <FormErrorSummary message={formError} />

        <div className="flex flex-col gap-5">
          <SelectField
            form={form}
            name="addressType"
            label={t('addresses.fields.addressType')}
            required
            options={ADDRESS_TYPES.map((type) => ({
              value: type,
              label: t(`addressTypes.${type}`, { defaultValue: type }),
            }))}
          />

          <AddressFields form={form} />

          <fieldset className="border-t pt-4">
            <legend className="mb-1 text-sm font-medium">{t('orders.services.contacts')}</legend>
            <p className="mb-3 text-xs text-muted-foreground">
              {t('orders.services.newContactHint')}
            </p>

            <div className="grid gap-5 sm:grid-cols-2">
              <TextField form={form} name="contactFirstName" label={t('contacts.fields.firstName')} />
              <TextField form={form} name="contactLastName" label={t('contacts.fields.lastName')} />
              <TextField form={form} name="contactPhone" label={t('contacts.fields.phone')} type="tel" />
              <TextField form={form} name="contactEmail" label={t('contacts.fields.email')} type="email" />
              <SelectField
                form={form}
                name="contactRole"
                label={t('orders.services.contactRole')}
                options={CONTACT_ROLES.map((role) => ({
                  value: role,
                  label: t(`contactRoles.${role}`),
                }))}
              />
            </div>
          </fieldset>
        </div>

        <DialogFooter>
          <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={() => void submit()} disabled={form.formState.isSubmitting}>
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/**
 * Crée le contact et le rattache à l'adresse, puis le rend au service.
 *
 * Comme l'adresse, il est porté par l'organisation : le destinataire d'une
 * livraison ponctuelle n'appartient pas au carnet du donneur d'ordre.
 */
async function attachContact(
  addressId: string,
  values: FormValues,
  organizationId: string | null,
): Promise<ServiceContactDraft | null> {
  if (values.contactFirstName.trim() === '') return null

  const contact = await contactsApi.create({
    firstName: values.contactFirstName,
    lastName: values.contactLastName,
    phone: values.contactPhone || null,
    email: values.contactEmail || null,
    entityType: 'organization',
    entityId: organizationId ?? '',
    contactRole: values.contactRole,
    isPrimary: true,
  })

  await addressesApi.attachContact(addressId, {
    contactId: contact.id,
    contactRole: values.contactRole,
    isPrimary: true,
  })

  return {
    key: newKey(),
    contactId: contact.id,
    contactRole: values.contactRole,
    isPrimary: true,
    firstName: values.contactFirstName,
    lastName: values.contactLastName,
    phone: values.contactPhone,
    mobile: '',
    email: values.contactEmail,
  }
}
