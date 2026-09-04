import { ShieldAlert } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { Button } from '@/shared/components/ui/button'

/**
 * Acces refuse.
 *
 * Distincte de la page de connexion, et ce n'est pas un detail : renvoyer un
 * utilisateur authentifie vers la connexion lui laisserait croire que sa
 * session a expire, alors qu'il lui manque seulement un droit.
 */
export function ForbiddenPage() {
  const { t } = useTranslation()

  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-6 text-center">
      <ShieldAlert className="size-12 text-muted-foreground" aria-hidden />
      <h1 className="text-2xl font-semibold">{t('errors.forbidden')}</h1>
      <p className="max-w-md text-sm text-muted-foreground">{t('errors.forbiddenHint')}</p>
      <Button asChild>
        <Link to="/dashboard">{t('nav.dashboard')}</Link>
      </Button>
    </div>
  )
}
