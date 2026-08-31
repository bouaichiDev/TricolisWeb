import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'

import { usePermissions as usePermissionCatalogue } from '@/modules/roles/hooks/useRoles'
import { SearchInput } from '@/shared/components/data/SearchInput'
import { Checkbox } from '@/shared/components/ui/checkbox'
import { Label } from '@/shared/components/ui/label'

interface CustomerApiPermissionsEditorProps {
  value: string[]
  onChange: (value: string[]) => void
  error?: string
}

/**
 * Ce que la clé d'un client a le droit de faire.
 *
 * Les codes proposés sont ceux du **référentiel RBAC**, parce que le backend
 * n'en a pas d'autre : `ApiPermissionValidator` valide chaque code contre la
 * table `permissions`. Le §27 réserve l'éditeur JSON au cas où aucune liste
 * blanche n'existerait — ce n'est pas le cas ici.
 *
 * **Cinq modules sont refusés à une clé client** — organisations, utilisateurs,
 * rôles, permissions, abonnements — mais aucune route ne publie cette liste.
 * Elle n'est donc pas recopiée : le serveur refuse ces codes en 422 et son
 * message est affiché. Une liste dupliquée ici divergerait au premier
 * changement, et le §105 interdit d'inventer une vérité métier.
 *
 * Le regroupement suit `menuSection`, comme le formulaire de rôle : les 48
 * modules techniques produiraient 48 blocs dans lesquels personne ne compose.
 */
export function CustomerApiPermissionsEditor({
  value,
  onChange,
  error,
}: CustomerApiPermissionsEditorProps) {
  const { t } = useTranslation()
  const { data: permissions, isPending } = usePermissionCatalogue()
  const [search, setSearch] = useState('')

  const groups = useMemo(() => {
    const needle = search.trim().toLowerCase()

    const matching = (permissions ?? []).filter(
      (permission) =>
        needle === '' ||
        permission.code.toLowerCase().includes(needle) ||
        permission.name.toLowerCase().includes(needle),
    )

    const bySection = new Map<string, typeof matching>()

    for (const permission of matching) {
      const section = permission.menuSection ?? 'other'
      bySection.set(section, [...(bySection.get(section) ?? []), permission])
    }

    return [...bySection.entries()]
  }, [permissions, search])

  const toggle = (code: string) => {
    onChange(value.includes(code) ? value.filter((item) => item !== code) : [...value, code])
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <Label>{t('integrations.api.permissions')}</Label>
        <span className="text-xs text-muted-foreground">
          {t('integrations.api.permissionsCount', { count: value.length })}
        </span>
      </div>

      <SearchInput
        value={search}
        onChange={setSearch}
        label={t('integrations.api.searchPermissions')}
        placeholder={t('integrations.api.searchPermissions')}
      />

      {isPending ? (
        <p className="text-sm text-muted-foreground">{t('common.loading')}</p>
      ) : (
        <div className="max-h-80 overflow-y-auto rounded-lg border">
          {groups.map(([section, rows]) => (
            <div key={section} className="border-b p-3 last:border-b-0">
              <p className="mb-2 text-xs font-medium uppercase text-muted-foreground">
                {t(`menuSections.${section}`, { defaultValue: section })}
              </p>

              <ul className="grid gap-1.5 sm:grid-cols-2">
                {rows.map((permission) => (
                  <li key={permission.id} className="flex items-start gap-2">
                    <Checkbox
                      id={`perm-${permission.id}`}
                      checked={value.includes(permission.code)}
                      onCheckedChange={() => toggle(permission.code)}
                    />
                    <Label
                      htmlFor={`perm-${permission.id}`}
                      className="cursor-pointer font-normal leading-tight"
                    >
                      {permission.name}
                      <span className="block font-mono text-[11px] text-muted-foreground">
                        {permission.code}
                      </span>
                    </Label>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}

      {error ? (
        <p className="text-sm text-destructive">{t(error, { defaultValue: error })}</p>
      ) : (
        <p className="text-xs text-muted-foreground">
          {value.length === 0
            ? t('integrations.api.permissionsEmpty')
            : t('integrations.api.permissionsHint')}
        </p>
      )}
    </div>
  )
}
