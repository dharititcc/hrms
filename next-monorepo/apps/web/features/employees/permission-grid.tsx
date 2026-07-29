"use client"

import { ChevronDown, RotateCcw, ShieldCheck } from "lucide-react"
import { useState } from "react"
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
  view: "See own",
  "view-all": "See all",
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
 * Modules collapse to a summary line. Fifteen of them open at once is a wall
 * of ticks nobody reads, and the question being asked is almost always about
 * one module rather than all of them.
 *
 * Actions are per module rather than a uniform matrix: nobody pays an
 * announcement, and a grid full of impossible combinations is harder to read
 * than a short one.
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
  const [open, setOpen] = useState<string | null>(null)

  const grid = data?.grid ?? []
  // Nobody may hand out access they do not hold; the server refuses it, so the
  // interface should not offer it either.
  const mine = new Set(data?.permissions ?? [])

  if (grid.length === 0) return null

  const set = (permissions: Permission[], on: boolean) => {
    const next = new Set<string>(granted)
    for (const permission of permissions) {
      if (!mine.has(permission)) continue
      if (on) next.add(permission)
      else next.delete(permission)
    }
    onChange(next)
  }

  const changed = [...granted].filter((permission) => !roleDefaults.has(permission)).length
    + [...roleDefaults].filter((permission) => !granted.has(permission)).length

  return (
    <div className="grid gap-2">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div>
          <p className="text-sm font-medium">Access</p>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {changed === 0
              ? `Follows the ${role} role. Open a module to adjust it for this person.`
              : `${changed} ${changed === 1 ? "change" : "changes"} from the ${role} role.`}
          </p>
        </div>
        {changed > 0 && (
          <Button type="button" variant="outline" size="sm" onPress={() => onChange(null)}>
            <RotateCcw />Follow role
          </Button>
        )}
      </div>

      <div className="divide-y overflow-hidden rounded-xl border">
        {grid.map(({ module, actions }) => {
          const permissions = actions.map((action) => `${module}.${action}` as Permission)
          const on = permissions.filter((permission) => granted.has(permission))
          const differs = permissions.some((permission) => granted.has(permission) !== roleDefaults.has(permission))
          const expanded = open === module
          const grantable = permissions.filter((permission) => mine.has(permission))

          return (
            <div key={module}>
              <button
                type="button"
                aria-expanded={expanded}
                onClick={() => setOpen(expanded ? null : module)}
                className="flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors hover:bg-muted/40"
              >
                <ChevronDown className={`size-3.5 shrink-0 text-muted-foreground transition-transform ${expanded ? "" : "-rotate-90"}`} />

                <span className="flex-1 text-sm font-medium">{MODULE_LABELS[module] ?? module}</span>

                {differs && (
                  <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[0.7rem] font-medium text-primary">Adjusted</span>
                )}

                <span className="w-24 shrink-0 text-right text-xs text-muted-foreground">
                  {on.length === 0
                    ? "No access"
                    : on.length === permissions.length ? "Everything" : `${on.length} of ${permissions.length}`}
                </span>
              </button>

              {expanded && (
                <div className="border-t bg-muted/20 px-3 py-3">
                  <div className="mb-2 flex gap-2">
                    <Button type="button" variant="outline" size="xs" onPress={() => set(grantable, true)}>Select all</Button>
                    <Button type="button" variant="outline" size="xs" onPress={() => set(grantable, false)}>Clear</Button>
                  </div>

                  <div className="grid gap-1 sm:grid-cols-2">
                    {actions.map((action) => {
                      const permission = `${module}.${action}` as Permission
                      const allowed = mine.has(permission)
                      const isOn = granted.has(permission)

                      return (
                        <label
                          key={action}
                          title={allowed ? undefined : "You cannot grant access you do not hold yourself"}
                          className={`flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition-colors ${
                            allowed ? "cursor-pointer hover:bg-background" : "cursor-not-allowed opacity-40"
                          }`}
                        >
                          <input
                            type="checkbox"
                            className="size-4 shrink-0 rounded border"
                            checked={isOn}
                            disabled={!allowed}
                            onChange={() => set([permission], !isOn)}
                          />
                          <span className={isOn !== roleDefaults.has(permission) ? "font-medium" : "text-muted-foreground"}>
                            {ACTION_LABELS[action] ?? action}
                          </span>
                        </label>
                      )
                    })}
                  </div>

                  {actions.includes("view-all") && (
                    <p className="mt-2 flex items-start gap-1.5 text-xs text-muted-foreground">
                      <ShieldCheck className="mt-0.5 size-3 shrink-0" />
                      {/* The one distinction worth spelling out: it is the
                          difference between your own payslip and everybody's. */}
                      <span>
                        <strong className="font-medium">See own</strong> is this person&rsquo;s own records;{" "}
                        <strong className="font-medium">See all</strong> is everybody&rsquo;s.
                      </span>
                    </p>
                  )}
                </div>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
