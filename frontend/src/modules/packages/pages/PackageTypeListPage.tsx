import { ReferentialListPage } from './ReferentialListPage'

/** Types de colis — palette, carton, bac. Gouverné par `packages.*`. */
export function PackageTypeListPage() {
  return <ReferentialListPage kind="package-types" namespace="packageTypes" />
}
