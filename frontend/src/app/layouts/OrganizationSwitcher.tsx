import { Check, ChevronsUpDown } from 'lucide-react'
import { useTranslation } from 'react-i18next'

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu'
import { Badge } from '@/shared/components/ui/badge'
import { Button } from '@/shared/components/ui/button'
import { useAuth } from '@/shared/hooks/useAuth'

/**
 * Sélecteur d'organisation active.
 *
 * Changer d'organisation change l'en-tête `X-Organization-Id`, donc **tout**
 * ce que l'API renverra ensuite, ainsi que les permissions — elles sont
 * portées par l'appartenance, pas par le compte. `switchOrganization` invalide
 * le cache en conséquence.
 *
 * Avec une seule appartenance, le sélecteur devient un simple libellé : un menu
 * à un choix n'est pas un choix.
 */
export function OrganizationSwitcher() {
  const { t } = useTranslation()
  const { memberships, membership, switchOrganization } = useAuth()

  if (membership === null) return null

  if (memberships.length === 1) {
    return (
      <div className="flex min-w-0 items-center gap-2 px-2">
        <span className="truncate text-sm font-medium">{membership.name}</span>
        {membership.isOwner ? (
          <Badge variant="secondary" className="shrink-0">
            {t('organization.owner')}
          </Badge>
        ) : null}
      </div>
    )
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="outline"
          className="w-full max-w-64 justify-between"
          aria-label={t('organization.switch')}
        >
          <span className="truncate">{membership.name}</span>
          <ChevronsUpDown className="size-4 shrink-0 opacity-60" aria-hidden />
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" className="w-64">
        <DropdownMenuLabel>{t('organization.switcher')}</DropdownMenuLabel>
        <DropdownMenuSeparator />

        {memberships.map((item) => (
          <DropdownMenuItem
            key={item.id}
            onSelect={() => switchOrganization(item.id)}
            className="gap-2"
          >
            <Check
              className={item.id === membership.id ? 'size-4 opacity-100' : 'size-4 opacity-0'}
              aria-hidden
            />
            <span className="min-w-0 flex-1 truncate">{item.name}</span>
            {item.isOwner ? (
              <Badge variant="secondary" className="shrink-0 text-xs">
                {t('organization.owner')}
              </Badge>
            ) : null}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
