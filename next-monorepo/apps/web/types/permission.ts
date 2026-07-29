export type PermissionModule =
  | "staff" | "attendance" | "leave" | "payroll" | "expenses"
  | "tasks" | "meetings" | "projects" | "recruitment" | "performance"
  | "assets" | "announcements" | "reports" | "attachments" | "activity"

export type PermissionAction =
  | "view" | "create" | "edit" | "delete"
  | "assign" | "approve" | "comment" | "upload" | "export"

/** Mirrors the server's module.action strings, so typos fail to compile. */
export type Permission = `${PermissionModule}.${PermissionAction}`

export type WorkspacePermissions = {
  role: "admin" | "manager" | "employee" | "client"
  is_workspace_owner: boolean
  permissions: Permission[]
  modules: PermissionModule[]
  actions: PermissionAction[]
}
