"use client"

import { X } from "lucide-react"
import { useForm, useWatch } from "react-hook-form"
import { zodResolver } from "@hookform/resolvers/zod"
import { z } from "zod"
import { Button } from "@workspace/ui/components/button"
import { FormField } from "@/components/ui/form-fields"
import { useSalaryStructureMutations } from "@/hooks/use-payroll-config"
import { getApiErrorMessage } from "@/lib/api-error"
import { useToast } from "@/providers/toast-provider"
import type { PayrollCountryOption, SalaryStructure } from "@/types/payroll"

const structureSchema = z.object({
  name: z.string().trim().min(2, "Give the structure a name"),
  description: z.string().max(255).optional(),
  country: z.string().min(2, "Choose a country"),
  is_active: z.boolean().optional(),
  seed_statutory: z.boolean().optional(),
})

type StructureFormValues = z.infer<typeof structureSchema>

export function StructureDialog({ structure, countries, onClose }: {
  structure: SalaryStructure | null
  countries: PayrollCountryOption[]
  onClose: () => void
}) {
  const { create, update } = useSalaryStructureMutations()
  const { toast } = useToast()
  const editing = Boolean(structure)

  const { register, handleSubmit, control, formState: { errors, isSubmitting } } = useForm<StructureFormValues>({
    resolver: zodResolver(structureSchema),
    defaultValues: {
      name: structure?.name ?? "",
      description: structure?.description ?? "",
      country: structure?.country ?? countries[0]?.value ?? "IN",
      is_active: structure?.is_active ?? true,
      seed_statutory: false,
    },
  })

  const country = useWatch({ control, name: "country" })
  const selected = countries.find((option) => option.value === country)

  const onSubmit = async (values: StructureFormValues) => {
    const input = {
      name: values.name,
      description: values.description || null,
      country: values.country,
      is_active: values.is_active ?? true,
      // Only meaningful on create; the server ignores it on update.
      seed_statutory: editing ? undefined : values.seed_statutory,
    }

    try {
      if (structure) await update.mutateAsync({ id: structure.id, input })
      else await create.mutateAsync(input)
      toast({ tone: "success", title: editing ? "Structure updated" : "Structure created" })
      onClose()
    } catch (error) {
      toast({ tone: "error", title: "Unable to save structure", description: getApiErrorMessage(error) })
    }
  }

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4"
      role="presentation"
      onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}
    >
      <div className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border bg-background p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="structure-dialog-title">
        <div className="flex items-start justify-between">
          <div>
            <h2 id="structure-dialog-title" className="text-lg font-semibold">{editing ? "Edit structure" : "New salary structure"}</h2>
            <p className="mt-1 text-sm text-muted-foreground">A template employees are assigned to, holding the lines their payslip is built from.</p>
          </div>
          <Button variant="ghost" size="icon-sm" aria-label="Close dialog" onPress={onClose}><X /></Button>
        </div>

        <form className="mt-6 grid gap-4" onSubmit={handleSubmit(onSubmit)} noValidate>
          <FormField label="Name" placeholder="India Standard" error={errors.name?.message} {...register("name")} />
          <FormField label="Description" placeholder="Optional" error={errors.description?.message} {...register("description")} />

          <label className="grid gap-2 text-sm font-medium">
            Country
            <select
              className="h-11 rounded-lg border bg-background px-3 text-sm outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
              {...register("country")}
            >
              {countries.map((country) => (
                <option key={country.value} value={country.value}>{country.label} ({country.currency_code})</option>
              ))}
            </select>
          </label>
          <p className="-mt-2 text-xs text-muted-foreground">
            Sets the currency and which statutory deductions apply. Payslips already issued keep the currency they were generated with.
          </p>

          {!editing && (selected?.statutory_count ?? 0) > 0 && (
            <label className="flex items-start gap-2 rounded-xl border p-3 text-sm">
              <input type="checkbox" className="mt-0.5 size-4 rounded border" {...register("seed_statutory")} />
              <span>
                Add {selected?.label}&rsquo;s {selected?.statutory_count} statutory deductions
                <span className="mt-1 block text-xs text-muted-foreground">
                  Starting defaults, not verified law. Check every rate against current legislation before anyone is paid — they are ordinary
                  components, so correcting one is just an edit.
                </span>
              </span>
            </label>
          )}

          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="size-4 rounded border" {...register("is_active")} />
            Active
          </label>

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="outline" onPress={onClose}>Cancel</Button>
            <Button type="submit" isDisabled={isSubmitting || create.isPending || update.isPending}>
              {isSubmitting || create.isPending || update.isPending ? "Saving…" : editing ? "Save changes" : "Create structure"}
            </Button>
          </div>
        </form>
      </div>
    </div>
  )
}
