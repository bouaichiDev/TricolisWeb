import { useTranslation } from 'react-i18next'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'

import { AuthLayout } from '@/modules/auth/components/AuthLayout'
import { LoginForm } from '@/modules/auth/components/LoginForm'
import { Button } from '@/shared/components/ui/button'
import { useAuth } from '@/shared/hooks/useAuth'

interface LocationState {
  from?: { pathname: string }
}

/**
 * Page de connexion.
 *
 * Un utilisateur déjà authentifié n'a rien à y faire : il est renvoyé vers la
 * destination qu'il visait, ou le tableau de bord. Sans cette redirection, un
 * retour arrière du navigateur après connexion réafficherait le formulaire.
 *
 * Deux portes de sortie accompagnent le formulaire, et aucune n'est décorative :
 * celui qui a perdu son mot de passe, et celui qui n'a pas encore de compte.
 * Sans elles, les deux écrivent au support.
 */
export function LoginPage() {
  const { t } = useTranslation()
  const { isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const target = (location.state as LocationState | null)?.from?.pathname ?? '/dashboard'

  if (isAuthenticated) return <Navigate to={target} replace />

  return (
    <AuthLayout
      title={t('auth.loginTitle')}
      subtitle={t('auth.loginSubtitle')}
      aside={
        <>
          <div className="relative my-6">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-border" />
            </div>
            <div className="relative flex justify-center">
              <span className="bg-background px-3 text-xs tracking-wider text-muted-foreground uppercase">
                {t('auth.enterpriseAccess')}
              </span>
            </div>
          </div>

          <Button asChild variant="outline" className="h-11 w-full rounded-xl">
            <Link to="/request-access">{t('auth.requestAccess')}</Link>
          </Button>
        </>
      }
    >
      <LoginForm onAuthenticated={() => void navigate(target, { replace: true })} />
    </AuthLayout>
  )
}
