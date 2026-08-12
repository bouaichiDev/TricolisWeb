import { Route } from 'react-router-dom'

import { guarded } from './guarded'
import { AuditLogPage } from '@/modules/audit/pages/AuditLogPage'
import { OrganizationCreatePage } from '@/modules/organizations/pages/OrganizationCreatePage'
import { OrganizationDetailPage } from '@/modules/organizations/pages/OrganizationDetailPage'
import { OrganizationEditPage } from '@/modules/organizations/pages/OrganizationEditPage'
import { OrganizationListPage } from '@/modules/organizations/pages/OrganizationListPage'
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
    key="users"
    path="/users"
    element={guarded('users.view', <UserListPage />)}
  />,
  <Route
    key="user-create"
    path="/users/create"
    element={guarded('users.create', <UserCreatePage />)}
  />,
  <Route
    key="user-detail"
    path="/users/:id"
    element={guarded('users.view', <UserDetailPage />)}
  />,
  <Route
    key="user-edit"
    path="/users/:id/edit"
    element={guarded('users.update', <UserEditPage />)}
  />,

  <Route key="roles" path="/roles" element={guarded('roles.view', <RoleListPage />)} />,
  <Route
    key="role-create"
    path="/roles/create"
    element={guarded('roles.create', <RoleCreatePage />)}
  />,
  <Route
    key="role-detail"
    path="/roles/:id"
    element={guarded('roles.view', <RoleDetailPage />)}
  />,
  <Route
    key="role-edit"
    path="/roles/:id/edit"
    element={guarded('roles.update', <RoleEditPage />)}
  />,

  <Route
    key="organizations"
    path="/organizations"
    element={guarded('organizations.view', <OrganizationListPage />)}
  />,
  <Route
    key="organization-create"
    path="/organizations/create"
    element={guarded('organizations.create', <OrganizationCreatePage />)}
  />,
  <Route
    key="organization-detail"
    path="/organizations/:id"
    element={guarded('organizations.view', <OrganizationDetailPage />)}
  />,
  <Route
    key="organization-edit"
    path="/organizations/:id/edit"
    element={guarded('organizations.update', <OrganizationEditPage />)}
  />,

  <Route key="audit" path="/audit" element={guarded('audit.view', <AuditLogPage />)} />,
]
