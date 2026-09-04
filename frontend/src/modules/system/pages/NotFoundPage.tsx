import { FileQuestion } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'

import { Button } from '@/shared/components/ui/button'

export function NotFoundPage() {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col items-center justify-center gap-4 py-24 text-center">
      <FileQuestion className="size-12 text-muted-foreground" aria-hidden />
      <h1 className="text-2xl font-semibold">{t('errors.notFound')}</h1>
      <p className="max-w-md text-sm text-muted-foreground">{t('errors.notFoundHint')}</p>
      <Button asChild variant="outline">
        <Link to="/dashboard">{t('nav.dashboard')}</Link>
      </Button>
    </div>
  )
}
