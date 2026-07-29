"use client"

import { X } from "lucide-react"
import { useEffect } from "react"
import { useForm, useWatch } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { useSalaryComponentMutations } from "@/hooks/use-payroll-config"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import {
  salaryCalculationLabels, salaryComponentTypeLabels,
  type SalaryCalculation, type SalaryComponent, type SalaryComponentType,
} from "@/types/payroll"

const componentSchema = z.object({
  code: z.string().trim().regex(/^[A-Za-z][A-Za-z0-9_]*$/, "Letters, digits and underscores, starting with a letter"),
  name: z.string().trim().min(2, "Give the component a name"),
  type: z.enum(["earning", "deduction", "employer_contribution"]),
  calculation: z.enum(["fixed", "percent_of_basic", "percent_of_gross", "manual"]),
  value: z.string(),
  sort_order: z.string().optional(),
  is_taxable: z.boolean().optional(),
  is_statutory: z.boolean().optional(),
  is_active: z.boolean().optional(),
}).refine((values) => values.calculation === "manual" || Number(values.value) >= 0, {
  message: "Enter an amount of zero or more", path: ["value"],
}).refine(
  // Mirrors the server: gross includes earnings, so an earning derived from
  // gross would depend on itself. The engine throws rather than guess.
  (values) => !(values.type === "earning" && values.calculation === "percent_of_gross"),
  { message: "An earning cannot be a percentage of gross", path: ["calculation"] },
).refine(
  (values) => !["percent_of_basic", "percent_of_gross"].includes(values.calculation) || Number(values.value) <= 100,
  { message: "A percentage cannot exceed 100", path: ["value"] },
)

type ComponentFormValues = z.infer<typeof componentSchema>

/** Module-level, so the reset effect below has no changing dependency. */
function valuesFor(component: SalaryComponent | null): ComponentFormValues {
  return {
    code: component?.code ?? "",
    name: component?.name ?? "",
    type: component?.type ?? "earning",
    calculation: component?.calculation ?? "fixed",
    value: component ? String(Number(component.value)) : "0",
    sort_order: String(component?.sort_order ?? 0),
    is_taxable: component?.is_taxable ?? true,
    is_statutory: component?.is_statutory ?? false,
    is_active: component?.is_active ?? true,
  }
}

export function ComponentDialog({ component, structureId, currencySymbol, onClose }: {
  component: SalaryComponent | null
  /** null means workspace-wide: applies to every payslip regardless of structure. */
  structureId: number | null
  currencySymbol: string
  onClose: () => void
}) {
  const { create, update } = useSalaryComponentMutations()
  const { toast } = useToast()
  const editing = Boolean(component)

  const { register, handleSubmit, control, reset, formState: { errors, isSubmitting } } = useForm<ComponentFormValues>({
    resolver: zodResolver(componentSchema),
    defaultValues: valuesFor(component),
  })

  // react-aria's TextField does not read react-hook-form's defaultValues, so
  // without this an edit dialog opens showing placeholders instead of values.
  useEffect(() => { reset(valuesFor(component)) }, [reset, component])

  const type = useWatch({ control, name: "type" }) as SalaryComponentType
  const calculation = useWatch({ control, name: "calculation" }) as SalaryCalculation

  // Offering the impossible combination and then rejecting it is worse than
  // not offering it, so the option disappears for earnings.
  const calculations = (Object.keys(salaryCalculationLabels) as SalaryCalculation[])
    .filter((option) => !(type === "earning" && option === "percent_of_gross"))

  const isPercentage = calculation === "percent_of_basic" || calculation === "percent_of_gross"

  const onSubmit = async (values: ComponentFormValues) => {
    const input = {
      salary_structure_id: structureId,
      code: values.code.toUpperCase(),
      name: values.name,
      type: values.type,
      calculation: values.calculation,
      value: values.calculation === "manual" ? 0 : Number(values.value),
      sort_order: Number(values.sort_order ?? 0),
      is_taxable: values.is_taxable ?? true,
      is_statutory: values.is_statutory ?? false,
      is_active: values.is_active ?? true,
    }

    try {
      if (component) await update.mutateAsync({ id: component.id, input })
      else await create.mutateAsync(input)
      toast({ tone: "success", title: editing ? "Component updated" : "Component added" })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: "Unable to save component", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      role="presentation"
      onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}
    >
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="component-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="component-dialog-title" className="text-lg font-semibold">{editing ? "Edit component" : "Add component"}</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              {structureId === null
                ? "Applies to every payslip in the workspace, whatever structure the employee is on."
                : "Applies to employees assigned to this structure."}
            </p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Code" placeholder="HRA" error={errors.code?.message} {...register("code")} />
            <FormField label="Name" placeholder="House Rent Allowance" error={errors.name?.message} {...register("name")} />
          </div>
          <p className="-mt-2 text-xs text-muted-foreground">
            The code identifies the line when an amount is entered per payslip, so it is worth keeping stable.
          </p>

          <div className="grid gap-4 sm:grid-cols-2">
            <label className="grid gap-2 text-sm font-medium">
              Type
              <select className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20" {...register("type")}>
                {(Object.keys(salaryComponentTypeLabels) as SalaryComponentType[]).map((option) => (
                  <option key={option} value={option}>{salaryComponentTypeLabels[option]}</option>
                ))}
              </select>
            </label>

            <label className="grid gap-2 text-sm font-medium">
              Calculation
              <select className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20" {...register("calculation")}>
                {calculations.map((option) => (
                  <option key={option} value={option}>{salaryCalculationLabels[option]}</option>
                ))}
              </select>
              {errors.calculation && <span className="text-xs text-destructive">{errors.calculation.message}</span>}
            </label>
          </div>

          {calculation === "manual" ? (
            <p className="rounded-xl border border-dashed p-3 text-xs text-muted-foreground">
              No value to set. The amount is entered when a payroll run is generated — used for progressive taxes, which cannot be reduced
              to one rate without being wrong.
            </p>
          ) : (
            <FormField
              label={isPercentage ? "Percentage" : `Amount (${currencySymbol})`}
              type="number"
              step="0.01"
              min="0"
              error={errors.value?.message}
              {...register("value")}
            />
          )}

          <FormField label="Sort order" type="number" min="0" error={errors.sort_order?.message} {...register("sort_order")} />
          <p className="-mt-2 text-xs text-muted-foreground">Controls where the line appears on the payslip. Lower comes first.</p>

          <div className="grid gap-2">
            {type !== "deduction" && (
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" className="size-4 rounded border" {...register("is_taxable")} />
                Taxable
              </label>
            )}
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="size-4 rounded border" {...register("is_statutory")} />
              Statutory — required by law rather than chosen
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="size-4 rounded border" {...register("is_active")} />
              Active
            </label>
          </div>

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || create.isPending || update.isPending}>
              {isSubmitting || create.isPending || update.isPending ? "Saving…" : editing ? "Save changes" : "Add component"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
