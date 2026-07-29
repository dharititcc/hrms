"use client"

import { RotateCcw } from "lucide-react"
import { Button } from "@workspace/ui/components/button"
import { usePermissions } from "@/hooks/use-permissions"
import type { EmployeeRole } from "@/types/employee"
import type { Permission } from "@/types/permission"

const MODULE_LABELS: Record<string, string> = {
  employees: "Employees",
  attendance: "Attendance",
  leave: "Leave",
  payroll: "Payroll",
  expenses: "Expenses",
  tasks: "Tasks",
  meetings: "Meetings",
  projects: "Projects",
  recruitment: "Recruitment",
  performance: "Performance",
  assets: "Assets",
  announcements: "Announcements",
  reports: "Reports",
  attachments: "Attachments",
  activity: "Activity",
}

const ACTION_LABELS: Record<string, string> = {
  view: "Own",
  "view-all": "All",
  create: "Create",
  edit: "Edit",
  delete: "Delete",
  assign: "Assign",
  approve: "Approve",
  comment: "Comment",
  upload: "Upload",
  export: "Export",
  generate: "Generate",
  pay: "Pay",
  download: "Download",
}

/**
 * What one employee may do, module by module.
 *
 * Starts from what their role grants and can be adjusted per person. Only the
 * differences are stored, so somebody left alone stays tied to their role and
 * follows it if the role's definition ever changes.
 *
 * Actions are per module rather than a uniform matrix: nobody pays an
 * announcement, and offering the combination would only invite the question.
 */
export function PermissionGrid({ role, granted, roleDefaults, onChange }: {
  role: EmployeeRole
  granted: Set<string>
  /** What the selected role grants, so departures can be shown as such. */
  roleDefaults: Set<string>
  /** null puts the employee back to following their role. */
  onChange: (next: Set<string> | null) => void
}) {
  const { data } = usePermissions()
  const grid = data?.grid ?? []
  // Nobody may hand out access they do not hold; the server refuses it, so
  // the interface should not offer it either.
  const mine = new Set(data?.permissions ?? [])

  if (grid.length === 0) return null

  const toggle = (permission: string) => {
    const next = new Set(granted)
    if (next.has(permission)) next.delete(permission)
    else next.add(permission)
    onChange(next)
  }

  const differs = [...granted].some((p) => !roleDefaults.has(p))
    || [...roleDefaults].some((p) => !granted.has(p))

  return (
    <div className="grid gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-sm font-medium">Permissions</p>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {differs
              ? "Adjusted for this person. Anything left as the role grants it stays tied to the role."
              : `Exactly what a ${role} gets. Tick to adjust for this person only.`}
          </p>
        </div>
        {differs && (
          <Button type="button" variant="outline" size="sm" onPress={() => onChange(null)}>
            <RotateCcw />Reset to role
          </Button>
        )}
      </div>

      <div className="max-h-64 overflow-y-auto rounded-xl border">
        <table className="w-full text-left text-sm">
          <tbody className="divide-y">
            {grid.map(({ module, actions }) => (
              <tr key={module} className="align-top">
                <th scope="row" className="w-36 px-3 py-2 text-xs font-medium">
                  {MODULE_LABELS[module] ?? module}
                </th>
                <td className="px-3 py-2">
                  <div className="flex flex-wrap gap-x-3 gap-y-1.5">
                    {actions.map((action) => {
                      const permission = `${module}.${action}` as Permission
                      const fromRole = roleDefaults.has(permission)
                      const allowed = mine.has(permission)

                      return (
                        <label
                          key={action}
                          className={`inline-flex items-center gap-1.5 text-xs ${allowed ? "" : "opacity-40"}`}
                          title={allowed ? undefined : "You cannot grant access you do not hold yourself"}
                        >
                          <input
                            type="checkbox"
                            className="size-3.5 rounded border"
                            checked={granted.has(permission)}
                            disabled={!allowed}
                            onChange={() => toggle(permission)}
                          />
                          <span className={granted.has(permission) !== fromRole ? "font-medium text-foreground" : "text-muted-foreground"}>
                            {ACTION_LABELS[action] ?? action}
                          </span>
                        </label>
                      )
                    })}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p className="text-xs text-muted-foreground">
        {/* "view" versus "view-all" is the one distinction worth spelling out:
            it is the difference between your own payslip and everybody's. */}
        On attendance, leave, payroll and expenses, <span className="font-medium text-foreground">Own</span> means this person&rsquo;s own
        records and <span className="font-medium text-foreground">All</span> means everybody&rsquo;s.
      </p>
    </div>
  )
}
