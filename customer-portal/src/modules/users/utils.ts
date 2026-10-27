import type { MemberStatus, PortalUser } from '@/shared/api/types'

export function displayName(member: PortalUser): string {
  return `${member.user.firstName} ${member.user.lastName}`.trim() || member.user.email
}

export const STATUS_OPTIONS: { value: MemberStatus; label: string }[] = [
  { value: 'active', label: 'Actif' },
  { value: 'invited', label: 'Invité' },
  { value: 'suspended', label: 'Suspendu' },
  { value: 'disabled', label: 'Désactivé' },
]
