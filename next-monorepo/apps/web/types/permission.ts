export type PermissionModule =
  | "employees" | "attendance" | "leave" | "payroll" | "expenses"
  | "tasks" | "meetings" | "projects" | "recruitment" | "performance"
  | "assets" | "announcements" | "reports" | "attachments" | "activity"

export type PermissionAction =
  /** On personal modules, "view" means your own records only. */
  | "view"
  /** See other people's records, not just your own. */
  | "view-all"
  | "create" | "edit" | "delete"
  | "assign" | "approve" | "comment" | "upload" | "export"
  /** Run a process that produces records, such as a monthly payroll. */
  | "generate"
  /** Record money actually leaving the business. */
  | "pay"
  /** Retrieve a generated document, such as a payslip PDF. */
  | "download"

/** Mirrors the server's module.action strings, so typos fail to compile. */
export type Permission = `${PermissionModule}.${PermissionAction}`

export type WorkspacePermissions = {
  role: "admin" | "manager" | "employee" | "client"
  is_workspace_owner: boolean
  /** The caller's own employee record, or null for the workspace owner. */
  employee_id: number | null
  permissions: Permission[]
  modules: PermissionModule[]
  actions: PermissionAction[]
  /** Only the actions that mean something on each module. */
  grid: { module: PermissionModule; actions: PermissionAction[] }[]
  /** What each role grants before any per-employee adjustment. */
  role_defaults: Record<string, Permission[]>
}
