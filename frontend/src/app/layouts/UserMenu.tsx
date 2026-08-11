import { LogOut, User } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'

import { Avatar, AvatarFallback } from '@/shared/components/ui/avatar'
import { Button } from '@/shared/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/shared/components/ui/dropdown-menu'
import { useAuth } from '@/shared/hooks/useAuth'

function initials(firstName: string, lastName: string): string {
  return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase() || '?'
}

export function UserMenu() {
  const { t } = useTranslation()
  const { user, roles, logout } = useAuth()
  const navigate = useNavigate()

  if (user === null) return null

  const handleLogout = async () => {
    await logout()
    void navigate('/login', { replace: true })
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="rounded-full" aria-label={t('auth.profile')}>
          <Avatar className="size-8">
            <AvatarFallback className="text-xs">
              {initials(user.firstName, user.lastName)}
            </AvatarFallback>
          </Avatar>
        </Button>
      </DropdownMenuTrigger>

      <DropdownMenuContent align="end" className="w-64">
        <DropdownMenuLabel className="font-normal">
          <p className="truncate text-sm font-medium">{user.fullName}</p>
          <p className="truncate text-xs text-muted-foreground">{user.email}</p>
          {roles.length > 0 ? (
            <p className="mt-1 truncate text-xs text-muted-foreground">
              {roles.map((role) => role.name).join(', ')}
            </p>
          ) : null}
        </DropdownMenuLabel>

        <DropdownMenuSeparator />

        <DropdownMenuItem onSelect={() => void navigate('/profile')}>
          <User className="size-4" aria-hidden />
          {t('auth.profile')}
        </DropdownMenuItem>

        <DropdownMenuItem onSelect={() => void handleLogout()} variant="destructive">
          <LogOut className="size-4" aria-hidden />
          {t('auth.logout')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
