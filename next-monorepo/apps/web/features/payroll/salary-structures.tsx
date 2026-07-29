"use client"

import { AlertTriangle, Edit3, Globe, Layers, Plus, Trash2 } from "lucide-react"
import { useState } from "react"
import { Button } from "@workspace/ui/components/button"
import { ComponentDialog } from "@/features/payroll/component-dialog"
import { StructureDialog } from "@/features/payroll/structure-dialog"
import { useSalaryComponents, useSalaryStructureMutations, useSalaryStructures, useSalaryComponentMutations } from "@/hooks/use-payroll-config"
import { usePermissions } from "@/hooks/use-permissions"
import { formatComponentValue } from "@/services/payroll-service"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import {
  salaryCalculationLabels, salaryComponentTypeLabels,
  type SalaryComponent, type SalaryStructure,
} from "@/types/payroll"

/** The pseudo-structure holding components that apply to every payslip. */
const WORKSPACE_WIDE = "workspace-wide"

/**
 * Payroll configuration: the templates employees are assigned to, and the
 * lines their payslips are built from.
 *
 * Master/detail rather than a flat list, because a component only makes sense
 * in the context of the structure it belongs to — or of applying to everyone,
 * which is easy to forget and so gets its own entry at the top.
 */
export function SalaryStructures() {
  const { can } = usePermissions()
  const canEdit = can("payroll.edit")
  const canCreate = can("payroll.create")

  const { data, isLoading } = useSalaryStructures()
  const { remove } = useSalaryStructureMutations()
  const { toast } = useToast()

  const [selected, setSelected] = useState<number | typeof WORKSPACE_WIDE>(WORKSPACE_WIDE)
  const [editingStructure, setEditingStructure] = useState<SalaryStructure | null | undefined>(undefined)

  const structures = data?.data ?? []
  const countries = data?.meta.countries ?? []
  const active = typeof selected === "number" ? structures.find((s) => s.id === selected) : undefined

  const confirmDelete = async (structure: SalaryStructure) => {
    if (!window.confirm(`Delete ${structure.name}?`)) return
    try {
      await remove.mutateAsync(structure.id)
      if (selected === structure.id) setSelected(WORKSPACE_WIDE)
      toast({ tone: "success", title: "Structure deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete structure", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[20rem_1fr]">
      <section className="rounded-2xl border bg-background p-5">
        <div className="flex items-center justify-between gap-3">
          <h2 className="flex items-center gap-2 text-sm font-semibold"><Layers className="size-4" />Structures</h2>
          {canCreate && <Button size="sm" variant="outline" onPress={() => setEditingStructure(null)}><Plus />New</Button>}
        </div>

        <ul className="mt-4 grid gap-1">
          <li>
            <button
              type="button"
              onClick={() => setSelected(WORKSPACE_WIDE)}
              aria-current={selected === WORKSPACE_WIDE}
              className={`w-full rounded-xl border px-3 py-2.5 text-left text-sm transition-colors ${selected === WORKSPACE_WIDE ? "border-primary bg-primary/5" : "hover:bg-muted/40"}`}
            >
              <span className="flex items-center gap-2 font-medium"><Globe className="size-3.5" />Applies to everyone</span>
              <span className="mt-0.5 block text-xs text-muted-foreground">Components with no structure</span>
            </button>
          </li>

          {isLoading
            ? [1, 2].map((row) => <li key={row} className="h-14 animate-pulse rounded-xl bg-muted" />)
            : structures.map((structure) => (
              <li key={structure.id}>
                <button
                  type="button"
                  onClick={() => setSelected(structure.id)}
                  aria-current={selected === structure.id}
                  className={`w-full rounded-xl border px-3 py-2.5 text-left text-sm transition-colors ${selected === structure.id ? "border-primary bg-primary/5" : "hover:bg-muted/40"}`}
                >
                  <span className="font-medium">
                    {structure.name}
                    {!structure.is_active && <span className="ml-2 rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground">Inactive</span>}
                  </span>
                  <span className="mt-0.5 block text-xs text-muted-foreground">
                    {structure.country_label} · {structure.components_count ?? 0} component{structure.components_count === 1 ? "" : "s"}
                    {(structure.assignments_count ?? 0) > 0 && ` · ${structure.assignments_count} assigned`}
                  </span>
                </button>
              </li>
            ))}
        </ul>

        {!isLoading && structures.length === 0 && (
          <p className="mt-3 rounded-xl border border-dashed p-4 text-center text-xs text-muted-foreground">
            No structures yet. Employees can still be paid from a basic salary alone, but a structure is what adds allowances and deductions.
          </p>
        )}
      </section>

      {selected === WORKSPACE_WIDE ? (
        <ComponentPanel
          key="workspace-wide"
          title="Applies to everyone"
          description="These components are added to every payslip in the workspace, whatever structure the employee is on."
          structureId={null}
          currencySymbol={countries[0]?.currency_symbol ?? "$"}
          canEdit={canEdit}
          canCreate={canCreate}
        />
      ) : active ? (
        <ComponentPanel
          key={active.id}
          title={active.name}
          description={active.description ?? `${active.country_label} · paid in ${active.currency_code}`}
          structureId={active.id}
          currencySymbol={active.currency_symbol}
          canEdit={canEdit}
          canCreate={canCreate}
          onEditStructure={canEdit ? () => setEditingStructure(active) : undefined}
          onDeleteStructure={can("payroll.delete") ? () => void confirmDelete(active) : undefined}
          assignmentsCount={active.assignments_count ?? 0}
        />
      ) : (
        <section className="grid place-items-center rounded-2xl border bg-background p-12 text-center text-sm text-muted-foreground">
          Select a structure.
        </section>
      )}

      {editingStructure !== undefined && (
        <StructureDialog
          key={editingStructure?.id ?? "new"}
          structure={editingStructure}
          countries={countries}
          onClose={() => setEditingStructure(undefined)}
        />
      )}
    </div>
  )
}

function ComponentPanel({
  title, description, structureId, currencySymbol, canEdit, canCreate,
  onEditStructure, onDeleteStructure, assignmentsCount = 0,
}: {
  title: string
  description: string
  structureId: number | null
  currencySymbol: string
  canEdit: boolean
  canCreate: boolean
  onEditStructure?: () => void
  onDeleteStructure?: () => void
  assignmentsCount?: number
}) {
  const { data, isLoading } = useSalaryComponents(
    structureId === null ? { global_only: true } : { salary_structure_id: structureId },
  )
  const { remove } = useSalaryComponentMutations()
  const { toast } = useToast()
  const [editing, setEditing] = useState<SalaryComponent | null | undefined>(undefined)

  const components = data?.data ?? []

  const confirmDelete = async (component: SalaryComponent) => {
    if (!window.confirm(`Delete ${component.name}? Payslips already issued keep their lines.`)) return
    try {
      await remove.mutateAsync(component.id)
      toast({ tone: "success", title: "Component deleted" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to delete component", description: getApiErrorMessage(error) })
    }
  }

  return (
    <section className="rounded-2xl border bg-background">
      <div className="flex flex-wrap items-start justify-between gap-3 border-b p-5">
        <div className="min-w-48">
          <h2 className="font-semibold">{title}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{description}</p>
        </div>
        <div className="flex gap-2">
          {onEditStructure && <Button size="sm" variant="outline" onPress={onEditStructure}><Edit3 />Edit</Button>}
          {onDeleteStructure && (
            <Button size="sm" variant="outline" isDisabled={assignmentsCount > 0} onPress={onDeleteStructure}>
              <Trash2 />Delete
            </Button>
          )}
          {canCreate && <Button size="sm" onPress={() => setEditing(null)}><Plus />Add component</Button>}
        </div>
      </div>

      {assignmentsCount > 0 && onDeleteStructure && (
        <p className="border-b bg-muted/30 px-5 py-2.5 text-xs text-muted-foreground">
          {assignmentsCount} salary assignment{assignmentsCount === 1 ? "" : "s"} reference this structure, so it cannot be deleted.
          Deactivate it instead to stop it being assigned to anyone new.
        </p>
      )}

      {isLoading ? (
        <div className="animate-pulse divide-y">
          {[1, 2, 3].map((row) => <div key={row} className="p-5"><div className="h-4 w-48 rounded bg-muted" /></div>)}
        </div>
      ) : components.length === 0 ? (
        <div className="grid place-items-center p-12 text-center">
          <p className="font-medium">No components here</p>
          <p className="mt-1 max-w-sm text-sm text-muted-foreground">
            {structureId === null
              ? "Anything added here lands on every payslip. Most workspaces keep this empty and put components on a structure instead."
              : "Payslips on this structure would show basic salary alone."}
          </p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full min-w-[42rem] text-left text-sm">
            <thead className="border-b bg-muted/30 text-xs text-muted-foreground">
              <tr>
                <th className="px-5 py-3 font-medium">Code</th>
                <th className="px-5 py-3 font-medium">Name</th>
                <th className="px-5 py-3 font-medium">Type</th>
                <th className="px-5 py-3 font-medium">Calculation</th>
                <th className="px-5 py-3 font-medium">Value</th>
                <th className="px-5 py-3"><span className="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody className="divide-y">
              {components.map((component) => (
                <tr key={component.id} className={`transition-colors hover:bg-muted/20 ${component.is_active ? "" : "opacity-60"}`}>
                  <td className="px-5 py-4 font-mono text-xs">{component.code}</td>
                  <td className="px-5 py-4">
                    {component.name}
                    {component.is_statutory && (
                      <span className="ml-2 rounded-full bg-amber-500/10 px-2 py-0.5 text-[0.7rem] font-medium text-amber-600 dark:text-amber-400">
                        Statutory
                      </span>
                    )}
                    {!component.is_active && <span className="ml-2 text-xs text-muted-foreground">Inactive</span>}
                  </td>
                  <td className="px-5 py-4 text-muted-foreground">{salaryComponentTypeLabels[component.type]}</td>
                  <td className="px-5 py-4 text-muted-foreground">{salaryCalculationLabels[component.calculation]}</td>
                  <td className="px-5 py-4 tabular-nums">{formatComponentValue(component, currencySymbol)}</td>
                  <td className="px-5 py-4">
                    <div className="flex justify-end gap-1">
                      {canEdit && (
                        <Button variant="ghost" size="icon-sm" aria-label={`Edit ${component.name}`} onPress={() => setEditing(component)}>
                          <Edit3 />
                        </Button>
                      )}
                      {canEdit && (
                        <Button variant="ghost" size="icon-sm" aria-label={`Delete ${component.name}`} onPress={() => void confirmDelete(component)}>
                          <Trash2 />
                        </Button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {components.some((component) => component.is_statutory) && (
        <p className="flex items-start gap-2 border-t px-5 py-3 text-xs text-muted-foreground">
          <AlertTriangle className="mt-0.5 size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
          Statutory rates seeded from the country are starting defaults, not verified law. Check each against current legislation before
          anyone is paid.
        </p>
      )}

      {editing !== undefined && (
        <ComponentDialog
          key={editing?.id ?? "new"}
          component={editing}
          structureId={structureId}
          currencySymbol={currencySymbol}
          onClose={() => setEditing(undefined)}
        />
      )}
    </section>
  )
}
