import { AlertCircle } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import { Button } from '@/shared/components/ui/button'

interface ErrorStateProps {
  error: Error
  onRetry?: () => void
}

export function ErrorState({ error, onRetry }: ErrorStateProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border bg-card py-12 text-center">
      <AlertCircle className="size-8 text-destructive" aria-hidden />
      <div>
        <p className="font-medium">{t('errors.title')}</p>
        <p className="mt-1 max-w-md text-sm text-muted-foreground">{error.message}</p>
      </div>
      {onRetry ? (
        <Button variant="outline" onClick={onRetry}>
          {t('common.retry')}
        </Button>
      ) : null}
    </div>
  )
}
