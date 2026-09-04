import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { CheckboxField } from '@/shared/components/form/CheckboxField'
import { FormActions } from '@/shared/components/form/FormActions'
import { FormErrorSummary } from '@/shared/components/form/FormErrorSummary'
import { SelectField } from '@/shared/components/form/SelectField'
import { TextField } from '@/shared/components/form/TextField'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { useApiFormError } from '@/shared/hooks/useApiForm'

import type { MailConfiguration, MailConfigurationPayload } from '../types/mailConfiguration'

/** Sentinelle « aucun chiffrement » : Radix refuse une option de valeur vide. */
const NO_ENCRYPTION = 'none'

/** Contraintes reprises de `UpsertOrganizationMailConfigurationRequest`. */
const schema = z.object({
  host: z.string().min(1, 'validation.required').max(255, 'validation.max'),
  port: z.coerce.number().int().min(1, 'validation.required').max(65535),
  encryption: z.string(),
  username: z.string(),
  password: z.string(),
  fromAddress: z.string().min(1, 'validation.required').email('validation.email'),
  fromName: z.string(),
  replyTo: z.string(),
  isActive: z.boolean(),
})

type Values = z.infer<typeof schema>

interface MailConfigurationFormProps {
  configuration: MailConfiguration | null
  isPending: boolean
  onSubmit: (payload: MailConfigurationPayload) => Promise<unknown>
}

/**
 * D'où partent les courriers de l'organisation.
 *
 * **L'authentification et l'identité sont deux choses.** On s'authentifie
 * souvent avec un compte technique — `envoi@atlas.ch` — et l'on signe avec
 * l'adresse que le client doit voir et à laquelle il répondra. Les mêler dans
 * un seul champ oblige à choisir laquelle sacrifier.
 *
 * **Le mot de passe n'est jamais relu.** Le champ reste vide même quand un
 * secret existe : la case dit qu'il y en a un, le laisser vide le conserve.
 * L'afficher, fût-ce en points, le ferait circuler pour rien.
 */
export function MailConfigurationForm({
  configuration,
  isPending,
  onSubmit,
}: MailConfigurationFormProps) {
  const { t } = useTranslation()

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      host: configuration?.host ?? '',
      port: configuration?.port ?? 587,
      encryption: configuration?.encryption ?? 'tls',
      username: configuration?.username ?? '',
      password: '',
      fromAddress: configuration?.fromAddress ?? '',
      fromName: configuration?.fromName ?? '',
      replyTo: configuration?.replyTo ?? '',
      isActive: configuration?.isActive ?? true,
    },
  })

  const { formError, handleError, clearError } = useApiFormError(form)

  const submit = form.handleSubmit(async (values) => {
    clearError()

    try {
      await onSubmit({
        host: values.host,
        port: values.port,
        encryption: values.encryption === NO_ENCRYPTION ? null : values.encryption,
        username: values.username === '' ? null : values.username,
        // Vide, le champ ne touche à rien : omettre la clé conserve le secret
        // en place. C'est ce qui permet de changer un port sans ressaisir un
        // mot de passe qu'on n'a plus sous la main.
        ...(values.password === '' ? {} : { password: values.password }),
        fromAddress: values.fromAddress,
        fromName: values.fromName === '' ? null : values.fromName,
        replyTo: values.replyTo === '' ? null : values.replyTo,
        isActive: values.isActive,
      })
    } catch (error) {
      handleError(error)
    }
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
      <FormErrorSummary message={formError} />

      <SectionCard
        title={t('mailConfiguration.sections.server')}
        description={t('mailConfiguration.sections.serverHint')}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField form={form} name="host" label={t('mailConfiguration.fields.host')} required />
          <TextField
            form={form}
            name="port"
            type="number"
            label={t('mailConfiguration.fields.port')}
            required
          />
          <SelectField
            form={form}
            name="encryption"
            label={t('mailConfiguration.fields.encryption')}
            description={t('mailConfiguration.fields.encryptionHint')}
            options={[
              { value: 'tls', label: 'TLS (STARTTLS)' },
              { value: 'ssl', label: 'SSL' },
              { value: NO_ENCRYPTION, label: t('mailConfiguration.fields.noEncryption') },
            ]}
          />
          <TextField
            form={form}
            name="username"
            label={t('mailConfiguration.fields.username')}
            description={t('mailConfiguration.fields.usernameHint')}
          />
          <TextField
            form={form}
            name="password"
            type="password"
            label={t('mailConfiguration.fields.password')}
            description={
              configuration?.hasPassword === true
                ? t('mailConfiguration.fields.passwordKept')
                : t('mailConfiguration.fields.passwordHint')
            }
          />
        </div>
      </SectionCard>

      <SectionCard
        title={t('mailConfiguration.sections.identity')}
        description={t('mailConfiguration.sections.identityHint')}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField
            form={form}
            name="fromAddress"
            type="email"
            label={t('mailConfiguration.fields.fromAddress')}
            required
          />
          <TextField form={form} name="fromName" label={t('mailConfiguration.fields.fromName')} />
          <TextField
            form={form}
            name="replyTo"
            type="email"
            label={t('mailConfiguration.fields.replyTo')}
            description={t('mailConfiguration.fields.replyToHint')}
          />
          <CheckboxField
            form={form}
            name="isActive"
            label={t('mailConfiguration.fields.isActive')}
            description={t('mailConfiguration.fields.isActiveHint')}
          />
        </div>
      </SectionCard>

      {/* « Annuler » remet les valeurs enregistrees : sur un ecran de reglages
          il n'y a pas de page d'ou l'on viendrait, et quitter sans rien
          reprendre laisserait un formulaire a demi modifie. */}
      <FormActions
        onCancel={() => form.reset()}
        submitLabel={t('common.save')}
        isSubmitting={isPending || form.formState.isSubmitting}
      />
    </form>
  )
}
