/** Site client — champs releves sur `CustomerSiteResource`. */
export interface CustomerSite {
  id: string
  customerId: string
  addressId: string
  code: string
  name: string
  siteType: string | null
  isDefault: boolean
  status: string
}
