import { useQuery } from '@tanstack/react-query'

import { errorMessage } from '@/shared/api/errors'
import { ErrorState, InlineLoader } from '@/shared/components/feedback/States'
import { PageHeader } from '@/shared/components/layout/PageHeader'

import { profileApi } from '../api/profileApi'
import { IdentityForm } from '../components/IdentityForm'
import { PasswordForm } from '../components/PasswordForm'
import { ProviderSheet } from '../components/ProviderSheet'

/**
 * Profil : la fiche du fournisseur (lecture seule), puis le compte connecté —
 * identité et mot de passe. Permission : `provider.profile.view`.
 */
export function ProfilePage() {
  const profile = useQuery({ queryKey: ['profile'], queryFn: profileApi.get })

  if (profile.isPending) return <InlineLoader />
  if (profile.isError) return <ErrorState message={errorMessage(profile.error)} onRetry={() => void profile.refetch()} />

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Mon profil" description={profile.data.user.email} />
      <div className="flex max-w-3xl flex-col gap-6">
        <ProviderSheet provider={profile.data.provider} />
        <IdentityForm user={profile.data.user} onSaved={() => void profile.refetch()} />
        <PasswordForm />
      </div>
    </div>
  )
}
