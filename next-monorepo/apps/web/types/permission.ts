export type PermissionModule =
  | "staff" | "attendance" | "leave" | "payroll" | "expenses"
  | "tasks" | "meetings" | "projects" | "recruitment" | "performance"
  | "assets" | "announcements" | "reports" | "attachments" | "activity"

export type PermissionAction =
  /** On personal modules, "view" means your own records only. */
  | "view"
  /** See other people's records, not just your own. */
  | "view-all"
  | "create" | "edit" | "delete"
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
