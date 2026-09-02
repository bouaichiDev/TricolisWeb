import { KeyRound, Mail } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { ConfirmDialog } from '@/shared/components/feedback/ConfirmDialog'
import { SectionCard } from '@/shared/components/layout/SectionCard'
import { Button } from '@/shared/components/ui/button'

import { MemberPasswordDialog } from './MemberPasswordDialog'
import { useSendPasswordResetLink } from '../hooks/useMembers'
import type { Member } from '../types/member'

/**
 * Rendre l'accès à un membre qui l'a perdu.
 *
 * **Deux chemins, parce que deux situations.** Le lien par courriel est le bon
 * par défaut : l'administrateur ne connaît jamais le mot de passe, et le membre
 * le choisit lui-même. Mais tous les comptes ne relèvent pas leur boîte — un
 * chauffeur inscrit sous une adresse de service, un poste partagé — et attendre
 * un courriel qui n'arrivera pas bloque la journée.
 *
 * **Le lien passe en premier, et c'est délibéré.** Le second bouton est en
 * retrait : poser un mot de passe le fait connaître à deux personnes au lieu
 * d'une, et le transmettre est alors un problème de plus.
 */
export function MemberAccessCard({ member }: { member: Member }) {
  const { t } = useTranslation()

  const [confirming, setConfirming] = useState(false)
  const [setting, setSetting] = useState(false)

  const sendLink = useSendPasswordResetLink()

  return (
    <PermissionGuard permission="users.reset_password">
      <SectionCard title={t('users.sections.access')} description={t('users.password.hint')}>
        <div className="flex flex-wrap items-center gap-3">
          <Button
            type="button"
            variant="outline"
            disabled={sendLink.isPending}
            onClick={() => setConfirming(true)}
          >
            <Mail className="size-4" aria-hidden />
            {sendLink.isPending ? t('users.password.sending') : t('users.password.sendLink')}
          </Button>

          <Button type="button" variant="ghost" onClick={() => setSetting(true)}>
            <KeyRound className="size-4" aria-hidden />
            {t('users.password.setTitle')}
          </Button>
        </div>

        {/* L'adresse servie, sous les boutons : un lien parti vers une adresse
            périmée ressemble sinon à un envoi réussi. */}
        <p className="mt-3 text-xs text-muted-foreground">
          {t('users.password.willSendTo', { email: member.user.email })}
        </p>
      </SectionCard>

      <ConfirmDialog
        open={confirming}
        onOpenChange={setConfirming}
        title={t('users.password.confirmLinkTitle')}
        description={t('users.password.confirmLinkBody', { email: member.user.email })}
        confirmLabel={t('users.password.sendLink')}
        onConfirm={() => {
          sendLink.mutate(member.id)
          setConfirming(false)
        }}
      />

      <MemberPasswordDialog member={member} open={setting} onOpenChange={setSetting} />
    </PermissionGuard>
  )
}
