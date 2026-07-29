"use client"

import { History, X } from "lucide-react"
import { useEffect, useState } from "react"
import { useForm, useWatch } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { PayrollProfileForm } from "@/features/payroll/payroll-profile-form"
import { useSalaryStructures } from "@/hooks/use-payroll-config"
import { useSalary, useSalaryMutations } from "@/hooks/use-payroll-runs"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import { salaryAssignmentStatusLabels, type SalaryAssignment } from "@/types/payroll"

const salarySchema = z.object({
  basic_salary: z.string().refine((v) => Number(v) > 0, "Enter a basic salary"),
  country: z.string().min(2, "Choose a country"),
  salary_structure_id: z.string().optional(),
  effective_from: z.string().min(1, "Choose when this takes effect"),
  revision_reason: z.string().max(255).optional(),
})

type SalaryFormValues = z.infer<typeof salarySchema>

/** Module-level, so the reset effect below has no changing dependency. */
function valuesFor(current: SalaryAssignment | null, fallbackCountry: string): SalaryFormValues {
  return {
    basic_salary: current ? String(Number(current.basic_salary)) : "",
    country: current?.country ?? fallbackCountry,
    salary_structure_id: current?.salary_structure_id ? String(current.salary_structure_id) : "",
    // A revision always states its own start date, never inherits one.
    effective_from: "",
    revision_reason: "",
  }
}

/**
 * An employee's salary, and the revisions behind it.
 *
 * Revising never edits the current row: the existing assignment is closed the
 * day before the new one starts, so a payslip already issued can still be
 * explained by the assignment in force when it was generated.
 */
export function SalaryDialog({ employeeId, employeeName, canEdit, onClose }: {
  employeeId: number
  employeeName: string
  canEdit: boolean
  onClose: () => void
}) {
  const { current, history } = useSalary(employeeId)
  const { data: structureData } = useSalaryStructures()
  const { assign, end } = useSalaryMutations(employeeId)
  const { toast } = useToast()
  const [revising, setRevising] = useState(false)
  const [tab, setTab] = useState<"salary" | "bank">("salary")

  const active = current.data ?? null
  const structures = structureData?.data ?? []
  const countries = structureData?.meta.countries ?? []
  const revisions = history.data ?? []

  const endSalary = async () => {
    const date = window.prompt(`Last day ${employeeName} is paid for (YYYY-MM-DD):`)
    if (!date) return
    try {
      await end.mutateAsync(date)
      toast({ tone: "success", title: "Salary ended" })
    } catch (error) {
      toast({ tone: "error", title: "Unable to end salary", description: getApiErrorMessage(error) })
    }
  }

  const showForm = canEdit && (revising || (!current.isLoading && active === null))

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      role="presentation"
      onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}
    >
      <div className="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="salary-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="salary-dialog-title" className="text-lg font-semibold">Salary — {employeeName}</h2>
            <p className="mt-1 text-sm text-muted-foreground">What payroll calculates this employee&rsquo;s payslip from.</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <div className="mt-5 flex gap-2">
          <Button size="sm" variant={tab === "salary" ? "secondary" : "outline"} onPress={() => setTab("salary")}>Salary</Button>
          <Button size="sm" variant={tab === "bank" ? "secondary" : "outline"} onPress={() => setTab("bank")}>Bank &amp; tax</Button>
        </div>

        {tab === "bank" ? (
          <PayrollProfileForm employeeId={employeeId} employeeName={employeeName} canEdit={canEdit} />
        ) : (
        <>
        {current.isLoading ? (
          <div className="mt-6 h-24 animate-pulse rounded-xl bg-muted" />
        ) : active ? (
          <div className="mt-6 rounded-xl border p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-xs font-medium text-muted-foreground">Current salary</p>
                <p className="mt-1 text-2xl font-semibold tabular-nums">
                  {active.currency_symbol}{Number(active.basic_salary).toLocaleString()}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  Basic, from {active.effective_from}
                  {active.structure_name ? ` · ${active.structure_name}` : " · no structure"}
                </p>
              </div>
              {canEdit && !revising && (
                <div className="flex gap-2">
                  <Button size="sm" variant="outline" onPress={() => setRevising(true)}>Revise</Button>
                  <Button size="sm" variant="outline" isDisabled={end.isPending} onPress={() => void endSalary()}>End</Button>
                </div>
              )}
            </div>
            {active.structure_name === null && (
              <p className="mt-3 border-t pt-3 text-xs text-muted-foreground">
                Without a structure the payslip shows basic salary alone, plus any component that applies to everyone.
              </p>
            )}
          </div>
        ) : (
          <p className="mt-6 rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
            {canEdit ? "No salary set. Payroll runs skip this employee until one is." : "No salary set."}
          </p>
        )}

        {showForm && (
          <SalaryForm
            employeeName={employeeName}
            current={active}
            structures={structures}
            countries={countries}
            onCancel={revising ? () => setRevising(false) : undefined}
            onSaved={() => { setRevising(false); toast({ tone: "success", title: active ? "Salary revised" : "Salary set" }) }}
            assign={assign}
          />
        )}

        {revisions.length > 1 && (
          <section className="mt-6">
            <h3 className="flex items-center gap-2 text-sm font-semibold"><History className="size-4" />History</h3>
            <ul className="mt-3 grid gap-2">
              {revisions.map((revision) => (
                <li key={revision.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2 text-sm">
                  <div>
                    <span className="font-medium tabular-nums">{revision.currency_symbol}{Number(revision.basic_salary).toLocaleString()}</span>
                    <span className="ml-2 text-xs text-muted-foreground">
                      {revision.effective_from} → {revision.effective_to ?? "present"}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    {revision.revision_reason && <span className="text-xs text-muted-foreground">{revision.revision_reason}</span>}
                    <span className="rounded-full bg-muted px-2 py-0.5 text-[0.7rem] text-muted-foreground">
                      {salaryAssignmentStatusLabels[revision.status]}
                    </span>
                  </div>
                </li>
              ))}
            </ul>
          </section>
        )}
        </>
        )}
      </div>
    </div>
  )
}

function SalaryForm({ employeeName, current, structures, countries, onCancel, onSaved, assign }: {
  employeeName: string
  current: SalaryAssignment | null
  structures: { id: number; name: string; country: string }[]
  countries: { value: string; label: string; currency_code: string }[]
  onCancel?: () => void
  onSaved: () => void
  assign: ReturnType<typeof useSalaryMutations>["assign"]
}) {
  const { toast } = useToast()

  const fallbackCountry = countries[0]?.value ?? "IN"

  const { register, handleSubmit, control, reset, formState: { errors, isSubmitting } } = useForm<SalaryFormValues>({
    resolver: zodResolver(salarySchema),
    defaultValues: valuesFor(current, fallbackCountry),
  })

  // react-aria's TextField does not read react-hook-form's defaultValues, so
  // without this the current salary never appears in the revision form.
  useEffect(() => { reset(valuesFor(current, fallbackCountry)) }, [reset, current, fallbackCountry])

  const country = useWatch({ control, name: "country" })
  // A structure carries its own country, so offering one from elsewhere would
  // produce a slip in the wrong currency.
  const available = structures.filter((structure) => structure.country === country)

  const onSubmit = async (values: SalaryFormValues) => {
    try {
      await assign.mutateAsync({
        basic_salary: Number(values.basic_salary),
        country: values.country,
        salary_structure_id: values.salary_structure_id ? Number(values.salary_structure_id) : null,
        effective_from: values.effective_from,
        revision_reason: values.revision_reason || null,
      })
      onSaved()
    } catch (error) {
      toast({ tone: "error", title: "Unable to save salary", description: getApiErrorMessage(error) })
    }
  }

  return (
    <form className="mt-6 grid gap-4 rounded-xl border p-4" onSubmit={handleSubmit(onSubmit)} noValidate>
      <p className="text-sm font-medium">{current ? `Revise ${employeeName}’s salary` : "Set salary"}</p>

      <div className="grid gap-4 sm:grid-cols-2">
        <FormField label="Basic salary" type="number" step="0.01" min="0" error={errors.basic_salary?.message} {...register("basic_salary")} />
        <FormField label="Effective from" type="date" error={errors.effective_from?.message} {...register("effective_from")} />
      </div>

      {current && (
        <p className="-mt-2 text-xs text-muted-foreground">
          Must start after {current.effective_from}, when the current salary began. The existing one is closed the day before, leaving no
          gap and no overlap.
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <label className="grid gap-2 text-sm font-medium">
          Country
          <select className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20" {...register("country")}>
            {countries.map((option) => <option key={option.value} value={option.value}>{option.label} ({option.currency_code})</option>)}
          </select>
        </label>

        <label className="grid gap-2 text-sm font-medium">
          Structure
          <select className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20" {...register("salary_structure_id")}>
            <option value="">None — basic salary only</option>
            {available.map((structure) => <option key={structure.id} value={structure.id}>{structure.name}</option>)}
          </select>
        </label>
      </div>

      {available.length === 0 && (
        <p className="-mt-2 text-xs text-muted-foreground">
          No structure defined for this country yet. One can be added under Payroll → Structures; without it the payslip is basic salary alone.
        </p>
      )}

      <FormField label="Reason" placeholder="Annual review, promotion…" error={errors.revision_reason?.message} {...register("revision_reason")} />

      <div className="flex justify-end gap-2">
        {onCancel && <Button type="button" variant="outline" onPress={onCancel}>Cancel</Button>}
        <Button type="submit" isDisabled={isSubmitting || assign.isPending}>
          {isSubmitting || assign.isPending ? "Saving…" : current ? "Save revision" : "Set salary"}
        </Button>
      </div>
    </form>
  )
}
