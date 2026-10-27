import type { ReactNode } from 'react'

import { Alert, AlertDescription } from '@/shared/components/ui/alert'

import { AuthLayout } from './AuthLayout'

interface AuthScreenProps {
  title: string
  description?: string
  error?: string | null
  notice?: string | null
  children: ReactNode
  footer?: ReactNode
}

/**
 * Écran public secondaire (mot de passe oublié, réinitialisation, activation) :
 * même mise en page que la connexion, avec les messages en tête.
 */
export function AuthScreen({ title, description, error, notice, children, footer }: AuthScreenProps) {
  return (
    <AuthLayout
      title={title}
      subtitle={description}
      aside={footer && <div className="text-muted-foreground mt-6 text-center text-sm [&_a]:text-primary [&_a]:font-semibold [&_a:hover]:underline">{footer}</div>}
    >
      <div className="flex flex-col gap-4">
        {error && (
          <Alert variant="destructive" role="alert">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}
        {notice && (
          <Alert role="status">
            <AlertDescription>{notice}</AlertDescription>
          </Alert>
        )}
        {children}
      </div>
    </AuthLayout>
  )
}
