import { ArrowLeft, CheckCircle2 } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { AccessRequestForm } from '../components/AccessRequestForm'
import { AuthLayout } from '@/modules/auth/components/AuthLayout'

/**
 * Demander un accès entreprise.
 *
 * **Rien n'est créé ici.** La demande est enregistrée, la plateforme en est
 * avertie, et c'est elle qui décide : l'organisation et son compte
 * administrateur ne naissent qu'à l'acceptation. C'est toute la différence avec
 * une inscription libre, où trois champs suffisaient à obtenir un back-office.
 *
 * L'écran d'arrivée le dit franchement — « vous serez recontacté » — plutôt que
 * d'annoncer un compte que personne n'a encore ouvert.
 */
export function AccessRequestPage() {
  const { t } = useTranslation()
  const [submitted, setSubmitted] = useState(false)

  return (
    <AuthLayout
      title={t('accessRequests.public.title')}
      subtitle={t('accessRequests.public.subtitle')}
      aside={
        <Link
          to="/login"
          className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
        >
          <ArrowLeft className="size-4" aria-hidden />
          {t('auth.reset.backToLogin')}
        </Link>
      }
    >
      {submitted ? (
        <div className="flex items-start gap-3 rounded-xl border border-success/25 bg-success/10 p-4">
          <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" aria-hidden />
          <p className="text-sm">{t('accessRequests.public.sent')}</p>
        </div>
      ) : (
        <AccessRequestForm onSubmitted={() => setSubmitted(true)} />
      )}
    </AuthLayout>
  )
}
