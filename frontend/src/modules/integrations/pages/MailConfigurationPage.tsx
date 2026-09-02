import { Send, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { PageHeader } from '@/shared/components/layout/PageHeader'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Alert, AlertDescription } from '@/shared/components/ui/alert'
import { Button } from '@/shared/components/ui/button'
import { Input } from '@/shared/components/ui/input'
import { Label } from '@/shared/components/ui/label'
import { ApiError } from '@/shared/api/errors'

import { MailConfigurationForm } from '../components/MailConfigurationForm'
import {
  useDeleteMailConfiguration,
  useMailConfiguration,
  useSaveMailConfiguration,
  useTestMailConfiguration,
} from '../hooks/useMailConfiguration'

/**
 * D'où partent les courriers de cette organisation.
 *
 * **Deux transporteurs sur la même installation ne peuvent pas signer du même
 * nom.** Sans cet écran, le client d'Atlas recevait sa facture depuis l'adresse
 * de Tricolis et se demandait qui la lui réclamait. C'est aussi ce qui permet au
 * domaine du client de publier un SPF valable — un courrier « de »
 * contact@atlas.ch parti d'un serveur qu'Atlas n'a pas déclaré finit en
 * indésirable.
 *
 * **Rien n'est obligatoire.** Tant qu'aucune boîte n'est réglée, l'organisation
 * envoie avec la messagerie du projet : activer la fonctionnalité ne doit
 * couper personne.
 */
export function MailConfigurationPage() {
  const { t } = useTranslation()

  const { data: configuration, isPending } = useMailConfiguration()
  const save = useSaveMailConfiguration()
  const remove = useDeleteMailConfiguration()
  const test = useTestMailConfiguration()

  const [recipient, setRecipient] = useState('')
  const [deleting, setDeleting] = useState(false)

  if (isPending) return null

  // `undefined` vaut « rien de reglee » comme `null` : la ressource rend null
  // quand la boite n'existe pas, et la requete peut n'avoir rien rendu.
  const saved = configuration ?? null

  return (
    <div className="flex flex-col gap-6">
      <PageHeader
        title={t('mailConfiguration.title')}
        description={t('mailConfiguration.subtitle')}
      />

      {saved === null ? (
        <Alert>
          <AlertDescription>{t('mailConfiguration.usingProjectMailer')}</AlertDescription>
        </Alert>
      ) : null}

      <MailConfigurationForm
        // Remonte le formulaire quand la boite change : `defaultValues` n'est
        // lu qu'au montage, et un enregistrement laisserait sinon les anciennes
        // valeurs a l'ecran.
        key={saved?.updatedAt ?? 'new'}
        configuration={saved}
        isPending={save.isPending}
        onSubmit={(payload) => save.mutateAsync(payload)}
      />

      {saved === null ? null : (
        <PermissionGuard permission="mail_configuration.update">
          <SectionCard
            title={t('mailConfiguration.sections.test')}
            description={t('mailConfiguration.sections.testHint')}
          >
            <div className="flex flex-col gap-3">
              <div className="flex flex-col gap-2 sm:max-w-sm">
                <Label htmlFor="test-recipient">{t('mailConfiguration.testRecipient')}</Label>
                <Input
                  id="test-recipient"
                  type="email"
                  value={recipient}
                  placeholder={saved.fromAddress}
                  onChange={(event) => setRecipient(event.target.value)}
                />
              </div>

              <div>
                <Button
                  type="button"
                  variant="outline"
                  disabled={test.isPending}
                  onClick={() => test.mutate(recipient === '' ? undefined : recipient)}
                >
                  <Send className="size-4" aria-hidden />
                  {test.isPending ? t('mailConfiguration.testing') : t('mailConfiguration.test')}
                </Button>
              </div>

              {/* L'erreur du serveur distant, en entier : « 535 authentification
                  refusée » se cherche, « envoi impossible » non. */}
              {test.error === null || test.error === undefined ? null : (
                <Alert variant="destructive">
                  <AlertDescription className="break-words">
                    {remoteError(test.error)}
                  </AlertDescription>
                </Alert>
              )}
            </div>
          </SectionCard>

          <PermissionGuard permission="mail_configuration.delete">
            <div>
              <Button type="button" variant="ghost" onClick={() => setDeleting(true)}>
                <Trash2 className="size-4" aria-hidden />
                {t('mailConfiguration.remove')}
              </Button>
            </div>
          </PermissionGuard>
        </PermissionGuard>
      )}

      <ConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        title={t('mailConfiguration.removeTitle')}
        description={t('mailConfiguration.removeBody')}
        confirmLabel={t('common.delete')}
        variant="destructive"
        onConfirm={() => {
          remove.mutate()
          setDeleting(false)
        }}
      />
    </div>
  )
}

/**
 * Ce que le serveur distant a répondu, en entier.
 *
 * Le contrôleur range son message sous `recipient` : c'est lui qui permet de
 * chercher. « 535 authentification refusée » se trouve dans une documentation,
 * « l'essai d'envoi a échoué » nulle part.
 */
function remoteError(error: unknown): string {
  if (!(error instanceof ApiError)) return String(error)

  return error.errors.recipient?.join(' ') ?? error.message
}
