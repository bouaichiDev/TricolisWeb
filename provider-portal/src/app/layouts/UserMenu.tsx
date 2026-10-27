import { LogOut, User } from 'lucide-react'
import { useNavigate } from 'react-router-dom'

import { useAuth } from '@/app/auth/useAuth'
import { paths } from '@/app/router/paths'
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

/** Menu du compte, identique à celui du back-office. */
export function UserMenu() {
  const { context, logout } = useAuth()
  const navigate = useNavigate()

  if (context === null) return null

  const { user, roles } = context
  const fullName = `${user.firstName} ${user.lastName}`.trim() || user.email
  const initials =
    `${user.firstName.charAt(0)}${user.lastName.charAt(0)}`.toUpperCase() || user.email.charAt(0).toUpperCase()

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="rounded-full" aria-label="Menu du compte">
          <Avatar className="size-8">
            <AvatarFallback className="text-xs">{initials}</AvatarFallback>
          </Avatar>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-64">
        <DropdownMenuLabel className="font-normal">
          <p className="truncate text-sm font-medium">{fullName}</p>
          <p className="text-muted-foreground truncate text-xs">{user.email}</p>
          {roles.length > 0 && (
            <p className="text-muted-foreground mt-1 truncate text-xs">{roles.map((role) => role.name).join(', ')}</p>
          )}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={() => navigate(paths.profile)}>
          <User className="size-4" aria-hidden /> Mon profil
        </DropdownMenuItem>
        <DropdownMenuItem variant="destructive" onSelect={() => void logout()}>
          <LogOut className="size-4" aria-hidden /> Se déconnecter
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
