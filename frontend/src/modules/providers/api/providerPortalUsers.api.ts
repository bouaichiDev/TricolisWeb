import { api } from '@/shared/api/client'
import type { ApiResource } from '@/shared/api/types'

import type {
  ProviderPortalInvitePayload,
  ProviderPortalRole,
  ProviderPortalUser,
  ProviderPortalUserAction,
} from '../types/providerPortalUser'

const base = (providerId: string) => `/providers/${providerId}/portal-users`

export const providerPortalUsersApi = {
  list: (providerId: string) =>
    api.get<ApiResource<ProviderPortalUser[]>>(base(providerId)).then((response) => response.data),

  roles: (providerId: string) =>
    api.get<ApiResource<ProviderPortalRole[]>>(`/providers/${providerId}/portal-roles`).then((response) => response.data),

  invite: (providerId: string, payload: ProviderPortalInvitePayload) =>
    api.post<ApiResource<ProviderPortalUser>>(`${base(providerId)}/invite`, payload).then((response) => response.data),

  changeRole: (providerId: string, memberId: string, roleId: string) =>
    api.patch<ApiResource<ProviderPortalUser>>(`${base(providerId)}/${memberId}`, { roleId }).then((response) => response.data),

  act: (providerId: string, memberId: string, action: ProviderPortalUserAction) =>
    api.post<ApiResource<ProviderPortalUser>>(`${base(providerId)}/${memberId}/${action}`).then((response) => response.data),
}
