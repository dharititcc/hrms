"use client"

import { X } from "lucide-react"
import { useForm, useWatch } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { useSalaryStructures } from "@/hooks/use-payroll-config"
import { usePayrollRunMutations } from "@/hooks/use-payroll-runs"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"

const runSchema = z.object({
  title: z.string().trim().min(2, "Name the run"),
  country: z.string().min(2, "Choose a country"),
  period_start: z.string().min(1, "Choose when the period starts"),
  period_end: z.string().min(1, "Choose when the period ends"),
  pay_date: z.string().optional(),
  notes: z.string().max(2000).optional(),
}).refine((values) => values.period_end >= values.period_start, {
  message: "The period cannot end before it starts", path: ["period_end"],
}).refine((values) => !values.pay_date || values.pay_date >= values.period_end, {
  message: "Pay date cannot fall before the period ends", path: ["pay_date"],
})

type RunFormValues = z.infer<typeof runSchema>

/** Defaults to last month, which is what a payroll is normally run for. */
function lastMonth(): { start: string; end: string; title: string } {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
  const end = new Date(now.getFullYear(), now.getMonth(), 0)
  const iso = (date: Date) => `${date.getFullYear()}-${`${date.getMonth() + 1}`.padStart(2, "0")}-${`${date.getDate()}`.padStart(2, "0")}`

  return {
    start: iso(start),
    end: iso(end),
    title: start.toLocaleDateString(undefined, { month: "long", year: "numeric" }),
  }
}

export function GenerateRunDialog({ onClose, onGenerated }: { onClose: () => void; onGenerated: (id: number) => void }) {
  const { generate } = usePayrollRunMutations()
  const { data: structureData } = useSalaryStructures()
  const { toast } = useToast()
  const period = lastMonth()

  const countries = structureData?.meta.countries ?? []

  const { register, handleSubmit, control, formState: { errors, isSubmitting } } = useForm<RunFormValues>({
    resolver: zodResolver(runSchema),
    defaultValues: {
      title: period.title,
      country: countries[0]?.value ?? "IN",
      period_start: period.start,
      period_end: period.end,
      pay_date: "",
      notes: "",
    },
  })

  const country = useWatch({ control, name: "country" })
  const selected = countries.find((option) => option.value === country)

  const onSubmit = async (values: RunFormValues) => {
    try {
      const run = await generate.mutateAsync({
        title: values.title,
        country: values.country,
        period_start: values.period_start,
        period_end: values.period_end,
        pay_date: values.pay_date || null,
        notes: values.notes || null,
      })
      toast({
        tone: "success",
        title: `Generated ${run.slip_count} payslip${run.slip_count === 1 ? "" : "s"}`,
        description: run.slip_count === 0 ? "No active employee has a salary for this country." : undefined,
      })
      onGenerated(run.id)
    } catch (error) {
      toast({ tone: "error", title: "Unable to generate payroll", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      role="presentation"
      onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}
    >
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="run-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="run-dialog-title" className="text-lg font-semibold">Generate payroll</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Produces a draft slip for every active employee with a salary in this country.
            </p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormField label="Name" placeholder="March 2026" error={errors.title?.message} {...register("title")} />

          <label className="grid gap-2 text-sm font-medium">
            Country
            <select className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20" {...register("country")}>
              {countries.map((option) => <option key={option.value} value={option.value}>{option.label} ({option.currency_code})</option>)}
            </select>
          </label>
          <p className="-mt-2 text-xs text-muted-foreground">
            One run covers one country. Employees whose salary is set in {selected?.label ?? "another country"} are not included.
          </p>

          <div className="grid gap-4 sm:grid-cols-2">
            <FormField label="Period start" type="date" error={errors.period_start?.message} {...register("period_start")} />
            <FormField label="Period end" type="date" error={errors.period_end?.message} {...register("period_end")} />
          </div>
          <p className="-mt-2 text-xs text-muted-foreground">
            Each slip uses the salary in force on the period end date, so a mid-month rise is paid at the new rate for the whole period.
          </p>

          <FormField label="Pay date" type="date" error={errors.pay_date?.message} {...register("pay_date")} />
          <FormField label="Notes" placeholder="Optional" error={errors.notes?.message} {...register("notes")} />

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || generate.isPending}>
              {isSubmitting || generate.isPending ? "Generating…" : "Generate draft"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
