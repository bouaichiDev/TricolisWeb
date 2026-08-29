import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { PricingVariablesPage } from '@/modules/pricing/pages/PricingVariablesPage'
import { CommunicationTemplateListPage } from '@/modules/communications/pages/CommunicationTemplateListPage'
import { ApiConfigurationListPage } from '@/modules/integrations/pages/ApiConfigurationListPage'
import { JourneyConfigurationPage } from '@/modules/tracking/pages/JourneyConfigurationPage'
import { AuditLogPage } from '@/modules/audit/pages/AuditLogPage'
import { MyOrganizationPage } from '@/modules/organizations/pages/MyOrganizationPage'
import { OrganizationCreatePage } from '@/modules/organizations/pages/OrganizationCreatePage'
import { OrganizationDetailPage } from '@/modules/organizations/pages/OrganizationDetailPage'
import { OrganizationEditPage } from '@/modules/organizations/pages/OrganizationEditPage'
import { OrganizationListPage } from '@/modules/organizations/pages/OrganizationListPage'
import { StatusListPage } from '@/modules/statuses/pages/StatusListPage'
import { RoleCreatePage } from '@/modules/roles/pages/RoleCreatePage'
import { RoleDetailPage } from '@/modules/roles/pages/RoleDetailPage'
import { RoleEditPage } from '@/modules/roles/pages/RoleEditPage'
import { RoleListPage } from '@/modules/roles/pages/RoleListPage'
import { UserCreatePage } from '@/modules/users/pages/UserCreatePage'
import { UserDetailPage } from '@/modules/users/pages/UserDetailPage'
import { UserEditPage } from '@/modules/users/pages/UserEditPage'
import { UserListPage } from '@/modules/users/pages/UserListPage'

/**
 * Administration : organisations, utilisateurs, rôles, audit.
 *
 * Les routes `/users/:id` portent l'identifiant du **rattachement**, pas celui
 * du compte : c'est la ressource que manipule `/organization-users`, et celle
 * qui porte les rôles.
 *
 * L'audit n'a ni création ni édition : `audit-logs` n'expose qu'un `index`.
 */
export const adminRoutes = [
  <Route
    key="api-configurations"
    path="/api-configurations"
    element={guarded('api_configurations.view', <ApiConfigurationListPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="journey"
    path="/journey"
    element={guarded('tracking_event_definitions.view', <JourneyConfigurationPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="communication-templates"
    path="/communication-templates"
    element={guarded('communication_templates.view', <CommunicationTemplateListPage />, {
      organizationOnly: true,
    })}
  />,

  <Route
    key="users"
    path="/users"
    element={guarded('users.view', <UserListPage />, { organizationOnly: true })}
  />,
  <Route
    key="user-create"
    path="/users/create"
    element={guarded('users.create', <UserCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="user-detail"
    path="/users/:id"
    element={guarded('users.view', <UserDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="user-edit"
    path="/users/:id/edit"
    element={guarded('users.update', <UserEditPage />, { organizationOnly: true })}
  />,

  <Route key="roles" path="/roles" element={guarded('roles.view', <RoleListPage />, { organizationOnly: true })} />,
  <Route
    key="role-create"
    path="/roles/create"
    element={guarded('roles.create', <RoleCreatePage />, { organizationOnly: true })}
  />,
  <Route
    key="role-detail"
    path="/roles/:id"
    element={guarded('roles.view', <RoleDetailPage />, { organizationOnly: true })}
  />,
  <Route
    key="role-edit"
    path="/roles/:id/edit"
    element={guarded('roles.update', <RoleEditPage />, { organizationOnly: true })}
  />,

  // L'annuaire global et la création relèvent de la plateforme. Sans
  // `platformOnly`, masquer le bouton suffisait à croire la route protégée :
  // saisir /organizations/create dans la barre d'adresse ouvrait le formulaire.
  <Route
    key="organizations"
    path="/organizations"
    element={guarded('organizations.view', <OrganizationListPage />, { platformOnly: true })}
  />,
  <Route
    key="organization-create"
    path="/organizations/create"
    element={guarded('organizations.create', <OrganizationCreatePage />, { platformOnly: true })}
  />,
  <Route
    key="organization-detail"
    path="/organizations/:id"
    element={guarded('organizations.view', <OrganizationDetailPage />, { platformOnly: true })}
  />,
  <Route
    key="organization-edit"
    path="/organizations/:id/edit"
    element={guarded('organizations.update', <OrganizationEditPage />, { platformOnly: true })}
  />,

  // Référentiel des statuts : commun à toute la plateforme. Un organisme le
  // lit à travers les écrans qui affichent un libellé, il ne le règle pas.
  <Route
    key="statuses"
    path="/statuses"
    element={guarded('statuses.view', <StatusListPage />, { platformOnly: true })}
  />,

  // Point d'entrée d'un administrateur d'organisme : sa propre organisation,
  // désignée par l'appartenance active et non par l'URL.
  <Route
    key="my-organization"
    path="/my-organization"
    element={guarded('organizations.view', <MyOrganizationPage />, { organizationOnly: true })}
  />,

  <Route key="audit" path="/audit" element={guarded('audit.view', <AuditLogPage />, { organizationOnly: true })} />,
  // Le catalogue des variables tarifaires : plateforme, comme le referentiel
  // des statuts. Un organisme le lit par l'API, il ne le regle pas.
  <Route
    key="pricing-variables"
    path="/pricing-variables"
    element={guarded('pricing_variables.manage', <PricingVariablesPage />)}
  />,
]
